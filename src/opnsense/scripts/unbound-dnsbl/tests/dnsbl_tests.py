import unittest
import tempfile
import sys
import os
sys.path.insert(0, "%s/../lib" % os.path.dirname(__file__))
from lib import Query, ModuleContext
from lib.dnsbl import DNSBL


class TestDNSBLMethods(unittest.TestCase):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._dnsbl_json = "%s/dnsbl.json" % os.path.dirname(__file__)
        self._dnsbl_json_size_out = "%s/dnsbl.size" % tempfile.gettempdir()

    def _get_query(self, client, family, type, domain):
        return Query(client=client, family=family, type=type, domain=domain)

    def test_construct_query(self):
        item = self._get_query('127.0.0.1', 'ip4', 'A', 'www.opnsense.org')
        self.assertEqual(item.request[1:], ('127.0.0.1', 'ip4', 'A', 'www.opnsense.org'), 'Query request malformed')
        self.assertEqual(type(item.request[0]), int, 'Wrong timestamp format')

    def test_net_matches(self):
        ctx = ModuleContext(None)
        dnsbl = DNSBL(ctx, self._dnsbl_json, self._dnsbl_json_size_out)
        for domain in ['block.1.local', '0.beer']:
            qry = self._get_query('10.0.0.1', 'ip4', 'A', domain)
            m = dnsbl.policy_match(qry)
            self.assertEqual(m.get('id'), '580e7f8b-3242-401a-b32e-ed56c3e8cf4a', 'policy mismatch for %s' % domain)

    def test_net_wildcard_matches(self):
        ctx = ModuleContext(None)
        dnsbl = DNSBL(ctx, self._dnsbl_json, self._dnsbl_json_size_out)
        for domain in ['a.wildcard.2.local', 'wildcard.2.local', 'always_allowx.wildcard.2.local']:
            qry = self._get_query('10.0.0.1', 'ip4', 'A', domain)
            m = dnsbl.policy_match(qry)
            self.assertEqual(m.get('id'), '580e7f8b-3242-401a-b32e-ed56c3e8cf4a', 'policy mismatch for %s' % domain)

    def test_all_net_matches(self):
        ctx = ModuleContext(None)
        dnsbl = DNSBL(ctx, self._dnsbl_json, self._dnsbl_json_size_out)
        for domain in ['0.beer']:
            qry = self._get_query('10.10.10.1', 'ip4', 'A', domain)
            m = dnsbl.policy_match(qry)
            self.assertEqual(m.get('id'), '709af075-f67f-40ca-959a-ec7c900991da', 'policy mismatch for %s' % domain)

    def test_passlist_net(self):
        ctx = ModuleContext(None)
        dnsbl = DNSBL(ctx, self._dnsbl_json, self._dnsbl_json_size_out)
        for domain in ['always_allow.wildcard.2.local']:
            qry = self._get_query('10.0.0.1', 'ip4', 'A', domain)
            m = dnsbl.policy_match(qry)
            self.assertEqual(m, False, 'policy mismatch for %s' % domain)
