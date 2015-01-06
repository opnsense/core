"""
    Copyright (c) 2015 Ad Schellevis

    part of OPNsense (https://www.opnsense.org/)

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
    package : check_reload_status
    function: config handler



"""
__author__ = 'Ad Schellevis'

import os
import stat
import collections
import copy
import xml.etree.cElementTree as ElementTree


class Config(object):
    def __init__(self,filename):
        self._filename = filename
        self._config_data = {}
        self._file_mod = 0

        self._load()

    def _load(self):
        """ load config ( if timestamp is changed )

        :return:
        """
        mod_time = os.stat(self._filename)[stat.ST_MTIME]
        if self._file_mod != mod_time:
            xml_node = ElementTree.parse(self._filename)
            root = xml_node.getroot()
            self._config_data = self._traverse(root)
            self._file_mod = mod_time

    def _traverse(self,xmlNode):
        """ traverse xml node and return ordered dictionary structure
        :param xmlNode: ElementTree node
        :return: collections.OrderedDict
        """
        this_item = collections.OrderedDict()
        if len(list(xmlNode)) > 0 :
            for item in list(xmlNode):
                item_content = self._traverse(item)
                if this_item.has_key(item.tag):
                    if type(this_item[item.tag]) != list:
                        tmp_item = copy.deepcopy(this_item[item.tag])
                        this_item[item.tag] = []
                        this_item[item.tag].append(tmp_item)

                    if item_content != None:
                        # skip empty fields
                        this_item[item.tag].append(item_content)
                elif item_content != None:
                    # create a new named item
                    this_item[item.tag] = self._traverse(item)
        else:
            # last node, return text
            return xmlNode.text

        return this_item


    def indent(self,elem, level=0):
        """ indent cElementTree (prettyprint fix)
            used from : http://infix.se/2007/02/06/gentlemen-indent-your-xml
            @param elem: cElementTree
            @param level: Currentlevel
        """
        i = "\n" + level*"  "
        if len(elem):
            if not elem.text or not elem.text.strip():
                elem.text = i + "  "
            for e in elem:
                self.indent(e, level+1)
                if not e.tail or not e.tail.strip():
                    e.tail = i + "  "
            if not e.tail or not e.tail.strip():
                e.tail = i
        else:
            if level and (not elem.tail or not elem.tail.strip()):
                elem.tail = i


    def get(self):
        """ get active config data, load from disc if file in memory is different

        :return: dictionary
        """
        # refresh config if source xml is changed
        self._load()

        return self._config_data