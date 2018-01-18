"""
    Copyright (c) 2017 Ad Schellevis <ad@opnsense.org>
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
    Alias representation
"""
import os
import re
import md5
import time
import requests
import ipaddress
import dns.resolver
import syslog
import geoip

class Alias(object):
    def __init__(self, elem, known_aliases=[], ttl=-1):
        """ construct alias object
            :param elem: ElementTree alias item
            :param known_aliases: all known alias names
            :param ttl: time to live in seconds (for other then ip/network types)
            :return: None
        """
        self._known_aliases = known_aliases
        self._dnsResolver = dns.resolver.Resolver()
        self._dnsResolver.timeout = 2
        self._is_changed = None
        self._has_expired = None
        self._ttl = ttl
        self._name = None
        self._type = None
        self._proto = None
        self._items = list()
        self._resolve_content = list()
        for subelem in elem:
            if subelem.tag == 'type':
                self._type = subelem.text
            elif subelem.tag == 'proto':
                self._proto = subelem.text
            elif subelem.tag == 'name':
                self._name = subelem.text
            elif subelem.tag == 'ttl':
                tmp = subelem.text.strip()
                if len(tmp.split('.')) <= 2 and tmp.replace('.', '').isdigit():
                    self._ttl = int(float(tmp))
            elif subelem.tag == 'aliasurl':
                self._items = set(sorted(subelem.text.split()))
            elif subelem.tag == 'address' and len(self._items) == 0:
                # special case, aliasurl fetched addresses in old implentation we don't want to use them
                self._items = set(sorted(subelem.text.split()))
            elif subelem.tag == 'url':
                self._items = set(sorted(subelem.text.split()))
        # we'll save the calculated hash for the unparsed alias content
        self._filename_alias_hash = '/var/db/aliastables/%s.md5.txt' % self._name
        # the generated alias contents, without dependencies
        self._filename_alias_content = '/var/db/aliastables/%s.self.txt' % self._name

    def _parse_address(self, address, ssl_no_verify=False, timeout=120):
        """ parse addresses and hostnames, yield only valid addresses and networks
            :param address: address or network
            :return: boolean
        """
        address = address.strip()
        if address.find('/') > -1:
            # provided address could be a network
            try:
                ipaddress.ip_network(unicode(address), strict=False)
                yield address
                return
            except (ipaddress.AddressValueError, ValueError):
                pass
        else:
            # check if address is an ipv4/6 address or range
            try:
                tmp = unicode(address).split('-')
                addr1 = ipaddress.ip_address(tmp[0])
                if len(tmp) > 1:
                    # address range (from-to)
                    addr2 = ipaddress.ip_address(tmp[1])
                    for addr in ipaddress.summarize_address_range(addr1, addr2):
                        yield str(addr)
                else:
                    yield address
                return
            except (ipaddress.AddressValueError, ValueError):
                pass

        # try to resolve provided address
        for record_type in ['A', 'AAAA']:
            try:
                for rdata in self._dnsResolver.query(address, record_type):
                    yield str(rdata)
            except (dns.resolver.NoAnswer, dns.resolver.NXDOMAIN, dns.exception.Timeout):
                pass

    def _fetch_url(self, url, ssl_no_verify=False, timeout=120):
        """ return unparsed (raw) alias entries without dependencies
            :param url: url
            :param ssl_no_verify: disable ssl cert validation
            :param timeout: timeout
            :return: iterator
        """
        # set request parameters
        req_opts = dict()
        req_opts['url'] = url
        req_opts['stream'] = True
        req_opts['timeout'] = timeout
        if ssl_no_verify:
            req_opts['verify'] = False
        # fetch data
        try:
            req = requests.get(**req_opts)
            if req.status_code == 200:
                # only handle content if response is correct
                req.raw.decode_content = True
                lines = req.raw.read().splitlines()
                if len(lines) > 100:
                    # when larger alias lists are downloaded, make sure we log before handling.
                    syslog.syslog(syslog.LOG_ERR, 'fetch alias url %s (lines: %s)' % (url, len(lines)))
                for line in lines:
                    raw_address = re.split(r'[\s,;|#]+', line)[0]
                    if raw_address and not raw_address.startswith('//'):
                        for address in self._parse_address(raw_address):
                            yield address
        except:
            syslog.syslog(syslog.LOG_ERR, 'error fetching alias url %s' % (url))

    def _fetch_geo(self, geoitem, ssl_no_verify=False, timeout=120):
        """ fetch geoip addresses, if not downloaded or outdated force an update
            :return: iterator
        """
        do_update = True
        if os.path.isfile('/usr/local/share/GeoIP/alias/NL-IPv4'):
            fstat = os.stat('/usr/local/share/GeoIP/alias/NL-IPv4')
            if (time.time() - fstat.st_mtime) < (86400 - 90):
                do_update = False
        if do_update:
            syslog.syslog(syslog.LOG_ERR, 'geoip updated (files: %s lines: %s)' % geoip.download_geolite())

        for proto in self._proto.split(','):
            geoip_filename = "/usr/local/share/GeoIP/alias/%s-%s" % (geoitem, proto)
            if os.path.isfile(geoip_filename):
                with open(geoip_filename) as f_in:
                    for line in f_in:
                        for address in self._parse_address(line):
                            yield address

    def items(self):
        """ return unparsed (raw) alias entries without dependencies
            :return: iterator
        """
        for item in self._items:
            if item not in self._known_aliases:
                yield item

    def uniqueid(self):
        """ generate an identification hash for this alias
            :return: md5 (string)
        """
        tmp = ','.join(sorted(list(self.items())))
        if self._proto:
            tmp = '%s[%s]' % (tmp, self._proto)
        return md5.new(tmp).hexdigest()

    def changed(self):
        """ is the alias changed (cached result, if changed within this objects lifetime)
            :return: boolean
        """
        if self._is_changed is None:
            if os.path.isfile(self._filename_alias_hash) and os.path.isfile(self._filename_alias_content):
                self._is_changed = open(self._filename_alias_hash).read().strip() != self.uniqueid()
            else:
                self._is_changed = True

        return self._is_changed

    def expired(self):
        """ if this alias has an expiry (ttl), has it reached the end of it's lifetime
            :return: boolean
        """
        if self._has_expired is None:
            if self._ttl > 0 and os.path.isfile(self._filename_alias_hash):
                fstat = os.stat(self._filename_alias_hash)
                self._has_expired = time.time() - fstat.st_mtime > self._ttl
            else:
                self._has_expired = False
        return self._has_expired

    def resolve(self, ssl_no_verify=False, timeout=120, force=False):
        """ resolve (fetch) alias content, without dependencies.
            :param ssl_no_verify: when alias content is fetched from a remote location, disable ssl verify
            :param timeout: fetch timeout
            :force: force load
            :return: string
        """
        if not self._resolve_content:
            if self.expired() or self.changed() or force:
                with open(self._filename_alias_content, 'w') as f_out:
                    for item in self.items():
                        address_parser = self.get_parser()
                        if address_parser:
                            for address in address_parser(item, ssl_no_verify=ssl_no_verify, timeout=timeout):
                                if address not in self._resolve_content:
                                    # flush new alias content (without dependencies) to disk, so progress can easliy
                                    # be followed, large lists of domain names can take quite some resolve time.
                                    f_out.write('%s\n' % address)
                                    f_out.flush()
                                    # preserve addresses
                                    self._resolve_content.append(address)
                    # flush md5 hash to disk
                    open(self._filename_alias_hash, 'w').write(self.uniqueid())
            else:
                self._resolve_content = open(self._filename_alias_content).read().split()
        # return the addresses and networks of this alias
        return self._resolve_content

    def get_parser(self):
        """ fetch address parser to use, None if alias type is not handled here
            :return: function or None
        """
        if self._type in ['host', 'network']:
            return self._parse_address
        elif self._type in ['url', 'urltable']:
            return self._fetch_url
        elif self._type == 'geoip':
            return self._fetch_geo
        else:
            return None

    def get_type(self):
        """ get type of alias
            :return: string
        """
        return self._type

    def get_name(self):
        """ get alias name
            :return: string
        """
        return self._name

    def get_deps(self):
        """ fetch alias dependencies
            :param in_data: raw input data (ruleset)
            :return: new ruleset
        """
        for item in self._items:
            if item in self._known_aliases:
                yield item
