#!/usr/local/bin/python3

"""
    Copyright (c) 2024 Deciso B.V.
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
import glob
import sys
import subprocess
import select
from dateutil.parser import isoparse
from logformats import FormatContainer, BaseLogFormat
sys.path.insert(0, "/usr/local/opnsense/site-python")
from log_helper import reverse_log_reader

class LogMatcher:
    def __init__(self, filter, filename, module, severity):
        self.filter_clauses = filter.lower().split()
        self.filename = filename
        self.log_filenames = self.fetch_log_filenames(filename, module)
        self.severity = severity.split(',') if severity.strip() != '' else []
        self.row_number = 0

    def _match_line(self, line):
        # matcher, checks if all clauses are matched.
        tmp = line.lower()
        for clause in self.filter_clauses:
            if tmp.find(clause) == -1:
                return False

        return line != ''

    def live_match_records(self):
        # row number does not make sense anymore, set it to 0
        self.row_number = 0
        latest = "/var/log/%s/latest.log" % os.path.basename(self.filename)
        if not os.path.exists(latest):
            latest = self.log_filenames[0] if len(self.log_filenames) > 0 else ''
        if os.path.exists(latest):
            format_container = FormatContainer(latest)
            p = subprocess.Popen(
                ['tail', '-f', '-n 0', latest],
                stdout=subprocess.PIPE,
                stderr=subprocess.DEVNULL,
                bufsize=0,
                text=True
            )
            try:
                while True:
                    ready, _, _ = select.select([p.stdout], [], [], 1)
                    if not ready:
                        yield None
                        continue
                    line = p.stdout.readline()
                    if self._match_line(line):
                        record = self.parse_line(line, format_container)
                        if len(self.severity) == 0 or record['severity'] is None or record['severity'] in self.severity:
                            yield record
            except KeyboardInterrupt:
                p.terminate()
            finally:
                p.terminate()

    def match_records(self, timestamp=None):
        for filename in self.log_filenames:
            if os.path.exists(filename):
                format_container = FormatContainer(filename)
                for rec in reverse_log_reader(filename):
                    self.row_number += 1
                    if self._match_line(rec['line']):
                        record = self.parse_line(rec['line'], format_container)
                        if len(self.severity) == 0 or record['severity'] is None or record['severity'] in self.severity:
                            yield record

                    # exit when data found is older than offered timestamp
                    try:
                        if timestamp and isoparse(record['timestamp']).timestamp() < timestamp:
                            return
                    except (ValueError, TypeError):
                        pass

    def parse_line(self, line, format_container):
        frmt = format_container.get_format(line)
        record = {
            'timestamp': None,
            'parser': None,
            'facility': 1,
            'severity': None,
            'process_name': '',
            'pid': None,
            'rnum': self.row_number
        }
        if frmt:
            if issubclass(frmt.__class__, BaseLogFormat):
                # backwards compatibility, old style log handler
                record['timestamp'] = frmt.timestamp(line)
                record['process_name'] = frmt.process_name(line)
                record['line'] = frmt.line(line)
                record['parser'] = frmt.name
            else:
                record['timestamp'] = frmt.timestamp
                record['process_name'] = frmt.process_name
                record['pid'] = frmt.pid
                record['facility'] = frmt.facility
                record['severity'] = frmt.severity_str
                record['line'] = frmt.line
                record['parser'] = frmt.name
        else:
            record['line'] = line

        return record

    @staticmethod
    def fetch_log_filenames(filename, module):
        log_filenames = list()
        if module == 'core':
            log_basename = "/var/log/%s" % os.path.basename(filename)
        else:
            log_basename = "/var/log/%s/%s" % (
                os.path.basename(module), os.path.basename(filename)
            )
        if os.path.isdir(log_basename):
            # new syslog-ng local targets use an extra directory level
            filenames = glob.glob("%s/%s_*.log" % (log_basename, log_basename.split('/')[-1].split('.')[0]))
            for filename in sorted(filenames, reverse=True):
                log_filenames.append(filename)
        # legacy log output is always stashed last
        log_filenames.append("%s.log" % log_basename)
        if module != 'core':
            log_filenames.append("/var/log/%s_%s.log" % (module, os.path.basename(filename)))

        return log_filenames
