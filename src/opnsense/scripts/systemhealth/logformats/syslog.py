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
from . import BaseLogFormat

class SysLogFormat(BaseLogFormat):
    def __init__(self, filename):
        super(SysLogFormat, self).__init__(filename)
        self._priority = 1
        self._startup_timestamp = datetime.datetime.now()

    @staticmethod
    def match(line):
        return len(line) > 15 and re.match(r'(?:[01]\d|2[0123]):(?:[012345]\d):(?:[012345]\d)', line[7:15])

    def timestamp(self, line):
        # syslog format, strip timestamp and return actual log data
        ts = datetime.datetime.strptime("%s %s" % (self._startup_timestamp.year, line[0:15]), "%Y %b %d %H:%M:%S")
        ts = ts.replace(year=self._startup_timestamp.year)
        if (self._startup_timestamp - ts).days < 0:
            # likely previous year, (month for this year not reached yet)
            ts = ts.replace(year=ts.year - 1)
        return ts.isoformat()

    @staticmethod
    def line(line):
        # parse [date] [hostname] [process_name] [line] format
        response = line[16:]
        tmp = response.find(':')
        return response[tmp+1:].strip() if tmp > -1 else response[response.find(' ')+1:].strip()

    @staticmethod
    def process_name(line):
        response = line[16:]
        tmp = response.find(':')
        return response[:tmp].strip().split()[-1] if tmp > -1 else ""


class SysLogFormatEpoch(BaseLogFormat):
    def __init__(self, filename):
        super(SysLogFormatEpoch, self).__init__(filename)
        self._priority = 2

    @staticmethod
    def match(line):
        # looks like an epoch
        return len(line) > 15 and line[0:10].isdigit() and line[10] == '.' and line[11:13].isdigit()

    @staticmethod
    def timestamp(line):
        return datetime.datetime.fromtimestamp(float(line[0:13])).isoformat()

    @staticmethod
    def line(line):
        return line[14:].strip()
