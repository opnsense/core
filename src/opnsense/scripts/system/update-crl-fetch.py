#!/usr/local/bin/python3
"""
    Copyright (c) 2023-2024 Ad Schellevis <ad@opnsense.org>
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

import argparse
import glob
import ipaddress
import sys
import os
import time
sys.path.insert(0, "/usr/local/opnsense/site-python")
import tls_helper
from cryptography import x509
from cryptography.hazmat.primitives import serialization
from cryptography.x509.extensions import CRLDistributionPoints


def fetch_certs(domains):
    result = []
    for domain in domains:
        depth = 0
        try:
            ipaddress.ip_address(domain)
        except ValueError:
            pass
        else:
            print('[!!] refusing to fetch from ip address %s' % domain, file=sys.stderr)
            continue
        url = 'https://%s' % domain
        try:
            print('# [i] fetch certificate for %s' % url)
            with tls_helper.RequestsWrapper().get(url, timeout=30, stream=True) as response:
                # XXX: in python > 3.13, replace with sock.get_verified_chain()
                for cert in response.raw.connection.sock._sslobj.get_verified_chain():
                    result.append({'domain': domain, 'depth': depth, 'pem': cert.public_bytes(1).encode()}) # _ssl.ENCODING_PEM
                    depth += 1
        except Exception as e:
            # XXX: probably too broad, but better make sure
            print("[!!] Chain fetch failed for %s (%s)" % (url, e), file=sys.stderr)

    return result


def main(domains, target, lifetime):
    crl_index = target + 'index'
    crl_bundle = []

    domains = sorted(set(domains))
    current = ",".join(domains)

    # assume we run under a firmware lock
    if os.path.isfile(crl_index):
        crl_stale = False

        with open(crl_index, "r") as idx:
            if idx.readline().strip('\n') != current:
                crl_stale = True

        fstat = os.stat(crl_index)
        if (time.time() - fstat.st_mtime) >= lifetime and fstat.st_size > 0:
            crl_stale = True

        if not crl_stale:
            # failure means do not rehash now
            exit(1)

        os.unlink(crl_index)

    with open(crl_index, 'a+') as sys.stdout:
        print(current);

        for fetched in fetch_certs(domains):
            try:
                dp_uri = None
                cert = x509.load_pem_x509_certificate(fetched['pem'])
                for ext in cert.extensions:
                    if type(ext.value) is CRLDistributionPoints:
                        for Distributionpoint in ext.value:
                            dp_uri = Distributionpoint.full_name[0].value
                            print("# [i] fetch CRL from %s" % dp_uri)
                            # XXX: only support http for now
                            response = tls_helper.RequestsWrapper().get(dp_uri)
                            if 200 <= response.status_code <= 299:
                                crl = x509.load_der_x509_crl(response.content)
                                crl_bundle.append({"domain": fetched['domain'], "depth": fetched['depth'], "name": str(cert.subject), "data": crl.public_bytes(serialization.Encoding.PEM).decode().strip()})
            except ValueError:
                print("[!!] Error processing pem file (%s)" % cert.subject if cert else '' , file=sys.stderr)
            except Exception as e:
                if dp_uri:
                    print("[!!] CRL fetch failed for %s (%s)" % (dp_uri, e), file=sys.stderr)
                else:
                    print("[!!] CRL fetch issue (%s) (%s)" % (cert.subject if cert else '', e) , file=sys.stderr)

    for i in glob.glob(target + '*.crl'):
        os.unlink(i)

    for i in crl_bundle:
       with open(target + "%s-%d.crl" % (i['domain'], i['depth']), 'w') as f_out:
            f_out.write("# " + i['name'] + "\n" + i['data'] + "\n")

parser = argparse.ArgumentParser()
parser.add_argument("-l", help="CRL cache lifetime", type=int, default=3600)
parser.add_argument("-t", help="target filename prefix", type=str, default="/usr/local/share/certs/ca-crl-firmware-")
parser.add_argument('domains', metavar='N', type=str, nargs='*', help='list of domains to merge')
args = parser.parse_args()
main(args.domains, args.t, args.l)
