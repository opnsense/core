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

"""
import os.path
import stat
import xml.etree.ElementTree
from configparser import ConfigParser


class Config(object):
    """ handle to captive portal config (/usr/local/etc/captiveportal.conf)
    """
    _cnf_filename = "/usr/local/etc/captiveportal.conf"

    def __init__(self):
        """ construct new config object
        """
        self.last_updated = 0
        self._conf_handle = None
        self._update()

    def _update(self):
        """ check if config is changed and (re)load
        """
        mod_time = os.stat(self._cnf_filename)[stat.ST_MTIME]
        if os.path.exists(self._cnf_filename) and self.last_updated != mod_time:
            self._conf_handle = ConfigParser()
            self._conf_handle.read(self._cnf_filename)
            self.last_updated = mod_time

    def get_zones(self):
        """ return list of configured zones
            :return: dictionary index by zoneid, containing dictionaries with zone properties
        """
        result = dict()
        self._update()
        if self._conf_handle is not None:
            for section in self._conf_handle.sections():
                if section.find('zone_') == 0:
                    zoneid = section.split('_')[1]
                    result[zoneid] = dict()
                    for item in self._conf_handle.items(section):
                        result[zoneid][item[0]] = item[1]
                    # convert allowed(MAC)addresses string to list
                    if 'allowedaddresses' in result[zoneid] and result[zoneid]['allowedaddresses'].strip() != '':
                        result[zoneid]['allowedaddresses'] = \
                            [x.strip() for x in result[zoneid]['allowedaddresses'].split(',')]
                    else:
                        result[zoneid]['allowedaddresses'] = list()
                    if 'allowedmacaddresses' in result[zoneid] and result[zoneid]['allowedmacaddresses'].strip() != '':
                        result[zoneid]['allowedmacaddresses'] = \
                            [x.strip() for x in result[zoneid]['allowedmacaddresses'].split(',')]
                    else:
                        result[zoneid]['allowedmacaddresses'] = list()
        return result

    def fetch_template_data(self, zoneid):
        """ fetch template content from config
        """
        for section in self._conf_handle.sections():
            if section.find('template_for_zone_') == 0 and section.split('_')[-1] == str(zoneid):
                if self._conf_handle.has_option(section, 'content'):
                    return self._conf_handle.get(section, 'content')
        return None


class OPNsenseConfig(object):
    """ Read configuration data from config.xml
    """
    def __init__(self):
        self.rootNode = None
        self.load_config()

    def load_config(self):
        """ load config.xml
        """
        tree = xml.etree.ElementTree.parse('/conf/config.xml')
        self.rootNode = tree.getroot()

    def get_template(self, fileid):
        """ fetch template content from config.xml
            :param fileid: internal fileid (field in template node)
            :return: string, bse64 encoded data or None if not found
        """
        templates = self.rootNode.findall("./OPNsense/captiveportal/templates/template")
        if templates is not None:
            for template in templates:
                if template.find('fileid') is not None and template.find('content') is not None:
                    if template.find('fileid').text == fileid:
                        return template.find('content').text

        return None
