"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
import re
import datetime
from . import NewBaseLogFormat

class SysLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super(SysLogFormat, self).__init__(filename)
        self._priority = 2
        self._startup_timestamp = datetime.datetime.now()

    @staticmethod
    def match(line):
        return len(line) > 15 and re.match(r'(?:[01]\d|2[0123]):(?:[012345]\d):(?:[012345]\d)', line[7:15])

    @property
    def timestamp(self):
        # syslog format, strip timestamp and return actual log data
        ts = datetime.datetime.strptime("%s %s" % (self._startup_timestamp.year, self._line[0:15]), "%Y %b %d %H:%M:%S")
        ts = ts.replace(year=self._startup_timestamp.year)
        if (self._startup_timestamp - ts).days < 0:
            # likely previous year, (month for this year not reached yet)
            ts = ts.replace(year=ts.year - 1)
        return ts.isoformat()

    @property
    def line(self):
        # parse [date] [hostname] [process_name] [line] format
        response = self._line[16:]
        tmp = response.find(':')
        return response[tmp+1:].strip() if tmp > -1 else response[response.find(' ')+1:].strip()

    @property
    def process_name(self):
        response = self._line[16:]
        tmp = response.find(':')
        return response[:tmp].strip().split()[-1] if tmp > -1 else ""


class ServiceLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super(ServiceLogFormat, self).__init__(filename)

    @staticmethod
    def match(line):
        return len(line) > 25 and line[19] in ['+', '-'] \
                and re.match(r'\d{4}(.\d{2}){2}(\s|T)(\d{2}.){2}\d{2}', line[0:19])

    @property
    def timestamp(self):
        return self._line[0:19]

    @property
    def line(self):
        return self._line[26:]


class SysLogFormatEpoch(NewBaseLogFormat):
    def __init__(self, filename):
        super(SysLogFormatEpoch, self).__init__(filename)
        self._priority = 3

    @staticmethod
    def match(line):
        # looks like an epoch
        return len(line) > 15 and line[0:10].isdigit() and line[10] == '.' and line[11:14].isdigit()

    @property
    def timestamp(self):
        return datetime.datetime.fromtimestamp(float(self._line[0:14])).isoformat(timespec='milliseconds')

    @property
    def line(self):
        return self._line[14:].strip()


class SysLogFormatRFC5424(NewBaseLogFormat):
    def __init__(self, filename):
        super().__init__(filename)
        self._priority = 1
        self._parts = list()

    @staticmethod
    def match(line):
        return len(line) > 15 and line[0] == '<' and '>' in line[1:5] and line.find(']') > 0

    def set_line(self, line):
        super().set_line(line)
        self._parts = self._line.split(maxsplit=5)

    @property
    def line(self):
        return self._line.split(']', 1)[-1]

    @property
    def timestamp(self):
        return self._parts[1].split('+', maxsplit=1)[0]

    @property
    def process_name(self):
        return self._parts[3]

    @property
    def pid(self):
        return self._parts[4]

    @property
    def facility(self):
        return int(int(self._line[1:].split('>', 1)[0]) / 8)

    @property
    def severity(self):
        tmp = int(self._line[1:].split('>', 1)[0])
        return tmp - (int((tmp / 8)) * 8)
