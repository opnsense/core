import json
import sys
import unittest
from unittest.mock import Mock, patch

# OPNsense provides ujson. The standard library has the API used by allow.py
# and keeps these orchestration tests dependency-free on development hosts.
sys.modules.setdefault('ujson', json)

import allow
from lib.ipfw import IPFWError
from lib.pf import PFError


class AllowTest(unittest.TestCase):
    def setUp(self):
        self.db = Mock()
        self.db.add_client.return_value = {
            'sessionId': 'session-1',
            'zoneid': '1',
            'ipAddress': '192.0.2.10'
        }
        self.arp = Mock()
        self.arp.get_by_ipaddress.return_value = {'mac': '00:11:22:33:44:55'}

    def authorize(self):
        return allow.authorize_client(
            zoneid='1',
            username='anonymous@192.0.2.10',
            authenticated_via='',
            ip_address='192.0.2.10',
            db=self.db,
            arp=self.arp
        )

    @patch.object(allow.IPFW, 'add_accounting')
    @patch.object(allow.PF, 'add_to_table')
    def test_success_is_authorized(self, add_to_table, add_accounting):
        response = self.authorize()

        self.assertEqual(response['clientState'], 'AUTHORIZED')
        self.db.del_client.assert_not_called()

    @patch.object(allow.syslog, 'syslog')
    @patch.object(allow.PF, 'add_to_table', side_effect=PFError('pf failed'))
    def test_pf_failure_rolls_back_session_and_is_not_authorized(self, add_to_table, syslog):
        response = self.authorize()

        self.assertEqual(response['clientState'], 'UNKNOWN')
        self.db.del_client.assert_called_once_with('1', 'session-1', 'Admin-Reset')

    @patch.object(allow.syslog, 'syslog')
    @patch.object(allow.IPFW, 'add_accounting', side_effect=IPFWError('ipfw failed'))
    @patch.object(allow.PF, 'add_to_table')
    def test_accounting_failure_keeps_pf_authorization(self, add_to_table, add_accounting, syslog):
        response = self.authorize()

        self.assertEqual(response['clientState'], 'AUTHORIZED')
        self.db.del_client.assert_not_called()

    def test_mac_lookup_reloads_arp_between_attempts(self):
        arp = Mock()
        arp.get_by_ipaddress.side_effect = [None, {'mac': '00:11:22:33:44:55'}]
        sleep = Mock()

        mac = allow.find_client_mac(arp, '192.0.2.10', retry_delays=(0, 0.1), sleep=sleep)

        self.assertEqual(mac, '00:11:22:33:44:55')
        sleep.assert_called_once_with(0.1)
        arp.reload.assert_called_once_with()


if __name__ == '__main__':
    unittest.main()
