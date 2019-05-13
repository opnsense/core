#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2019 Ad Schellevis <ad@opnsense.org>
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
    watch dhcp lease file and build include file for unbound
"""
import os
import sys

sys.path.insert(0, "/usr/local/opnsense/site-python")
import subprocess
import time
import tempfile
from daemonize import Daemonize
import watchers.dhcpd
import params


def unbound_control(commands, output_stream=None):
    """ execute (chrooted) unbound-control command
        :param commands: command list (parameters)
        :param output_stream: (optional)output stream
        :return: None
    """
    if output_stream is None:
        output_stream = open(os.devnull, 'w')
    subprocess.check_call(['/usr/sbin/chroot', '-u', 'unbound', '-g', 'unbound', '/',
                           '/usr/local/sbin/unbound-control', '-c', '/var/unbound/unbound.conf'] + commands,
                          stdout=output_stream, stderr=subprocess.STDOUT)
    output_stream.seek(0)


def unbound_known_addresses():
    """ fetch known addresses
        :return: list
    """
    result = list()
    with tempfile.NamedTemporaryFile() as output_stream:
        unbound_control(['list_local_data'], output_stream)
        for line in output_stream:
            parts = line.decode().split()
            if len(parts) > 4 and parts[3] == 'A':
                result.append(parts[4])
    return result


# parse input params
app_params = {'pid': '/var/run/unbound_dhcpd.pid',
              'domain': 'local',
              'target': '/var/unbound/dhcpleases.conf',
              'background': '1'}
params.update_params(app_params)


def main():
    # cleanup interval (seconds)
    cleanup_interval = 60

    # initiate lease watcher and setup cache
    dhcpdleases = watchers.dhcpd.DHCPDLease()
    cached_leases = dict()
    known_addresses = unbound_known_addresses()

    # start watching dhcp leases
    last_cleanup = time.time()
    while True:
        dhcpd_changed = False
        for lease in dhcpdleases.watch():
            if 'ends' in lease and lease['ends'] > time.time() and 'client-hostname' in lease and 'address' in lease:
                cached_leases[lease['address']] = lease
                dhcpd_changed = True
        if time.time() - last_cleanup > cleanup_interval:
            # cleanup every x seconds
            last_cleanup = time.time()
            addresses = cached_leases.keys()
            for address in addresses:
                if cached_leases[address]['ends'] < time.time():
                    del cached_leases[address]
                    dhcpd_changed = True

        if dhcpd_changed:
            # dump dns output to target
            with open(app_params['target'], 'w') as unbound_conf:
                for address in cached_leases:
                    unbound_conf.write('local-data-ptr: "%s %s.%s"\n' % (
                        address, cached_leases[address]['client-hostname'], app_params['domain'])
                    )
                    unbound_conf.write('local-data: "%s.%s IN A %s"\n' % (
                        cached_leases[address]['client-hostname'], app_params['domain'], address)
                    )
            # signal unbound
            for address in cached_leases:
                if address not in known_addresses:
                    fqdn = '%s.%s' % (cached_leases[address]['client-hostname'], app_params['domain'])
                    unbound_control(['local_data', address, 'PTR', fqdn])
                    unbound_control(['local_data', fqdn, 'IN A', address])
                    known_addresses.append(address)

        # wait for next cycle
        time.sleep(1)


# startup
if app_params['background'] == '1':
    daemon = Daemonize(app="unbound_dhcpd", pid=app_params['pid'], action=main)
    daemon.start()
else:
    main()
