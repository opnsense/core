#!/usr/local/bin/python3

"""
    Copyright (c) 2023-2025 Deciso B.V.
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

    def _blocklist_reader(self, uri, cache_ttl):
        """
        Decides whether a blocklist can be read from a cached file or
        needs to be downloaded. Yields (unformatted) domains either way
        """
        total_lines = 0
        from_cache = True
        h = hashlib.md5(uri.encode()).hexdigest()
        cache_loc = '/tmp/bl_cache/'
        filep = cache_loc + h
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

    def _domains_in_blocklist(self, blocklist, cache_ttl):
        for line in self._blocklist_reader(blocklist, cache_ttl):
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
        for section in self.cnf.sections():
            if section.startswith('blocklist_'):
                config = {
                    'id': section[10:],
                    'excludes.default': [],
                    'excludes.patterns': [],
                    'includes.patterns': [],
                    'includes.wildcards': [],
                    'source_nets': []
                }
                for k,v in self.cnf[section].items():
                    if type(config.get(k, None)) is list:
                        config[k] = [x.strip() for x in v.split(',') if x.strip() != '']
                    else:
                        config[k] = v.strip()
                configs.append(config)
        return configs

    def get_policies(self):
        result = []
        for config in self.cnf_parsed:
            cfg = {
                'source_nets': config.get('source_nets', []),
                'address': config.get('address'),
                'rcode': config.get('rcode'),
                'description': config.get('description'),
                'id': config.get('id')
            }
            # format passlist
            compiled_passlist = set()
            for pattern in  config.get('excludes.default', []) +config.get("excludes.patterns", []):
                try:
                    re.compile(pattern, re.IGNORECASE)
                    compiled_passlist.add(pattern)
                except re.error:
                    syslog.syslog(syslog.LOG_ERR,
                        'blocklist download : skip invalid whitelist exclude pattern "%s" (%s)' % (
                            pattern, self.__class__.__name__
                        )
                    )
            cfg['passlist'] = '|'.join(compiled_passlist)
            result.append(cfg)

        return result

    def blocklists_iter(self):
        """ yield blocklists per policy
            @returns dict[domain] = {'policy': {'bl':'xx', 'wildcard': False}}
        """
        domains = {}
        # blocklist may be used in different configs (for different nets), make sure we only fetch ones using the
        # the shortest TTL
        uris_to_fetch = {}
        for config in self.cnf_parsed:
            policy = config.get('id')
            for key, blocklist in config.items():
                if key.startswith('blocklists.'):
                    if blocklist not in uris_to_fetch:
                        uris_to_fetch[blocklist] = {'ttl': float(config.get('cache_ttl', 72000)), 'items': []}
                    uris_to_fetch[blocklist]['items'].append({
                        'bl_shortcode': key[11:],
                        'policy': policy
                    })
                    uris_to_fetch[blocklist]['ttl'] = min(
                        uris_to_fetch[blocklist]['ttl'],
                        float(config.get('cache_ttl', 72000))
                    )
            for domain in config.get('includes.patterns', []):
                entry = domain.rstrip().lower()
                if self.domain_pattern.match(entry):
                    yield entry, policy, {'bl': 'Custom', 'wildcard': False}

            for wildcard in config.get('includes.wildcards', []):
                entry = wildcard.rstrip().lower()
                if self.domain_pattern.match(entry):
                    # do not apply whitelist to wildcard domains
                    yield entry, policy, {'bl': 'Custom', 'wildcard': True}

        for blocklist, settings in uris_to_fetch.items():
            per_file_stats = {'uri': blocklist, 'blocklist': 0, 'wildcard': 0}
            for domain in self._domains_in_blocklist(blocklist, settings['ttl']):
                if self.domain_pattern.match(domain):
                    per_file_stats['blocklist'] += 1
                    wildcard = False
                    if domain.startswith('*.'):
                        domain = domain[2:]
                        wildcard = True
                        per_file_stats['wildcard'] += 1
                    for conf_item in settings['items']:
                        yield domain, conf_item['policy'], {
                            'bl': conf_item['bl_shortcode'],
                            'wildcard': wildcard
                        }
            syslog.syslog(
                syslog.LOG_NOTICE,
                'blocklist: %(uri)s (block: %(blocklist)d wildcard: %(wildcard)d)' % per_file_stats
            )

        return domains
