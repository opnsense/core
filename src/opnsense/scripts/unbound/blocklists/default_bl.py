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
import hashlib
import time
from . import BaseBlocklistHandler

class DefaultBlocklistHandler(BaseBlocklistHandler):
    def __init__(self):
        super().__init__('/usr/local/etc/unbound/unbound-blocklists.conf')
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
            per_file_stats = {'uri': blocklist, 'skip': 0, 'blocklist': 0, 'wildcard': 0}
            for domain in self._domains_in_blocklist(blocklist):
                if self._whitelist_pattern.match(domain):
                    per_file_stats['skip'] += 1
                else:
                    if self.domain_pattern.match(domain):
                        per_file_stats['blocklist'] += 1
                        if domain in result:
                            # duplicate domain, signify in dataset for debugging purposes
                            if 'duplicates' in result[domain]:
                                result[domain]['duplicates'] += ',%s' % bl_shortcode
                            else:
                                result[domain]['duplicates'] = '%s' % bl_shortcode
                        else:
                            if domain.startswith('*.'):
                                result[domain[2:]] = {'bl': bl_shortcode, 'wildcard': True}
                                per_file_stats['wildcard'] += 1
                            else:
                                result[domain] = {'bl': bl_shortcode, 'wildcard': False}
                    else:
                        per_file_stats['skip'] += 1
            syslog.syslog(
                syslog.LOG_NOTICE,
                'blocklist: %(uri)s (exclude: %(skip)d block: %(blocklist)d wildcard: %(wildcard)d)' % per_file_stats
            )

        if self.cnf and self.cnf.has_section('include'):
            for key, value in self.cnf['include'].items():
                if key.startswith('custom'):
                    entry = value.rstrip().lower()
                    if not self._whitelist_pattern.match(entry):
                        if self.domain_pattern.match(entry):
                            result[entry] = {'bl': 'Manual', 'wildcard': False}
                elif key.startswith('wildcard'):
                    entry = value.rstrip().lower()
                    if self.domain_pattern.match(entry):
                        # do not apply whitelist to wildcard domains
                        result[entry] = {'bl': 'Manual', 'wildcard': True}

        return result

    def _blocklists_in_config(self):
        """
        Generator for derived classes to iterate over configured blocklists.
        """
        if self.cnf and self.cnf.has_section('blocklists'):
            for blocklist in self.cnf['blocklists']:
                list_type = blocklist.split('_', 1)
                bl_shortcode = 'Custom' if list_type[0] == 'custom' else list_type[1]
                yield (self.cnf['blocklists'][blocklist], bl_shortcode)

    def _blocklist_reader(self, uri):
        """
        Decides whether a blocklist can be read from a cached file or
        needs to be downloaded. Yields (unformatted) domains either way
        """
        total_lines = 0
        from_cache = True
        h = hashlib.md5(uri.encode()).hexdigest()
        cache_loc = '/tmp/bl_cache/'
        filep = cache_loc + h
        if not os.path.exists(filep) or (time.time() - os.stat(filep).st_ctime >= self.cache_ttl):
            # cache expired (20 hours) or not available yet, try to read, keep old one when failed
            try:
                os.makedirs(cache_loc, exist_ok=True)
                filep_t = "%s.tmp" % filep
                with open(filep_t, 'w') as outf:
                    for line in self._uri_reader(uri):
                        outf.write(line + '\n')
                from_cache = False
                if os.path.exists(filep): os.remove(filep)
                os.rename(filep_t, filep)
            except Exception as e:
                syslog.syslog(syslog.LOG_ERR, 'blocklist download : error reading file from %s (error : %s)' % (uri, e))
                # keep original file on failure
                if not os.path.exists(filep) and os.path.getsize(filep_t) > 0: os.rename(filep_t, filep)

        if os.path.exists(filep):
            for line in open(filep):
                total_lines += 1
                yield line

            syslog.syslog(
                syslog.LOG_NOTICE, 'blocklist download: %d total lines %s for %s' %
                    (total_lines, 'from cache' if from_cache else 'downloaded', uri)
            )
        else:
            syslog.syslog(syslog.LOG_ERR, 'unable to download blocklist from %s and no cache available' % uri)


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
