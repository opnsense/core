#!/usr/local/bin/python3

"""
    Copyright (c) 2019-2020 Ad Schellevis <ad@opnsense.org>
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

    query log files
"""

import sys
import os.path
import re
import sre_constants
import ujson
import datetime
import glob
from logformats import FormatContainer
sys.path.insert(0, "/usr/local/opnsense/site-python")
from log_helper import reverse_log_reader, fetch_clog
import argparse

if __name__ == '__main__':
    # handle parameters
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', help='output type [json/text]', default='json')
    parser.add_argument('--filter', help='filter results', default='')
    parser.add_argument('--limit', help='limit number of results', default='')
    parser.add_argument('--offset', help='begin at row number', default='')
    parser.add_argument('--filename', help='log file name (excluding .log extension)', default='')
    parser.add_argument('--module', help='module', default='core')
    inputargs = parser.parse_args()

    result = {'filters': inputargs.filter, 'rows': [], 'total_rows': 0, 'origin': os.path.basename(inputargs.filename)}
    if inputargs.filename != "":
        log_filenames = list()
        if inputargs.module == 'core':
            log_basename = "/var/log/%s" % os.path.basename(inputargs.filename)
        else:
            log_basename = "/var/log/%s/%s" % (
                os.path.basename(inputargs.module), os.path.basename(inputargs.filename)
            )
        if os.path.isdir(log_basename):
            # new syslog-ng local targets use an extra directory level
            for filename in sorted(glob.glob("%s/*.log" % log_basename), reverse=True):
                log_filenames.append(filename)
        # legacy log output is always stiched last
        log_filenames.append("%s.log" % log_basename)

        limit = int(inputargs.limit) if inputargs.limit.isdigit()  else 0
        offset = int(inputargs.offset) if inputargs.offset.isdigit() else 0
        try:
            filter = inputargs.filter.replace('*', '.*').lower()
            if filter.find('*') == -1:
                # no wildcard operator, assume partial match
                filter =  ".*%s.*" % filter
            filter_regexp = re.compile(filter)
        except sre_constants.error:
            # remove illegal expression
            filter_regexp = re.compile('.*')

        for log_filename in log_filenames:
            if os.path.exists(log_filename):
                format_container = FormatContainer(log_filename)
                try:
                    filename = fetch_clog(log_filename)
                except Exception as e:
                    filename = log_filename
                for rec in reverse_log_reader(filename):
                    if rec['line'] != "" and filter_regexp.match(('%s' % rec['line']).lower()):
                        result['total_rows'] += 1
                        if (len(result['rows']) < limit or limit == 0) and result['total_rows'] >= offset:
                            record = {
                                'timestamp': None,
                                'parser': None,
                                'process_name': ''
                            }
                            frmt = format_container.get_format(rec['line'])
                            if frmt:
                                record['timestamp'] = frmt.timestamp(rec['line'])
                                record['process_name'] = frmt.process_name(rec['line'])
                                record['line'] = frmt.line(rec['line'])
                                record['parser'] = frmt.name
                            else:
                                record['line'] = rec['line']
                            result['rows'].append(record)
                        elif limit > 0 and result['total_rows'] > offset + limit:
                            # do not fetch data until end of file...
                            break
            if limit > 0 and result['total_rows'] > offset + limit:
                break

    # output results
    print(ujson.dumps(result))
