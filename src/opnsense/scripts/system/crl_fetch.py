#!/usr/local/bin/python3
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
    --------------------------------------------------------------------------------------------------------------
    Simple CRL Distributionpoint downloader using the CA's configured in the central trust store
    Script returns exit status 0 when nothing has changed, 1 when changes have been made so a rehash can be scheduled
"""
import glob
import hashlib
import urllib
import os
import subprocess
import ldap3
import requests
import sys
from cryptography import x509
from cryptography.hazmat.primitives import serialization
from cryptography.x509.extensions import CRLDistributionPoints

TRUSTPATH = ['/usr/share/certs/trusted', '/usr/local/share/certs']

def fetch_crl(uri):
    p = urllib.parse.urlparse(uri)
    payload = None
    if p.scheme.lower() == 'ldap':
        server = ldap3.Server(p.netloc)
        conn = ldap3.Connection(server, auto_bind=True)
        conn.search(
            search_base=urllib.parse.unquote(p.path.lstrip('/')),
            search_filter='(objectClass=*)',
            search_scope=ldap3.SUBTREE,
            attributes=[p.query]
        )
        for entry in conn.entries:
            for key, value in entry.entry_attributes_as_dict.items():
                if value and key.split(';')[0].lower() == 'certificaterevocationlist':
                    payload = value[0]
                    break
    elif p.scheme.lower() == 'http':
        r = requests.get(uri)
        if r.status_code >= 200 and r.status_code < 300:
            payload = r.content
    else:
        raise Exception('unsupported scheme %s in uri %s' % (p.scheme, uri))

    if not payload:
        raise Exception('Empty or no response received from uri %s' % uri)

    try:
        crl = x509.load_der_x509_crl(payload)
    except ValueError:
        try :
            crl = x509.load_pem_x509_crl(payload)
        except ValueError:
            raise Exception("Invalid CRL received from %s" % uri)

    return {
        'next_update_utc': crl.next_update_utc,
        'pem': crl.public_bytes(serialization.Encoding.PEM).decode()
    }


def main():
    changes = 0
    output_pattern = '/usr/local/share/certs/ca-crl-upd-opn-%s.crl'
    crl_files = []
    dp_uri = ''
    for path in TRUSTPATH:
        for filename in glob.glob('%s/*[.pem|.crt]' % path):
            try:
                cert = x509.load_pem_x509_certificate(open(filename, 'rb').read())
                for ext in cert.extensions:
                    if type(ext.value) is CRLDistributionPoints:
                        for Distributionpoint in ext.value:
                            dp_uri = Distributionpoint.full_name[0].value
                            target_filename = output_pattern % hashlib.sha256(dp_uri.encode()).hexdigest()
                            this_crl = fetch_crl(dp_uri)
                            crl_files.append(target_filename)
                            # use local trust store to validate if the received CRL is valid
                            sp = subprocess.run(
                                ['/usr/local/bin/openssl', 'crl', '-verify'],
                                input=this_crl['pem'],
                                capture_output=True,
                                text=True
                            )
                            if sp.stderr.strip() == 'verify OK':
                                if os.path.isfile(target_filename):
                                    if open(target_filename).read() == this_crl['pem']:
                                        print('[-] skip unchanged crl from %s' % dp_uri)
                                        continue
                                with open(target_filename, 'w') as f_out:
                                    print('[+] store crl from %s' % dp_uri)
                                    f_out.write(this_crl['pem'])
                                    changes += 1
                            else:
                                print('[-] skip crl from %s (%s)' % (dp_uri, sp.stderr.strip()))
            except Exception as e:
                # error handling
                print('[-] error processing %s [%s]' % (dp_uri, e))

    # cleanup unused CRLs within our responsible scope
    for filename in glob.glob(output_pattern % '*'):
        if filename not in crl_files:
            os.unlink(filename)
            changes += 1

    return changes


if __name__ == '__main__':
    sys.exit(0 if main() == 0 else 1)
