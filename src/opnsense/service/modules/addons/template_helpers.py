"""
    Copyright (c) 2015 Ad Schellevis
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
"""

from operator import itemgetter

class Helpers(object):
    def __init__(self, template_in_data):
        """ initialize template helpers

        :param template_in_data: configuration data used by the engine
        :return:
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
        if self.getNodeByTag(tag):
            return True
        else:
            return False

    def toList(self, tag, sortBy=None):
        """ if an item should be a list of items (repeating tag), use this method to make sure that we always return
            a list. The configuration doesn't know if a non repeating item is supposed to be a list of items, this makes
            it explicit.
        :param tag: tag in dot notation (section.item)
        :param sortBy: resort result by specfied key
        :return: []
        """
        result = self.getNodeByTag(tag)
        if type(result) != list:
            # wrap result
            result = [result]

        if sortBy is None:
            return result
        else:
            # resort list by tag
            return sorted(result,key=itemgetter(sortBy))

    def getUUIDtag(self, uuid):
        """ retrieve tag name of registered uuid, returns __not_found__ if none available
        :param uuid:
        :return: string
        """
        if uuid in self._template_in_data['__uuid_tags__']:
            return self._template_in_data['__uuid_tags__'][uuid]
        else:
            return "__not_found__"

    def getUUID(self, uuid):
        """ retrieve item by uuid if found
        :param uuid:
        :return: dict
        """
        if uuid in self._template_in_data['__uuid__']:
            return self._template_in_data['__uuid__'][uuid]
        else:
            return {}
