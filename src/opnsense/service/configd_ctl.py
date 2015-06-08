#!/usr/local/bin/python2.7
"""
    Copyright (c) 2015 Ad Schellevis

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
    package : configd
    function: commandline tool to send commands to configd (response to stdout)


"""
import socket
import os.path
import traceback
import syslog
import sys

__author__ = 'Ad Schellevis'

configd_socket_name = '/var/run/configd.socket'


def exec_config_cmd(exec_command):
    """ execute command using configd socket
    :param exec_command: command string
    :return: string
    """
    # Create and open unix domain socket
    try:
        sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        sock.connect(configd_socket_name)
    except socket.error:
        syslog.syslog(syslog.LOG_ERR,'unable to connect to configd socket (@%s)'%configd_socket_name)
        print('unable to connect to configd socket (@%s)'%configd_socket_name)
        return None

    try:
        sock.send(exec_command)
        data = []
        while True:
            line = sock.recv(65536)
            if line:
                data.append(line)

            # end of stream marker found, exit
            if line.find("%c%c%c"%(chr(0), chr(0), chr(0))) > -1 or (
                len(line) < 3 and len(data) > 1 and (''.join(data[-2:])).find("%c%c%c"%(chr(0), chr(0), chr(0))) > -1
            ):
                break

        return ''.join(data)[:-3]
    except:
        print ('error in configd communication %s, see syslog for details')
        syslog.syslog(syslog.LOG_ERR,'error in configd communication \n%s'%traceback.format_exc())
    finally:
        sock.close()



# set a timeout to the socket
socket.setdefaulttimeout(120)

# validate parameters
if len(sys.argv) <= 1:
    print ('usage : %s [-m] <command>'%sys.argv[0])
    sys.exit(0)

# check if configd socket exists
if not os.path.exists(configd_socket_name):
    print ('configd socket missing (@%s)'%configd_socket_name)
    sys.exit(-1)

if sys.argv[1] == '-m':
    # execute multiple commands at once ( -m "action1 param .." "action2 param .." )
    for exec_command in sys.argv[2:]:
        result=exec_config_cmd(exec_command=exec_command)
        if result is None:
            sys.exit(-1)
        print('%s'%(result))
else:
    # execute single command sequence
    exec_command=' '.join(sys.argv[1:])
    result=exec_config_cmd(exec_command=exec_command)
    if result is None:
        sys.exit(-1)
    print('%s'%(result))

