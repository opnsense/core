"""
    Copyright (c) 2015-2025 Ad Schellevis <ad@opnsense.org>
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


class ARP(object):
    def __init__(self):
        """ construct new arp helper
        :return: None
        """
        self._arp_table = dict()
        self._ndp_table = dict()
        self._fetch_arp_table()
        self._fetch_ndp_table()

    def reload(self):
        """ reload / parse arp and ndp tables
        """
        self._fetch_arp_table()
        self._fetch_ndp_table()

    def _fetch_arp_table(self):
        """ parse system arp table and store result in this object
        :return: None
        """
        # parse arp table
        self._arp_table = dict()
        sp = subprocess.run(['/usr/sbin/arp', '-an'], capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            line_parts = line.split()

            if len(line_parts) < 6 or line_parts[2] != 'at' or line_parts[4] != 'on':
                continue
            elif len(line_parts[1]) < 2 or line_parts[1][0] != '(' or line_parts[1][-1] != ')':
                continue

            address = line_parts[1][1:-1]
            physical_intf = line_parts[5]
            mac = line_parts[3]
            expires = -1

            for index in range(len(line_parts) - 3):
                if line_parts[index] == 'expires' and line_parts[index + 1] == 'in':
                    if line_parts[index + 2].isdigit():
                        expires = int(line_parts[index + 2])

            if address in self._arp_table:
                self._arp_table[address]['intf'].append(physical_intf)
            elif mac.find('incomplete') == -1:
                self._arp_table[address] = {'mac': mac, 'intf': [physical_intf], 'expires': expires}

    def _fetch_ndp_table(self):
        """ parse system ndp (IPv6 neighbor discovery) table and store result in this object
        :return: None
        """
        # parse ndp table
        self._ndp_table = dict()
        sp = subprocess.run(['/usr/sbin/ndp', '-an'], capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            line = line.strip()
            if not line or line.startswith('Neighbor'):
                continue

            line_parts = line.split()

            if len(line_parts) < 3:
                continue

            # FreeBSD ndp -an output format: <ipv6-address>%<scope> <mac-address> <interface> <expire> <flags>
            # Example: fe80::dc33:c4ff:fe0b:120a%vtnet0    de:33:c4:0b:12:0a vtnet0 23h57m1s  S
            # or: 3eef::f6d9:27f4:a7f5:a4bb            de:33:c4:0b:12:0a vtnet0 23h56m2s  S
            # MAC address is the second token (index 1), interface is third token (index 2)
            address = line_parts[0].split('%')[0]  # remove scope if present
            mac = line_parts[1] if len(line_parts) > 1 else None
            physical_intf = line_parts[2] if len(line_parts) > 2 else None

            # Validate MAC address format (should contain colons)
            if mac and ':' in mac and 'incomplete' not in mac.lower():
                if address in self._ndp_table:
                    if physical_intf and physical_intf not in self._ndp_table[address]['intf']:
                        self._ndp_table[address]['intf'].append(physical_intf)
                else:
                    self._ndp_table[address] = {'mac': mac, 'intf': [physical_intf] if physical_intf else [], 'expires': -1}

    def list_items(self):
        """ return parsed arp list
        :return: dict
        """
        return self._arp_table

    def list_ndp_items(self):
        """ return parsed ndp list
        :return: dict
        """
        return self._ndp_table

    def get_by_ipaddress(self, address):
        """ search arp or ndp entry by ip address
        :param address: ip address (IPv4 or IPv6)
        :return: dict or None (if not found)
        """
        # check IPv4 ARP table first
        if address in self._arp_table:
            return self._arp_table[address]
        # check IPv6 NDP table
        elif address in self._ndp_table:
            return self._ndp_table[address]
        else:
            return None

    def get_address_by_mac_ndp(self, mac_address):
        """ search ndp table for IPv6 addresses matching MAC address
        :param mac_address: MAC address to search for
        :return: IPv6 address string or None (if not found)
        """
        # Prefer stable addresses over temporary addresses
        # Temporary addresses typically have random interface identifiers
        # Stable addresses are usually SLAAC-based with EUI-64 or DHCPv6 assigned
        stable_addresses = []
        temporary_addresses = []
        link_local_addresses = []

        for address, entry in self._ndp_table.items():
            if entry['mac'] == mac_address:
                # Check if link-local (fe80::/10)
                if address.startswith('fe80:'):
                    link_local_addresses.append(address)
                # Check if temporary (random interface ID, typically starts with 2000::/3)
                # Temporary addresses often have random-looking interface IDs
                # For now, prefer non-link-local addresses
                elif not address.startswith('fe80:'):
                    # Prefer addresses that look stable (could be enhanced with better heuristics)
                    stable_addresses.append(address)
                    # Note: In practice, distinguishing temporary vs stable requires
                    # checking system configuration or address flags, which NDP output doesn't provide
                    # So we'll return the first non-link-local address found

        # Return priority: stable > temporary > link-local
        if stable_addresses:
            return stable_addresses[0]
        elif temporary_addresses:
            return temporary_addresses[0]
        elif link_local_addresses:
            return link_local_addresses[0]
        return None

    def get_all_addresses_by_mac(self, mac_address):
        """ get prioritized IP addresses (IPv4 + IPv6) for a MAC address
        :param mac_address: MAC address to search for
        :return: List of IP addresses prioritized: [ipv4, global_ipv6, unique_local_ipv6, link_local_ipv6]

        Note: This method returns addresses found in ARP (IPv4) and NDP (IPv6) tables.

        IMPORTANT LIMITATION: We can only discover addresses that appear in NDP (Neighbor Discovery Protocol).
        NDP only shows addresses that have been used for neighbor discovery. Privacy extensions,
        unused SLAAC addresses, and unused DHCPv6 leases will NOT appear in NDP until the client
        actually uses them. The background process (cp-background-process.py) periodically checks
        for new addresses as they appear in NDP and adds them automatically (every 5 seconds).

        Priority order (RFC-compliant):
        1. IPv4 addresses (all)
        2. Global unicast IPv6 (2000::/3 per RFC 4291)
        3. Unique Local Address IPv6 (fc00::/7 per RFC 4193, effectively fd00::/8)
        4. Link-local IPv6 (fe80::/10 per RFC 4291) - limited to 1

        Addresses are classified using Python's ipaddress module which implements
        proper RFC-compliant prefix matching (not string prefix matching).
        """
        addresses = []
        ipv4_addresses = []
        global_ipv6 = []
        unique_local_ipv6 = []
        link_local_ipv6 = []

        # Check ARP table for IPv4 addresses (highest priority)
        for address, entry in self._arp_table.items():
            if entry['mac'] == mac_address:
                ipv4_addresses.append(address)

        # Check NDP table for IPv6 addresses, categorize by type using RFC-compliant classification
        for address, entry in self._ndp_table.items():
            if entry['mac'] == mac_address:
                try:
                    # Remove scope ID if present (e.g., fe80::1%em0 -> fe80::1)
                    addr_str = address.split('%')[0]
                    ipv6_addr = ipaddress.IPv6Address(addr_str)

                    # Classify according to RFC 4291 and RFC 4193
                    # Check special addresses first (multicast, loopback, unspecified)
                    if ipv6_addr.is_multicast:
                        # Multicast (ff00::/8 per RFC 4291) - skip, not used for client addresses
                        continue
                    elif ipv6_addr.is_loopback:
                        # Loopback (::1/128 per RFC 4291) - skip
                        continue
                    elif ipv6_addr.is_unspecified:
                        # Unspecified (::/128 per RFC 4291) - skip
                        continue
                    elif ipv6_addr.is_link_local:
                        # Link-local (fe80::/10 per RFC 4291) - lowest priority
                        link_local_ipv6.append(address)
                    elif ipv6_addr.is_private:
                        # Unique Local Address (fc00::/7 per RFC 4193, effectively fd00::/8)
                        # Note: is_private checks for fc00::/7 which includes both fc00::/8 and fd00::/8
                        # Also includes documentation prefix (2001:db8::/32 per RFC 3849)
                        unique_local_ipv6.append(address)
                    elif ipv6_addr.is_global:
                        # Global unicast (2000::/3 per RFC 4291)
                        global_ipv6.append(address)
                    else:
                        # Other reserved or unassigned addresses - treat as global for safety
                        # This handles edge cases and future address types
                        global_ipv6.append(address)
                except (ValueError, ipaddress.AddressValueError):
                    # Invalid IPv6 address format - skip
                    continue

        # Build prioritized list: IPv4 first, then IPv6 by priority
        addresses.extend(ipv4_addresses)
        addresses.extend(global_ipv6)
        addresses.extend(unique_local_ipv6)

        # Limit link-local to 1 address (usually only one is needed)
        if link_local_ipv6:
            addresses.append(link_local_ipv6[0])

        # Return all addresses found in NDP (no artificial limit)
        # Note: We can only discover addresses that have been used (appear in NDP)
        # New addresses will be discovered by the background process as they appear
        return addresses

    def count_addresses_by_mac(self, mac_address):
        """ count total number of IP addresses (IPv4 + IPv6) for a MAC address
        :param mac_address: MAC address to search for
        :return: Total count of addresses found (before any limits)
        """
        count = 0

        # Count IPv4 addresses
        for address, entry in self._arp_table.items():
            if entry['mac'] == mac_address:
                count += 1

        # Count IPv6 addresses
        for address, entry in self._ndp_table.items():
            if entry['mac'] == mac_address:
                count += 1

        return count

    def get_address_by_mac(self, address):
        """ search arp or ndp entry by mac address, most recent entry
        :param address: MAC address
        :return: IP address string or None (if not found)
        """
        # Check IPv4 ARP table first (backward compatibility)
        result = None
        for item in self._arp_table:
            if self._arp_table[item]['mac'] == address:
                if result is None:
                    result = item
                elif self._arp_table[result]['expires'] < self._arp_table[item]['expires']:
                    result = item

        # If IPv4 found, return it (prefer IPv4 for backward compatibility)
        if result is not None:
            return result

        # Otherwise check IPv6 NDP table
        return self.get_address_by_mac_ndp(address)
