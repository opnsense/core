"""
    Copyright (c) 2026 Logan Galloway
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

    --------------------------------------------------------------------------------------

    package : configd
"""
import unittest
from unittest import mock
from modules.addons import template_helpers


class TestGetPrefix(unittest.TestCase):
    @staticmethod
    def fake_files(files):
        """ patch the file system access of getPrefix() with a dictionary of filename -> content
        :param files: dict
        :return: list of patches to use as context managers
        """
        def isfile(filename):
            return filename in files

        def fake_open(filename, *args, **kwargs):
            return mock.mock_open(read_data=files[filename])(filename, *args, **kwargs)

        return [
            mock.patch.object(template_helpers.os.path, 'isfile', isfile),
            mock.patch('builtins.open', fake_open)
        ]

    def get_prefix(self, files, device):
        patches = self.fake_files(files)
        with patches[0], patches[1]:
            return template_helpers.Helpers.getPrefix(device)

    def test_prefix(self):
        files = {'/tmp/igc0_prefixv6': '2001:db8:1234:ab00::/60\n'}
        self.assertEqual(self.get_prefix(files, 'igc0'), '2001:db8:1234:ab00::/60')

    def test_slaac_name_when_there_is_no_plain_file(self):
        files = {'/tmp/igc0:slaac_prefixv6': '2001:db8:1234:ab00::/64\n'}
        self.assertEqual(self.get_prefix(files, 'igc0'), '2001:db8:1234:ab00::/64')

    def test_plain_file_wins(self):
        files = {
            '/tmp/igc0_prefixv6': '2001:db8:1234:ab00::/60\n',
            '/tmp/igc0:slaac_prefixv6': '2001:db8:ffff::/64\n'
        }
        self.assertEqual(self.get_prefix(files, 'igc0'), '2001:db8:1234:ab00::/60')

    def test_more_than_one_prefix_is_kept_as_found(self):
        files = {'/tmp/igc0_prefixv6': '2001:db8:1234:ab00::/60\n2001:db8:5678:cd00::/60\n'}
        self.assertEqual(
            self.get_prefix(files, 'igc0').split(),
            ['2001:db8:1234:ab00::/60', '2001:db8:5678:cd00::/60']
        )

    def test_missing_is_empty(self):
        self.assertEqual(self.get_prefix({}, 'igc0'), '')
        self.assertEqual(self.get_prefix({'/tmp/igc1_prefixv6': '2001:db8:1234:ab00::/60\n'}, 'igc0'), '')

    def test_empty_file_is_empty(self):
        self.assertEqual(self.get_prefix({'/tmp/igc0_prefixv6': '\n'}, 'igc0'), '')

    def test_ipv4_file_is_not_used(self):
        self.assertEqual(self.get_prefix({'/tmp/igc0_prefix': '10.0.0.0/8\n'}, 'igc0'), '')

    def test_no_device_or_path_in_device(self):
        files = {'/tmp/_prefixv6': '2001:db8::/60\n', '/tmp/../etc/passwd_prefixv6': 'root\n'}
        self.assertEqual(self.get_prefix(files, ''), '')
        self.assertEqual(self.get_prefix(files, None), '')
        self.assertEqual(self.get_prefix(files, '../etc/passwd'), '')
