#!/usr/local/bin/python3

"""
    Copyright (c) 2022-2025 Ad Schellevis <ad@opnsense.org>
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
import ujson
import zipfile
import pwd
import grp
from datetime import datetime


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

def netmap_interfaces():
    """
        :return: interfaces in netmap mode
    """
    result = []
    this_if = None
    for line in subprocess.run(['/sbin/ifconfig'], capture_output=True, text=True).stdout.split("\n"):
        if not line.startswith('\t') and line.find(':') > -1:
            this_if = line.split()[0].rstrip(':')
        elif this_if is not None and line.find('options=') > -1 and line.find('NETMAP') > -1:
            result.append(this_if)
    return result


def load_settings(filename):
    try:
        return ujson.load(open(filename, 'r'))
    except ValueError:
        return {}

def pcap_reader(filename, eargs=None):
    args = ['/usr/sbin/tcpdump', '-n', '-e', '-tt', '-r', filename]
    if eargs:
        args = args + eargs
    sp = subprocess.Popen(args, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    for line in sp.stdout:
        if not line.startswith(b'reading'):
            yield line.decode()
    sp.communicate()

    yield '\n'

if __name__ == '__main__':
    result = dict()
    parser = argparse.ArgumentParser()
    parser.add_argument('--job', help='job id', default=None)
    parser.add_argument('--detail', help='detail level', choices=['normal', 'medium','high'], default='normal')
    parser.add_argument(
        'action',
        help='action to perfom',
        choices=['list', 'start', 'stop', 'remove', 'view', 'archive']
    )
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
            settings = load_settings(all_jobs[jobid])
            settings['id'] = jobid
            settings['status'] = "running" if len(this_pids) > 0 else "stopped"
            result['jobs'].append(settings)
    elif cmd_args.action == 'start' and cmd_args.job in all_jobs:
        this_pids = capture_pids(cmd_args.job)
        if len(this_pids) > 0:
            result['status'] = 'failed'
            result['status_msg'] = 'already active (pids: %s)' % ','.join(this_pids)
        else:
            netmap_ifs = netmap_interfaces()
            result['status'] = 'ok'
            result['started_processes'] = 0
            settings = load_settings(all_jobs[cmd_args.job])
            for intf in settings.get('interface', '').split(','):
                result['started_processes'] += 1
                args = [
                    '/usr/sbin/daemon',
                    '-f',
                    '/usr/sbin/tcpdump',
                    '-i', intf if intf not in netmap_ifs else "netmap:%s/tr" % intf,
                    '-n',
                    '-U',
                    '-w', "%s%s_%s.pcap" % (TEMP_DIR, cmd_args.job, intf)
                ]
                if settings.get('promiscuous', '0') == '1':
                    args.append('-p')
                if settings.get('snaplen', '').isdigit():
                    args.append('-s')
                    args.append(settings.get('snaplen'))
                if settings.get('count', '').isdigit() and int(settings.get('count')) > 0:
                    args.append('-c')
                    args.append(settings.get('count'))

                filters = []
                if settings.get('fam', 'any') != 'any':
                    filters.append(settings.get('fam'))
                if settings.get('protocol', 'any') != 'any':
                    if settings.get('protocol_not', '0') == '1':
                        filters.append('proto not %s' % settings.get('protocol'))
                    else:
                        filters.append('proto %s' % settings.get('protocol'))
                if settings.get('host', '') != '':
                    tokens = []
                    for token in settings.get('host').split():
                        if token.lower() in ['and', 'or', 'not']:
                            tokens.append(token)
                        elif token.find('/') > -1:
                            tokens.append('net %s' % token)
                        elif token.count(':') == 5 and sum([len(x) == 2 for x in token.split(':')]) == 6:
                            tokens.append('ether host %s' % token)
                        else:
                            tokens.append('host %s' % token)
                    filters.append('( %s )' % ' '.join(tokens))
                if settings.get('port', '') != '':
                    if settings.get('port_not', '0') == '1':
                        filters.append('port not %s' % settings.get('port'))
                    else:
                        filters.append('port %s' % settings.get('port'))

                if len(filters) > 0:
                    args.append(' and '.join(filters))
                subprocess.run(args)
    elif cmd_args.action == 'stop' and cmd_args.job in all_jobs:
        result['status'] = 'ok'
        result['stopped_processes'] = 0
        for pid in capture_pids(cmd_args.job):
            subprocess.run(['kill', pid])
            result['stopped_processes'] += 1
    elif cmd_args.action == 'remove' and cmd_args.job in all_jobs:
        result['status'] = 'ok'
        result['stopped_processes'] = 0
        for pid in capture_pids(cmd_args.job):
            subprocess.run(['kill', pid])
            result['stopped_processes'] += 1
        for filename in glob.glob("%s%s*" % (TEMP_DIR, cmd_args.job)):
            os.remove(filename)
    elif cmd_args.action == 'view' and cmd_args.job in all_jobs:
        result['settings'] = load_settings(all_jobs[cmd_args.job])
        result['status'] = 'ok'
        result['interfaces'] = {}
        for filename in glob.glob("%s%s*.pcap" % (TEMP_DIR, cmd_args.job)):
            intf = filename.split('_', 1)[-1].rsplit('.', 1)[0]
            result['interfaces'][intf] = {'rows': []}
            args = []
            if cmd_args.detail == 'normal':
                args.append('-q')
            elif cmd_args.detail == 'medium':
                args.append('-v')
            elif cmd_args.detail == 'high':
                args.append('-vv')

            this_record = []
            for line in pcap_reader(filename, args):
                if len(line) > 0 and not line[0] in [' ', '\t']:
                    if this_record and this_record[0].count(' ') > 3:
                        first_row = this_record[0].split()
                        if first_row[0].replace('.', '').isdigit():
                            this_record[0] = this_record[0][this_record[0].find(',')+1:].lstrip()
                            payload = {
                                'timestamp': datetime.fromtimestamp(float(first_row[0])).isoformat(),
                                'esrc': first_row[1] if first_row[1].count(':') == 5 else None,
                                'edst': first_row[3].rstrip(',') if first_row[3].count(':') == 5 > 0 else None,
                                'raw': ''.join(this_record)
                            }
                            if 'IPv4,' in first_row:
                                payload['fam'] = 'ip'
                            elif 'IPv6,' in first_row:
                                payload['fam'] = 'ip6'

                            result['interfaces'][intf]['rows'].append(payload)
                        if len(result['interfaces'][intf]['rows']) > 500:
                            # max frames reached
                            break
                    this_record = [line]
                else:
                    this_record.append(line)
    elif cmd_args.action == 'archive' and cmd_args.job in all_jobs:
        result['filename'] = "%s%s.zip" % (TEMP_DIR, cmd_args.job)
        result['status'] = 'ok'
        zfh = zipfile.ZipFile(result['filename'], mode='w')
        for filename in glob.glob("%s%s*" % (TEMP_DIR, cmd_args.job)):
            if not filename.endswith('.zip'):
                zfh.write(filename, os.path.basename(filename))
        zfh.close()

        # always set file ownership for strict security mode
        os.chown(result['filename'], pwd.getpwnam("wwwonly").pw_uid, grp.getgrnam("wheel").gr_gid)
        os.chmod(result['filename'], 0o640)

    print (ujson.dumps(result))
