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

    --------------------------------------------------------------------------------------

    package : configd
    function: config handler
"""
import os
import stat
import collections
import copy
import xml.etree.cElementTree as ElementTree

__author__ = 'Ad Schellevis'


class Config(object):
    def __init__(self, filename):
        self._config_data = {}
        self._filename = filename
        self._file_mod = 0
        self._load()

    def _load(self):
        """ load config ( if timestamp is changed ), stores all found uuids into an item __uuid__ at the config root

        :return:
        """
        mod_time = os.stat(self._filename)[stat.ST_MTIME]
        if self._file_mod != mod_time:
            xml_node = ElementTree.parse(self._filename)
            root = xml_node.getroot()
            # initialize uuid containers, holds references to all uuid tagged items and names in the xml
            self.__uuid_data = {}
            self.__uuid_tags = {}
            self._config_data = self._traverse(root)
            self._config_data['__uuid__'] = self.__uuid_data
            self._config_data['__uuid_tags__'] = self.__uuid_tags
            self._file_mod = mod_time

        return self._config_data

    def _traverse(self, xml_node):
        """ traverse xml node and return ordered dictionary structure
        :param xml_node: ElementTree node
        :return: collections.OrderedDict
        """
        this_item = collections.OrderedDict()
        if len(list(xml_node)) > 0:
            for item in list(xml_node):
                item_content = self._traverse(item)
                # for dictionary type items, add tag attributes
                if type(item_content) == collections.OrderedDict:
                    for attr_key in item.attrib:
                        item_content["@%s" % attr_key] = item.attrib[attr_key]
                # store uuid's
                if 'uuid' in item.attrib:
                    self.__uuid_data[item.attrib['uuid']] = item_content
                    self.__uuid_tags[item.attrib['uuid']] = item.tag
                # add (list) items to this result
                if item.tag in this_item:
                    if type(this_item[item.tag]) != list:
                        tmp_item = copy.deepcopy(this_item[item.tag])
                        this_item[item.tag] = []
                        this_item[item.tag].append(tmp_item)

                    if item_content is not None:
                        # skip empty fields
                        this_item[item.tag].append(item_content)
                elif item_content is not None:
                    # create a new named item
                    this_item[item.tag] = item_content
        else:
            # last node, return text
            return xml_node.text

        return this_item

    def get(self):
        """ get active config data, load from disc if file in memory is different

        :return: dictionary
        """
        # refresh config if source xml is changed
        return self._load()
