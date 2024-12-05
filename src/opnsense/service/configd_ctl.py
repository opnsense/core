#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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

import argparse
import socket
import os.path
import traceback
import sys
import syslog
import time
from select import select
from modules import syslog_error, syslog_notice

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
        syslog_error('unable to connect to configd socket (@%s)'%configd_socket_name)
        print('unable to connect to configd socket (@%s)'%configd_socket_name, file=sys.stderr)
        yield None

    try:
        sock.send(exec_command.encode())
        while True:
            line = sock.recv(65536).decode()
            if line:
                yield line
            else:
                break
    except KeyboardInterrupt:
        # intentional
        pass
    except:
        syslog_error('error in configd communication \n%s'%traceback.format_exc())
        print ('error in configd communication, see syslog for details', file=sys.stderr)
    finally:
        sock.close()


parser = argparse.ArgumentParser()
parser.add_argument("-e", help="use as event handler, execute command on receiving input", action="store_true")
parser.add_argument("-d", help="detach the execution of the command and return immediately", action="store_true")
parser.add_argument("-q", help="run quietly by muting standard output", action="store_true")
parser.add_argument("-w", help="wait specified amount of seconds for socket to become available", type=int, default=0)
parser.add_argument(
    "-t",
    help="threshold between events,  wait this interval before executing commands, combine input into single events",
    type=float
)
parser.add_argument("command", help="command(s) to execute", nargs="*")
args = parser.parse_args()

syslog.openlog(os.path.basename(sys.argv[0]))

# set a timeout to the socket
socket.setdefaulttimeout(120)

# check if configd socket exists (wait for a maximum of specified seconds for configd to start)
i=0
while not os.path.exists(configd_socket_name):
    if i >= args.w:
        break
    time.sleep(1)
    i += 1

if not os.path.exists(configd_socket_name):
    print('configd socket missing (@%s)'%configd_socket_name, file=sys.stderr)
    sys.exit(-1)

# command to execute
if not args.command:
    exec_commands=['configd actions']
else:
    exec_commands=[' '.join(args.command)]

if args.e:
    # use as event handler, execute configd command on every line on stdin
    last_message_stamp = time.time()
    stashed_lines = list()
    while True:
        rlist, _, _ = select([sys.stdin], [], [], args.t)
        if rlist:
            last_message_stamp = time.time()
            r_line = sys.stdin.readline()
            if len(r_line) == 0:
                #EOFError. pipe broken?
                sys.exit(-1)
            stashed_lines.append(r_line)

        if len(stashed_lines) >= 1 and (args.t is None or time.time() - last_message_stamp > args.t):
            # emit event trigger(s) to syslog
            for line in stashed_lines:
                syslog_notice("event @ %.2f msg: %s" % (last_message_stamp, line))
            # execute command(s)
            for exec_command in exec_commands:
                if args.d:
                    exec_command = '&' + exec_command
                # we need to fetch the generator's response in order to execute the command, lets return it to the
                # users as well.
                cmd_outp = (' '.join(exec_config_cmd(exec_command=exec_command))).strip()
                syslog_notice("event @ %.2f exec: %s response: %s" % (last_message_stamp, exec_command, cmd_outp))
            stashed_lines = list()
else:
    # normal execution mode
    for exec_command in exec_commands:
        if args.d:
            exec_command = '&' + exec_command
        endmarker = (chr(0), chr(0), chr(0))
        for block in exec_config_cmd(exec_command=exec_command):
            if block is None:
                sys.exit(-1)
            elif not args.q:
                if block.endswith(endmarker):
                    print(block[:-3].rstrip())
                else:
                    print(block, end="")
