"""
    Copyright (c) 2014-2023 Ad Schellevis <ad@opnsense.org>
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
    package : configd
    function: unix domain socket process worker process
"""

import os
import subprocess
import socket
import traceback
import threading
import configparser
import glob
import time
import uuid
import shlex
from .actions import ActionFactory
from .actions.base import BaseAction
from . import syslog_error, syslog_info, syslog_notice, singleton


class Handler(object):
    """ Main handler class, opens unix domain socket and starts listening
        - New connections are handed over to a HandlerClient type object in a new thread
        - All possible actions are stored in 1 ActionHandler type object and parsed to every client for script execution

        processflow:
            Handler ( waits for client )
                -> new client is send to HandlerClient
                    -> execute ActionHandler command using BaseAction type objects (delivered via ActionFactory)
                    <- send back result string
    """

    def __init__(self, socket_filename, config_path, config_environment=None, simulation_mode=False):
        """ Constructor
        :param socket_filename: filename of unix domain socket to use
        :param config_path: location of configuration files
        :param simulation_mode: emulation mode, do not start actual (script) commands
        """
        if config_environment is None:
            config_environment = {}
        self.socket_filename = socket_filename
        self.config_path = config_path
        self.simulation_mode = simulation_mode
        self.config_environment = config_environment
        self.single_threaded = False

    def run(self):
        """ Run process handler

        :return:
        """
        while True:
            # noinspection PyBroadException
            try:
                act_handler = ActionHandler(config_path=self.config_path, config_environment=self.config_environment)
                try:
                    os.unlink(self.socket_filename)
                except OSError:
                    if os.path.exists(self.socket_filename):
                        raise

                sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
                sock.bind(self.socket_filename)
                os.chmod(self.socket_filename, 0o666)
                sock.listen(30)
                while True:
                    # wait for a connection to arrive
                    connection, client_address = sock.accept()
                    # spawn a client connection
                    cmd_thread = HandlerClient(
                        connection=connection,
                        client_address=client_address,
                        action_handler=act_handler,
                        simulation_mode=self.simulation_mode
                    )
                    if self.single_threaded:
                        # run single threaded
                        cmd_thread.run()
                    else:
                        # run threaded
                        cmd_thread.start()

            except KeyboardInterrupt:
                # exit on <ctrl><c>
                if os.path.exists(self.socket_filename):
                    # cleanup, remove socket
                    os.remove(self.socket_filename)
                raise
            except SystemExit:
                # stop process handler on system exit
                if os.path.exists(self.socket_filename):
                    # cleanup on exit, remove socket
                    os.remove(self.socket_filename)
                return
            except Exception:
                # something went wrong... send traceback to syslog, restart listener (wait for a short time)
                print(traceback.format_exc())
                syslog_error('Handler died on %s' % traceback.format_exc())
                time.sleep(1)


class HandlerClient(threading.Thread):
    """ Handle commands via specified socket connection
    """

    def __init__(self, connection, client_address, action_handler, simulation_mode=False):
        """
        :param connection: socket connection object
        :param client_address: client address ( from socket accept )
        :param action_handler: action handler object
        :param simulation_mode: Emulation mode, do not start actual (script) commands
        :return: None
        """
        threading.Thread.__init__(self)
        self.connection = connection
        self.client_address = client_address
        self.action_handler = action_handler
        self.simulation_mode = simulation_mode
        self.message_uuid = uuid.uuid4()

    def run(self):
        """ handle single action ( read data, execute command, send response )

        :return: None
        """
        result = ''
        exec_command = ''
        exec_action = ''
        exec_params = ''
        exec_in_background = False
        # noinspection PyBroadException
        try:
            # receive command, maximum data length is 4k... longer messages will be truncated
            data = self.connection.recv(4096).decode()
            # map command to action
            data_parts = shlex.split(data)
            if len(data_parts) == 0 or len(data_parts[0]) == 0:
                # no data found
                self.connection.sendall('no data\n'.encode())
            else:
                if data_parts[0][0] == "&":
                    # set run in background
                    exec_in_background = True
                    data_parts[0] = data_parts[0][1:]

                # when running in background, return this message uuid and detach socket
                if exec_in_background:
                    result = self.message_uuid
                    self.connection.sendall(('%s\n%c%c%c' % (result, chr(0), chr(0), chr(0))).encode())
                    self.connection.shutdown(socket.SHUT_RDWR)
                    self.connection.close()

                # execute requested action
                if self.simulation_mode:
                    self.action_handler.show_action(data_parts, self.message_uuid)
                    result = 'OK'
                else:
                    result = self.action_handler.execute(data_parts, self.message_uuid)

                if not exec_in_background:
                    # send response back to client( including trailing enter )
                    self.connection.sendall(('%s\n' % result).encode())
                else:
                    # log response
                    syslog_info("message %s [%s] returned %s " % (
                        self.message_uuid, ' '.join(data_parts), result[:100]
                    ))

            # send end of stream characters
            if not exec_in_background:
                self.connection.sendall(("%c%c%c" % (chr(0), chr(0), chr(0))).encode())
        except SystemExit:
            # ignore system exit related errors
            pass
        except Exception:
            print(traceback.format_exc())
            syslog_notice('unable to sendback response [%s] for [%s][%s][%s] {%s}, message was %s' % (
                result, exec_command, exec_action, exec_params, self.message_uuid, traceback.format_exc()
            ))
        finally:
            if not exec_in_background:
                self.connection.shutdown(socket.SHUT_RDWR)
                self.connection.close()


@singleton
class ActionHandler(object):
    """ Start/stop services and functions using configuration data defined in conf/actions_<topic>.conf
    """

    def __init__(self, config_path=None, config_environment=None):
        """ Initialize action handler to start system functions

        :param config_path: full path of configuration data
        :param config_environment: environment to use (if possible)
        :return:
        """
        if config_path is not None:
            self.config_path = config_path
        if config_environment is not None:
            self.config_environment = config_environment

        # try to load data on initial start
        if not hasattr(self, 'action_map'):
            self.action_map = {}
            self.load_config()

    def load_config(self):
        """ load action configuration from config files into local dictionary

        :return: None
        """
        action_factory = ActionFactory()
        for config_filename in glob.glob('%s/actions_*.conf' % self.config_path) \
                + glob.glob('%s/actions.d/actions_*.conf' % self.config_path):
            # this topic's name (service, filter, template, etc)
            # make sure there's an action map index for this topic
            topic_name = config_filename.split('actions_')[-1].split('.')[0]
            if topic_name not in self.action_map:
                self.action_map[topic_name] = {}

            # traverse config directory and open all filenames starting with actions_
            cnf = configparser.RawConfigParser()
            try:
                cnf.read(config_filename)
            except configparser.Error:
                syslog_error('exception occurred while reading "%s": %s' % (config_filename, traceback.format_exc(0)))

            for section in cnf.sections():
                # map configuration data on object
                conf = {}
                for act_prop in cnf.items(section):
                    conf[act_prop[0]] = act_prop[1]
                action_obj = action_factory.get(environment=self.config_environment, conf=conf)

                target = self.action_map[topic_name]
                sections = section.split('.')
                while sections:
                    action_name = sections.pop(0)
                    if action_name in target:
                        if type(target[action_name]) is not dict or len(sections) == 0:
                            syslog_error('unsupported overlay command [%s.%s]' % (topic_name, section))
                            break
                    elif len(sections) == 0:
                        target[action_name] = action_obj
                        break
                    else:
                        target[action_name] = {}
                    target = target[action_name]

    def list_actions(self, attributes=None, result=None, map_ptr=None, path=''):
        """ list all available actions
        :param attributes:
        :param result: (recursion) result dictionary to return
        :param map_ptr: (recursion) point to the leaves in the tree
        :param path: (recursion) path (items)
        :return: dict
        """
        if attributes is None:
            attributes = []
        result = {} if result is None else result
        map_ptr = self.action_map if map_ptr is None else map_ptr

        for key in map_ptr:
            this_path = ('%s %s' % (path, key)).strip()
            if type(map_ptr[key]) is dict:
                self.list_actions(attributes, result, map_ptr[key], this_path)
            else:
                result[this_path] = {}
                for actAttr in attributes:
                    if hasattr(map_ptr[key], actAttr):
                        result[this_path][actAttr] = getattr(map_ptr[key], actAttr)
                    else:
                        result[this_path][actAttr] = ''

        return result

    def find_action(self, action):
        """ find action object

        :param action: list of commands and parameters
        :return: action object or None if not found
        """
        target = self.action_map
        while type(target) is dict and len(action) > 0 and action[0] in target:
            tmp = action.pop(0)
            target = target[tmp]

        if isinstance(target, BaseAction):
            return target, action

        return None, []

    def execute(self, action, message_uuid):
        """ execute configuration defined action
        :param action: list of commands and parameters
        :param message_uuid: message unique id
        :return: OK on success, else error code
        """
        action_obj, action_params = self.find_action(action)

        if action_obj is not None:
            return '%s\n' % action_obj.execute(action_params, message_uuid)

        return 'Action not found\n'

    def show_action(self, action, message_uuid):
        """ debug/simulation mode: show action information
        :param action: list of commands and parameters
        :param message_uuid: message unique id
        :return: None
        """
        action_obj, parameters = self.find_action(action)
        if action_obj is not None:
            print('---------------------------------------------------------------------')
            print('execute %s ' % ' '.join(action))
            print('action object %s (%s) %s' % (action_obj, action_obj.command, message_uuid))
            print('---------------------------------------------------------------------')
