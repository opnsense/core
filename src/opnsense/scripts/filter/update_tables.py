#!/usr/local/bin/python2.7

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
    update aliases
"""
import sys
import argparse
import syslog
import xml.etree.cElementTree as ET
import syslog
from lib.alias import Alias


class Aliases(object):
    def __init__(self, source_tree):
        self._source_tree = source_tree
        self._aliases = dict()

    def read(self):
        known_aliases_list = map(lambda x: x.text, self._source_tree.iterfind('table/name'))
        self._aliases = dict()
        for elem in self._source_tree.iterfind('table'):
            alias = Alias(elem, known_aliases=known_aliases_list, ttl=60)
            alias.resolve()
            self._aliases[alias.get_name()] = alias

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


if __name__ == '__main__':
    status = dict()
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', help='output type [json/text]', default='json')
    parser.add_argument('--source_conf', help='configuration xml', default='/usr/local/etc/filter_tables.conf')
    inputargs = parser.parse_args()

    try:
        source_tree = ET.ElementTree(file=inputargs.source_conf)
    except ET.ParseError as e:
        syslog.syslog(syslog.LOG_ERR, 'filter table parse error (%s) %s' % (str(e), inputargs.source_conf))
        sys.exit(-1)

    aliases = Aliases(source_tree)
    aliases.read()
    print ('result:',aliases.get_alias_deps('recursionC'))
