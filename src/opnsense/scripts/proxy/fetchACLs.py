#!/usr/local/bin/python2.7
"""
    Copyright (c) 2015 Jos Schellevis - Deciso B.V.

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
"""

import urllib2
import os
import os.path
from ConfigParser import ConfigParser

acl_config_fn = ('/usr/local/etc/squid/externalACLs.conf')
acl_target_dir = ('/usr/local/etc/squid/acl')
acl_max_timeout = 30

# parse OPNsense external ACLs config
if os.path.exists(acl_config_fn):
    # create acl directory (if new)
    if not os.path.exists(acl_target_dir):
        os.mkdir(acl_target_dir)
    # read config and download per section
    cnf = ConfigParser()
    cnf.read(acl_config_fn)
    for section in cnf.sections():
        # check if tag enabled exists in section
        if cnf.has_option(section,'enabled'):
            # if enabled fetch file
            if cnf.get(section,'enabled')=='1':
                if cnf.has_option(section,'url'):
                    f = urllib2.urlopen(cnf.get(section,'url'),timeout = acl_max_timeout)
                    with open('%s/%s'%(acl_target_dir,section), "wb") as code:
                            code.write(f.read())
            # if disabled try to remove old file
            elif cnf.get(section,'enabled')=='0':
                try:
                    os.remove(section)
                except OSError:
                    pass
