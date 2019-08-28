#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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

    list all available alert logs
"""

import sys
sys.path.insert(0, "/usr/local/opnsense/site-python")
import os
import glob
import ujson
import time
import datetime
from lib import suricata_alert_log
from log_helper import reverse_log_reader

if __name__ == '__main__':
    result = []
    for filename in sorted(glob.glob('%s*' % suricata_alert_log)):
        row = dict()
        row['size'] = os.stat(filename).st_size
        # always list first file and non empty next.
        if row['size'] > 0 or filename.split('/')[-1].count('.') == 1:
            row['modified'] = os.stat(filename).st_mtime
            row['filename'] = filename.split('/')[-1]
            # try to find actual timestamp from file
            for line in reverse_log_reader(filename=filename):
                if line['line'] != '':
                    try:
                        record = ujson.loads(line['line'])
                    except ValueError:
                        continue
                    if 'timestamp' in record:
                        row['modified'] = int(time.mktime(datetime.datetime.strptime(record['timestamp'].split('.')[0],
                                                                                     "%Y-%m-%dT%H:%M:%S").timetuple()))
                        break

            ext = filename.split('.')[-1]
            if ext.isdigit():
                row['sequence'] = int(ext)
            else:
                row['sequence'] = None

            result.append(row)

    # output results
    print(ujson.dumps(result))
