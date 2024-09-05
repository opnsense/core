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

TRUSTPATH = ['/usr/share/certs/trusted', '/usr/local/share/certs', '/usr/local/etc/ssl/certs']
BLACKLISTPATH = ['/usr/share/certs/blacklisted', '/usr/local/etc/ssl/blacklisted']
CERTDESTDIR = '/etc/ssl/certs'
BLACKLISTDESTDIR = '/etc/ssl/blacklisted'


def get_name_hash_file_pattern(filename):
    fext = os.path.splitext(filename)[1][1:].lower()
    try:
        if fext == 'crl':
            x509_item = x509.load_pem_x509_crl(open(filename, 'rb').read())
        elif fext in ['pem', 'cer', 'crt']:
            tmp = x509.load_pem_x509_certificates(open(filename, 'rb').read())
            if len(tmp) > 1:
                print('Skipping %s as it does not contain exactly one certificate' % filename)
                return None
            x509_item = tmp[0]
        else:
            # not supported
            return None
    except (ValueError, TypeError):
        return None

    tmp = OpenSSL.crypto.X509().get_issuer()
    for item in x509_item.issuer:
        setattr(tmp, item.rfc4514_attribute_name, item.value)

    return '%s.%s%%d' % (hex(tmp.hash()).lstrip('0x').zfill(8), 'r' if fext == 'crl' else '')

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
                pattern = get_name_hash_file_pattern(filename)
                if pattern:
                    if pattern not in targets[targetname]:
                        targets[targetname][pattern] = []
                    targets[targetname][pattern].append(filename)

    for path in [BLACKLISTDESTDIR, CERTDESTDIR]:
        for filename in glob.glob('%s/*.[0-9]' % path) + glob.glob('%s/*.r[0-9]' % path):
            if os.path.islink(filename):
                os.unlink(filename)

    for target_name in targets:
        for pattern in targets[target_name]:
            for seq, filename in enumerate(targets[target_name][pattern]):
                if target_name == 'blacklisted':
                    os.symlink(os.path.relpath(filename, BLACKLISTDESTDIR), "%s/%s" % (BLACKLISTDESTDIR, pattern % seq))
                else:
                    if hash in targets['blacklisted']:
                        print(
                            "Skipping blacklisted certificate %s (%s/%s)" % (filename, BLACKLISTDESTDIR, pattern % seq)
                        )
                    else:
                        os.symlink(os.path.relpath(filename, CERTDESTDIR), "%s/%s" % (CERTDESTDIR, pattern % seq))


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
