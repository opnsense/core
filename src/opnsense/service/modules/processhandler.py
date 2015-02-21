"""
    Copyright (c) 2014 Ad Schellevis

    part of OPNsense (https://www.opnsense.org/)

    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------
    package : check_reload_status
    function: unix domain socket process worker process


"""
__author__ = 'Ad Schellevis'

import os
import subprocess
import socket
import traceback
import syslog
import threading
import ConfigParser
import glob
import time
import ph_inline_actions

class Handler(object):
    """ Main handler class, opens unix domain socket and starts listening
        - New connections are handed over to a HandlerClient type object in a new thread
        - All possible actions are stored in 1 ActionHandler type object and parsed to every client for script execution

        processflow:
            Handler ( waits for client )
                -> new client is send to HandlerClient
                    -> execute ActionHandler command using Action objects
                    <- send back result string
    """
    def __init__(self,socket_filename,config_path,simulation_mode=False):
        """ Constructor

        :param socket_filename: filename of unix domain socket to use
        :param config_path: location of configuration files
        :param emulate: emulation mode, do not start actual (script) commands
        :return: object
        """
        self.socket_filename = socket_filename
        self.config_path = config_path
        self.simulation_mode = simulation_mode
        self.single_threaded = False

    def run(self):
        """ Run process handler

        :return:
        """
        while True:
            try:
                # open action handler
                actHandler = ActionHandler(config_path=self.config_path)

                # remove previous socket ( if exists )
                try:
                    os.unlink(self.socket_filename)
                except OSError:
                    if os.path.exists(self.socket_filename):
                        raise

                sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
                sock.bind(self.socket_filename)
                os.chmod(self.socket_filename,0o666)
                sock.listen(30)
                while True:
                    # wait for a connection to arrive
                    connection, client_address = sock.accept()
                    # spawn a client connection
                    cmd_thread = HandlerClient(connection=connection,
                                               client_address=client_address,
                                               action_handler=actHandler,
                                               simulation_mode=self.simulation_mode)
                    if self.single_threaded :
                        # run single threaded
                        cmd_thread.run()
                    else:
                        # rnu threaded
                        cmd_thread.start()

            except KeyboardInterrupt:
                # exit on <ctrl><c>
                raise
            except:
                # something went wrong... send traceback to syslog, restart listener (wait for a short time)
                print (traceback.format_exc())
                syslog.syslog(syslog.LOG_ERR, 'Handler died on %s'%traceback.format_exc())
                time.sleep(1)


class HandlerClient(threading.Thread):
    """ Handle commands via specified socket connection
    """
    def __init__ (self,connection,client_address,action_handler,simulation_mode=False):
        """

        :param connection: socket connection object
        :param client_address: client address ( from socket accept )
        :param action_handler: action handler object
        :param emulate: Emulation mode, do not start actual (script) commands
        :return: None
        """
        threading.Thread.__init__(self)
        self.connection = connection
        self.client_address = client_address
        self.action_handler = action_handler
        self.simulation_mode = simulation_mode

    def run(self):
        """ handle single action ( read data, execute command, send response )

        :return: None
        """
        result = ''
        exec_command = ''
        try:
            # receive command, maximum data length is 4k... longer messages will be truncated
            data = self.connection.recv(4096)
            # map command to action
            data_parts = data.strip().split(' ')
            if len(data_parts) == 0 or len(data_parts[0]) == 0:
                # no data found
                self.connection.sendall('no data\n')
            else:
                exec_command = data_parts[0]
                if len(data_parts) > 1:
                    exec_action = data_parts[1]
                else:
                    exec_action = None
                if len(data_parts) >2:
                    exec_params = data_parts[2:]
                else:
                    exec_params = None

                # execute requested action
                if  self.simulation_mode:
                    self.action_handler.showAction(exec_command,exec_action,exec_params)
                    result='OK'
                else:
                    result = self.action_handler.execute(exec_command,exec_action,exec_params)

                # send response back to client( including trailing enter )
                self.connection.sendall('%s\n'%result)
        except:
            print (traceback.format_exc())
            syslog.syslog(syslog.LOG_ERR,'unable to sendback response [%s] for [%s], message was %s'%(result,exec_command ,traceback.format_exc()))
        finally:
            self.connection.close()

class ActionHandler(object):
    """ Start/stop services and functions using configuration data definced in conf/actions_<topic>.conf
    """
    def __init__(self,config_path):
        """ Initialize action handler to start system functions

        :param config_path: full path of configuration data
        :return:
        """
        self.config_path = config_path
        self.action_map = {}
        self.load_config()


    def load_config(self):
        """ load action configuration from config files into local dictionary

        :return: None
        """

        self.action_map = {}
        for config_filename in glob.glob('%s/actions_*.conf'%(self.config_path)) + glob.glob('%s/actions.d/actions_*.conf'%(self.config_path)):
            # this topic's name (service, filter, template, etc)
            # make sure there's an action map index for this topic
            topic_name = config_filename.split('actions_')[-1].split('.')[0]
            if self.action_map.has_key(topic_name) == False:
                self.action_map[topic_name] = {}

            # traverse config directory and open all filenames starting with actions_
            cnf=ConfigParser.RawConfigParser()
            cnf.read(config_filename)
            for section in cnf.sections():
                # map configuration data on object
                action_obj = Action()
                for act_prop in cnf.items(section):
                    setattr(action_obj,act_prop[0],act_prop[1])

                if section.find('.') > -1:
                    # at this moment we only support 2 levels of actions ( 3 if you count topic as well )
                    for alias in section.split('.')[0].split('|'):
                        if self.action_map[topic_name].has_key(alias) == False:
                            self.action_map[topic_name][alias] = {}
                        self.action_map[topic_name][alias][section.split('.')[1]] = action_obj
                else:
                    for alias in section.split('|'):
                        self.action_map[topic_name][alias] = action_obj

    def findAction(self,command,action,parameters):
        """ find action object

        :param command: command/topic for example interface
        :param action: action to run ( for example linkup )
        :param parameters: the parameters to supply
        :return: action object or None if not found
        """
        action_obj = None
        if self.action_map.has_key(command):
            if self.action_map[command].has_key(action):
                if type(self.action_map[command][action]) == dict:
                    if len(parameters) > 0 and self.action_map[command][action].has_key(parameters[0]) == True:
                        # 3 level action (  "interface linkup start" for example )
                        if isinstance(self.action_map[command][action][parameters[0]],Action):
                            action_obj = self.action_map[command][action][parameters[0]]
                            action_obj.setParameterStartPos(1)
                elif isinstance(self.action_map[command][action],Action):
                    action_obj = self.action_map[command][action]

        return action_obj

    def execute(self,command,action,parameters):
        """ execute configuration defined action

        :param command: command/topic for example interface
        :param action: action to run ( for example linkup )
        :param parameters: the parameters to supply
        :return: OK on success, else error code
        """
        action_params = []
        action_obj = self.findAction(command,action,parameters)

        if action_obj != None:
            if parameters != None and len(parameters) > action_obj.getParameterStartPos():
                action_params = parameters[action_obj.getParameterStartPos():]

            return '%s\n'%action_obj.execute(action_params)

        return 'Action not found\n'


    def showAction(self,command,action,parameters):
        """ debug/simulation mode: show action information
        :return:
        """
        action_obj = self.findAction(command,action,parameters)
        print ('---------------------------------------------------------------------')
        print ('execute %s.%s with parameters : %s '%(command,action,parameters) )
        print ('action object %s (%s)' % (action_obj,action_obj.command) )
        print ('---------------------------------------------------------------------')

class Action(object):
    """ Action class,  handles actual (system) calls.
    set command, parameters (template) type and log message
    """
    def __init__(self):
        """ setup default properties

        :return:
        """
        self.command = None
        self.parameters = None
        self.type = None
        self.message = None
        self._parameter_start_pos = 0

    def setParameterStartPos(self,pos):
        """

        :param pos: start position of parameter list
        :return: position
        """
        self._parameter_start_pos = pos

    def getParameterStartPos(self):
        """ getter for _parameter_start_pos
        :return: start position of parameter list ( first argument can be part of action to start )
        """
        return self._parameter_start_pos

    def execute(self,parameters):
        """ execute an action

        :param parameters: list of parameters
        :return:
        """
        # validate input
        if self.type == None:
            return 'No action type'
        elif self.type.lower() == 'script':
            #
            # script command, execute a shell script and return (simple) status
            #
            if self.command == None:
                return 'No command'
            try:
                script_command = self.command
                if self.parameters != None and type(self.parameters) == str:
                    script_command = '%s %s'%(script_command,self.parameters)

                    if script_command.find('%s') > -1 and len(parameters) > 0:
                        # use command execution parameters in action parameter template
                        script_command = script_command % tuple(parameters[0:script_command.count('%s')])

                # execute script command
                if self.message != None:
                    if self.message.count('%s') > 0 and parameters != None and len(parameters) > 0:
                        syslog.syslog(syslog.LOG_NOTICE,self.message % tuple(parameters[0:self.message.count('%s')]) )
                    else:
                        syslog.syslog(syslog.LOG_NOTICE,self.message)

                exit_status = subprocess.call(script_command, shell=True)
            except:
                syslog.syslog(syslog.LOG_ERR, 'Script action failed at %s'%traceback.format_exc())
                return 'Execute error'

            # send response
            if exit_status == 0 :
                return 'OK'
            else:
                return 'Error (%d)'%exit_status

        elif self.type.lower() == 'inline':
            # Handle inline service actions
            try:
                # match parameters, serialize to parameter string defined by action template
                if len(parameters) > 0:
                    inline_act_parameters = self.parameters % tuple(parameters)
                else:
                    inline_act_parameters = ''

                # send message to syslog
                if self.message != None:
                    if self.message.count('%s') > 0 and parameters != None and len(parameters) > 0:
                        syslog.syslog(syslog.LOG_NOTICE,self.message % tuple(parameters[0:self.message.count('%s')]) )
                    else:
                        syslog.syslog(syslog.LOG_NOTICE,self.message)

                return ph_inline_actions.execute(self,inline_act_parameters)

            except:
                syslog.syslog(syslog.LOG_ERR, 'Inline action failed at %s'%traceback.format_exc())
                return 'Execute error'



        return 'Unknown action type'
