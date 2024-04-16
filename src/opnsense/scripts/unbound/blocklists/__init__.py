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

import os
import requests
import syslog
import re
import glob
import importlib
import sys
import fcntl
import ujson
import time
from configparser import ConfigParser

class BaseBlocklistHandler:
    def __init__(self, config=None):
        self.config = config
        self.cnf = None
        self.priority = 0
        self.cache_ttl = 72000

        self.cur_bl_location = '/var/unbound/data/dnsbl.json'

        self.domain_pattern = re.compile(
            r'^(\*\.){,1}(([\da-zA-Z_])([_\w-]{,62})\.){,127}(([\da-zA-Z])[_\w-]{,61})'
            r'?([\da-zA-Z]\.((xn\-\-[a-zA-Z\d]+)|([a-zA-Z\d]{2,})))$'
        )

        self._load_config()

    def get_config(self):
        """
        Get statically defined configuration options.
        """
        pass

    def get_blocklist(self):
        """
        Overridden by derived classes to produce a formatted blocklist. Returns a dictionary
        with domains as keys and a dictionary of metadata as values
        """
        pass

    def _blocklist_reader(self, uri):
        """
        Used by a derived class to define a caching and/or download routine.
        """
        pass

    def _blocklists_in_config(self):
        """
        Generator for derived classes to iterate over configured blocklists.
        """
        pass

    def _load_config(self):
        """
        Load a configuration file.
        """
        if os.path.exists(self.config):
            self.cnf = ConfigParser()
            self.cnf.read(self.config)

    def _domains_in_blocklist(self, blocklist):
        """
        Generator for derived classes to iterate over cached/downloaded domains.
        """
        for line in self._blocklist_reader(blocklist):
            # cut line into parts before comment marker (if any)
            tmp = line.split('#')[0].split()
            entry = None
            while tmp:
                entry = tmp.pop(-1)
                if entry not in ['127.0.0.1', '0.0.0.0']:
                    break
            if entry:
                yield entry.lower()

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
        self.handlers = handlers

    def _get_config(self):
        cfg = {}
        for handler in self.handlers:
            tmp = handler.get_config()
            if tmp:
                cfg = tmp | cfg
        return cfg

    def _merge_results(self, blocklists):
        """
        Take output of all the handlers and merge based on each handlers' priority.
        The default handler has highest priority
        """
        if len(blocklists) == 1:
            return next(iter(blocklists.values()))

        blocklists = dict(sorted(blocklists.items(), reverse=True))
        first = next(iter(blocklists.values()))
        for bl in list(blocklists.values())[1:]:
            for key, value in bl.items():
                if key not in first:
                    # no collision, merge
                    first[key] = value
                else:
                    # a handler with a lower priority has provided a policy
                    # on a domain that already exists in the blocklist,
                    # add it for debugging purposes
                    first[key].setdefault('collisions', []).append(value)

        return first

    def update_blocklist(self):
        blocklists = {}
        merged = {}
        for handler in self.handlers:
            blocklists[handler.priority] = handler.get_blocklist()

        merged['data'] = self._merge_results(blocklists)
        merged['config'] = self._get_config()

        # check if there are wildcards in the dataset
        has_wildcards = False

        for item in merged['data']:
            if merged['data'][item].get('wildcard') == True:
                has_wildcards = True
                break
        merged['config']['has_wildcards'] = has_wildcards

        # write out results
        if not os.path.exists('/var/unbound/data'):
            os.makedirs('/var/unbound/data')
        with open("/var/unbound/data/dnsbl.json.new", 'w') as unbound_outf:
            if merged['data']:
                ujson.dump(merged, unbound_outf)

        # atomically replace the current dnsbl so unbound can pick up on it
        os.replace('/var/unbound/data/dnsbl.json.new', '/var/unbound/data/dnsbl.json')

        syslog.syslog(syslog.LOG_NOTICE, "blocklist parsing done in %0.2f seconds (%d records)" % (
            time.time() - self.startup_time, len(merged['data'])
        ))
