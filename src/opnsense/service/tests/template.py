"""
    Copyright (c) 2016 Ad Schellevis
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
        self.assertEquals(type(self.conf.get()), collections.OrderedDict)

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
        generated_filenames = self.tmpl.generate('OPNsense.Sample')
        self.assertEquals(len(generated_filenames), 3, 'number of output files <> 3')
