#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Ad Schellevis <ad@opnsense.org>
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

TEMP_DIR = '/tmp/captures/'

import argparse
import glob
import subprocess
import os
import sys
import ujson


def capture_pids(jobid):
    """ search for capture pids using output filename
        :param jobid: job uuid number
        :return: list of pids
    """
    pids = []
    args = ['/bin/pgrep', '-af', "%s%s" % (TEMP_DIR, jobid)]
    for line in subprocess.run(args, capture_output=True, text=True).stdout.split():
        if line.isdigit():
            pids.append(line)
    return pids


if __name__ == '__main__':
    result = dict()
    parser = argparse.ArgumentParser()
    parser.add_argument('--job', help='job id', default=None)
    parser.add_argument('action', help='action to perfom', choices=['list', 'start', 'stop', 'remove'])
    cmd_args = parser.parse_args()

    all_jobs = {}
    if os.path.exists(TEMP_DIR):
        for filename in glob.glob("%s*.json" % TEMP_DIR):
            all_jobs[os.path.basename(filename).split('.')[0]] = filename

    if cmd_args.action == 'list':
        result['jobs'] = []
        result['status'] = 'ok'
        for jobid in all_jobs:
            this_pids = capture_pids(jobid)
            try:
                settings  = ujson.load(open(all_jobs[jobid], 'r'))
            except ValueError:
                settings = {}
            settings['id'] = jobid
            settings['status'] = "running" if len(this_pids) > 0 else "stopped"
            result['jobs'].append(settings)
    elif cmd_args.action == 'start' and cmd_args.job in all_jobs:
        this_pids = capture_pids(cmd_args.job)
        if len(this_pids) > 0:
            result['status'] = 'failed'
            result['status_msg'] = 'already active (pids: %s)' % ','.join(this_pids)
        else:
            result['status'] = 'ok'
            result['started_processes'] = 0
            settings = ujson.load(open(all_jobs[cmd_args.job], 'r'))
            for intf in settings.get('interface', []):
                result['started_processes'] += 1
                args = [
                    '/usr/sbin/daemon',
                    '-f',
                    '/usr/sbin/tcpdump',
                    '-i', intf,
                    '-U',
                    '-w', "%s%s_%s.cap" % (TEMP_DIR, cmd_args.job, intf)
                ]
                subprocess.run(args)
    elif cmd_args.action == 'stop' and cmd_args.job in all_jobs:
        result['status'] = 'ok'
        result['stopped_processes'] = 0
        for pid in capture_pids(cmd_args.job):
            subprocess.run(['kill', pid])
            result['stopped_processes'] += 1

    print (ujson.dumps(result))
