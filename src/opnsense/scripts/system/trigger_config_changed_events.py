#!/usr/local/bin/python3

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
import decimal
import fcntl
import glob
import os
import subprocess
import sys
import ujson


try:
    fm = 'r+' if os.path.isfile('/conf/event_config_changed.json') else 'w+'
    status_fhandle = open('/conf/event_config_changed.json', fm)
    fcntl.flock(status_fhandle, fcntl.LOCK_EX | fcntl.LOCK_NB)
except IOError:
    # already running, exit status 99, it should be safe to skip an event when config changes happen too frequently
    sys.exit(99)

status_fhandle.seek(0)
try:
    metadata = ujson.loads(status_fhandle.read())
    # ujson treats decimals as floats, round these numbers to avoid re-triggering the previous handled event
    metadata['last_proccessed_stamp'] = round(decimal.Decimal(metadata['last_proccessed_stamp']), 4)
except ValueError:
     metadata = {'last_proccessed_stamp': 0}

for filename in sorted(glob.glob('/conf/backup/config-*.xml')):
    ts=filename.split('-')[-1].split('.xml')[0].replace('_', '')
    if ts.count('.') <= 1 and ts.replace('.', '').isdigit():
        # only process valid config backups containing a timestamp
        ts_num = decimal.Decimal(ts)
        if ts_num > metadata['last_proccessed_stamp']:
            subprocess.run(["/usr/local/etc/rc.syshook", "config", filename])
            metadata['last_proccessed_stamp'] = ts_num

# write metadata and exit
status_fhandle.seek(0)
status_fhandle.truncate()
status_fhandle.write(ujson.dumps(metadata))
