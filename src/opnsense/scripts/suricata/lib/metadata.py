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

    shared module for suricata rule metadata
"""

import os
import syslog
import glob
import xml.etree.ElementTree


class Metadata(object):
    def __init__(self):
        self._rules_dir = '%s/../metadata/rules/' % (os.path.dirname(os.path.abspath(__file__)))

    def list_rules(self):
        """ list all available rules
        :return: generator method returning all known rulefiles
        """
        for filename in sorted(glob.glob('%s*.xml' % self._rules_dir)):
            try:
                rule_xml = xml.etree.ElementTree.fromstring(open(filename).read())
            except xml.etree.ElementTree.ParseError:
                # unparseable metadata
                syslog.syslog(syslog.LOG_ERR, 'suricata metadata unparsable @ %s' % filename)
                continue

            src_location = rule_xml.find('location')
            if src_location is None or 'url' not in src_location.attrib:
                syslog.syslog(syslog.LOG_ERR, 'suricata metadata missing location  @ %s' % filename)
            else:
                if rule_xml.find('files') is None:
                    syslog.syslog(syslog.LOG_ERR, 'suricata metadata missing files  @ %s' % filename)
                else:
                    for rule_filename in rule_xml.find('files'):
                        if 'documentation_url' in rule_filename.attrib:
                            documentation_url = rule_filename.attrib['documentation_url']
                        elif 'documentation_url' in rule_xml.attrib:
                            documentation_url = rule_xml.attrib['documentation_url']
                        else:
                            documentation_url = ""
                        metadata_record = dict()
                        metadata_record['documentation_url'] = documentation_url
                        metadata_record['source'] = src_location.attrib
                        metadata_record['filename'] = rule_filename.text.strip()
                        # for an archive, define file to extract
                        metadata_record['url_filename'] = None
                        if 'url' in rule_filename.attrib and rule_filename.attrib['url'].startswith('inline::'):
                            metadata_record['url'] = (metadata_record['source']['url'])
                            metadata_record['url_filename'] = rule_filename.attrib['url'][8:]
                        elif 'url' in rule_filename.attrib:
                            metadata_record['url'] = (rule_filename.attrib['url'])
                        else:
                            metadata_record['url'] = ('%s/%s' % (metadata_record['source']['url'],
                                                                 metadata_record['filename']))

                        if 'prefix' in src_location.attrib:
                            description_prefix = "%s/" % src_location.attrib['prefix']
                        else:
                            description_prefix = ""
                        if 'description' in rule_filename.attrib:
                            metadata_record['description'] = '%s%s' % (description_prefix,
                                                                       rule_filename.attrib['description'])
                        else:
                            metadata_record['description'] = '%s%s' % (description_prefix,
                                                                       rule_filename.text)

                        yield metadata_record
