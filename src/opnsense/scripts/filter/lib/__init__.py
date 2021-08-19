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
import asyncio
import dns.resolver
import ipaddress
import itertools
import syslog
import time
from dns.rdatatype import RdataType
from dns.asyncresolver import Resolver


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


class AsyncDNSResolver:
    """ Asynchronous DNS resolver, collect addresses for hostnames collected in request queue.
        simple example usecase collecting addresses associated with two domains:

        asyncresolver = AsyncDNSResolver()
        asyncresolver.add('www.example.com')
        asyncresolver.add('mail.example.com')
        asyncresolver.collect()
        print(asyncresolver.addresses())
    """
    batch_size = 100
    report_size = 10000

    def __init__(self, origin="<unknown>"):
        self._request_queue = list()
        self._requested = set()
        self._response = set()
        self._origin = origin
        self._domains_queued = 0

    def add(self, hostname):
        self._request_queue.append(hostname)

    async def request_ittr(self, loop):
        dnsResolver = Resolver()
        dnsResolver.timeout = 2
        collected_errors = set()
        while len(self._request_queue) > 0:
            tasks = []
            while len(tasks) < self.batch_size and len(self._request_queue) > 0:
                hostname = self._request_queue.pop()
                if hostname not in self._requested:
                    self._domains_queued += 1
                    # make sure we only request a host once
                    for record_type in ['A', 'AAAA']:
                        tasks.append(dnsResolver.resolve(hostname, record_type))
                    self._requested.add(hostname)
            if len(tasks) > 0:
                responses = await asyncio.gather(*tasks, return_exceptions=True)
                for response in responses:
                    if type(response) is dns.resolver.Answer:
                        for item in response.response.answer:
                            if type(item) is dns.rrset.RRset:
                                for addr in item.items:
                                    if addr.rdtype is RdataType.CNAME:
                                        # query cname (recursion)
                                        self._request_queue.append(addr.target)
                                    else:
                                        self._response.add(addr.address)
                    elif type(response) in [dns.resolver.NXDOMAIN, dns.exception.Timeout, dns.resolver.NoNameservers]:
                        if str(response) not in collected_errors:
                            syslog.syslog(syslog.LOG_ERR, '%s [for %s]' % (response, self._origin))
                            collected_errors.add(str(response))
            if self._domains_queued % self.report_size == 0:
                syslog.syslog(syslog.LOG_NOTICE, 'requested %d hostnames for %s' % (self._domains_queued, self._origin))

    def collect(self):
        if len(self._request_queue) > 0:
            start_time = time.time()
            loop = asyncio.new_event_loop()
            asyncio.set_event_loop(loop)
            asyncio.run(self.request_ittr(loop))
            loop.close()
            syslog.syslog(syslog.LOG_NOTICE, 'resolving %d hostnames (%d addresses) for %s took %.2f seconds' % (
                self._domains_queued, len(self._response), self._origin, time.time() - start_time
            ))
        return self

    def addresses(self):
        return self._response
