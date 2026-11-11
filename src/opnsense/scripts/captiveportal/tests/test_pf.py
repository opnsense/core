import subprocess
import unittest
from unittest.mock import patch

from lib.pf import PF, PFError


class PFTest(unittest.TestCase):
    @patch.object(PF, '_run')
    def test_remove_attempts_state_cleanup_after_table_failure(self, run):
        run.side_effect = [PFError('table failure'), None, None]

        with self.assertRaisesRegex(PFError, 'table failure'):
            PF.remove_from_table('1', '192.0.2.10')

        self.assertEqual(run.call_count, 3)

    @patch('lib.pf.subprocess.run')
    def test_add_to_table_accepts_success(self, run):
        run.return_value = subprocess.CompletedProcess([], 0, '', '')

        PF.add_to_table('1', '192.0.2.10')

        run.assert_called_once_with(
            ['/sbin/pfctl', '-t', '__captiveportal_zone_1', '-T', 'add', '192.0.2.10'],
            capture_output=True,
            text=True
        )

    @patch('lib.pf.subprocess.run')
    def test_add_to_table_reports_failure(self, run):
        run.return_value = subprocess.CompletedProcess([], 1, '', 'table does not exist')

        with self.assertRaisesRegex(PFError, 'table does not exist'):
            PF.add_to_table('1', '192.0.2.10')

    @patch('lib.pf.subprocess.run')
    def test_list_table_reports_failure_instead_of_returning_empty_table(self, run):
        run.return_value = subprocess.CompletedProcess([], 1, '', 'pf unavailable')

        with self.assertRaisesRegex(PFError, 'pf unavailable'):
            list(PF.list_table('1'))


if __name__ == '__main__':
    unittest.main()
