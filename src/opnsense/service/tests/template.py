"""
    Copyright (c) 2016 Ad Schellevis <ad@opnsense.org>
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
import os
import unittest
import collections
from modules import config
from modules import template


class TestConfigMethods(unittest.TestCase):
    def setUp(self):
        """ setup test, load config
        :return:
        """
        conf_path = '%s/config/config.xml' % '/'.join(__file__.split('/')[:-1])
        self.conf = config.Config(conf_path)

    def tearDown(self):
        """ end test
        :return:
        """
        self.conf = None

    def test_type(self):
        """ test correct config type
        :return:
        """
        self.assertEqual(type(self.conf.get()), collections.OrderedDict)

    def test_interface(self):
        """ test existence of interface
        :return:
        """
        self.assertIn('interfaces', self.conf.get(), 'interfaces section missing')
        self.assertIn('lan', self.conf.get()['interfaces'], 'lan section missing')
        self.assertIn('ipaddr', self.conf.get()['interfaces']['lan'], 'lan address missing')


class TestTemplateMethods(unittest.TestCase):
    def setUp(self):
        """ setup test, load config create temp directory
        :return:
        """
        conf_path = '%s/config/config.xml' % '/'.join(__file__.split('/')[:-1])
        self.output_path = '%s/output/' % '/'.join(__file__.split('/')[:-1])
        self.conf = config.Config(conf_path)
        self.tmpl = template.Template(target_root_directory=self.output_path)
        self.tmpl.set_config(self.conf.get())
        if not os.path.exists(self.output_path):
            os.mkdir(self.output_path)

    def tearDown(self):
        """ end test, remove test data
        :return:
        """
        self.conf = None
        if os.path.exists(self.output_path):
            for root, dirs, files in os.walk(self.output_path, topdown=False):
                for filename in files:
                    os.unlink('%s/%s' % (root, filename))
                for dirname in dirs:
                    os.rmdir('%s/%s' % (root, dirname))
            os.rmdir(self.output_path)

    def test_sample(self):
        """ test sample template
        :return:
        """
        generated_filenames = self.tmpl.generate('OPNsense/Sample')
        self.assertEqual(len(generated_filenames), 4, 'number of output files not 4')

    @unittest.skip("Very fragile test, only works on clean install")
    def test_all(self):
        """ Test if all expected templates are created, can only find test for static defined cases.
            Calls "generate *" and compares that to all defined templates in all +TARGET files
            Fails on first missing case.
        :return:
        """
        self.expected_filenames = dict()
        self.generated_filenames = list()
        templates_path = '%s/../templates' % '/'.join(__file__.split('/')[:-1])
        for root, dirs, files in os.walk(templates_path):
            for filenm in files:
                if filenm == '+TARGETS':
                    filename = '%s/%s' % (root, filenm)
                    with open(filename) as fhandle:
                        for line in fhandle.read().split('\n'):
                            line = line.strip()
                            if len(line) > 1 and line[0] != '#' and line.find('[') == -1:
                                expected_filename = (
                                    '%s%s' % (self.output_path, line.split(':')[-1])
                                ).replace('//', '/')
                                self.expected_filenames[expected_filename] = {'src': filename}

        for filename in self.tmpl.generate('*'):
            self.generated_filenames.append(filename.replace('//', '/'))

        for expected_filename in self.expected_filenames:
            message = 'missing %s (%s' % (expected_filename, self.expected_filenames[expected_filename]['src'])
            self.assertIn(expected_filename, self.generated_filenames, message)
