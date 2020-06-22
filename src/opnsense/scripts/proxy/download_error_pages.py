#!/usr/local/bin/python3

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
import base64
import ujson
import os
import re
import zipfile
from io import BytesIO
from lib import ProxyTemplates

if __name__ == '__main__':
    root_dir = "/proxy_template"
    proxy_templates = ProxyTemplates()
    output_data = BytesIO()
    processed = list()
    with zipfile.ZipFile(output_data, mode='w', compression=zipfile.ZIP_DEFLATED) as zf:
        for filename, data in proxy_templates.templates(True):
            zf.writestr("%s/%s" % (root_dir, filename), data)
            for dep_filename in proxy_templates.css_dependencies(filename, True):
                if dep_filename not in processed:
                    zf.writestr("%s/%s" % (root_dir, dep_filename), proxy_templates.get_file(dep_filename, True))
                    processed.append(dep_filename)

    response = dict()
    response['payload'] = base64.b64encode(output_data.getvalue()).decode()
    response['size'] = len(response['payload'])
    print(ujson.dumps(response))
