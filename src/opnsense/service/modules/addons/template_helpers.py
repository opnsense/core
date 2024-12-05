"""
    Copyright (c) 2015-2023 Ad Schellevis <ad@opnsense.org>
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
import collections
import ipaddress

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
        return self.getNodeByTag(tag)

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
