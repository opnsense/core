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

import copy
import configparser
import glob
import os
import shlex
import socket
import traceback
import threading
import time
import uuid
from .session import get_session_context
from .actions import ActionFactory
from .actions.base import BaseAction
from . import syslog_error, syslog_info, syslog_notice, syslog_auth_info, syslog_auth_error, singleton


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

    def __init__(self, socket_filename, config_path, config_environment=None, action_defaults=None):
        """ Constructor
        :param socket_filename: filename of unix domain socket to use
        :param config_path: location of action configuration files
        :param config_environment: env to use in shell commands
        :param action_defaults: default properties for action objects
        """
        if config_environment is None:
            config_environment = {}
        if action_defaults is None:
            action_defaults = {}
        self.socket_filename = socket_filename
        self.config_path = config_path
        self.config_environment = config_environment
        self.action_defaults = action_defaults
        self.single_threaded = False

    def run(self):
        """ Run process handler

        :return:
        """
        while True:
            # noinspection PyBroadException
            try:
                act_handler = ActionHandler(
                    config_path=self.config_path,
                    config_environment=self.config_environment,
                    action_defaults=self.action_defaults
                )
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
                        action_handler=act_handler
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

    def __init__(self, connection, client_address, action_handler):
        """
        :param connection: socket connection object
        :param client_address: client address ( from socket accept )
        :param action_handler: action handler object
        :return: None
        """
        threading.Thread.__init__(self)
        self.connection = connection
        self.client_address = client_address
        self.action_handler = action_handler
        self.message_uuid = uuid.uuid4()
        self.session = get_session_context(connection)

    def run(self):
        """ handle single action ( read data, execute command, send response )

        :return: None
        """
        result = ''
        exec_in_background = False
        # noinspection PyBroadException
        try:
            cmd_preludes = set()
            # receive command, maximum data length is 4k... longer messages will be truncated
            data = self.connection.recv(4096).decode()
            while len(data):
                if data[0] in [' ', '&', '!']:
                    cmd_preludes.add(data[0])
                    data = data[1:]
                else:
                    break
            # map command to action
            data_parts = shlex.split(data)
            if len(data_parts) == 0 or len(data_parts[0]) == 0:
                # no data found
                self.connection.sendall('no data\n'.encode())
            else:
                exec_in_background = "&" in cmd_preludes    # run in background?
                if '!' in cmd_preludes:
                     self.action_handler.cache_flush(data_parts) # flush cache when applicable

                # when running in background, return this message uuid and detach socket
                if exec_in_background:
                    self.connection.sendall(('%s\n%c%c%c' % (self.message_uuid, chr(0), chr(0), chr(0))).encode())
                    self.connection.shutdown(socket.SHUT_RDWR)
                    self.connection.close()

                # execute requested action
                result = self.action_handler.execute(data_parts, self.message_uuid, self.connection, self.session)

                if not exec_in_background:
                    # send response back to client (including trailing enters)
                    # ignore when result is None, in which case the content was streamed via the pipe
                    if type(result) is bytes:
                        self.connection.sendall(result)
                        self.connection.sendall(b'\n\n')
                    elif result is not None:
                        self.connection.sendall(('%s\n\n' % result).encode())
                else:
                    # log response
                    syslog_info("message %s [%s] returned %s " % (
                        self.message_uuid, ' '.join(data_parts), result[:100]
                    ))

            # send end of stream characters
            if not exec_in_background:
                self.connection.sendall(("%c%c%c" % (chr(0), chr(0), chr(0))).encode())
        except (SystemExit, BrokenPipeError):
            # ignore system exit or "client left" related errors
            pass
        except Exception:
            print(traceback.format_exc())
            syslog_notice('unable to sendback response for %s, message was %s' % (
                self.message_uuid, traceback.format_exc()
            ))
        finally:
            if not exec_in_background:
                try:
                    self.connection.shutdown(socket.SHUT_RDWR)
                    self.connection.close()
                except OSError:
                    # ignore shutdown errors when listener disconnected
                    pass


@singleton
class ActionHandler(object):
    """ Start/stop services and functions using configuration data defined in conf/actions_<topic>.conf
    """

    def __init__(self, config_path=None, config_environment=None, action_defaults=None):
        """ Initialize action handler to start system functions

        :param config_path: full path of configuration data
        :param config_environment: environment to use (if possible)
        :param action_defaults: default properties for action objects
        :return:
        """
        self.config_path = config_path
        self.config_environment = config_environment if config_environment else {}
        self.action_defaults = action_defaults if action_defaults else {}
        self.action_map = {}
        self.load_config()

    def load_config(self):
        """ load action configuration from config files into local dictionary

        :return: None
        """
        if self.config_path is None:
            return
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
                # map configuration data on object, start with default action config and add __full_command for
                # easy reference.
                conf = copy.deepcopy(self.action_defaults)
                conf['__full_command'] = "%s.%s" % (topic_name, section)
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
        action = list(action) # copy
        while type(target) is dict and len(action) > 0 and action[0] in target:
            tmp = action.pop(0)
            target = target[tmp]

        if isinstance(target, BaseAction):
            return target, action

        return None, []

    def cache_flush(self, action):
        action_obj, action_params = self.find_action(action)
        if action_obj is not None:
            action_obj.cache_flush(action_params)

    def execute(self, action, message_uuid, connection, session):
        """ execute configuration defined action
        :param action: list of commands and parameters
        :param message_uuid: message unique id
        :param connection: socket connection (in case we need to stream data back)
        :param session: this session context (used for access management)
        :return: OK on success, else error code
        """
        full_command = '.'.join(action)
        action_obj, action_params = self.find_action(action)

        if action_obj is not None:
            is_allowed = action_obj.is_allowed(session)
            if is_allowed:
                syslog_auth_info("action allowed %s for user %s" % (action_obj.full_command,session.get_user()))
                return action_obj.execute(action_params, message_uuid, connection)
            else:
                syslog_auth_error("action denied %s for user %s (requires : %s)" % (
                    action_obj.full_command,
                    session.get_user(),
                    action_obj.requires())
                )
                return 'Action not allowed or missing\n'

        syslog_auth_error("action %s not found for user %s" % (full_command, session.get_user()))
        return 'Action not allowed or missing\n'
