#!/usr/local/bin/python2.7

"""
    Copyright (c) 2016-2017 Ad Schellevis <ad@opnsense.org>
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
    download maxmind GeoLite2 Free database into easy to use alias files [<COUNTRY>-<PROTO>] located
    in /usr/local/share/GeoIP/alias
"""
import tempfile
import subprocess
import os
import sys
import ujson
import requests
import zipfile

def download_geolite():
    # define geoip download location
    url = 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country-CSV.zip'

    address_count = 0
    file_count = 0
    # flush data from remote url to temp file and unpack from there
    with tempfile.NamedTemporaryFile() as tmp_stream:
        r = requests.get(url)
        if r.status_code == 200:
            tmp_stream.write(r.content)
            tmp_stream.seek(0)
            with zipfile.ZipFile(tmp_stream, mode='r', compression=zipfile.ZIP_DEFLATED) as zf:
                # fetch zip file contents
                file_handles = dict()
                for item in zf.infolist():
                    if item.file_size > 0:
                        file_handles[os.path.basename(item.filename)] = item
                # only process geo ip data when archive contains country definitions
                if 'GeoLite2-Country-Locations-en.csv' in file_handles:
                    country_codes = dict()
                    # parse geoname_id to country code map
                    for line in zf.open(file_handles['GeoLite2-Country-Locations-en.csv']).read().split('\n'):
                        parts = line.split(',')
                        if len(parts) > 4 and len(parts[4]) >= 1 and len(parts[4]) <= 3:
                            country_codes[parts[0]] = parts[4]
                    # process all details into files per country / protocol
                    for proto in ['IPv4', 'IPv6']:
                        if 'GeoLite2-Country-Blocks-%s.csv' % proto in file_handles:
                            output_handles = dict()
                            for line in zf.open(file_handles['GeoLite2-Country-Blocks-%s.csv' % proto]).read().split('\n'):
                                parts = line.split(',')
                                if len(parts) > 3 and parts[1] in country_codes:
                                    country_code = country_codes[parts[1]]
                                    if country_code not in output_handles:
                                        if not os.path.exists('/usr/local/share/GeoIP/alias'):
                                            os.makedirs('/usr/local/share/GeoIP/alias')
                                        output_handles[country_code] = open(
                                            '/usr/local/share/GeoIP/alias/%s-%s'%(country_code,proto), 'w'
                                        )
                                        file_count += 1
                                    output_handles[country_code].write("%s\n" % parts[0])
                                    address_count += 1
                            for country_code in output_handles:
                                output_handles[country_code].close()

            return (file_count, address_count)
