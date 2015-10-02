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

"""
import os.path
import stat
from ConfigParser import ConfigParser

class Config(object):
    """ handle to captive portal config (/usr/local/etc/captiveportal.conf)
    """
    _cnf_filename = "/usr/local/etc/captiveportal.conf"

    def __init__(self):
        """ consctruct new config object
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
        if self._conf_handle != None:
            for section in self._conf_handle.sections():
                if section.find('zone_') == 0:
                    result[section.split('_')[1]] = dict()
                    for item in self._conf_handle.items(section):
                        result[section.split('_')[1]][item[0]] = item[1]
        return result
