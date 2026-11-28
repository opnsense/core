import subprocess
import unittest
from unittest.mock import patch

from lib.ipfw import IPFW, IPFWError


class IPFWTest(unittest.TestCase):
    @patch.object(IPFW, '_run')
    def test_accounting_info_marks_only_complete_rules(self, run):
        run.return_value.stdout = '\n'.join([
            '30000 1 100 10 count ip from 192.0.2.10 to any',
            '30000 2 200 11 count ip from any to 192.0.2.10',
            '30000 0 0 0 skipto 60000 ip from any to any',
            '30001 1 100 10 count ip from 192.0.2.11 to any',
        ])

        details = IPFW.list_accounting_info()

        self.assertTrue(details['192.0.2.10']['_complete'])
        self.assertFalse(details['192.0.2.11']['_complete'])

    @patch('lib.ipfw.subprocess.run')
    def test_run_reports_failure(self, run):
        run.return_value = subprocess.CompletedProcess([], 1, '', 'ipfw unavailable')

        with self.assertRaisesRegex(IPFWError, 'ipfw unavailable'):
            IPFW.list_table('1')

    @patch.object(IPFW, '_run')
    @patch.object(IPFW, 'list_accounting_info', return_value={})
    @patch.object(IPFW, 'list_table', return_value={'192.0.2.10': '30000'})
    def test_matching_table_rule_is_not_replaced(self, list_table, list_accounting_info, run):
        IPFW.add_accounting('1', {'192.0.2.10'})

        commands = [call.args[0] for call in run.call_args_list]
        self.assertNotIn(['/sbin/ipfw', 'table', '1', 'del', '192.0.2.10'], commands)
        self.assertNotIn(['/sbin/ipfw', 'table', '1', 'add', '192.0.2.10', '30000'], commands)

    @patch.object(IPFW, '_run')
    @patch.object(IPFW, 'list_accounting_info', return_value={})
    @patch.object(IPFW, 'list_table', return_value={'192.0.2.10': '30001'})
    def test_mismatching_table_rule_is_replaced(self, list_table, list_accounting_info, run):
        IPFW.add_accounting('1', {'192.0.2.10'})

        commands = [call.args[0] for call in run.call_args_list]
        self.assertIn(['/sbin/ipfw', 'table', '1', 'del', '192.0.2.10'], commands)
        self.assertIn(['/sbin/ipfw', 'table', '1', 'add', '192.0.2.10', '30000'], commands)

    @patch.object(IPFW, '_run')
    @patch.object(IPFW, 'list_accounting_info', return_value={
        '192.0.2.10': {
            'rule': 30000,
            '_in_present': True,
            '_out_present': False,
            '_complete': False,
        }
    })
    @patch.object(IPFW, 'list_table', return_value={'192.0.2.10': '30000'})
    def test_incomplete_rule_adds_only_missing_direction(self, list_table, list_accounting_info, run):
        IPFW.add_accounting('1', {'192.0.2.10'})

        commands = [call.args[0] for call in run.call_args_list]
        self.assertNotIn(
            ['/sbin/ipfw', 'add', '30000', 'count', 'ip', 'from', '192.0.2.10', 'to', 'any'],
            commands
        )
        self.assertIn(
            ['/sbin/ipfw', 'add', '30000', 'count', 'ip', 'from', 'any', 'to', '192.0.2.10'],
            commands
        )
        self.assertIn(
            ['/sbin/ipfw', 'add', '30000', 'skipto', '60000', 'ip', 'from', 'any', 'to', 'any'],
            commands
        )


if __name__ == '__main__':
    unittest.main()
