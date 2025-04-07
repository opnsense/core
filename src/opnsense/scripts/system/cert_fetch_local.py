#!/usr/local/bin/python3
"""
    Copyright (c) 2025 Ad Schellevis <ad@opnsense.org>
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

    ---------------------------------------------------------------------------------------------------
    fetch a pluggable set of locally managed certificates which are not available in the configuration
"""
import glob
import hashlib
import os
import re
import ujson
from configparser import ConfigParser


if __name__ == '__main__':
    result = []
    for conffile in glob.glob("/usr/local/etc/ssl/ext_sources/*.conf"):
        cnf = ConfigParser()
        cnf.read(conffile)
        if cnf.has_section('location') and cnf.has_option('location', 'base') and cnf.has_option('location', 'pattern'):
            if cnf.has_option('location', 'description'):
                loc_descr = cnf.get('location', 'description')
            else:
                loc_descr = os.path.basename(conffile)[0:-5]
            match_pattern = re.compile(cnf.get('location', 'pattern'))
            for root, dirs, files in os.walk(cnf.get('location', 'base')):
                for filename in files:
                    full_path = "%s/%s" % (root, filename)
                    if match_pattern.match(filename) and os.path.getsize(full_path) < 1024*1024:
                        payload = open(full_path).read()
                        result.append({
                            'cert': payload,
                            'descr': loc_descr,
                            'id': hashlib.md5(payload.encode()).hexdigest()
                        })
    print(ujson.dumps(result))
