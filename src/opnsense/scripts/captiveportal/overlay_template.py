#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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
    overlay user web template package on installed default template, default template should be installed
    in /var/captiveportal/zone<zoneid>/htdocs/ first.
"""
import os
import sys
import zipfile
import base64
from io import BytesIO
from lib import Config

if len(sys.argv) > 1:
    cnf = Config()
    zoneid = sys.argv[1]
    target_directory = '/var/captiveportal/zone%s/htdocs/' % zoneid
    template_data = cnf.fetch_template_data(zoneid)
    if template_data is not None and len(template_data) > 20:
        print ('overlay user template package for zone %s' % zoneid)
        zip_content = base64.b64decode(template_data)
        input_data = BytesIO(zip_content)
        with zipfile.ZipFile(input_data, mode='r', compression=zipfile.ZIP_DEFLATED) as zf_in:
            for zf_info in zf_in.infolist():
                target_filename = '%s%s' % (target_directory, zf_info.filename)
                if os.path.realpath(target_filename).startswith(target_directory):
                    file_target_directory = '/'.join(target_filename.split('/')[:-1])
                    if not os.path.isdir(file_target_directory):
                        os.makedirs(file_target_directory)
                    with open(target_filename, 'wb') as f_out:
                        f_out.write(zf_in.read(zf_info.filename))
                    os.chmod(target_filename, 0o444)

sys.exit(0)
