"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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

    def _list_xml_sources(self, replace_tags = {}):
        """ list all available rule xml files
        :return: generator method returning all known rule xml files
        """
        for filename in sorted(glob.glob('%s*.xml' % self._rules_dir), reverse=True):
            try:
                xml_data = open(filename, 'r').read()
                for tag in replace_tags:
                    search_tag = '%%%%%s%%%%' % tag
                    if xml_data.find(search_tag) > -1:
                        xml_data = xml_data.replace(search_tag, replace_tags[tag])
                rule_xml = xml.etree.ElementTree.fromstring(xml_data)
                rule_xml.attrib['metadata_source'] = os.path.basename(filename)
                yield rule_xml
            except xml.etree.ElementTree.ParseError:
                # unparseable metadata
                syslog.syslog(syslog.LOG_ERR, 'suricata metadata unparsable @ %s' % filename)
                continue

    def list_rule_properties(self):
        """ collect settable properties from installed ruleset
        :return: dict unique properties
        """
        result = dict()
        for rule_xml in self._list_xml_sources():
            if rule_xml.find('properties') is not None:
                for rule_prop in rule_xml.find('properties'):
                    if 'name' in rule_prop.attrib:
                        result[rule_prop.attrib['name']] = {'default': None}
                        if 'default' in rule_prop.attrib:
                            result[rule_prop.attrib['name']]['default'] = rule_prop.attrib['default']
        return result

    def list_rules(self, replace_tags = {}):
        """ list all available rules
        :return: generator method returning all known rulefiles
        """
        target_filenames = list()
        for rule_xml in self._list_xml_sources(replace_tags):
            src_location = rule_xml.find('location')
            if src_location is None or 'url' not in src_location.attrib:
                syslog.syslog(syslog.LOG_ERR, 'suricata metadata missing location  @ %s' % filename)
            else:
                if rule_xml.find('files') is None:
                    syslog.syslog(syslog.LOG_ERR, 'suricata metadata missing files  @ %s' % filename)
                else:
                    http_headers = dict()
                    if rule_xml.find('headers') is not None:
                        for header in rule_xml.find('headers'):
                            http_headers[header.tag] = header.text.strip()

                    required_files = list()
                    for rule_filename in rule_xml.find('files'):
                        if 'documentation_url' in rule_filename.attrib:
                            documentation_url = rule_filename.attrib['documentation_url']
                        elif 'documentation_url' in rule_xml.attrib:
                            documentation_url = rule_xml.attrib['documentation_url']
                        else:
                            documentation_url = ""
                        metadata_record = {
                            'required': False,
                            'deprecated': False,
                            'metadata_source': rule_xml.attrib['metadata_source']
                        }
                        metadata_record['documentation_url'] = documentation_url
                        metadata_record['source'] = src_location.attrib
                        metadata_record['filename'] = rule_filename.text.strip()
                        metadata_record['http_headers'] = http_headers
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
                        if rule_xml.find('version') is not None and 'url' in rule_xml.find('version').attrib:
                            metadata_record['version_url'] = rule_xml.find('version').attrib['url']
                        else:
                            metadata_record['version_url'] = None
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
                        if 'deprecated' in rule_filename.attrib \
                                and rule_filename.attrib['deprecated'].lower().strip() == 'true':
                            metadata_record['deprecated'] = True

                        if metadata_record['filename'] not in target_filenames:
                            if 'required' in rule_filename.attrib \
                                    and rule_filename.attrib['required'].lower().strip() == 'true':
                                # collect required rules/files, flush when this metadata package is parsed
                                metadata_record['required'] = True
                                required_files.append(metadata_record)
                            else:
                                yield metadata_record
                        target_filenames.append(metadata_record['filename'])
                    # flush required files last, so we can skip required when there's nothing else in the set selected
                    for metadata_record in required_files:
                        yield metadata_record
