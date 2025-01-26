
"""
    Copyright (c) 2016-2023 Ad Schellevis <ad@opnsense.org>
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
import re
import syslog
import requests
import urllib3
import ujson
from .base import BaseContentParser
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)


class UriParser(BaseContentParser):

    def __init__(self, timeout=120, ssl_no_verify=False, authtype=None, username=None, password=None, **kwargs):
        super().__init__(**kwargs)
        self._timeout = timeout
        self._ssl_no_verify = ssl_no_verify
        self._authtype = authtype
        self._username = username
        self._password = password
        self._type = kwargs.get('type', None)
        # optional path expresion
        if kwargs.get('path_expression', None):
            self._path_expression = kwargs['path_expression'].split('.')
        else:
            self._path_expression = []

    def _parse_line(self, line):
        """ return unparsed (raw) alias entries without dependencies
            :param line: string item to parse
            :return: iterator
        """
        if self._type == 'urljson':
            try:
                record = ujson.loads(line)
            except ValueError:
                record = {}
            for field in self._path_expression:
                if type(record) is dict and field in record:
                    record = record[field]
                else:
                    return
            if type(record) is str:
                yield record
            elif type(record) is list:
                for item in record:
                    yield item
        else:
            raw_address = re.split(r'[\s,;|#]+', line)[0]
            if raw_address and not raw_address.startswith('//'):
                yield raw_address

    def iter_addresses(self, url):
        """ parse addresses, yield only valid addresses and networks
            :param url: url
            :return: iterator
        """
        # set request parameters
        req_opts = {'url': url, 'stream': True, 'timeout': self._timeout}
        if self._ssl_no_verify:
            req_opts['verify'] = False

        if self._authtype is not None and self._password is not None:
            if self._authtype == 'Basic' and self._username is not None:
                req_opts['auth'] = requests.auth.HTTPBasicAuth(self._username, self._password)
            elif self._authtype == 'Bearer':
                req_opts['headers'] = {'Authorization': f'Bearer {self._password}'}

        # fetch data
        try:
            req = requests.get(**req_opts)
            if req.status_code == 200:
                # only handle content if response is correct
                req.raw.decode_content = True
                lines = req.raw.read().decode().splitlines()
                syslog.syslog(syslog.LOG_NOTICE, 'fetch alias url %s (lines: %s)' % (url, len(lines)))
                for line in lines:
                    for raw_address in self._parse_line(line):
                        for address in super().iter_addresses(raw_address):
                            yield address
            else:
                syslog.syslog(syslog.LOG_ERR, 'error fetching alias url %s [http_code:%s]' % (url, req.status_code))
                raise IOError('error fetching alias url %s' % (url))
        except:
            syslog.syslog(syslog.LOG_ERR, 'error fetching alias url %s' % (url))
            raise IOError('error fetching alias url %s' % (url))
