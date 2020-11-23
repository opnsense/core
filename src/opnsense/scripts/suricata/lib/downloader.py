"""
    Copyright (c) 2015-2018 Ad Schellevis <ad@opnsense.org>
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

    rule downloader module (may need to be extended in the future, this version only processes http(s))
"""

import syslog
import tarfile
import gzip
import zipfile
import tempfile
import requests
import json
import hashlib
import os
import re


class Downloader(object):
    def __init__(self, target_dir):
        self._target_dir = target_dir
        self._download_cache = dict()


    @staticmethod
    def _unpack(src, source_filename, filename=None):
        """ unpack data if archived
            :param src: handle to temp file
            :param source_filename: original source filename
            :param filename: filename to extract
            :return: string
        """
        src.seek(0)
        unpack_type=None
        if source_filename.endswith('.tar.gz') or source_filename.endswith('.tgz'):
            unpack_type = 'tar'
        elif source_filename.endswith('.gz'):
            unpack_type = 'gz'
        elif source_filename.endswith('.zip'):
            unpack_type = 'zip'

        if unpack_type is not None:
            rule_content = list()
            # handle compression types
            if unpack_type == 'tar':
                tf = tarfile.open(fileobj=src)
                for tf_file in tf.getmembers():
                    # extract partial or all (*.rules) from archive
                    if filename is not None and tf_file.name == filename:
                        rule_content.append(tf.extractfile(tf_file).read())
                    elif filename is None and tf_file.isfile() and tf_file.name.lower().endswith('.rules'):
                        rule_content.append(tf.extractfile(tf_file).read())
            elif unpack_type == 'gz':
                gf = gzip.GzipFile(mode='r', fileobj=src)
                rule_content.append(gf.read())
            elif unpack_type == 'zip':
                with zipfile.ZipFile(src, mode='r', compression=zipfile.ZIP_DEFLATED) as zf:
                    for item in zf.infolist():
                        if filename is not None and item.filename == filename:
                            rule_content.append(zf.open(item).read())
                        elif filename is None and item.file_size > 0 and item.filename.lower().endswith('.rules'):
                            rule_content.append(zf.open(item).read())
            return '\n'.join([x.decode() for x in rule_content])
        else:
            return src.read().decode()

    def fetch(self, url, auth=None, headers=None):
        """ Fetch file from remote location and save to temp, return filehandle pointed to start of temp file.
            Results are cached, which prevents downloading the same archive twice for example.
            :param url: download url
            :param auth: authentication
            :param headers: headers to send
            :return: dict {'handle': filehandle, 'filename': filename}
        """
        if str(url).split(':')[0].lower() in ('http', 'https'):
            frm_url = url.replace('//', '/').replace(':/', '://')
            # stream to temp file
            if frm_url not in self._download_cache:
                req_opts = dict()
                req_opts['url'] = frm_url
                req_opts['stream'] = True
                if auth is not None:
                    req_opts['auth'] = auth
                if headers is not None:
                    req_opts['headers'] = headers
                req = requests.get(**req_opts)
                if 'content-disposition' not in req.headers \
                        or req.headers['content-disposition'].find('filename=') == -1:
                    filename = url.strip().lower().split('?')[0]
                else:
                    filename = re.findall('filename=(.+)', req.headers['content-disposition'])[0].strip('"')

                if req.status_code == 200:
                    req.raw.decode_content = True
                    src = tempfile.NamedTemporaryFile('wb+', 10240)
                    while True:
                        data = req.raw.read(10240)
                        if not data:
                            break
                        else:
                             src.write(data)
                    self._download_cache[frm_url] = {'handle': src, 'filename': filename, 'cached': False}
                else:
                    syslog.syslog(syslog.LOG_ERR, 'download failed for %s (http_code: %d)' % (url, req.status_code))
            else:
                self._download_cache[frm_url]['cached'] = True
        else:
            syslog.syslog(syslog.LOG_ERR, 'unsupported download type for %s' % (url))

        if frm_url in self._download_cache:
            self._download_cache[frm_url]['handle'].seek(0)
            return self._download_cache[frm_url]
        else:
            return None

    def fetch_version_hash(self, check_url, auth=None, headers=None):
        """ Calculate a hash value using the download settings and a predefined version url (check_url).
            :param check_url: download url, version identifier
            :param auth: authentication
            :param headers: headers to send
            :return: None or hash
        """
        if check_url is not None:
            # when no check url provided, assume different
            if self.is_supported(check_url):
                version_fetch = self.fetch(url=check_url, auth=auth, headers=headers)
                if version_fetch:
                    version_response = version_fetch['handle'].read().decode()
                    hash_value = [json.dumps(auth), json.dumps(headers), version_response]
                    if not version_fetch['cached']:
                        syslog.syslog(syslog.LOG_NOTICE, 'version response for %s : %s' % (check_url, version_response))
                    return hashlib.md5(('\n'.join(hash_value)).encode()).hexdigest()
        return None

    def installed_file_hash(self, filename):
        """ Fetch file version hash from header
            :param filename: target filename
            :return: None or hash
        """
        target_filename = '%s/%s' % (self._target_dir, filename)
        if os.path.isfile(target_filename):
            with open(target_filename, 'r') as f_in:
                line = f_in.readline()
                if line.find("#@opnsense_download_hash:") == 0:
                    return line.split(':')[1].strip()
        return None

    def download(self, url, url_filename, filename, auth=None, headers=None, version=None):
        """ download ruleset file
            :param url: download url
            :param url_filename: if provided the filename within the (packet) resource
            :param filename: target filename
            :param auth: authentication
            :param headers: headers to send
            :param version: version hash
        """
        frm_url = url.replace('//', '/').replace(':/', '://')
        fetch_result = self.fetch(url=url, auth=auth, headers=headers)
        if fetch_result is not None:
            try:
                target_filename = '%s/%s' % (self._target_dir, filename)
                if version:
                    save_data = "#@opnsense_download_hash:%s\n" % version
                else:
                    save_data = ""
                save_data += self._unpack(
                    src=fetch_result['handle'], source_filename=fetch_result['filename'],
                    filename=url_filename
                )
                open(target_filename, 'w', buffering=10240).write(save_data)
            except IOError:
                syslog.syslog(syslog.LOG_ERR, 'cannot write to %s' % target_filename)
                return None
            except UnicodeDecodeError:
                syslog.syslog(syslog.LOG_ERR, 'unable to read %s from %s (decode error)' % (
                        target_filename, fetch_result['filename']
                ))
                return None
            if not fetch_result['cached']:
                syslog.syslog(syslog.LOG_NOTICE, 'download completed for %s' % frm_url)

    @staticmethod
    def is_supported(url):
        """ check if protocol is supported
        :param url: uri to request resource from
        :return: bool
        """
        if str(url).split(':')[0].lower() in ['http', 'https']:
            return True
        else:
            return False
