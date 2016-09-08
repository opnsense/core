#!/usr/local/bin/python2.7

"""
    Copyright (c) 2016 Ad Schellevis
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
    edit /boot/loader.conf.local
"""

import sys
import os

# parse parameters
cmd_switch=None
cmd_params=list()
for param in sys.argv[1:]:
    if param[0] == '-':
        cmd_switch = param[1:]
    else:
        cmd_params.append(param)

# load current loader.conf.local
if os.path.isfile('/boot/loader.conf.local'):
    current_loader_conf = open('/boot/loader.conf.local', 'r').read()
else:
    current_loader_conf = ''

# parse command switch
if cmd_switch == 's':
    # -s : show current data
    if current_loader_conf == '':
        print ("(empty)")
    else:
        print(current_loader_conf)
elif (cmd_switch == 'r' and len(cmd_params) == 1) or (cmd_switch == 'e' and len(cmd_params) == 2):
    # -r remove, -e edit
    new_loader_conf=[]
    param_found = False
    for line in current_loader_conf.split('\n'):
        if line.strip() != "":
            param_parts=line.split('=')
            if param_parts[0] == cmd_params[0]:
                if cmd_switch == 'e':
                    new_loader_conf.append('%s="%s"'%tuple(cmd_params))
                    param_found=True
            else:
                new_loader_conf.append(line)
    if not param_found and cmd_switch == 'e':
        new_loader_conf.append('%s="%s"'%tuple(cmd_params))
    open('/boot/loader.conf.local','w').write('\n'.join(new_loader_conf))
else:
    # no (or illegal) command given, explain options
    print ("usage %s -ser [parameter] [value]")
    print ("  -s show current loader.conf.local")
    print ("  -e edit/add loader.conf.local parameter")
    print ("  -r remove loader.conf.local parameter")
