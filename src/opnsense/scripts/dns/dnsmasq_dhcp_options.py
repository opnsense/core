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
import json
import subprocess
import argparse

parser = argparse.ArgumentParser()
parser.add_argument("mode", nargs="?", default="dhcp", choices=["dhcp", "dhcp6"])
args = parser.parse_args()

result = {}

# not yet registered by name, but practical to have (only for DHCPv4)
# https://www.iana.org/assignments/bootp-dhcp-parameters/bootp-dhcp-parameters.xhtml
if args.mode == "dhcp":
    result['1'] = 'subnet-mask [1]'
    result['2'] = 'time-offset [2]'
    result['3'] = 'router [3]'
    result['4'] = 'time-server [4]'
    result['5'] = 'name-server [5]'
    result['6'] = 'domain-server [6]'
    result['7'] = 'log-server [7]'
    result['8'] = 'quotes-server [8]'
    result['9'] = 'lpr-server [9]'
    result['10'] = 'impress-server [10]'
    result['11'] = 'rlp-server [11]'
    result['12'] = 'hostname [12]'
    result['13'] = 'boot-file-size [13]'
    result['14'] = 'merit-dump-file [14]'
    result['15'] = 'domain-name [15]'
    result['16'] = 'swap-server [16]'
    result['17'] = 'root-path [17]'
    result['18'] = 'extension-file [18]'
    result['19'] = 'forward-on/off [19]'
    result['20'] = 'srcrte-on/off [20]'
    result['21'] = 'policy-filter [21]'
    result['22'] = 'max-dg-assembly [22]'
    result['23'] = 'default-ip-ttl [23]'
    result['24'] = 'mtu-timeout [24]'
    result['25'] = 'mtu-plateau [25]'
    result['26'] = 'mtu-interface [26]'
    result['27'] = 'mtu-subnet [27]'
    result['28'] = 'broadcast-address [28]'
    result['29'] = 'mask-discovery [29]'
    result['30'] = 'mask-supplier [30]'
    result['31'] = 'router-discovery [31]'
    result['32'] = 'router-request [32]'
    result['33'] = 'static-route [33]'
    result['34'] = 'trailers [34]'
    result['35'] = 'arp-timeout [35]'
    result['36'] = 'ethernet [36]'
    result['37'] = 'default-tcp-ttl [37]'
    result['38'] = 'keepalive-time [38]'
    result['39'] = 'keepalive-data [39]'
    result['40'] = 'nis-domain [40]'
    result['41'] = 'nis-servers [41]'
    result['42'] = 'ntp-servers [42]'
    result['43'] = 'vendor-specific-info [43]'
    result['44'] = 'netbios-name-server [44]'
    result['45'] = 'netbios-datagram-dist [45]'
    result['46'] = 'netbios-node-type [46]'
    result['47'] = 'netbios-scope [47]'
    result['48'] = 'x-window-font-server [48]'
    result['49'] = 'x-window-display-manager [49]'
    result['50'] = 'requested-ip-address [50]'
    result['51'] = 'ip-address-lease-time [51]'
    result['52'] = 'option-overload [52]'
    result['53'] = 'dhcp-message-type [53]'
    result['54'] = 'server-identifier [54]'
    result['55'] = 'parameter-request-list [55]'
    result['56'] = 'message [56]'
    result['57'] = 'maximum-dhcp-message-size [57]'
    result['58'] = 'renewal-time-value [58]'
    result['59'] = 'rebinding-time-value [59]'
    result['60'] = 'class-identifier [60]'
    result['61'] = 'client-identifier [61]'
    result['62'] = 'netware/ip-domain-name [62]'
    result['63'] = 'netware/ip-option [63]'
    result['64'] = 'nis+-domain-name [64]'
    result['65'] = 'nis+-server-address [65]'
    result['66'] = 'tftp-server-name [66]'
    result['67'] = 'bootfile-name [67]'
    result['68'] = 'mobile-ip-home-agent [68]'
    result['69'] = 'smtp-server [69]'
    result['70'] = 'pop3-server [70]'
    result['71'] = 'nntp-server [71]'
    result['72'] = 'www-server [72]'
    result['73'] = 'finger-server [73]'
    result['74'] = 'irc-server [74]'
    result['75'] = 'streettalk-server [75]'
    result['76'] = 'streettalk-directory-assistance-server [76]'
    result['77'] = 'user-class-information [77]'
    result['78'] = 'directory-agent-information [78]'
    result['79'] = 'service-location-agent-scope [79]'
    result['80'] = 'rapid-commit [80]'
    result['81'] = 'fully-qualified-domain-name [81]'
    result['82'] = 'relay-agent-information [82]'
    result['83'] = 'internet-storage-name-service [83]'
    result['84'] = 'nds-servers [84]'
    result['85'] = 'nds-tree-name [85]'
    result['86'] = 'nds-context [86]'
    result['87'] = 'bcmcs-controller-domain-name-list [87]'
    result['88'] = 'bcmcs-controller-ipv4-address-list [88]'
    result['89'] = 'authentication [89]'
    result['90'] = 'client-fqdn [90]'
    result['91'] = 'relay-agent-information-option [91]'
    result['92'] = 'isns [92]'
    result['93'] = 'client-system-architecture [93]'
    result['94'] = 'client-network-interface-identifier [94]'
    result['95'] = 'ldap [95]'
    result['96'] = 'ipv6-transition [96]'
    result['97'] = 'uuid/guid-based-client-identifier [97]'
    result['98'] = 'user-auth [98]'
    result['99'] = 'geoconf-civic [99]'
    result['100'] = 'pcode [100]'
    result['101'] = 'tcode [101]'
    result['102'] = 'removed/unassigned [102]'
    result['103'] = 'removed/unassigned [103]'
    result['104'] = 'removed/unassigned [104]'
    result['105'] = 'removed/unassigned [105]'
    result['106'] = 'removed/unassigned [106]'
    result['107'] = 'removed/unassigned [107]'
    result['108'] = 'ipv6-only-preferred [108]'
    result['109'] = 'option-dhcp4o6-s46-saddr [109]'
    result['110'] = 'removed/unassigned [110]'
    result['111'] = 'unassigned [111]'
    result['112'] = 'netinfo-address [112]'
    result['113'] = 'netinfo-tag [113]'
    result['114'] = 'dhcp-captive-portal [114]'
    result['128'] = 'tftp-server-ip-address [128]'
    result['129'] = 'etherboot-signature [129]'
    result['252'] = 'wpad-url [252]'

# overwrite the options with those already known to DNSmasq
sp = subprocess.run(['/usr/local/sbin/dnsmasq', '--help', args.mode], capture_output=True, text=True)
for line in sp.stdout.split("\n"):
    parts = line.split(maxsplit=1)
    if len(parts) == 2 and parts[0].isdigit():
        result[parts[0]] = "%s [%s]" % (parts[1], parts[0])

# fill in missing options as generic names to enable custom options - also for DHCPv6
for i in range(1, 256):
    key = str(i)
    if key not in result:
        result[key] = f"option-{i} [{i}]"

# sort options by number
print(json.dumps(result, sort_keys=True))
