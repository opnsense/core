#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2020 Ad Schellevis <ad@opnsense.org>
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
    watch dhcp lease file and build include file for dnsmasq
"""
import ipaddress
import os
import sys
import time
import argparse
import syslog
import signal
import re
from configparser import ConfigParser
sys.path.insert(0, "/usr/local/opnsense/site-python")
from daemonize import Daemonize
import watchers.dhcpd


def run_watcher(target_filename, default_domain, watch_file, service_pid):
    # cleanup interval (seconds)
    cleanup_interval = 60

    # initiate lease watcher and setup cache
    dhcpdleases = watchers.dhcpd.DHCPDLease(watch_file)
    cached_leases = dict()
    hostname_pattern = re.compile("(?!-)[A-Z0-9-_]*(?<!-)$", re.IGNORECASE)

    # start watching dhcp leases
    last_cleanup = time.time()

    while True:
        for lease in dhcpdleases.watch():
            if 'ends' in lease and lease['ends'] > time.time() \
                    and 'client-hostname' in lease and 'address' in lease and lease['client-hostname']:
                if all(hostname_pattern.match(part) for part in lease['client-hostname'].strip('.').split('.')):
                    address = ipaddress.ip_address(lease['address'])
                    lease['domain'] = default_domain
                    cached_leases[lease['address']] = lease
                    dhcpd_changed = True
                else:
                    syslog.syslog(
                        syslog.LOG_WARNING,
                        "dhcpd leases: %s not a valid hostname, ignoring" % lease['client-hostname']
                    )

        if time.time() - last_cleanup > cleanup_interval:
            # cleanup every x seconds
            last_cleanup = time.time()
            addresses = list(cached_leases)
            for address in addresses:
                if cached_leases[address]['ends'] < time.time():
                    syslog.syslog(
                        syslog.LOG_NOTICE,
                        "dhcpd expired %s @ %s" % (cached_leases[address]['client-hostname'], address)
                    )
                    del cached_leases[address]
                    dhcpd_changed = True

        if dhcpd_changed:
            with open(target_filename, 'w') as dnsmasq_conf:
                dnsmasq_conf.write('# dynamic entries from dhcpd.leases automatically entered\n')
                for address in cached_leases:
                    dnsmasq_conf.write('%s %s.%s %s\n' % (
                        address,
                        cached_leases[address]['client-hostname'],
                        cached_leases[address]['domain'],
                        cached_leases[address]['client-hostname']
                    ))

            pid = int(open(service_pid).read())
            os.kill(pid, signal.SIGHUP)

        dhcpd_changed = False

        # wait for next cycle
        time.sleep(1)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--domain', help='default domain to use', default='local')
    parser.add_argument('--foreground', help='run in foreground', default=False, action='store_true')
    parser.add_argument('--pid', help='pid file location', default='/var/run/dnsmasq_dhcpd.pid')
    parser.add_argument('--servicepid', help='dnsmasq pid file location', default='/var/run/dnsmasq.pid')
    parser.add_argument('--source', help='source leases file', default='/var/dhcpd/var/db/dhcpd.leases')
    parser.add_argument('--target', help='target config file', default='/var/etc/dnsmasq-leases')

    inputargs = parser.parse_args()

    syslog.openlog('dnsmasq', facility=syslog.LOG_LOCAL4)

    if inputargs.foreground:
        run_watcher(
            target_filename=inputargs.target,
            default_domain=inputargs.domain,
            watch_file=inputargs.source,
            service_pid=inputargs.servicepid
        )
    else:
        syslog.syslog(syslog.LOG_NOTICE, 'daemonize dnsmasq dhcpd watcher.')
        cmd = lambda : run_watcher(
            target_filename=inputargs.target,
            default_domain=inputargs.domain,
            watch_file=inputargs.source,
            service_pid=inputargs.servicepid
        )
        daemon = Daemonize(app="dnsmasq_dhcpd", pid=inputargs.pid, action=cmd)
        daemon.start()
