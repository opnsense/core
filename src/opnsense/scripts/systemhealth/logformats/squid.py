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
squid_ext_timeformat = r'.*(\[\d{1,2}/[A-Za-z]{3}/\d{4}:\d{1,2}:\d{1,2}:\d{1,2} \+\d{4}\]).*'
squid_timeformat = r'^(\d{4}/\d{1,2}/\d{1,2} \d{1,2}:\d{1,2}:\d{1,2}).*'


class SquidLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super().__init__(filename)
        self._priority = 100

    def match(self, line):
        return self._filename.find('squid') > -1 and re.match(squid_timeformat, line) is not  None

    @property
    def timestamp(self):
        tmp = re.match(squid_timeformat, self._line)
        grp = tmp.group(1)
        return datetime.datetime.strptime(grp, "%Y/%m/%d %H:%M:%S").isoformat()

    @property
    def process_name(self):
        return "squid"

    @property
    def line(self):
        return self._line[19:].strip()


class SquidExtLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super().__init__(filename)
        self._priority = 120

    def match(self, line):
        return self._filename.find('squid') > -1 and re.match(squid_ext_timeformat, line) is not  None

    @property
    def timestamp(self):
        tmp = re.match(squid_ext_timeformat, self._line)
        grp = tmp.group(1)
        return datetime.datetime.strptime(grp[1:].split()[0], "%d/%b/%Y:%H:%M:%S").isoformat()

    @property
    def process_name(self):
        return "squid"

    @property
    def line(self):
        tmp = re.match(squid_ext_timeformat, self._line)
        grp = tmp.group(1)
        return self._line.replace(grp, '')


class SquidJsonLogFormat(NewBaseLogFormat):
    def __init__(self, filename):
        super().__init__(filename)
        self._priority = 140
        local_now = datetime.datetime.now()
        utc_now = datetime.datetime.utcnow()
        self._localtimezone = datetime.timezone(local_now - utc_now)

    def match(self, line):
        return self._filename.find('squid') > -1 and line.find('"@timestamp"') > -1

    @property
    def timestamp(self, line):
        tmp = line[line.find('"@timestamp"')+13:].split(',')[0].strip().strip('"')
        try:
            return datetime.datetime.strptime(tmp, "%Y-%m-%dT%H:%M:%S%z")\
                    .astimezone(self._localtimezone).isoformat().split('.')[0].split('+')[0]
        except ValueError:
            return None

    @property
    def process_name(self):
        return "squid"

    @property
    def line(self):
        return self._line
