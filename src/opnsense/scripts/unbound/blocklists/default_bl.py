#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Deciso B.V.
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

import syslog
import re
import os
import ujson
from . import BaseBlocklistHandler

class DefaultBlocklistHandler(BaseBlocklistHandler):
    def __init__(self):
        super().__init__('/tmp/unbound-blocklists.conf')
        self.priority = 100
        self._whitelist_pattern = self._get_excludes()

    def get_config(self):
        cfg = {}
        if self.cnf and self.cnf.has_section('settings'):
            if self.cnf.has_option('settings', 'address'):
                cfg['dst_addr'] = self.cnf.get('settings', 'address')
            if self.cnf.has_option('settings', 'rcode'):
                cfg['rcode'] = self.cnf.get('settings', 'rcode')
        return cfg

    def get_blocklist(self):
        result = {}
        for blocklist, bl_shortcode in self._blocklists_in_config():
            per_file_stats = {'uri': blocklist, 'skip': 0, 'blocklist': 0}
            for entry in self._domains_in_blocklist(blocklist):
                domain = entry.lower()
                if self._whitelist_pattern.match(entry):
                    per_file_stats['skip'] += 1
                else:
                    if self.domain_pattern.match(domain):
                        per_file_stats['blocklist'] += 1
                        if entry in result:
                            # duplicate domain, signify in dataset for debugging purposes
                            if 'duplicates' in result[entry]:
                                result[entry]['duplicates'] += ',%s' % bl_shortcode
                            else:
                                result[entry]['duplicates'] = '%s' % bl_shortcode
                        else:
                            result[entry] = {'bl': bl_shortcode, 'wildcard': False}
                    else:
                        per_file_stats['skip'] += 1
            syslog.syslog(
                syslog.LOG_NOTICE,
                'blocklist: %(uri)s (exclude: %(skip)d block: %(blocklist)d)' % per_file_stats
            )

        if self.cnf and self.cnf.has_section('include'):
            for key, value in self.cnf['include'].items():
                if key.startswith('custom'):
                    entry = value.rstrip().lower()
                    if not self._whitelist_pattern.match(entry):
                        if self.domain_pattern.match(entry):
                            result[entry] = {'bl': 'Manual', 'wildcard': False}
                elif key.startswith('wildcard'):
                    if self.domain_pattern.match(value):
                        # do not apply whitelist to wildcard domains
                        result[value] = {'bl': 'Manual', 'wildcard': True}

        return result

    def _get_excludes(self):
        whitelist_pattern = re.compile('$^') # match nothing
        if self.cnf.has_section('exclude'):
            exclude_list = set()
            for exclude_item in self.cnf['exclude']:
                pattern = self.cnf['exclude'][exclude_item]
                try:
                    re.compile(pattern, re.IGNORECASE)
                    exclude_list.add(pattern)
                except re.error:
                    syslog.syslog(syslog.LOG_ERR,
                        'blocklist download : skip invalid whitelist exclude pattern "%s" (%s)' % (
                            exclude_item, pattern
                        )
                    )
            if not exclude_list:
                exclude_list.add('$^')

            wp = '|'.join(exclude_list)
            whitelist_pattern = re.compile(wp, re.IGNORECASE)
            syslog.syslog(syslog.LOG_NOTICE, 'blocklist download : exclude domains matching %s' % wp)

        return whitelist_pattern
