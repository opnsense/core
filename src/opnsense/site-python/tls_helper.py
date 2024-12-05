"""
    Copyright (c) 2024 Ad Schellevis <ad@opnsense.org>
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

import os
import requests
import ssl
from requests.adapters import HTTPAdapter
from requests.packages.urllib3.util.ssl_ import create_urllib3_context
from collections import OrderedDict
from configparser import ConfigParser


class IniDict(OrderedDict):
    def __setitem__(self, key, value):
        if isinstance(value, list) and key in self:
            self[key].extend(value)
        else:
            super().__setitem__(key, value)


class PlatformTLSAdaptor(HTTPAdapter):
    ssl_context = None
    @classmethod
    def get_sslcontext(cls):
        if cls.ssl_context is None:
            openssl_conf = {
                'cipherstring' : None,
                'minprotocol': None,
                # XXX: Curves (very) limited supported
                'groups' : None,
                # XXX: not supported, but openssl.cfg settings do seem to apply here.
                'signaturealgorithms' : None,
                # XXX:  TLS1.3 modifications not supported, openssl.cnf defaults should apply
                #       https://docs.python.org/3/library/ssl.html#ssl.SSLContext.set_ciphers
                'ciphersuites' : None,
            }
            conf_file = '/usr/local/openssl/openssl.cnf'
            conf_section = 'system_default_sect'
            if os.path.isfile(conf_file):
                cnf = ConfigParser(strict=False, dict_type=IniDict)
                data = "[openssl]\n%s" % open(conf_file, 'r').read()
                cnf.read_string(data)
                if cnf.has_section(conf_section):
                    for option in cnf.options(conf_section):
                        if option in openssl_conf:
                            openssl_conf[option] = cnf.get(conf_section, option)

            ctx_args = {}
            if openssl_conf['cipherstring']:
                ctx_args['ciphers'] = openssl_conf['cipherstring']

            cls.ssl_context = create_urllib3_context(**ctx_args)
            cls.ssl_context.load_verify_locations('/usr/local/etc/ssl/cert.pem')
            if openssl_conf['minprotocol']:
                for item in openssl_conf['minprotocol'].split("\n"):
                    if item == 'TLSv1':
                        cls.ssl_context.minimum_version = ssl.TLSVersion.TLSv1
                    elif item == 'TLSv1.1':
                        cls.ssl_context.minimum_version = ssl.TLSVersion.TLSv1_1
                    elif item == 'TLSv1.2':
                        cls.ssl_context.minimum_version = ssl.TLSVersion.TLSv1_2
                    elif item == 'TLSv1.3':
                        cls.ssl_context.minimum_version = ssl.TLSVersion.TLSv1_3
            if openssl_conf['groups']:
                # XXX: We can only select a single curve here instead of a group
                #      although this is very flaky, we should choose one to prevent accepting ffdhe2048
                #      which is enabled by default in openssl.
                cls.ssl_context.set_ecdh_curve(openssl_conf['groups'].split(':')[0])

        return cls.ssl_context

    def init_poolmanager(self, *args, **kwargs):
        kwargs['ssl_context'] = self.get_sslcontext()
        return super().init_poolmanager(*args, **kwargs)
    def proxy_manager_for(self, *args, **kwargs):
        kwargs['ssl_context'] = self.get_sslcontext()
        return super().proxy_manager_for(*args, **kwargs)

class RequestsWrapper:
    def get(self, *args, **kwargs):
        s = requests.Session()
        s.mount('https://', PlatformTLSAdaptor())
        return s.get(*args, **kwargs)

    def post(self, *args, **kwargs):
        s = requests.Session()
        s.mount('https://', PlatformTLSAdaptor())
        return s.post(*args, **kwargs)
