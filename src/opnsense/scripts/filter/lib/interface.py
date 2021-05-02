"""
    Copyright (c) 2021 Ad Schellevis <ad@opnsense.org>
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
import ipaddress
import subprocess


class InterfaceParser:
    """ Interface address parser
    """
    _ipv6_networks = dict()

    @classmethod
    def _update(cls):
        this_interface = None
        for line in subprocess.run(['/sbin/ifconfig'], capture_output=True, text=True).stdout.split('\n'):
            if not line.startswith("\t") and line.find(':') > -1:
                this_interface = line.strip().split(':')[0]
            elif this_interface is not None and line.startswith("\tinet6"):
                parts = line.strip().split()
                addr = None
                mask = None
                for i in range(len(parts)):
                    if parts[i] == 'inet6':
                        addr = parts[i+1].split("%")[0]
                    elif parts[i] == 'prefixlen':
                        mask = parts[i+1]
                if this_interface not in cls._ipv6_networks:
                    cls._ipv6_networks[this_interface] = []
                if mask and addr:
                    cls._ipv6_networks[this_interface].append({"addr": ipaddress.IPv6Address(addr), "mask": mask})

    def __init__(self, interface):
        self._interface = interface
        # collect addresses on class init (singleton)
        if len(self._ipv6_networks) == 0:
            self._update()

    def iter_dynipv6host(self, pattern):
        if self._interface in self._ipv6_networks and pattern.find("/") > -1:
            for network in self._ipv6_networks[self._interface]:
                # only global addresses apply
                if network["addr"].is_global:
                    base_mask = int(network["mask"])
                    base_size=int((128-base_mask)/16)
                    offset_address = ipaddress.IPv6Address('0' + pattern.split("/")[0])
                    calculated_address = ':'.join(
                        network["addr"].exploded.split(':')[:8-base_size] +
                        offset_address.exploded.split(':')[8-base_size:]
                    )
                    yield "%s/%s" % (calculated_address, pattern.split("/")[1])
