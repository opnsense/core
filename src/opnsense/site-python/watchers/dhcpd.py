"""
    Copyright (c) 2016-2019 Ad Schellevis <ad@opnsense.org>
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
import calendar
import datetime


class DHCPDLease(object):
    def __init__(self, watch_file='/var/dhcpd/var/db/dhcpd.leases'):
        """ init watcher
        :param watch_file: filename to watch
        :return: watcher object
        """
        self.watch_file = watch_file
        self._section_data = []
        self._fhandle = None
        self._last_pos = None

    def _open(self):
        """ (re)open watched file
        :return: None
        """
        try:
            self._fhandle = open(self.watch_file, 'r')
            self._last_pos = None
            self._section_data = []
        except IOError:
            self._fhandle = None

    @staticmethod
    def parse_lease(lines):
        """ parse dhcp lease
        :param lines: lease section as list item
        :return: dictionary
        """
        hostname_override = None
        lease = dict()
        lease['address'] = lines[0].split()[1]
        for line in lines:
            parts = line.split()
            field_name = parts[0]
            field_value = None
            if field_name in ('starts', 'ends', 'tstp', 'tsfp', 'atsfp', 'cltt') and len(parts) >= 3:
                dt = '%s %s' % (parts[2], parts[3])
                try:
                    field_value = calendar.timegm(datetime.datetime.strptime(dt, "%Y/%m/%d %H:%M:%S;").timetuple())
                except ValueError:
                    field_value = None
            elif field_name == 'hardware' and len(parts) >= 3:
                field_value = {'hardware-type': parts[1], 'mac-address': parts[2].split(';')[0]}
            elif field_name in('uid', 'client-hostname') and len(parts) >= 2 and parts[1].find('"') > -1:
                field_value = parts[1].split('"')[1]
            elif field_name == 'set' and len(parts) >= 4 and parts[1] == 'hostname-override' and parts[3].find('"') > -1:
                hostname_override = parts[3].split('"')[1]
            elif field_name == 'binding' and len(parts) >= 3 and parts[1] == 'state':
                field_value = parts[2].strip(';')

            if field_value is not None:
                lease[field_name] = field_value

        if hostname_override is not None:
            lease['client-hostname'] = hostname_override

        return lease

    def watch(self):
        """ watch file, return lease dictionaries
        :return: iterator for leases
        """
        if self._fhandle is None:
            # nothing to watch, try to (re)open return when failed
            self._open()
        elif os.fstat(self._fhandle.fileno()).st_ino != os.stat(self.watch_file).st_ino:
            # file rotation, inode changed
            self._open()
        elif self._last_pos is not None:
            self._fhandle.seek(self._last_pos)

        if self._fhandle is not None:
            while True:
                line = self._fhandle.readline()
                if line:
                    if len(line) > 5 and line[0:5] == 'lease':
                        self._section_data.append(line)
                    elif len(line) > 1 and line[0] == '}' and len(self._section_data) > 0:
                        self._section_data.append(line)
                        yield self.parse_lease(self._section_data)
                        self._section_data = []
                    elif len(self._section_data) > 0:
                        self._section_data.append(line)
                else:
                    break

            self._last_pos = self._fhandle.tell()
