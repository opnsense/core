"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
    Copyright (c) 2026 Deciso B.V.
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
import subprocess
import ipaddress
import ujson

class ARP(object):
    def __init__(self):
        """ construct new arp helper
        :return: None
        """
        self._table = {}
        self._source = None
        self.reload()

    def reload(self):
        """ reload / parse arp and ndp tables
        """
        self._table.clear()

        out = ujson.loads(subprocess.run(
            ['/usr/local/opnsense/scripts/interfaces/list_hosts.py'],
            capture_output=True,
            text=True
        ).stdout)

        self._source = out.get("rows")
        for row in out.get("rows", []):
            self._table[row[2]] = {'intf': row[0], 'mac': row[1]}

    def list_items(self):
        """ return parsed arp list
        :return: dict
        """
        return self._table

    def get_by_ipaddress(self, address):
        """ search arp or ndp entry by ip address
        :param address: ip address (IPv4 or IPv6)
        :return: dict or None (if not found)
        """
        return self._table.get(address, None)

    def get_all_addresses_by_mac(self, mac_address):
        """Get prioritized IP addresses (IPv4 + IPv6) for a MAC address.

        :param mac_address: MAC address to search for
        :return: List of IP addresses prioritized: [all_ipv4, global_ipv6, unique_local_ipv6, link_local_ipv6(1)]

        Note: This method returns addresses found in neighbor tables. The table may contain a mix of IPv4 and IPv6 keys.

        IMPORTANT LIMITATION: We can only discover addresses that appear in neighbor discovery tables.
        For IPv6, NDP only shows addresses that have been used for neighbor discovery. Privacy extensions,
        unused SLAAC addresses, and unused DHCPv6 leases will NOT appear until the client actually uses them.
        The background process (cp-background-process.py) periodically checks for new addresses as they appear
        and adds them automatically (every 5 seconds).

        Priority order (RFC-compliant):
        1. IPv4 addresses (all)
        2. Global unicast IPv6 (2000::/3 per RFC 4291)
        3. Unique Local Address IPv6 (fc00::/7 per RFC 4193, effectively fd00::/8)
        4. Link-local IPv6 (fe80::/10 per RFC 4291) - limited to 1

        Addresses are classified using Python's ipaddress module which implements proper RFC-compliant
        prefix matching (not string prefix matching).
        """
        ipv4_addresses = []
        global_ipv6 = []
        unique_local_ipv6 = []
        link_local_ipv6 = []

        # Single mixed table: keys are addresses (IPv4/IPv6), values contain at least {'mac': ...}
        for address, entry in self._table.items():
            if entry.get("mac") != mac_address:
                continue

            # Remove IPv6 scope ID if present (e.g., fe80::1%em0 -> fe80::1)
            address = address.split("%", 1)[0] if isinstance(address, str) else address

            try:
                ip_obj = ipaddress.ip_address(address)
            except ValueError:
                # Invalid IP address format - skip
                continue

            if isinstance(ip_obj, ipaddress.IPv4Address):
                # IPv4 (highest priority)
                ipv4_addresses.append(address)
                continue

            # IPv6 classification (RFC-compliant)
            ipv6_addr = ip_obj

            # Skip special IPv6 addresses not used as client addresses
            if ipv6_addr.is_multicast or ipv6_addr.is_loopback or ipv6_addr.is_unspecified:
                continue
            elif ipv6_addr.is_link_local:
                link_local_ipv6.append(address)
            elif ipv6_addr.is_private:
                # Unique Local Address (fc00::/7). Note: is_private also covers some other special ranges
                unique_local_ipv6.append(address)
            elif ipv6_addr.is_global:
                # Global unicast (commonly 2000::/3)
                global_ipv6.append(address)
            else:
                # Other reserved/unassigned: treat as global for safety/future-proofing
                global_ipv6.append(address)

        # Build prioritized list
        addresses = []
        addresses.extend(ipv4_addresses)
        addresses.extend(global_ipv6)
        addresses.extend(unique_local_ipv6)

        # Limit link-local to 1 address
        if link_local_ipv6:
            addresses.append(link_local_ipv6[0])

        return addresses
