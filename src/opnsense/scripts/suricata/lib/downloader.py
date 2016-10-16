"""
    Copyright (c) 2015 Ad Schellevis
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
import shutil
import tempfile
import requests


class Downloader(object):
    def __init__(self, target_dir):
        self._target_dir = target_dir

    def filter(self, in_data, filter_type):
        """ apply input filter to downloaded data
            :param in_data: raw input data (ruleset)
            :param filter_type: filter type to use on input data
            :return: ruleset data
        """
        if filter_type == "drop":
            return self.filter_drop(in_data)
        else:
            return in_data

    def filter_drop(self, in_data):
        """ change all alert rules to block
            :param in_data: raw input data (ruleset)
            :return: new ruleset
        """
        output = list()
        for line in in_data.split('\n'):
            if len(line) > 10:
                if line[0:5] == 'alert':
                    line = 'drop %s' % line[5:]
                elif line[0:6] == '#alert':
                    line = '#drop %s' % line[5:]
            output.append(line)
        return '\n'.join(output)

    @staticmethod
    def _unpack(req_obj, source_url):
        source_url = source_url.strip().lower()
        unpack_type=None
        if source_url.endswith('.tar.gz') or source_url.endswith('.tgz'):
            unpack_type = 'tar'
        elif source_url.endswith('.gz'):
            unpack_type = 'gz'
        elif source_url.endswith('.zip'):
            unpack_type = 'zip'

        if unpack_type is not None:
            rule_content = list()
            # flush to temp
            src = tempfile.NamedTemporaryFile()
            shutil.copyfileobj(req_obj.raw, src)
            src.seek(0)
            # handle compression types
            if unpack_type == 'tar':
                tf = tarfile.open(fileobj=src)
                for tf_file in tf.getmembers():
                    if tf_file.isfile() and tf_file.name.lower().endswith('.rules'):
                        rule_content.append(tf.extractfile(tf_file).read())
            elif unpack_type == 'gz':
                gf = gzip.GzipFile(mode='r', fileobj=src)
                rule_content.append(gf.read())
            elif unpack_type == 'zip':
                with zipfile.ZipFile(src, mode='r', compression=zipfile.ZIP_DEFLATED) as zf:
                    for item in zf.infolist():
                        if item.file_size > 0 and item.filename.lower().endswith('.rules'):
                            rule_content.append(zf.open(item).read())
            return '\n'.join(rule_content)
        else:
            return req_obj.text

    def download(self, proto, url, filename, input_filter):
        """ download ruleset file
            :param proto: protocol (http,https)
            :param url: download url
            :param filename: target filename
            :param input_filter: filter to use on received data before save
        """
        if proto in ('http', 'https'):
            frm_url = url.replace('//', '/').replace(':/', '://')
            req = requests.get(url=frm_url, stream=True)
            if req.status_code == 200:
                try:
                    target_filename = '%s/%s' % (self._target_dir, filename)
                    save_data = self._unpack(req, url)
                    save_data = self.filter(save_data, input_filter)
                    open(target_filename, 'wb').write(save_data)
                except IOError:
                    syslog.syslog(syslog.LOG_ERR, 'cannot write to %s' % target_filename)
                    return None
                syslog.syslog(syslog.LOG_INFO, 'download completed for %s' % frm_url)
            else:
                syslog.syslog(syslog.LOG_ERR, 'download failed for %s' % frm_url)

    @staticmethod
    def is_supported(proto):
        """ check if protocol is supported
        :param proto:
        :return:
        """
        if proto in ['http', 'https']:
            return True
        else:
            return False
