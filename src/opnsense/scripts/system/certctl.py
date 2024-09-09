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
    -----------------------------------------------------------------------------------------------
    Simple re-implementation of certctl tool in FreeBSD (only supporting the parameters we use)
"""
import glob
import sys
import os
import OpenSSL.crypto
from cryptography import x509
from cryptography.hazmat.primitives import serialization

TRUSTPATH = ['/usr/share/certs/trusted', '/usr/local/share/certs', '/usr/local/etc/ssl/certs']
BLACKLISTPATH = ['/usr/share/certs/untrusted', '/usr/share/certs/blacklisted', '/usr/local/etc/ssl/blacklisted']
CERTDESTDIR = '/etc/ssl/certs'
BLACKLISTDESTDIR = '/etc/ssl/blacklisted'


def certificate_iterator(filename):
    fext = os.path.splitext(filename)[1][1:].lower()
    try:
        if fext == 'crl':
            x509_items = [x509.load_pem_x509_crl(open(filename, 'rb').read())]
        elif fext in ['pem', 'cer', 'crt']:
            x509_items = x509.load_pem_x509_certificates(open(filename, 'rb').read())
        else:
            # not supported
            return None
    except (ValueError, TypeError):
        return None

    needs_copy = len(x509_items) > 1
    for x509_item in x509_items:
        data = x509_item.public_bytes(serialization.Encoding.PEM) if needs_copy else filename
        tmp = OpenSSL.crypto.X509().get_issuer()
        for item in x509_item.issuer:
            setattr(tmp, item.rfc4514_attribute_name, item.value)
        hashval = hex(tmp.hash()).lstrip('0x').zfill(8)
        yield {
            'hash': hashval,
            'target_pattern': '%s.%s%%d' % (hashval, 'r' if fext == 'crl' else ''),
            'type': 'copy' if needs_copy else 'link',
            'data': data,
            'filename': filename
        }


def get_cert_common_name(filename):
    try:
        issuer = x509.load_pem_x509_certificate(open(filename, 'rb').read()).issuer
        for item in issuer:
            if item.rfc4514_attribute_name == 'CN':
                return item.value
        return issuer.rfc4514_string()
    except (ValueError, TypeError):
        return None

def cmd_list():
    print('Listing Trusted Certificates:')
    for filename in glob.glob('%s/*.[0-9]' % CERTDESTDIR):
        basename = os.path.basename(filename)
        cn = get_cert_common_name(filename)
        if cn:
            print("%s\t%s" % (basename, cn))
        else:
            print('Invalid certificate %s' % basename)


def cmd_blacklisted():
    print('Listing Blacklisted Certificates:')
    for filename in glob.glob('%s/*.[0-9]' % BLACKLISTDESTDIR):
        basename = os.path.basename(filename)
        cn = get_cert_common_name(filename)
        if cn:
            print("%s\t%s" % (basename, cn))
        else:
            print('Invalid certificate %s' % basename)


def cmd_rehash():
    targets = {'trusted': {}, 'blacklisted': {}}
    for path in BLACKLISTPATH + TRUSTPATH:
        if os.path.isdir(path):
            targetname = 'trusted' if path in TRUSTPATH else 'blacklisted'
            print("Scanning %s for certificates..." % path)
            for filename in glob.glob('%s/*' % path):
                for record in certificate_iterator(filename):
                    pattern = record['target_pattern']
                    if pattern not in targets[targetname]:
                        targets[targetname][pattern] = []
                    if record['type'] == 'copy' and len(targets[targetname][pattern]) > 0:
                        # skip hardcopies when a link or hardcopy already exists
                        continue
                    targets[targetname][pattern].append(record)

    for path in [BLACKLISTDESTDIR, CERTDESTDIR]:
        for filename in glob.glob('%s/*.[0-9]' % path) + glob.glob('%s/*.r[0-9]' % path):
            if os.path.islink(filename):
                os.unlink(filename)
            else:
                os.remove(filename)

    for target_name in targets:
        for pattern in targets[target_name]:
            for seq, record in enumerate(targets[target_name][pattern]):
                is_bl = target_name == 'blacklisted'
                src_filename = os.path.relpath(record['filename'], BLACKLISTDESTDIR if is_bl else CERTDESTDIR)
                dst_filename = "%s/%s" % (BLACKLISTDESTDIR if is_bl else CERTDESTDIR, pattern % seq)
                if not is_bl and hash in targets['blacklisted']:
                    print(
                        "Skipping blacklisted certificate %s (%s/%s)" % (filename, BLACKLISTDESTDIR, pattern % seq)
                    )
                    continue

                if record['type'] == 'copy':
                    with open(dst_filename, 'wb') as f_out:
                        f_out.write(record['data'])
                    os.chmod(dst_filename, 0o644)
                else:
                    os.symlink(src_filename, dst_filename)


if __name__ == '__main__':
    cmds =  {
        'list': cmd_list,
        'rehash': cmd_rehash,
        'blacklisted': cmd_blacklisted
    }
    if len(sys.argv) < 2 or sys.argv[1] not in cmds:
        script_name = os.path.basename(sys.argv[0])
        print('Manage the TLS trusted certificates on the system')
        print('%s list\n\tList trusted certificates' % script_name)
        print('%s blacklisted\n\tList blacklisted certificates' % script_name)
        print('%s rehash\n\tGenerate hash links for all certificates' % script_name)
    else:
        cmds[sys.argv[1]]()
