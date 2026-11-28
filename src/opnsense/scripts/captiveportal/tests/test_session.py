import unittest
from unittest.mock import Mock

from lib.session import client_mac_changed


class SessionIdentityTest(unittest.TestCase):
    def test_first_learned_mac_is_persisted(self):
        db = Mock()
        arp = Mock()
        arp.get_by_ipaddress.return_value = {'mac': '00:11:22:33:44:55'}
        client = {
            'ipAddress': '192.0.2.10',
            'macAddress': None,
            'sessionId': 'session-1'
        }

        changed = client_mac_changed(db, arp, '1', client)

        self.assertFalse(changed)
        self.assertEqual(client['macAddress'], '00:11:22:33:44:55')
        db.update_client_mac.assert_called_once_with('1', 'session-1', '00:11:22:33:44:55')

    def test_same_known_mac_does_not_change_session(self):
        db = Mock()
        arp = Mock()
        arp.get_by_ipaddress.return_value = {'mac': '00:11:22:33:44:55'}
        client = {
            'ipAddress': '192.0.2.10',
            'macAddress': '00:11:22:33:44:55',
            'sessionId': 'session-1'
        }

        self.assertFalse(client_mac_changed(db, arp, '1', client))
        db.update_client_mac.assert_not_called()

    def test_two_different_known_macs_change_session_identity(self):
        db = Mock()
        arp = Mock()
        arp.get_by_ipaddress.return_value = {'mac': '00:11:22:33:44:66'}
        client = {
            'ipAddress': '192.0.2.10',
            'macAddress': '00:11:22:33:44:55',
            'sessionId': 'session-1'
        }

        self.assertTrue(client_mac_changed(db, arp, '1', client))
        db.update_client_mac.assert_not_called()

    def test_missing_arp_entry_keeps_session(self):
        db = Mock()
        arp = Mock()
        arp.get_by_ipaddress.return_value = None
        client = {
            'ipAddress': '192.0.2.10',
            'macAddress': None,
            'sessionId': 'session-1'
        }

        self.assertFalse(client_mac_changed(db, arp, '1', client))
        db.update_client_mac.assert_not_called()


if __name__ == '__main__':
    unittest.main()
