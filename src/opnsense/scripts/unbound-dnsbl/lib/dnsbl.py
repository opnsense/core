"""
    Copyright (c) 2022-2025 Deciso B.V.
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
import os
import time
import ipaddress
import json
import traceback
from . import Query

try:
    # create log_info(), log_err() function when not started within unbound
    from unboundmodule import log_info, log_err
except ImportError:
    def log_info(msg):
        return
    def log_err(msg):
        print(msg)


class DNSBL:
    """
    DNSBL implementation. Handles dynamically updating the blocklist as well as matching policies
    on incoming queries.
    """
    def __init__(self, context, dnsbl_path='/data/dnsbl.json', size_file='/data/dnsbl.size'):
        self.dnsbl_path = dnsbl_path
        self.size_file = size_file
        self.dnsbl_mtime_cache = 0
        self.dnsbl_update_time = 0
        self.dnsbl_available = False
        self.dnsbl = None
        self._context = context

        self._update_dnsbl()

    def _dnsbl_exists(self):
        return os.path.isfile(self.dnsbl_path) and os.path.getsize(self.dnsbl_path) > 0

    def _update_dnsbl(self):
        t = time.time()
        if (t - self.dnsbl_update_time) > 60:
            self.dnsbl_update_time = t
            if not self._dnsbl_exists():
                self.dnsbl_available = False
                return
            fstat = os.stat(self.dnsbl_path).st_mtime
            if fstat != self.dnsbl_mtime_cache:
                self.dnsbl_mtime_cache = fstat
                log_info("dnsbl_module: updating blocklist.")
                self._load_dnsbl()

    def _load_dnsbl(self):
        with open(self.dnsbl_path, 'r') as f:
            try:
                self.dnsbl = json.load(f)
                if self._context and type(self.dnsbl.get('config')) is dict:
                    if not self.dnsbl['config'].get('general'):
                        # old format, needs blocklist reload
                        raise ValueError("incompatible blocklist")
                    self._context.set_config(self.dnsbl['config'])
                log_info('dnsbl_module: blocklist loaded. length is %d' % len(self.dnsbl['data']))
                with open(self.size_file, 'w') as sfile:
                    sfile.write(str(len(self.dnsbl['data'])))
            except (json.decoder.JSONDecodeError, KeyError, ValueError) as e:
                if not self.dnsbl or isinstance(e, ValueError):
                    log_err("dnsbl_module: unable to parse blocklist file: %s. Please re-apply the blocklist settings." % e)
                    self.dnsbl_available = False
                    return
                else:
                    log_err("dnsbl_module: error parsing blocklist: %s, reusing last known list" % e)

        self.dnsbl_available = True

    def _in_network(self, client, networks):
        if not networks:
            return True
        try:
            src_address = ipaddress.ip_address(client)
        except ValueError:
            # when no valid source address could be found, we won't be able to match a policy either
            log_err('dnsbl_module: unable to parse client source: %s' % traceback.format_exc().replace('\n', ' '))
            return False

        for network in networks:
            if src_address in network:
                return True

        return False

    def policy_match(self, query: Query, qstate=None, orig=None):
        self._update_dnsbl()

        if not self.dnsbl_available:
            return False

        if not query.type in ('A', 'AAAA', 'CNAME', 'HTTPS'):
            return False

        domain = query.domain.rstrip('.').lower()
        sub = domain
        match = None
        while match is None:
            if sub in self.dnsbl['data']:
                meta_list = self.dnsbl['data'][sub]
                is_full_domain = sub == domain
                for meta in meta_list:
                    if (is_full_domain) or (not is_full_domain and meta.get('wildcard')):
                        policy = self._context.get_config(str(meta.get('idx')))
                        if policy:
                            if self._in_network(query.client, policy.get('source_nets')):
                                r = policy.get('pass_regex')
                                if r and (r.match(domain) or (orig and r.match(orig))):
                                    # if "orig" is defined, we know we are matching a CNAME.
                                    # the CNAME may be blocked, while the original query is explicitly whitelisted.
                                    # In these cases, the whitelisting should have priority since we don't expect
                                    # users to trace the CNAMEs themselves.
                                    return False
                                match = policy
                                match['bl'] = meta.get('bl')
                                break
                        else:
                            # allow query, but do not cache.
                            if qstate and hasattr(qstate, 'no_cache_store'):
                                qstate.no_cache_store = 1
                            return False

            if '.' not in sub or not self._context.has_wildcards:
                # either we have traversed all subdomains or there are no wildcards
                # in the dataset, in which case traversal is not necessary
                break
            else:
                sub = sub.split('.', maxsplit=1)[1]

        if match is not None:
            return match

        return False
