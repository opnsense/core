#!/usr/local/bin/python3

"""
    Copyright (c) 2019 Ad Schellevis <ad@opnsense.org>
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
sys.path.insert(0, "/usr/local/opnsense/site-python")
from log_helper import reverse_log_reader, fetch_clog
import argparse
squid_ext_timeformat = r'.*(\[\d{1,2}/[A-Za-z]{3}/\d{4}:\d{1,2}:\d{1,2}:\d{1,2} \+\d{4}\]).*'
squid_timeformat = r'^(\d{4}/\d{1,2}/\d{1,2} \d{1,2}:\d{1,2}:\d{1,2}).*'

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

    result = {'filters': filter, 'rows': [], 'total_rows': 0, 'origin': os.path.basename(inputargs.filename)}
    if inputargs.filename != "":
        startup_timestamp = datetime.datetime.now()
        if inputargs.module == 'core':
            log_filename = "/var/log/%s.log"  % os.path.basename(inputargs.filename)
        else:
            log_filename = "/var/log/%s/%s.log"  % (
                os.path.basename(inputargs.module), os.path.basename(inputargs.filename)
            )

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

        if os.path.exists(log_filename):
            try:
                filename = fetch_clog(log_filename)
            except Exception as e:
                filename = log_filename
            for record in reverse_log_reader(filename):
                if record['line'] != "" and filter_regexp.match(('%s' % record['line']).lower()):
                    result['total_rows'] += 1
                    if (len(result['rows']) < limit or limit == 0) and result['total_rows'] >= offset:
                        record['timestamp'] = None
                        if len(record['line']) > 15 and \
                                re.match(r'(?:[01]\d|2[0123]):(?:[012345]\d):(?:[012345]\d)', record['line'][7:15]):
                            # syslog format, strip timestamp and return actual log data
                            ts = datetime.datetime.strptime(
                                "%s %s" % (startup_timestamp.year, record['line'][0:15]), "%Y %b %d %H:%M:%S"
                            )
                            ts = ts.replace(year=startup_timestamp.year)
                            if (startup_timestamp - ts).days < 0:
                                # likely previous year, (month for this year not reached yet)
                                ts = ts.replace(year=ts.year - 1)
                            record['timestamp'] = ts.isoformat()
                            # strip timestamp from log line
                            record['line'] = record['line'][16:]
                            # strip hostname from log line
                            record['line'] = record['line'][record['line'].find(' ')+1:].strip()
                        elif len(record['line']) > 15 and record['line'][0:10].isdigit() and \
                                record['line'][10] == '.' and record['line'][11:13].isdigit():
                            # looks like an epoch
                            ts = datetime.datetime.fromtimestamp(float(record['line'][0:13]))
                            record['timestamp'] = ts.isoformat()
                            # strip timestamp
                            record['line'] = record['line'][14:].strip()
                        elif re.match(squid_ext_timeformat, record['line']):
                            tmp = re.match(squid_ext_timeformat, record['line'])
                            grp = tmp.group(1)
                            ts = datetime.datetime.strptime(grp[1:].split()[0], "%d/%b/%Y:%H:%M:%S")
                            record['timestamp'] = ts.isoformat()
                            # strip timestamp
                            record['line'] = record['line'].replace(grp, '')
                        elif re.match(squid_timeformat, record['line']):
                            tmp = re.match(squid_timeformat, record['line'])
                            grp = tmp.group(1)
                            ts = datetime.datetime.strptime(grp, "%Y/%m/%d %H:%M:%S")
                            record['timestamp'] = ts.isoformat()
                            record['line'] = record['line'][19:].strip()
                        result['rows'].append(record)
                    elif result['total_rows'] > offset + limit:
                        # do not fetch data until end of file...
                        break

    # output results
    print(ujson.dumps(result))
