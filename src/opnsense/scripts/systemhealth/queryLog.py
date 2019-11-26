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
sys.path.insert(0, "/usr/local/opnsense/site-python")
from log_helper import reverse_log_reader, fetch_clog
from params import update_params

if __name__ == '__main__':
    # handle parameters
    parameters = {'limit': '0', 'offset': '0', 'filter': '', 'filename': ''}
    update_params(parameters)

    result = {'filters': filter, 'rows': [], 'total_rows': 0, 'origin': parameters['filename'].split('/')[-1]}
    if parameters['filename'] != "":
        log_filename = "/var/log/%s.log"  % os.path.basename(parameters['filename'])
        limit = int(parameters['limit']) if parameters['limit'].isdigit()  else 0
        offset = int(parameters['offset']) if parameters['offset'].isdigit() else 0
        try:
            filter = parameters['filter'].replace('*', '.*').lower()
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
                        result['rows'].append(record)
                    elif result['total_rows'] > offset + limit:
                        # do not fetch data until end of file...
                        break

    # output results
    print(ujson.dumps(result))
