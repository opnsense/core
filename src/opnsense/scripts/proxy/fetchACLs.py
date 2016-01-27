#!/usr/local/bin/python2.7

"""
    Copyright (c) 2015 Jos Schellevis - Deciso B.V.
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

import urllib2
import os
import os.path
import tarfile
import gzip
import zipfile
import StringIO
import syslog
from ConfigParser import ConfigParser

acl_config_fn = ('/usr/local/etc/squid/externalACLs.conf')
acl_target_dir = ('/usr/local/etc/squid/acl')
acl_max_timeout = 30

class ACLDownload(object):

    def __init__(self, url, timeout):
        """ init new
        """
        self._url = url
        self._timeout = timeout
        self._source_data = None
        self._target_data = None

    def fetch(self):
        """ fetch (raw) source data into self._source_data
        """
        try:
            f = urllib2.urlopen(self._url,timeout = self._timeout)
            self._source_data = f.read()
            f.close()
        except (urllib2.URLError, urllib2.HTTPError, IOError) as e:
            syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s'%self._url)
            self._source_data = None

    def pre_process(self):
        """ pre process downloaded data, handle compression
        """
        if self._source_data is not None:
            # handle compressed data
            if (len(self._url) > 8 and self._url[-7:] == '.tar.gz') \
                or (len(self._url) > 4 and self._url[-4:] == '.tgz'):
                # source is in tar.gz format, extract all into a single string
                try:
                    tf = tarfile.open(fileobj=StringIO.StringIO(self._source_data))
                    target_data = []
                    for tf_file in tf.getmembers():
                        if tf_file.isfile():
                            target_data.append(tf.extractfile(tf_file).read())
                    self._target_data = ''.join(target_data)
                except IOError as e:
                    syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s (%s)'%(self._url, e))
            elif len(self._url) > 4 and self._url[-3:] == '.gz':
                # source is in .gz format unpack
                try:
                    gf = gzip.GzipFile(mode='r', fileobj=StringIO.StringIO(self._source_data))
                    self._target_data = gf.read()
                except IOError as e:
                    syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s (%s)'%(self._url, e))
            elif len(self._url) > 5 and self._url[-4:] == '.zip':
                # source is in .zip format, extract all into a single string
                target_data = []
                with zipfile.ZipFile(StringIO.StringIO(self._source_data),
                                     mode='r',
                                     compression=zipfile.ZIP_DEFLATED) as zf:
                    for item in zf.infolist():
                        target_data.append(zf.read(item))
                    self._target_data = ''.join(target_data)
            else:
                self._target_data = self._source_data

    def download(self):
        self.fetch()
        self.pre_process()

    def is_valid(self):
        """ did this ACL download successful
        """
        if self._target_data is not None:
            return True
        else:
            return False

    def get_data(self):
        """ retrieve data
        """
        # XXX: maybe some postprocessing is needed here, all will be used with a squid dstdom_regex tag
        return self._target_data


# parse OPNsense external ACLs config
if os.path.exists(acl_config_fn):
    # create acl directory (if new)
    if not os.path.exists(acl_target_dir):
        os.mkdir(acl_target_dir)
    # read config and download per section
    cnf = ConfigParser()
    cnf.read(acl_config_fn)
    for section in cnf.sections():
        # check if tag enabled exists in section
        if cnf.has_option(section,'enabled'):
            # if enabled fetch file
            target_filename = acl_target_dir+'/'+section
            if cnf.get(section,'enabled')=='1':
                if cnf.has_option(section,'url'):
                    download_url = cnf.get(section,'url')
                    acl = ACLDownload(download_url, acl_max_timeout)
                    acl.download()
                    if acl.is_valid():
                        output_data = acl.get_data()
                        with open(target_filename, "wb") as code:
                            code.write(output_data)
                    elif not os.path.isfile(target_filename):
                        # if there's no file available, create an empty one (otherwise leave the last download).
                        with open(target_filename, "wb") as code:
                            code.write("")
            # if disabled or not 1 try to remove old file
            elif cnf.get(section,'enabled')!='1':
                try:
                    os.remove(acl_target_dir+'/'+section)
                except OSError:
                    pass
