#!/usr/local/bin/python3

"""
    Copyright (c) 2025 Ad Schellevis <ad@opnsense.org>
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
import csv
import ipaddress
import os
import time
import subprocess
import syslog


def yield_log_records(filename, poll_interval=5):
    """ Simple log tailer, prepends known kea archive files and tails the active one indefinitely
    """
    # Lease processing after cleanup according to KEA
    # https://github.com/isc-projects/kea/blob/ef1f878f5272d/src/lib/dhcpsrv/memfile_lease_mgr.h#L1039-L1051
    if os.path.isfile('%s.completed' % filename):
        filenames = ['%s.completed' % filename, filename]
    else:
        filenames = ['%s.2' % filename, '%s.1' % filename, filename]

    for fn in (x for x in filenames if os.path.exists(x)):
        lstpos = None
        header = {}
        while True:
            if lstpos is None or (os.path.isfile(fn) and os.fstat(fhandle.fileno()).st_ino != os.stat(fn).st_ino):
                fhandle = open(fn, 'r') # open / rotate
                lstpos = None
            elif lstpos:
                fhandle.seek(lstpos)
            for idx, item in enumerate(csv.reader(fhandle, delimiter=',', quotechar='"')):
                if idx == 0 and lstpos is None:
                    header = item
                elif len(item) == len(header):
                    result_rec = {}
                    for i, key in enumerate(header):
                        result_rec[key] = int(item[i]) if item[i].isdigit() else item[i]
                    yield result_rec
            lstpos = fhandle.tell()
            if fn != filename:
                break
            else:
                time.sleep(poll_interval)

class NDP:
    """ simplistic NDP link local lookup
    """
    def __init__(self):
        self._def_local_db = {}

    def reload(self):
        ndpdata = subprocess.run(['/usr/sbin/ndp', '-an'], capture_output=True, text=True).stdout
        for idx, line in enumerate(ndpdata.split("\n")):
            parts = line.split()
            if idx > 0 and len(parts) > 1:
                try:
                    addr = parts[0].split('%')[0]
                    if ipaddress.ip_address(addr).is_link_local:
                        self._def_local_db[parts[1]] = parts[0]
                except (ValueError, IndexError):
                    pass


    def get(self, mac):
        if mac not in self._def_local_db:
            self.reload()

        if mac in self._def_local_db:
            return self._def_local_db[mac]



if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('filename',
                        help='filename to watch (including .1,.2, .completed)',
                        default='/var/db/kea/kea-leases6.csv'
    )
    inputargs = parser.parse_args()
    prefixes = {}
    syslog.openlog('kea-dhcp6', facility=syslog.LOG_LOCAL4)
    syslog.syslog(syslog.LOG_NOTICE, "startup kea prefix watcher")
    ndp = NDP()
    for record in yield_log_records(inputargs.filename):
        # IA_PD: type 2, prefix_len <= 64 - the delegated prefix
        if record.get('lease_type', 0) == 2 and record.get('prefix_len', 128) <= 64:
            prefix = "%(address)s/%(prefix_len)d" %  record
            if (prefix not in prefixes or prefixes[prefix].get('hwaddr') != record.get('hwaddr')) \
                    and record.get('expire', 0) > time.time():
                prefixes[prefix] = record
                ll_addr = ndp.get(record.get('hwaddr'))
                # lazy drop
                subprocess.run(['/sbin/route', 'delete', '-inet6', prefix], capture_output=True)
                if record.get('valid_lifetime', 0) > 0:
                    # only add when still valid
                    if subprocess.run(['/sbin/route', 'add', '-inet6', prefix, ll_addr], capture_output=True).returncode:
                        syslog.syslog(syslog.LOG_ERR, "failed adding route %s -> %s" % (prefix, ll_addr))
                    else:
                        syslog.syslog(syslog.LOG_NOTICE, "add route %s -> %s" % (prefix, ll_addr))
