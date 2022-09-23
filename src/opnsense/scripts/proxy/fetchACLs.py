#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2019 Ad Schellevis <ad@opnsense.org>
    Copyright (c) 2015 Jos Schellevis <jos@opnsense.org>
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

import tempfile
import os
import sys
import json
import glob
import os.path
import tarfile
import gzip
import zipfile
import syslog
import urllib3
from configparser import ConfigParser
from urllib.request import urlopen
from urllib.error import URLError
from urllib.error import HTTPError
import requests
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

acl_config_fn = '/usr/local/etc/squid/externalACLs.conf'
acl_target_dir = '/usr/local/etc/squid/acl'
acl_max_timeout = 30


class Downloader(object):
    """ Download helper
    """

    def __init__(self, url,username, password, timeout, ssl_no_verify=False):
        """ init new
            :param url: source url
            :param timeout: timeout in seconds
        """
        self._url = url.strip()
        self._timeout = timeout
        self._source_handle = None
        self._username = username
        self._password = password
        self._ssl_no_verify = ssl_no_verify

    def fetch(self):
        """ fetch (raw) source data into tempfile using self._source_handle
        """
        self._source_handle = None
        if self._url.lower().startswith('http://') or self._url.lower().startswith('https://'):
            # HTTP(S) download
            req_opts = dict()
            req_opts['url'] = self._url
            req_opts['stream'] = True
            req_opts['timeout'] = self._timeout
            if self._ssl_no_verify:
                req_opts['verify'] = False
            if self._username is not None:
                req_opts['auth'] = (self._username, self._password)
            req = requests.get(**req_opts)
            if req.status_code == 200:
                req.raw.decode_content = True
                self._source_handle = tempfile.NamedTemporaryFile('wb+', 10240)
                while True:
                    data = req.raw.read(10240)
                    if not data:
                        break
                    else:
                         self._source_handle.write(data)
                self._source_handle.seek(0)
            else:
                syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s (http code: %s)' % (self._url,
                                                                                                   req.status_code))
        elif self._url.lower().startswith('ftp://'):
            # FTP download
            try:
                f = urlopen(self._url, timeout=self._timeout)
                self._source_handle = tempfile.NamedTemporaryFile('wb+', 10240)
                while True:
                    data = f.read(10240)
                    if not data:
                        break
                    else:
                         self._source_handle.write(data)
                self._source_handle.seek(0)
                f.close()
            except (URLError, HTTPError, IOError) as e:
                 syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s' % self._url)
        else:
            syslog.syslog(syslog.LOG_ERR, 'proxy acl: unsupported protocol for %s' % self._url)

    def get_files(self):
        """ process downloaded data, handle compression
            :return: iterator filename, file handle
        """
        if self._source_handle is not None:
            # handle compressed data
            if (len(self._url) > 8 and self._url[-7:] == '.tar.gz') \
                    or (len(self._url) > 4 and self._url[-4:] == '.tgz'):
                # source is in tar.gz format, extract all into a single string
                try:
                    tf = tarfile.open(fileobj=self._source_handle)
                    for tf_file in tf.getmembers():
                        if tf_file.isfile():
                            yield tf_file.name, tf.extractfile(tf_file)
                except IOError as e:
                    syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s (%s)' % (self._url, e))
            elif len(self._url) > 4 and self._url[-3:] == '.gz':
                # source is in .gz format unpack
                try:
                    gf = gzip.GzipFile(mode='r', fileobj=self._source_handle)
                    yield os.path.basename(self._url), gf
                except IOError as e:
                    syslog.syslog(syslog.LOG_ERR, 'proxy acl: error downloading %s (%s)' % (self._url, e))
            elif len(self._url) > 5 and self._url[-4:] == '.zip':
                # source is in .zip format, extract all into a single string
                with zipfile.ZipFile(self._source_handle,
                                     mode='r',
                                     compression=zipfile.ZIP_DEFLATED) as zf:
                    for item in zf.infolist():
                        if item.file_size > 0:
                            yield item.filename, zf.open(item)
            else:
                yield os.path.basename(self._url), self._source_handle

    def download(self):
        """ download / unpack ACL
            :return: iterator filename, type, content
        """
        self.fetch()
        for filename, filehandle in self.get_files():
            basefilename = os.path.basename(filename).lower()
            file_ext = filename.split('.')[-1].lower()
            while True:
                line = filehandle.readline().decode(encoding='utf-8', errors='ignore')
                if not line:
                    break
                yield filename, basefilename, file_ext, line


class DomainSorter(object):
    """ Helper class for building sorted squid domain acl list.
        Use as file type object, close flushes the actual (sorted) data to disc
    """

    def __init__(self, filename=None):
        """ new sorted output file, uses an acl record in reverse order as sort key
            :param filename: target filename
            :param mode: file open mode
        """
        self._num_targets = 20
        self._separator = '|'
        self._buckets = dict()
        self._sort_map = dict()
        # setup target
        self._target_filename = filename
        # setup temp files
        self.generate_targets()

    def generate_targets(self):
        """ generate ordered targets
        """
        sets = 255
        for i in range(sets):
            target = chr(i + 1)
            setid = int(i / (sets / self._num_targets))
            if setid not in self._buckets:
                self._buckets[setid] = tempfile.NamedTemporaryFile('wb+', 10240)
            self._sort_map[target] = self._buckets[setid]

    def write(self, data):
        """ save content, send reverse sorted to buffers
            :param data: line to write
        """
        line = data.strip().lower()
        if len(line) > 0:
            # Calculate sort key, which is the reversed url with dots (.) replaced by spaces.
            # We need to replace dots (.) here to avoid having a wrong sorting order when dashes
            # or similar characters are used inside the url.
            # (The process writing out the domains checks for domain overlaps)
            sort_key = line[::-1].replace('.', ' ')
            self.add(sort_key, line)

    def add(self, key, value):
        """ spool data to temp
            :param key: key to use
            :param value: value to store
        """
        target = key[0]
        if target in self._sort_map:
            for part in (key, self._separator, value, '\n'):
                self._sort_map[target].write(part.encode('utf-8'))
        else:
            # not supposed to happen, every key should have a calculated target pool
            pass

    def reader(self):
        """ read reverse
        """
        for target in sorted(self._buckets):
            self._buckets[target].seek(0)
            set_content = dict()
            while True:
                line = self._buckets[target].readline().decode()
                if not line:
                    break
                else:
                    set_content[line.split('|')[0]] = '|'.join(line.split('|')[1:])
            for itemkey in sorted(set_content, reverse=True):
                yield set_content[itemkey]

    @staticmethod
    def is_domain(tag):
        """ check if tag is probably a domain name
            :param tag: tag to inspect
            :return: boolean
        """
        has_chars = False
        for tag_item in tag:
            if not tag_item.isdigit() and tag_item not in ('.', ',', '|', '/', '\n'):
                has_chars = True
            elif tag_item in (':', '|', '/'):
                return False
        if has_chars:
            return True
        else:
            return False

    def close(self):
        """ close and dump content
        """
        if self._target_filename is not None:
            # flush to file on close
            with open(self._target_filename, 'wb', buffering=10240) as f_out:
                prev_line = None
                for line in self.reader():
                    line = line.lstrip('.')
                    if prev_line == line:
                        # duplicate, skip
                        continue
                    if self.is_domain(line):
                        # prefix domain, if this domain is different then the previous one
                        if prev_line is None or '.%s' % line not in prev_line:
                            f_out.write(b'.')
                    f_out.write(line.encode())
                    prev_line = line


def filename_in_ignorelist(bfilename, filename_ext):
    """ ignore certain files from processing.
        :param bfilename: basefilename to inspect
        :param filename_ext: extension of the filename
    """
    if filename_ext in ['pdf', 'txt', 'doc']:
        return True
    elif bfilename in ('readme', 'license', 'usage', 'categories'):
        return True
    return False


def main():
    # parse OPNsense external ACLs config
    if os.path.exists(acl_config_fn):
        # create acl directory (if new)
        if not os.path.exists(acl_target_dir):
            os.mkdir(acl_target_dir)
        else:
            # remove index files
            for filename in glob.glob('%s/*.index' % acl_target_dir):
                os.remove(filename)
        # read config and download per section
        cnf = ConfigParser()
        cnf.read(acl_config_fn)
        for section in cnf.sections():
            target_filename = acl_target_dir + '/' + section
            if cnf.has_option(section, 'url'):
                # collect filters to apply
                acl_filters = list()
                if cnf.has_option(section, 'filter'):
                    for acl_filter in cnf.get(section, 'filter').strip().split(','):
                        if len(acl_filter.strip()) > 0:
                            acl_filters.append(acl_filter)

                # define target(s)
                targets = {'domain': {'filename': target_filename, 'handle': None, 'class': DomainSorter}}

                # only generate files if enabled, otherwise dump empty files
                if cnf.has_option(section, 'enabled') and cnf.get(section, 'enabled') == '1':
                    download_url = cnf.get(section, 'url')
                    if cnf.has_option(section, 'username'):
                        download_username = cnf.get(section, 'username')
                        download_password = cnf.get(section, 'password')
                    else:
                        download_username = None
                        download_password = None
                    if cnf.has_option(section, 'sslNoVerify') and cnf.get(section, 'sslNoVerify') == '1':
                        sslNoVerify = True
                    else:
                        sslNoVerify = False
                    acl = Downloader(download_url, download_username, download_password, acl_max_timeout, sslNoVerify)
                    all_filenames = list()
                    for filename, basefilename, file_ext, line in acl.download():
                        if filename_in_ignorelist(basefilename, file_ext):
                            # ignore documents, licenses and readme's
                            continue

                        # detect output type
                        if '/' in line or '|' in line:
                            filetype = 'url'
                        elif line.startswith('#'):
                            filetype = 'comment'
                        else:
                            filetype = 'domain'

                        if filename not in all_filenames:
                            all_filenames.append(filename)

                        if len(acl_filters) > 0:
                            acl_found = False
                            for acl_filter in acl_filters:
                                if acl_filter in filename:
                                    acl_found = True
                                    break
                            if not acl_found:
                                # skip this acl entry
                                continue

                        if filetype in targets and targets[filetype]['handle'] is None:
                            targets[filetype]['handle'] = targets[filetype]['class'](targets[filetype]['filename'])
                        if filetype in targets:
                            targets[filetype]['handle'].write(line)
                            targets[filetype]['handle'].write('\n')
                    # save index to disc
                    with open('%s.index' % target_filename, 'w', buffering=10240) as idx_out:
                        index_data = dict()
                        for filename in all_filenames:
                            if len(filename.split('/')) > 2:
                                index_key = '/'.join(filename.split('/')[1:-1])
                                if index_key not in index_data:
                                    index_data[index_key] = index_key
                        idx_out.write(json.dumps(index_data))

                # cleanup
                for filetype in targets:
                    if targets[filetype]['handle'] is not None:
                        targets[filetype]['handle'].close()
                    elif cnf.has_option(section, 'enabled') and cnf.get(section, 'enabled') != '1':
                        if os.path.isfile(targets[filetype]['filename']):
                            # disabled, remove previous data
                            os.remove(targets[filetype]['filename'])
                    elif not os.path.isfile(targets[filetype]['filename']):
                        # no data fetched and no file available, create new empty file
                        with open(targets[filetype]['filename'], 'w') as target_out:
                            target_out.write("")


# execute downloader
main()
