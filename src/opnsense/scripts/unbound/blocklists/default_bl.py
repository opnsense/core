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
import os
import hashlib
import time
import re
import json
from . import BaseBlocklistHandler

class DefaultBlocklistHandler(BaseBlocklistHandler):
    def __init__(self):
        super().__init__('/usr/local/etc/unbound/unbound-blocklists.conf')
        self.priority = 10

        self.cnf_parsed = self._parse_config()            

    def _get_path(self, d, path, default=None):
        cur = d
        for part in path.split('.'):
            if isinstance(cur, dict) and part in cur:
                cur = cur[part]
            else:
                return default
        return cur
    
    def _blocklist_reader(self, uri, config):
        """
        Decides whether a blocklist can be read from a cached file or
        needs to be downloaded. Yields (unformatted) domains either way
        """
        total_lines = 0
        from_cache = True
        h = hashlib.md5(uri.encode()).hexdigest()
        cache_loc = '/tmp/bl_cache/'
        filep = cache_loc + h
        cache_ttl = float(config.get('cache_ttl', 72000))
        if not os.path.exists(filep) or (time.time() - os.stat(filep).st_ctime >= cache_ttl):
            # cache expired or not available yet, try to read, keep old one when failed
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

    def _domains_in_blocklist(self, blocklist, config):
        for line in self._blocklist_reader(blocklist, config):
            # cut line into parts before comment marker (if any)
            tmp = line.split('#')[0].split()
            entry = None
            while tmp:
                entry = tmp.pop(-1)
                if entry not in ['127.0.0.1', '0.0.0.0']:
                    break
            if entry:
                yield entry.lower()

    def _parse_config(self):
        configs = []
        if not self.cnf:
            return configs
        
        # interpret these as arrays
        arr_keys = ['source_nets', 'patterns', 'wildcards']
        
        for section in self.cnf.sections():
            if section.startswith('blocklist:'):
                config = {}
                for k,v in self.cnf[section].items():
                    parts = k.split(".")
                    cur = config
                    for part in parts[:-1]:
                        cur = cur.setdefault(part, {})

                    # normalize
                    leaf = parts[-1]
                    if leaf in arr_keys and isinstance(v, str):
                        v = [] if v == '' else [x.strip() for x in v.split(',')]

                    if len(parts) > 1:
                        cur[leaf] = v
                    else:
                        cur.setdefault(leaf, v)

                configs.append(config)
        return configs

    def get_policies(self, start_idx):
        result = []
        for config in self.cnf_parsed:
            config.setdefault('__idx__', start_idx)

            cfg = {}
            # format passlist
            default_pass = [self._get_path(config, "excludes.default", [])]
            passlist = default_pass + self._get_path(config, "excludes.patterns", [])
            compiled_passlist = set()
            for pattern in passlist:
                try:
                    re.compile(pattern, re.IGNORECASE)
                    compiled_passlist.add(pattern)
                except re.error:
                    syslog.syslog(syslog.LOG_ERR,
                        'blocklist download : skip invalid whitelist exclude pattern "%s" (%s)' % (
                            pattern, self.__class__.__name__
                        )
                    )
            cfg.setdefault('passlist', '|'.join(compiled_passlist))
            cfg.setdefault('source_nets', config.get('source_nets', []))
            cfg.setdefault('address', config.get('address'))
            cfg.setdefault('rcode', config.get('rcode'))
            cfg.setdefault('description', config.get('description'))
            result.append(cfg)
            start_idx += 1

        return result

    def get_blocklists(self):
        blocklists = []

        for config in self.cnf_parsed:
            idx = config.get('__idx__')
            result = {}
            for bl_shortcode, blocklist in self._get_path(config, "blocklists", {}).items():
                per_file_stats = {'uri': blocklist, 'blocklist': 0, 'wildcard': 0}
                for domain in self._domains_in_blocklist(blocklist, config):
                    if self.domain_pattern.match(domain):
                        per_file_stats['blocklist'] += 1
                        if domain.startswith('*.'):
                            result[domain[2:]] = {'idx': idx, 'bl': bl_shortcode, 'wildcard': True}
                            per_file_stats['wildcard'] += 1
                        else:
                            result[domain] = {'idx': idx, 'bl': bl_shortcode, 'wildcard': False}
                syslog.syslog(
                    syslog.LOG_NOTICE,
                    'blocklist: %(uri)s (block: %(blocklist)d wildcard: %(wildcard)d)' % per_file_stats
                )

            for domain in self._get_path(config, 'includes.patterns', []):
                entry = domain.rstrip().lower()
                if self.domain_pattern.match(entry):
                    result[entry] = {'idx': idx, 'bl': 'Custom', 'wildcard': False}

            for wildcard in self._get_path(config, 'includes.wildcards', []):
                entry = wildcard.rstrip().lower()
                if self.domain_pattern.match(entry):
                    # do not apply whitelist to wildcard domains
                    result[entry] = {'idx': idx, 'bl': 'Custom', 'wildcard': True}
            
            blocklists.append(result)

        return blocklists
