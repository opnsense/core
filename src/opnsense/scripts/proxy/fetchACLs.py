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
import json
import glob
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

    def get_files(self):
        """ process downloaded data, handle compression
            :return: iterator filename, content
        """
        if self._source_data is not None:
            # handle compressed data
            if (len(self._url) > 8 and self._url[-7:] == '.tar.gz') \
                or (len(self._url) > 4 and self._url[-4:] == '.tgz'):
                # source is in tar.gz format, extract all into a single string
                try:
                    tf = tarfile.open(fileobj=StringIO.StringIO(self._source_data))
                    for tf_file in tf.getmembers():
                        if tf_file.isfile():
                            yield tf_file.name, tf.extractfile(tf_file).read()
                except IOError as e:
                    syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s (%s)'%(self._url, e))
            elif len(self._url) > 4 and self._url[-3:] == '.gz':
                # source is in .gz format unpack
                try:
                    gf = gzip.GzipFile(mode='r', fileobj=StringIO.StringIO(self._source_data))
                    yield os.path.basename(self._url), gf.read()
                except IOError as e:
                    syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s (%s)'%(self._url, e))
            elif len(self._url) > 5 and self._url[-4:] == '.zip':
                # source is in .zip format, extract all into a single string
                target_data = dict()
                with zipfile.ZipFile(StringIO.StringIO(self._source_data),
                                     mode='r',
                                     compression=zipfile.ZIP_DEFLATED) as zf:
                    for item in zf.infolist():
                        if item.file_size > 0:
                            yield item.filename, zf.read(item)
                    self._target_data = target_data
            else:
                yield os.path.basename(self._url), self._source_data

    def download(self):
        """ download / unpack ACL
            :return: iterator filename, type, content
        """
        self.fetch()
        for filename, filedata in self.get_files():
            for line in filedata.split('\n'):
                if line.find('/') > -1:
                    file_type = 'url'
                else:
                    file_type = 'domain'
                yield filename, file_type, line


# parse OPNsense external ACLs config
if os.path.exists(acl_config_fn):
    # create acl directory (if new)
    if not os.path.exists(acl_target_dir):
        os.mkdir(acl_target_dir)
    else:
        # remove index files
        for filename in glob.glob('%s/*.index'%acl_target_dir):
            os.remove(filename)
    # read config and download per section
    cnf = ConfigParser()
    cnf.read(acl_config_fn)
    for section in cnf.sections():
        # check if tag enabled exists in section
        if cnf.has_option(section,'enabled'):
            # if enabled fetch file
            target_filename = acl_target_dir+'/'+section
            if cnf.has_option(section,'url'):
                # define targets
                targets = {'domain': {'filename': target_filename, 'handle' : None},
                           'url': {'filename': '%s.url'%target_filename, 'handle': None}}

                # download file
                if cnf.get(section,'enabled') == '1':
                    # only generate files if enabled, otherwise dump empty files
                    download_url = cnf.get(section,'url')
                    acl = ACLDownload(download_url, acl_max_timeout)
                    all_filenames = list()
                    for filename, filetype, line in acl.download():
                        if filename not in all_filenames:
                            all_filenames.append(filename)
                        if filetype in targets and targets[filetype]['handle'] is None:
                            targets[filetype]['handle'] = open(targets[filetype]['filename'], 'wb')
                        if filetype in targets:
                            targets[filetype]['handle'].write('%s\n'%line)
                    # save index to disc
                    with open('%s.index'%target_filename,'wb') as idx_out:
                        index_data = dict()
                        for filename in all_filenames:
                            if len(filename.split('/')) > 3:
                                index_key = '/'.join(filename.split('/')[1:-1])
                                if index_key not in index_data:
                                    index_data[index_key] = index_key
                        idx_out.write(json.dumps(index_data))
                # cleanup
                for filetype in targets:
                    if targets[filetype]['handle'] is not None:
                        targets[filetype]['handle'].close()
                    elif cnf.get(section,'enabled') != '1':
                        if os.path.isfile(targets[filetype]['filename']):
                            # disabled, remove previous data
                            os.remove(targets[filetype]['filename'])
                    elif not os.path.isfile(targets[filetype]['filename']):
                        # no data fetched and no file available, create new empty file
                        with open(targets[filetype]['filename'], 'wb') as target_out:
                            target_out.write("")
