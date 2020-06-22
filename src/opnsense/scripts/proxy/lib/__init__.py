"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
import ujson
import os
import base64
import binascii
import zipfile
import glob
from io import BytesIO

class ProxyTemplates:
    error_config = "/usr/local/etc/squid/error_directory.in"

    def __init__(self):
        self._all_src_files = dict()
        self._all_ovl_files = dict()
        self._overlay_status = None
        self._install_overlay = False
        self._overlay_data = None
        self._load_config()
        self.load()

    def _load_config(self):
        if os.path.isfile(self.error_config):
            error_cfg = ujson.loads(open(self.error_config, 'rb').read())
            self._install_overlay = 'install' not in error_cfg or error_cfg['install'] != 'opnsense'
            self._overlay_data = error_cfg['content'] if 'content' in error_cfg else None

    def load(self):
        self._overlay_status = None
        self._all_src_files = dict()
        self._all_ovl_files = dict()
        # base (OPNsense) template
        for filename in glob.glob("/usr/local/opnsense/data/proxy/template_error_pages/*"):
            bfilename = os.path.basename(filename)
            with open(filename, "rb") as f_in:
                self._all_src_files[bfilename] = f_in.read()

        # when a (valid) overlay is provided, read it's contents
        if self._overlay_data and self._install_overlay:
            try:
                input_data = BytesIO(base64.b64decode(self._overlay_data))
                root_dir = ""
                with zipfile.ZipFile(input_data, mode='r', compression=zipfile.ZIP_DEFLATED) as zf_in:
                    for zf_info in zf_in.infolist():
                        if not root_dir and zf_info.filename.endswith('/'):
                            root_dir = zf_info.filename
                        else:
                            self._all_ovl_files[zf_info.filename.replace(root_dir, "")] = zf_in.read(zf_info.filename)
            except binascii.Error:
                self._overlay_status = 'Not a base64 encoded file'
            except zipfile.BadZipFile:
                self._overlay_status = 'Illegal zip file'
            except IOError:
                self._overlay_status = 'Error reading file'

    def templates(self, overlay=False):
        for filename in self._all_src_files:
            if overlay and filename in self._all_ovl_files:
                yield filename, self._all_ovl_files[filename]
            else:
                yield filename, self._all_src_files[filename]

    def get_file(self, filename, overlay=False):
        if filename in self._all_src_files:
            if overlay and filename in self._all_ovl_files:
                return self._all_ovl_files[filename]
            else:
                return self._all_src_files[filename]

    def overlay_enabled(self):
        return self._install_overlay

    def get_overlay_status(self):
        return self._overlay_status
