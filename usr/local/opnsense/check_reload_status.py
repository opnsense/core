#!/usr/local/bin/python2.7
"""
    Copyright (c) 2014 Ad Schellevis

    part of opnSense (https://www.opnsense.org/)

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
    function: delivers a process coordinator to handle frontend functions



"""
__author__ = 'Ad Schellevis'

#
import os
import sys
import ConfigParser
import modules.processhandler
from modules.daemonize import Daemonize


# find program path
if len(__file__.split('/')[:-1]) >0 :
    program_path = '/'.join(__file__.split('/')[:-1])
else:
    program_path = os.getcwd()

# set working directory to program_path
sys.path.append(program_path)
os.chdir(program_path)

# open configuration
cnf = ConfigParser.ConfigParser()
cnf.read('conf/check_reload_status.conf')

# validate configuration, exit on missing item
for config_item in  ['socket_filename','pid_filename']:
    if cnf.has_section('main') == False or cnf.has_option('main',config_item) == False:
        print('configuration item main/%s not found in %s/conf/check_reload_status.conf'%(config_item,program_path))
        sys.exit(0)

# run process coordinator ( on console or as daemon )
# if command-line arguments contain "emulate",  start in emulation mode
if len(sys.argv) > 1 and 'simulate' in sys.argv[1:]:
    proc_handler = modules.processhandler.Handler(socket_filename=cnf.get('main','socket_filename'),config_path='%s/conf'%(program_path),simulation_mode=True)
else:
    proc_handler = modules.processhandler.Handler(socket_filename=cnf.get('main','socket_filename'),config_path='%s/conf'%(program_path))

if len(sys.argv) > 1 and 'console' in sys.argv[1:]:
    print('run %s in console mode'%sys.argv[0])
    proc_handler.run()
else:
    # daemonize process
    daemon = Daemonize(app=__file__.split('/')[-1].split('.py')[0], pid=cnf.get('main','pid_filename'), action=proc_handler.run)
    daemon.start()

sys.exit(0)
