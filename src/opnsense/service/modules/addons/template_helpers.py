"""
    Copyright (c) 2015-2025 Ad Schellevis <ad@opnsense.org>
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
import datetime
import glob
import json
import collections
import ipaddress
import subprocess

class SortKeyHelper:
    """ generate item key for sort function
    """
    def __init__(self, fields):
        """
        :param fields: field names
        """
        self._fields = fields

    def get_key(self, record):
        """
        :param fields: dictionary item
        :return: list of keys for this record
        """
        result = list()
        for field in self._fields:
            result.append(record[field] if field in record else '')
        return result


# noinspection PyPep8Naming
class Helpers(object):
    def __init__(self, template_in_data):
        """
        :param template_in_data: configuration data used by the engine
        """
        self._template_in_data = template_in_data
        self._runtime_interface_address_cache = {}

    def getNodeByTag(self, tag):
        """ get tree node by tag
        :param tag: tag in dot notation (section.item)
        :return: dict or None if not found
        """
        node = self._template_in_data
        for item in tag.split('.'):
            if item in node:
                node = node[item]
            else:
                # not found
                return None
        # path found, return
        return node

    def exists(self, tag):
        """
        check if node exists in dictionary structure
        :param tag: tag in dot notation (section.item)
        :return: boolean
        """
        return self.getNodeByTag(tag) is not None

    def empty(self, tag):
        """ check if either the node does not exist or is empty
        :param tag: tag in dot notation (section.item)
        :return: boolean
        """
        node = self.getNodeByTag(tag)
        if node is None:
            return True
        elif len(node) == 0:
            return True
        elif hasattr(node, 'strip') and node.strip() in ('', '0'):
            return True
        else:
            return False

    def toList(self, tag, sortBy=None, sortAs=None):
        """ if an item should be a list of items (repeating tag), use this method to make sure that we always return
            a list. The configuration doesn't know if a non repeating item is supposed to be a list of items, this makes
            it explicit.
        :param tag: tag in dot notation (section.item)
        :param sortBy: resort result by specified key
        :return: []
        """
        result = self.getNodeByTag(tag)
        if result is None:
            return []
        if type(result) != list:
            # wrap result
            result = [result]

        if sortBy is None:
            return result
        else:
            # resort list by tag
            if sortAs == 'int':
                return sorted(result, key=lambda d: int(d[sortBy]))
            else:
                return sorted(result, key=lambda d: d[sortBy])

    def getUUIDtag(self, uuid):
        """
        :param uuid:
        :return: string tag name of registered uuid,  __not_found__ if none available
        """
        return self._template_in_data['__uuid_tags__'].get(uuid, '__not_found__')

    def getUUID(self, uuid):
        """
        :param uuid:
        :return: dict item by uuid if found
        """
        return self._template_in_data['__uuid__'].get(uuid, {})

    def physical_interface(self, name):
        """
        :param name: interface technical name [lan, wan, opt]
        :return: device name (e.g. em0), input name when not found
        """
        return self.getNodeByTag('interfaces.'+name+'.if') or name

    def physical_interfaces(self, names):
        """
        :param names: list of interface technical names [lan, wan, opt]
        :return: device names (e.g. ['em0']), skips missing entries
        """
        result = []
        for name in names:
            result.append(self.getNodeByTag('interfaces.'+name+'.if'))
        return list(filter(None, result))

    def _runtime_interface_address(self, name: str, family: str) -> str:
        cache_key = (name, family)
        if cache_key in self._runtime_interface_address_cache:
            return self._runtime_interface_address_cache[cache_key]

        if family not in ('inet', 'inet6'):
            self._runtime_interface_address_cache[cache_key] = ''
            return ''

        address = ''
        if family == 'inet':
            address = self._address_from_pluginctl46(name, family)
        elif family == 'inet6':
            address = self._address_from_pluginctl46(name, family)
            if not address or address.startswith('fe80:'):
                address = self._routed_address6_from_pluginctl_d(name)

        self._runtime_interface_address_cache[cache_key] = address
        return address

    def _address_from_pluginctl46(self, name: str, family: str) -> str:
        opt = '-4' if family == 'inet' else '-6'
        result = subprocess.run(
            ['/usr/local/sbin/pluginctl', opt, name],
            capture_output=True, text=True, check=False
        )
        if result.returncode != 0:
            return ''
        try:
            data = json.loads(result.stdout)
        except (json.JSONDecodeError, ValueError):
            return ''
        for entry in data.get(name, []):
            addr = entry.get('address', '')
            if addr:
                return addr
        return ''

    def _routed_address6_from_pluginctl_d(self, name: str) -> str:
        """Fall back to pluginctl -D for track6 interfaces where -6
        returns a link-local.  Walks the device and any tracked
        devices to find the first non-link-local, non-deprecated GUA."""
        device = self.physical_interface(name)
        devices = [device]
        # track6 interfaces derive their address on a different device
        for intf_name, intf_cfg in (self._template_in_data.get('interfaces') or {}).items():
            if isinstance(intf_cfg, dict) and intf_cfg.get('track6-interface') == name:
                tracked_dev = intf_cfg.get('if')
                if tracked_dev and tracked_dev not in devices:
                    devices.append(tracked_dev)

        for dev in devices:
            result = subprocess.run(
                ['/usr/local/sbin/pluginctl', '-D', dev],
                capture_output=True, text=True, check=False
            )
            if result.returncode != 0:
                continue
            try:
                data = json.loads(result.stdout)
            except (json.JSONDecodeError, ValueError):
                continue
            for addr_info in data.get(dev, {}).get('ipv6', []):
                if addr_info.get('link-local'):
                    continue
                if addr_info.get('deprecated') or addr_info.get('tentative'):
                    continue
                ipaddr = addr_info.get('ipaddr', '')
                if ipaddr:
                    return ipaddr
        return ''

    def interface_routed_address4(self, name: str) -> str:
        return self._runtime_interface_address(name, 'inet')

    def interface_routed_address6(self, name: str) -> str:
        return self._runtime_interface_address(name, 'inet6')

    def host_for_port(self, host_tag: str):
        return self.host_with_port(
            host_tag=host_tag,
            port_tag='',
            brackets_on_bare_ipv6=True
        )

    def host_with_port(self, host_tag: str, port_tag: str, brackets_on_bare_ipv6: bool = False):
        """ returns a formatting host and port and bracketed if IPv6 from tags
        :param host_tag: string
        :param port_tag: setting this < 0 will disable it's output and just output the ip formatted for inclusion with a separate formatted port
        :param no_brackets_on_bare_ip: setting this to True means that there will be brackets on IPv6 even if it just returns an address with no port
        :return: string
        """
        host = self.getNodeByTag(host_tag)
        port = self.getNodeByTag(port_tag)

        if port is None or port == "":
            port = -1
        else:
            port = int(port)


        skip_port = port < 0

        if host is None or host == "":
            return ""

        if skip_port and not brackets_on_bare_ipv6:
            return host

        if self.is_ipv6(host):
            host = "[" + host + "]"

        if skip_port:
            return host

        return '{}:{}'.format(host, port)

    @staticmethod
    def is_ipv6(ip: str) -> bool:
        return ":" in ip

    @staticmethod
    def getIPNetwork(network):
        """
        :param network: network
        :return: ip_network generated by ipaddress
        """
        return ipaddress.ip_network(network, False)

    @staticmethod
    def getUtcTime():
        """ return UTC timestamp in ISO 8601 format with second granularity
            :return: string
        """
        return datetime.datetime.utcnow().astimezone().isoformat(timespec="seconds")

    @staticmethod
    def sortDictList(lst, *operators):
        if type(lst) == list:
            lst.sort(key=SortKeyHelper(operators).get_key)
        elif type(lst) in (collections.OrderedDict, dict):
            return [lst]
        return lst

    @staticmethod
    def file_exists(pathname):
        """
        :param pathname: absolute or relative path name depending if it starts with a /
        :return: bool
        """
        if pathname.startswith('/'):
            return os.path.exists(pathname)
        else:
            template_path = os.path.realpath("%s/../../templates/" % os.path.dirname(__file__))
            return os.path.exists("%s/%s" % (template_path, pathname))

    @staticmethod
    def glob(pathname):
        """
        :param pathname: relative path name
        :return: list of files within template directory scope
        """
        result = list()
        template_path = os.path.realpath("%s/../../templates/" % os.path.dirname(__file__))
        for sfilename in glob.glob("%s/%s" % (template_path, pathname)):
            # only allowed to list templates within the template scope (default  /usr/local/opnsense/service/templates)
            if os.path.realpath(sfilename).startswith(template_path):
                result.append(sfilename[len(template_path):].lstrip('/'))

        return result

    @staticmethod
    def absolute_glob(pathname):
        """
        :param pathname: absolute glob pattern to be used to construct "conf.d"
                         type of patterns for applications which do not support to collect the items.
        :return: list of files matching the pattern
        """
        return list(glob.glob(pathname))
