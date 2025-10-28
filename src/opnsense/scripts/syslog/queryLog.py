#!/usr/local/bin/python3

"""
    Copyright (c) 2019-2024 Ad Schellevis <ad@opnsense.org>
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

    --------------------------------------------------------------------------------------

    query log files
"""

import os.path
import ujson
from dateutil.parser import isoparse
from log_matcher import LogMatcher
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
    parser.add_argument('--severity', help='comma separated list of severities', default='')
    parser.add_argument('--valid_from', help='oldest data to search for (epoch)', default='')
    inputargs = parser.parse_args()

    try:
        valid_from = float(inputargs.valid_from)
    except ValueError:
        valid_from = 0

    result = {'filters': inputargs.filter, 'rows': [], 'total_rows': 0, 'origin': os.path.basename(inputargs.filename)}
    if inputargs.filename != "":
        limit = int(inputargs.limit) if inputargs.limit.isdigit()  else 0
        offset = int(inputargs.offset) if inputargs.offset.isdigit() else 0
        log_matcher = LogMatcher(inputargs.filter, inputargs.filename, inputargs.module, inputargs.severity)
        for record in log_matcher.match_records(valid_from):
            result['total_rows'] += 1
            if (len(result['rows']) < limit or limit == 0) and (result['total_rows'] >= offset):
                if inputargs.output == 'json':
                    result['rows'].append(record)
                else:
                    print("%(timestamp)s\t%(severity)s\t%(process_name)s\t%(line)s" % record)
            if limit > 0 and result['total_rows'] > offset + limit:
                # do not fetch data until end of file...
                break

    # output results (when json)
    if inputargs.output == 'json':
        print(ujson.dumps(result))
