#!/usr/local/bin/python3
# -*- coding: utf-8 -*-

"""
    Copyright (c) 2014-2019 Ad Schellevis <ad@opnsense.org>
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
    function: delivers a process coordinator to handle frontend functions
"""

import glob
import os
import sys
import logging
import signal
import time
import socket
import subprocess
import syslog
import modules.processhandler
from configparser import ConfigParser
from modules.daemonize import Daemonize
import cProfile

# find program path
program_path = os.path.dirname(os.path.abspath(__file__))

# set working directory to program_path
sys.path.append(program_path)
os.chdir(program_path)


def get_config():
    """ open configuration
    """
    cnf = ConfigParser()
    cnf.optionxform = str
    configs = ['conf/configd.conf']
    for filename in glob.glob('conf/configd.conf.d/*.conf'):
        configs.append(filename)
    cnf.read(configs)
    return cnf


def validate_config(cnf):
    """ validate configuration, exit on missing item
        :param cnf: config handle
    """
    for config_item in ['socket_filename', 'pid_filename']:
        if not cnf.has_section('main') or not cnf.has_option('main', config_item):
            print('configuration item main/%s not found in %s/conf/configd.conf' % (config_item, program_path))
            sys.exit(0)


def main(cnf, single_threaded=False):
    """ configd startup
        :param cnf: config handle
        :param simulate: simulate only
        :param single_threaded: start single threaded
    """
    # setup configd environment to use for all configured actions
    if not cnf.has_section('environment'):
        config_environment = os.environ.copy()
    else:
        config_environment = dict()
        for envKey in cnf.items('environment'):
            config_environment[envKey[0]] = envKey[1]

    action_defaults = dict()
    if cnf.has_section('action_defaults'):
        for envKey in cnf.items('action_defaults'):
            action_defaults[envKey[0]] = envKey[1]

    # run process coordinator ( on console or as daemon )
    proc_handler = modules.processhandler.Handler(
        socket_filename=cnf.get('main', 'socket_filename'),
        config_path='%s/conf' % program_path,
        config_environment=config_environment,
        action_defaults=action_defaults,
    )
    proc_handler.single_threaded = single_threaded
    proc_handler.run()


def run_watch():
    """ start configd process and restart if it dies unexpected
    """
    current_child_pid = None

    def signal_handler(sig, frame):
        if current_child_pid is not None:
            os.kill(current_child_pid, sig)
        sys.exit(1)

    signal.signal(signal.SIGTERM, signal_handler)
    while True:
        process = subprocess.Popen(['/usr/local/opnsense/service/configd.py', 'console'])
        # save created pid for signal_handler() to use
        current_child_pid = process.pid
        process.wait()
        # wait a small period of time before trying to restart a new process
        time.sleep(0.5)


this_config = get_config()
validate_config(this_config)
if len(sys.argv) > 1 and 'console' in sys.argv[1:]:
    print('run %s in console mode' % sys.argv[0])
    syslog.openlog("configd.py")
    if 'profile' in sys.argv[1:]:
        # profile configd
        # for graphical output use gprof2dot:
        #   gprof2dot -f pstats /tmp/configd.profile  -o /tmp/callingGraph.dot
        # (https://code.google.com/p/jrfonseca/wiki/Gprof2Dot)
        print ("...<ctrl><c> to stop profiling")
        profile = cProfile.Profile(subcalls=True)
        profile.enable()
        try:
            main(cnf=this_config, single_threaded=True)
        except KeyboardInterrupt:
            pass
        except:
            raise
        profile.disable()
        profile.dump_stats('/tmp/configd.profile')
    else:
        main(cnf=this_config)
else:
    # run as daemon, wrap the actual work process to enable automatic restart on sudden death
    syslog_socket = "/var/run/log"
    if os.path.exists(syslog_socket):
        try:
            # bind log handle to syslog to catch messages from Daemonize()
            # (if syslog facility is active)
            loghandle = logging.getLogger("configd.py")
            loghandle.setLevel(logging.INFO)
            handler = logging.handlers.SysLogHandler(address=syslog_socket,
                                                     facility=logging.handlers.SysLogHandler.LOG_DAEMON)
            handler.setFormatter(logging.Formatter("%(name)s %(message)s"))
            loghandle.addHandler(handler)
        except socket.error:
            loghandle = None
    else:
        loghandle = None
    # daemonize process
    daemon = Daemonize(app=__file__.split('/')[-1].split('.py')[0],
                       pid=this_config.get('main', 'pid_filename'),
                       action=run_watch,
                       logger=loghandle
                       )
    daemon.start()
sys.exit(0)
