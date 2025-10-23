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

import os
import requests
import syslog
import re
import glob
import importlib
import sys
import fcntl
import ujson
import uuid
import time
from configparser import ConfigParser
from ipaddress import ip_network


class BaseBlocklistHandler:
    def __init__(self, config=None):
        self.config = config
        self.cnf = None
        self.cnf_indexed = None
        self.priority = 0
        # XXX: remove when plugins are refactored
        self._compat_id = str(uuid.uuid4())

        self.cur_bl_location = '/var/unbound/data/dnsbl.json'

        self.domain_pattern = re.compile(
            r'^(\*\.){,1}(([\da-zA-Z_])([_\w-]{,62})\.){,127}(([\da-zA-Z])[_\w-]{,61})'
            r'?([\da-zA-Z]\.((xn\-\-[a-zA-Z\d]+)|([a-zA-Z\d]{2,})))$'
        )

        self._load_config()

    def get_policies(self):
        """
        Output configuration exposed to Unbound, start_idx is used by the derived class
        to link a domain to a running policy.
        """
        if hasattr(self, "get_config") and callable(getattr(self, "get_config")):
            # static config, address and rcode can't be retrieved reliably after migration
            cfg = {
                'source_nets': [],
                'address': '0.0.0.0',
                'rcode': 'NOERROR',
                'description': 'compat',
                'id': self._compat_id
            }
            return [cfg]

    def blocklists_iter(self):
        """
        Overridden by derived classes to produce a formatted blocklist. Returns an array
        of dictionaries with domains as keys and a dictionary of metadata as values.
        """
        # backwards compat
        if hasattr(self, "get_blocklist") and callable(getattr(self, "get_blocklist")):
            for domain, cnf in self.get_blocklist().items():
                yield domain, self._compat_id, cnf

    def get_passlist_patterns(self):
        # unused, should now be returned in a 'passlist' property in get_policies()
        return []

    def _load_config(self):
        """
        Load a configuration file.
        """
        if os.path.exists(self.config):
            self.cnf = ConfigParser()
            self.cnf.read(self.config)

    def _uri_reader(self, uri):
        """
        Takes a URI and yields domain entries.
        """
        req_opts = {
            'url': uri,
            'timeout': 30,
            'stream': True
        }
        req = requests.get(**req_opts)
        if req.status_code >= 200 and req.status_code <= 299:
            req.raw.decode_content = True
            prev_chop  = ''
            while True:
                chop = req.raw.read(1024).decode()
                if not chop:
                    if prev_chop:
                        yield prev_chop
                    break
                else:
                    parts = (prev_chop + chop).split('\n')
                    if parts[-1] != "\n":
                        prev_chop = parts.pop()
                    else:
                        prev_chop = ''
                    for part in parts:
                        yield part
        else:
            raise Exception(
                'blocklist download : unable to download file from %s (status_code: %d)' % (uri, req.status_code)
            )

class BlocklistParser:
    def __init__(self):
        # check for a running download process, this may take a while so it's better to check...
        try:
            lck = open('/tmp/unbound-download_blocklists.tmp', 'w+')
            fcntl.flock(lck, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except IOError:
            # already running, exit status 99
            sys.exit(99)

        syslog.openlog('unbound', facility=syslog.LOG_LOCAL4)
        self.handlers = list()
        self._register_handlers()
        self.startup_time = time.time()

    def update_blocklist(self):
        merged_result = {
            'data': {},
            'config': {
                'general': {
                    'has_wildcards': False
                }
            }
        }
        # collect data from all handlers
        policies = {}
        blocklists = {}
        for hidx, handler in enumerate(self.handlers):
            for policy in handler.get_policies():
                policies[policy['id']] = policy.copy()
                if policy['source_nets']:
                    # networks should be equally sized to use them properly when determining prioritization
                    # the api validates these
                    prio = max([ip_network(src).num_addresses for src in policy['source_nets']])
                else:
                    # without network specification, choose the highest network (least important)
                    prio = ip_network('0::0/0').num_addresses
                # persist sort criteria, network prio and handler index
                policies[policy['id']]['prio'] = prio
                policies[policy['id']]['hidx'] = hidx
            # blocklist items per domain and policy
            for domain, policy, cnf in handler.blocklists_iter():
                if domain not in blocklists:
                    blocklists[domain] = {}
                if policy not in blocklists[domain]:
                    blocklists[domain][policy] = []
                blocklists[domain][policy].append(cnf)

        # sort policies by priority (smallest net first)
        policies = dict(sorted(policies.items(), key=lambda x: (x[1]['prio'], x[1]['hidx'])))

        # build domain list sorted by policy priority
        data = {}
        for domain in blocklists:
            data[domain] = []
            for policy in policies:
                if policy in blocklists[domain]:
                    for item in blocklists[domain][policy]:
                        item['idx'] = policy
                        data[domain].append(item)
                        if not merged_result['config']['general']['has_wildcards'] and item.get('wildcard'):
                            merged_result['config']['general']['has_wildcards'] = True
        merged_result['data'] = data
        merged_result['config'].update(policies)
        # write out results
        if not os.path.exists('/var/unbound/data'):
            os.makedirs('/var/unbound/data')
        with open("/var/unbound/data/dnsbl.json.new", 'w') as unbound_outf:
            if merged_result['data']:
                ujson.dump(merged_result, unbound_outf)

        # atomically replace the current dnsbl so unbound can pick up on it
        os.replace('/var/unbound/data/dnsbl.json.new', '/var/unbound/data/dnsbl.json')

        syslog.syslog(syslog.LOG_NOTICE, "blocklist parsing done in %0.2f seconds (%d records)" % (
            time.time() - self.startup_time, len(merged_result['data'])
        ))

    def _register_handlers(self):
        handlers = list()
        for filename in glob.glob("%s/*.py" % os.path.dirname(__file__)):
            importlib.import_module(".%s" % os.path.splitext(os.path.basename(filename))[0], __name__)

        for module_name in dir(sys.modules[__name__]):
            for attribute_name in dir(getattr(sys.modules[__name__], module_name)):
                cls = getattr(getattr(sys.modules[__name__], module_name), attribute_name)
                if isinstance(cls, type) and issubclass(cls, BaseBlocklistHandler)\
                        and cls not in (BaseBlocklistHandler,):
                    handlers.append(cls())
        handlers.sort(key=lambda h: h.priority)
        self.handlers = handlers
