#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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

TEMP_DIR = '/tmp/ping/'

import argparse
import collections
import glob
import subprocess
import os
import ujson
import sys
sys.path.insert(0, "/usr/local/opnsense/site-python")
from log_helper import reverse_log_reader


def ping_pids(jobid):
    """ search for capture pids using output filename
        :param jobid: job uuid number
        :return: list of pids
    """
    pids = []
    args = ['/bin/pgrep', '-af', "ping_%s" % jobid]
    for line in subprocess.run(args, capture_output=True, text=True).stdout.split():
        if line.isdigit():
            pids.append(line)
    return pids


def load_settings(filename):
    try:
        return ujson.load(open(filename, 'r'))
    except ValueError:
        return {}


def read_latest_stats(filename):
    result = {
        'loss': None,
        'send': None,
        'received': None,
        'min': None,
        'max': None,
        'avg': None,
        'std-dev': None,
        'last_error': None
    }
    next_break = False
    if os.path.isfile(filename):
        items = collections.deque(maxlen=5)
        for line in reverse_log_reader(filename):
            line = line['line']
            if line.startswith('ping:'):
                result['last_error'] = line[6:].strip()
                if next_break:
                    break
            items.append(line)
            if line.endswith('packet loss'):
                if next_break:
                    break
                # IPv6
                for item in items:
                    parts = item.split()
                    if item.endswith('packet loss') and len(parts) >=6:
                        result['loss'] = parts[6]
                        result['send'] = int(parts[0])
                        result['received'] = int(parts[3])
                    elif item.startswith('round-trip') and  len(parts) >= 4 and parts[3].count('/') == 3:
                        stats = parts[3].split('/')
                        result['min'] = float(stats[0])
                        result['avg'] = float(stats[1])
                        result['max'] = float(stats[2])
                        result['std-dev'] = float(stats[3])
                next_break=True
            elif line.find('packets received') > -1:
                if next_break:
                    break
                # IPv4
                parts = items[-1].split()
                if parts[0].find('/') > 0:
                    result['send'] = int(parts[0].split('/')[1])
                    result['received'] = int(parts[0].split('/')[0])
                if len(parts) >= 12:
                    if parts[5] == 'min':
                        result['min'] = float(parts[4])
                    if parts[8] == 'avg':
                        result['avg'] = float(parts[7])
                    if parts[11] == 'max':
                        result['max'] = float(parts[10])
                if result['send']:
                    loss = (result['send']-result['received']) / result['send'] * 100.0
                    result['loss'] = "%0.2f %%" % loss
                next_break=True
    return result


if __name__ == '__main__':
    result = dict()
    parser = argparse.ArgumentParser()
    parser.add_argument('--job', help='job id', default=None)
    parser.add_argument('action', help='action to perfom', choices=['list', 'start', 'stop', 'remove', 'view'])
    cmd_args = parser.parse_args()

    all_jobs = {}
    if os.path.exists(TEMP_DIR):
        for filename in glob.glob("%s*.json" % TEMP_DIR):
            all_jobs[os.path.basename(filename).split('.')[0]] = filename

    if cmd_args.action == 'list':
        result['jobs'] = []
        result['status'] = 'ok'
        for jobid in all_jobs:
            this_pids = ping_pids(jobid)
            if len(this_pids) > 0:
                with open("%s.pid" % all_jobs[jobid][:-5], 'r') as f_in:
                    subprocess.run(['kill', '-s', 'INFO', f_in.read().strip()])
            settings = load_settings(all_jobs[jobid])
            settings['id'] = jobid
            settings['status'] = "running" if len(this_pids) > 0 else "stopped"
            # merge stats
            settings.update(read_latest_stats("%s.log" % all_jobs[jobid][:-5]))
            result['jobs'].append(settings)
    elif cmd_args.action == 'start' and cmd_args.job in all_jobs:
        this_pids = ping_pids(cmd_args.job)
        if len(this_pids) > 0:
            result['status'] = 'failed'
            result['status_msg'] = 'already active (pids: %s)' % ','.join(this_pids)
        else:
            result['status'] = 'ok'
            settings = load_settings(all_jobs[cmd_args.job])
            log_target = "%s%s.log" % (TEMP_DIR, cmd_args.job)
            args = [
                '/usr/sbin/daemon',
                '-o', log_target,
                '-p', "%s%s.pid" % (TEMP_DIR, cmd_args.job),
                '-t',  'ping_%s' % cmd_args.job,
                '/sbin/ping',
                '-c', '86400', # hard limit: stop after 1 day
                '-4' if settings.get('fam', 'ip') == 'ip' else '-6'
            ]
            if settings.get('source_address', '') != '':
                args.append('-S')
                args.append(settings['source_address'])
            if settings.get('packetsize', '') != '':
                args.append('-s')
                args.append(settings['packetsize'])
            if settings.get('disable_frag', '0') == '1':
                args.append('-D')
            args.append(settings.get('hostname', ''))
            if os.path.isfile(log_target):
                os.remove(log_target)
            subprocess.run(args)
    elif cmd_args.action == 'stop' and cmd_args.job in all_jobs:
        result['status'] = 'ok'
        result['stopped_processes'] = 0
        for pid in ping_pids(cmd_args.job):
            subprocess.run(['kill', pid])
            result['stopped_processes'] += 1
    elif cmd_args.action == 'remove' and cmd_args.job in all_jobs:
        result['status'] = 'ok'
        result['stopped_processes'] = 0
        for pid in ping_pids(cmd_args.job):
            subprocess.run(['kill', pid])
            result['stopped_processes'] += 1
        for filename in glob.glob("%s%s*" % (TEMP_DIR, cmd_args.job)):
            os.remove(filename)

    print (ujson.dumps(result))
