#!/usr/local/bin/python3

"""
    Copyright (c) 2025 Ad Schellevis <ad@opnsense.org>
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
import sys
import os
import syslog
from modules import config, template, syslog_debug, syslog_error, syslog_notice

__author__ = 'Ad Schellevis'

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('template', help='template(s) to match')
    parser.add_argument('-c', help='configuration path (/conf/config.xml)', type=str, default='/conf/config.xml')
    parser.add_argument('-r', help='root directory (/)', type=str, default='/')
    args = parser.parse_args()
    syslog.openlog(os.path.basename(sys.argv[0]))

    # generate template
    tmpl = template.Template(args.r)
    conf = config.Config(args.c)
    tmpl.set_config(conf.get())
    filenames = tmpl.generate(args.template)
    if filenames is None:
        syslog_error('Unable to generate templates %s' % (args.template))
        print('templates...failed')
    else:
        for filename in filenames:
            syslog_debug(' %s generated %s' % (args.template, filename))
        syslog_notice('%s %s finished successfully' % (os.path.basename(sys.argv[0]), args.template))
        print('templates...done')
