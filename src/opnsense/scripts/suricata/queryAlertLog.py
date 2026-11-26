#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2025 Ad Schellevis <ad@opnsense.org>
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

import argparse
import sys
import os.path
import ujson
sys.path.insert(0, "/usr/local/opnsense/site-python")
from log_helper import reverse_log_reader
from lib import suricata_alert_log

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--limit', help='limit the amount of results', default=0, type=int)
    parser.add_argument('--offset', help='offset to start', default=0, type=int)
    parser.add_argument('--filter', help='filter to apply', default='')
    parser.add_argument('--fileid', help='log file number', default='')
    args = parser.parse_args()

    if args.fileid.isdigit():
        suricata_log = '%s.%d' % (suricata_alert_log, int(args.fileid))
    else:
        suricata_log = suricata_alert_log

    # filter one specific log line
    filepos = args.filter[len('filepos/'):] if args.filter.startswith('filepos/') else ''
    if filepos.isdecimal():
        log_start_pos = int(filepos) + 5000
    else:
        log_start_pos = None
    # every token must be found in the signature, action or addresses
    search_tokens = args.filter.lower().replace('*', ' ').split()

    # query suricata eve log
    result = {'rows': [], 'total_rows': 0, 'origin': suricata_log.split('/')[-1]}
    if os.path.exists(suricata_log):
        for line in reverse_log_reader(filename=suricata_log, start_pos=log_start_pos):
            try:
                record = ujson.loads(line['line'])
            except ValueError:
                # can not handle line
                record = {}

            # only process valid alert items
            if 'alert' in record:
                # add position in file
                record['filepos'] = line['pos']
                record['fileid'] = args.fileid
                # flatten structure
                record['alert_sid'] = record['alert']['signature_id']
                record['alert_action'] = record['alert']['action']
                record['alert'] = record['alert']['signature']

                if log_start_pos is not None:
                    do_output = record['filepos'] == int(filepos)
                else:
                    text = ' '.join(
                        '%s' % record.get(key, '') for key in ('alert', 'alert_action', 'src_ip', 'dest_ip')
                    ).lower()
                    do_output = all(token in text for token in search_tokens)
                if do_output:
                    result['total_rows'] += 1
                    if (len(result['rows']) < args.limit or args.limit == 0) and result['total_rows'] >= args.offset:
                        result['rows'].append(record)
                    elif result['total_rows'] > args.offset + args.limit:
                        # do not fetch data until end of file...
                        break

    # output results
    print(ujson.dumps(result))
