"""
    Copyright (c) 2017-2021 Ad Schellevis <ad@opnsense.org>
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
import itertools


def net_wildcard_iterator(network: str):
    """
    :param network: network definition (e.g. 192.168.0.1/0.0.255.0)
    :return: iterator with network objects (192.168.0.1/32, 192.168.1.1/32, ..)

    E.G for address="172.18.161.2" and wildcard "0.1.2.7"
    it returns:
        [IPv4Network('172.18.161.0/29'), IPv4Network('172.18.163.0/29'),
        IPv4Network('172.19.161.0/29'), IPv4Network('172.19.163.0/29')]
    """
    if network.find('/') > -1:
        # split address
        address = ipaddress.ip_address(network.split('/')[0])
        wildcard = ipaddress.ip_address(network.split('/')[1])
        if address.version == wildcard.version:
            # extract masked wildcard bits, trailing digits can be "masked", the rest should be calculated
            wildcard_bits = ("{0:0%db}" % wildcard.max_prefixlen).format(int(wildcard))
            masked_bits = [i for i, e in enumerate(reversed(wildcard_bits)) if e == "1"]
            mask_length = 0
            for (pos, bit) in enumerate(masked_bits):
                if pos != bit:
                    break
                mask_length += 1
            for bits_values in itertools.product((0,1), repeat=len(masked_bits[mask_length:])):
                this_ip = int(address)
                for (index, val) in zip(reversed(masked_bits[mask_length:]), bits_values):
                    sb_mask = 1 << index
                    if val:
                        this_ip |= sb_mask
                    else:
                        this_ip &= ~sb_mask
                if wildcard.version == 6:
                    yield ipaddress.IPv6Network((this_ip, wildcard.max_prefixlen - mask_length), strict=False)
                else:
                    yield ipaddress.IPv4Network((this_ip, wildcard.max_prefixlen - mask_length), strict=False)
