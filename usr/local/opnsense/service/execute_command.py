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
    function: commandline execute commands to check_reload_status daemon


"""
__author__ = 'Ad Schellevis'
import socket
import sys


if len(sys.argv) <= 1:
    print 'usage : %s [unix domain socket filename] <command>'%sys.argv[0]
    sys.exit(0)
else:
    server_address = sys.argv[1].strip()

if len(sys.argv) > 2:
    exec_command = ' '.join(sys.argv[2:])
else:
    # command line input
    exec_command = raw_input('command:')+'\n'

# Create and open unix domain socket
sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
print ('connecting to %s' % server_address)
try:
    sock.connect(server_address)
except socket.error, msg:
    print ('error connection to %s '%server_address)
    sys.exit(1)

# send command and await response
try:
    sock.send(exec_command)
    print ('response:%s'% sock.recv(4096))
finally:
    sock.close()



