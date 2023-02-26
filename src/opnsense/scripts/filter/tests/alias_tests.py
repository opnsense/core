import unittest
import sys
import os
import subprocess
sys.path.insert(0, "%s/../lib" % os.path.dirname(__file__))
from alias.arpcache import ArpCache
from alias.base import BaseContentParser
from alias.bgpasn import BGPASN
from alias.geoip import GEOIP
from alias.interface import InterfaceParser
from alias.uri import UriParser

class TestAliasMethods(unittest.TestCase):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.properties = {'name': 'test_alias', 'proto': 'IPv4,IPv6', 'timeout': 10, 'interface': 'lo0'}

    def test_parse_geoip(self):
        payload = list(GEOIP(**self.properties).iter_addresses('NL'))
        self.assertGreater(len(payload), 1000, 'GEO IP alias smaller than expected')

    def test_parse_arp(self):
        # search a "random" mac address from our arp table
        tmp = subprocess.run(['/usr/sbin/arp', '-n', '-a'], capture_output=True, text=True).stdout.strip()
        mac = None
        for line in tmp.split("\n"):
            parts = line.split()
            if len(parts) > 3 and parts[3].count(':') == 5:
                mac = parts[3]
                break

        payload = list(ArpCache(**self.properties).iter_addresses(mac))
        self.assertGreater(len(payload), 0, 'mac address not found')

    def test_parse_asn(self):
        payload = list(BGPASN(**self.properties).iter_addresses('13335'))
        self.assertGreater(len(payload), 1000, 'AS13335 is smaller than expected')

    def test_parse_interface(self):
        subprocess.run(['/sbin/ifconfig', 'lo0', 'inet6', '2001:fff:faaa::4f31', 'alias'], capture_output=True)
        payload = list(InterfaceParser(**self.properties).iter_addresses('::1005'))
        subprocess.run(['/sbin/ifconfig', 'lo0', 'inet6', '2001:fff:faaa::4f31', '-alias'], capture_output=True)
        self.assertEqual(payload, ['2001:0fff:faaa:0000:0000:0000:0000:1005/128'], 'Unexpected address received')

    def test_parse_uri(self):
        payload = list(UriParser(**self.properties).iter_addresses('http://www.spamhaus.org/drop/drop.txt'))
        self.assertGreater(len(payload), 100, 'http://www.spamhaus.org/drop/drop.txt is smaller than expected')

    def test_host_address(self):
        payload = list(BaseContentParser(**self.properties).iter_addresses('192.168.1.1'))
        self.assertEqual(payload, ['192.168.1.1'], 'Invalid host')
        payload = list(BaseContentParser(**self.properties).iter_addresses('192.999.1.1'))
        self.assertEqual(payload, [], 'Invalid host')

    def test_host_network(self):
        payload = list(BaseContentParser(**self.properties).iter_addresses('192.168.1.0/24'))
        self.assertEqual(payload, ['192.168.1.0/24'], 'Invalid network')
        payload = list(BaseContentParser(**self.properties).iter_addresses('192.168.999.0/24'))
        self.assertEqual(payload, [], 'Invalid network')

    def test_host_name(self):
        tmp = BaseContentParser(**self.properties)
        payload = list(tmp.iter_addresses('pkg.opnsense.org'))
        self.assertEqual(payload, [], 'unexpected result')
        self.assertGreater(len(tmp.resolve_dns()), 1, 'pkg.opnsense.org should return at least 2 addresses')

    def test_wildcard(self):
        payload = list(BaseContentParser(**self.properties).iter_addresses('192.168.0.0/0.0.255.0'))
        self.assertEqual(len(payload), 256, 'Invalid number of hosts')
