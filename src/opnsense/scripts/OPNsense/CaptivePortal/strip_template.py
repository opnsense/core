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
    read a user provided base64 encoded zip file and generate a new one without standard
    files. (strips javascript etc)
    Filename should be provided by user parameter and must be available in /tmp/
"""
import sys
import os.path
import binascii
import zipfile
import ujson
import base64
from io import BytesIO
from hashlib import md5

htdocs_default_root = '/usr/local/opnsense/scripts/OPNsense/CaptivePortal/htdocs_default'


def load_exclude_list():
    """ load exclude list, files that should be removed from the input stream
    """
    result = []
    excl_filename = '%s/exclude.list' % htdocs_default_root
    for line in open(excl_filename, 'r').read().split('\n'):
        line = line.strip()
        if len(line) > 1 and line[0] != '#':
            result.append(line)
    return result


response = dict()
zip_content = None
if len(sys.argv) < 2:
    response['error'] = 'Filename parameter missing'
else:
    input_filename = '/tmp/%s' % os.path.basename(sys.argv[1])
    try:
        zip_content = base64.b64decode(open(input_filename, 'rb').read())
    except binascii.Error:
        # not in base64
        response['error'] = 'Not a base64 encoded file'
    except IOError:
        # file npt found
        response['error'] = 'Error reading file'

if 'error' not in response:
    exclude_list = load_exclude_list()
    input_data = BytesIO(zip_content)
    output_data = BytesIO()
    with zipfile.ZipFile(input_data, mode='r', compression=zipfile.ZIP_DEFLATED) as zf_in:
        with zipfile.ZipFile(output_data, mode='w', compression=zipfile.ZIP_DEFLATED) as zf_out:
            # the zip content may be in a folder, use index to track actual location
            index_location = None
            for zf_info in zf_in.infolist():
                if zf_info.filename.find('index.html') > -1:
                    if index_location is None or len(index_location) > len(zf_info.filename):
                        index_location = zf_info.filename
            if index_location is not None:
                for zf_info in zf_in.infolist():
                    if zf_info.filename[-1] != '/':
                        filename = zf_info.filename.replace(index_location.replace('index.html', ''), '')
                        # ignore internal osx metadata files, maybe we need to ignore some others (windows?) as well
                        # here.
                        if filename.split('/')[0] == '__MACOSX' or filename.split('/')[-1] == '.DS_Store':
                            continue
                        if filename not in exclude_list:
                            file_data = zf_in.read(zf_info.filename)
                            src_filename = '%s/%s' % (htdocs_default_root, filename)
                            if os.path.isfile(src_filename):
                                md5_src = md5(open(src_filename, 'rb').read()).hexdigest()
                                md5_new = md5(file_data).hexdigest()
                                if md5_src != md5_new:
                                    # changed file
                                    zf_out.writestr(filename, file_data)
                            else:
                                # new file, always write
                                zf_out.writestr(filename, file_data)
        if 'error' not in response:
            response['payload'] = base64.b64encode(output_data.getvalue()).decode().replace('\n', '')
            response['size'] = len(response['payload'])

print(ujson.dumps(response))
