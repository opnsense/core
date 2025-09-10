"""
    Copyright (c) 2017-2023 Ad Schellevis <ad@opnsense.org>
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
import time
import syslog
import xml.etree.cElementTree as ET
from hashlib import md5
from dns.exception import DNSException
from .pf import PF
from .geoip import GEOIP
from .uri import UriParser
from .arpcache import ArpCache
from .bgpasn import BGPASN
from .interface import InterfaceParser
from .auth import AuthGroup
from .base import BaseContentParser


class Alias(object):
    def __init__(self, elem, known_aliases=[], ttl=-1, ssl_no_verify=False, timeout=120):
        """ construct alias object
            :param elem: ElementTree alias item
            :param known_aliases: all known alias names
            :param ttl: time to live in seconds (for other then ip/network types)
            :param ssl_no_verify: disable ssl verify when fetching content
            :param timeout: request timeout in seconds
            :return: None
        """
        self._known_aliases = known_aliases
        self._pf_addresses = 0
        self._is_changed = None
        self._has_expired = None
        # general alias properties, excluding content
        self._properties = {
            'ssl_no_verify': ssl_no_verify,
            'timeout': timeout,
            'interface': None,
            'proto': 'IPv4,IPv6'
        }
        self._ttl = ttl
        self._name = None
        self._type = None
        self._items = list()
        self._resolve_content = set()
        for subelem in elem:
            if subelem.tag == 'type':
                self._type = subelem.text
                self._properties['type'] = subelem.text
            elif subelem.tag == 'name':
                self._name = subelem.text
                self._properties['name'] = self._name
            elif subelem.tag == 'ttl':
                tmp = subelem.text.strip()
                if len(tmp.split('.')) <= 2 and tmp.replace('.', '').isdigit():
                    self._ttl = int(float(tmp))
            elif subelem.tag in ('aliasurl', 'address', 'url') and subelem.text is None:
                self._items = set()
            elif subelem.tag == 'aliasurl':
                self._items = set(sorted(subelem.text.split()))
            elif subelem.tag == 'address' and len(self._items) == 0:
                # special case, aliasurl fetched addresses in old implementation we don't want to use them
                self._items = set(sorted(subelem.text.split()))
            elif subelem.tag == 'url':
                self._items = set(sorted(subelem.text.split()))
            else:
                self._properties[subelem.tag] = subelem.text

        # we'll save the calculated hash for the unparsed alias content
        self._filename_alias_hash = '/var/db/aliastables/%s.md5.txt' % self._name
        # the generated alias contents, without dependencies
        self._filename_alias_content = '/var/db/aliastables/%s.self.txt' % self._name

    def get_pf_addr_count(self):
        return self._pf_addresses

    def set_pf_addr_count(self, cnt):
        self._pf_addresses = cnt

    def items(self):
        """ return unparsed (raw) alias entries without dependencies
            :return: iterator
        """
        for item in self._items:
            if item not in self._known_aliases or self.get_name() == item:
                yield item

    def uniqueid(self):
        """ generate an identification hash for this alias
            :return: md5 (string)
        """
        tmp = ','.join(sorted(list(self._items)))
        for fieldname in ['proto', 'path_expression', 'authtype', 'username', 'password']:
            if fieldname in self._properties:
                tmp = '%s[%s]' % (tmp, self._properties[fieldname])
        return md5(tmp.encode()).hexdigest()

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

    def is_managed(self):
        """ Is this alias managed by us (or only used as source)
            Since we only write md5 hash files if we know how to generate the contents, we can assume
            it was an alias from us if such a file exists.
            :return: boolean
        """
        return self.get_parser() is not None and os.path.isfile(self._filename_alias_hash)

    def cached(self):
        """ load cached contents in case we don't want to resolve the alias
        """
        if os.path.isfile(self._filename_alias_content) and len(self._resolve_content) == 0:
            self._resolve_content = set(open(self._filename_alias_content).read().split())
            self._is_changed = False
            self._has_expired = False
        elif not os.path.isfile(self._filename_alias_content):
            # to prevent an inconsistent state when using cached results we will still resolve the alias
            # in case the cache doesn't exist at all. Although this costs time, it's probably the safest option here.
            return self.resolve()

        return self._resolve_content

    @staticmethod
    def read_alias_file(filename):
        """
        :param filename: filename to read (when it exists)
        :return: string|bool, False when not found or not parseable
        """
        if os.path.isfile(filename):
            try:
                return open(filename, 'r').read()
            except UnicodeDecodeError:
                return False
        return False


    def resolve(self, force=False):
        """ resolve (fetch) alias content, without dependencies.
            :force: force load
            :return: set
        """
        if not self._resolve_content:
            if self.expired() or self.changed() or force:
                undo_content = self.read_alias_file(self._filename_alias_content)
                try:
                    self._resolve_content = self.pre_process()
                    address_parser = self.get_parser()
                    if address_parser:
                        for item in self.items():
                            for address in address_parser.iter_addresses(item):
                                self._resolve_content.add(address)
                        # resolve hostnames (async) if there are any in the collected set
                        self._resolve_content = self._resolve_content.union(address_parser.resolve_dns())
                except (IOError, DNSException) as e:
                    syslog.syslog(syslog.LOG_ERR, 'alias resolve error %s (%s)' % (self._name, e))
                    self._resolve_content = set(undo_content.split("\n")) if undo_content is not False else set()

                resolve_content_str = '\n'.join(sorted(self._resolve_content))
                if undo_content != resolve_content_str:
                    # Always save last recorded content to disk when changed, even when we're not responsible
                    # for the alias, so we can use cached results when reloading a single alias.
                    with open(self._filename_alias_content, 'w') as f_out:
                        f_out.write(resolve_content_str)

                if self.get_parser():
                    # flush md5 hash to disk (when unchanged)
                    new_uniqueid = self.uniqueid()
                    old_uniqueid = None
                    if os.path.isfile(self._filename_alias_hash):
                       old_uniqueid = open(self._filename_alias_hash, 'r').read()
                    if old_uniqueid != new_uniqueid:
                        open(self._filename_alias_hash, 'w').write(self.uniqueid())
                    else:
                        # update modification time for correct TTL measurements when unchanged
                        os.utime(self._filename_alias_hash, None)
            else:
                self._resolve_content = set(open(self._filename_alias_content).read().split())

        # return the addresses and networks of this alias
        return self._resolve_content

    def get_parser(self):
        """ fetch address parser to use, None if alias type is not handled here or only during pre processing
            :return: function or None
        """
        if self._type in ['host', 'network', 'networkgroup']:
            return BaseContentParser(**self._properties)
        elif self._type in ['url', 'urltable', 'urljson']:
            return UriParser(**self._properties)
        elif self._type == 'geoip':
            return GEOIP(**self._properties)
        elif self._type == 'dynipv6host':
            return InterfaceParser(**self._properties)
        elif self._type == 'mac':
            return ArpCache(**self._properties)
        elif self._type == 'asn':
            return BGPASN(**self._properties)
        elif self._type == 'authgroup':
            return AuthGroup(**self._properties)
        else:
            return None

    def pre_process(self, skip_result=False):
        """ alias type pre processors
            :return: set initial alias content
        """
        result = set()
        if self.get_type() == 'interface_net':
            PF.flush_network(self.get_name(), self._properties['interface'])
        # collect current table contents for selected types
        if self.get_type() in ['interface_net', 'external'] and not skip_result:
            result = result.union(set(PF.list_table(self.get_name())))

        return result

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

    def get_filename(self):
        """ return target filename for this alias content
            :return: string
        """
        return '/var/db/aliastables/%s.txt' % self._name

    def get_file_size(self):
        """ return filesize in bytes of the full alias
        """
        if os.path.isfile(self.get_filename()):
            return os.stat(self.get_filename()).st_size
        return 0

    def get_deps(self):
        """ fetch alias dependencies
            :param in_data: raw input data (ruleset)
            :return: new ruleset
        """
        for item in self._items:
            if item in self._known_aliases:
                yield item


class AliasParser(object):
    """ Alias Parser class, encapsulates all aliases
    """
    def __init__(self, source_tree):
        self._source_tree = source_tree
        self._aliases = dict()

    def read(self):
        """ read aliases
            :return: None
        """
        self._aliases = dict()
        external_aliases = list()
        alias_parameters = dict()
        alias_pf_stats = dict()
        alias_parameters['known_aliases'] = [x.text for x in self._source_tree.iterfind('table/name')]
        for alias_name, alias_info in PF.list_tables():
            alias_pf_stats[alias_name] = alias_info
            if alias_name not in alias_parameters['known_aliases']:
                alias_parameters['known_aliases'].append(alias_name)
                external_aliases.append(alias_name)

        # parse general alias settings
        conf_general = self._source_tree.find('general')
        if conf_general:
            if conf_general.find('ssl_no_verify') is not None and conf_general.find('ssl_no_verify').text == "1":
                alias_parameters['ssl_no_verify'] = True

        # loop through user defined aliases
        for elem in self._source_tree.iterfind('table'):
            alias = Alias(elem, **alias_parameters)
            if alias.get_name() in alias_pf_stats:
                alias.set_pf_addr_count(alias_pf_stats[alias.get_name()].get('addresses', 0))
            self._aliases[alias.get_name()] = alias

        # attach external aliases which aren't defined via the gui
        for alias_name in external_aliases:
            elem = ET.Element("table")
            ET.SubElement(elem, 'type').text = 'external'
            ET.SubElement(elem, 'name').text = alias_name
            ET.SubElement(elem, 'ttl').text = '1'
            self._aliases[alias_name] = Alias(elem, **alias_parameters)

    def get_alias_deps(self, alias, alias_deps=None):
        """ recursive fetch all alias dependencies
            :param alias: alias name
            :param alias_deps: dependencies gathered
            :return: list of aliases
        """
        if not alias_deps:
            alias_deps = list()
        if alias in self._aliases:
            for dep in self._aliases[alias].get_deps():
                if dep not in alias_deps:
                    alias_deps.append(dep)
                    self.get_alias_deps(dep, alias_deps)
        return alias_deps

    def get(self, name):
        """ get alias by name
            :param name: alias name
            :return: alias (or None if not found)
        """
        if name in self._aliases:
            return self._aliases[name]
        return None

    def __iter__(self):
        """ iterate all known aliases
            :return: iterator
        """
        for alias in self._aliases:
            yield self._aliases[alias]
