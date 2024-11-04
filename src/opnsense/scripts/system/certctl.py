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
import subprocess
import warnings
warnings.filterwarnings('ignore', message='.*cryptography', )
import OpenSSL.crypto
from cryptography import x509
from cryptography.hazmat.primitives import serialization

TRUSTPATH = ['/usr/share/certs/trusted', '/usr/local/share/certs']
UNTRUSTEDPATH = ['/usr/share/certs/untrusted', '/usr/local/share/certs/untrusted']
CERTDESTDIR = '/etc/ssl/certs'
UNTRUSTDESTDIR = '/etc/ssl/untrusted'

def certificate_iterator(filename):
    fext = os.path.splitext(filename)[1][1:].lower()
    needs_copy = False
    try:
        if fext == 'crl':
            x509_items = [filename]
        elif fext in ['pem', 'cer', 'crt']:
            x509_items = x509.load_pem_x509_certificates(open(filename, 'rb').read())
            needs_copy = len(x509_items) > 1
        else:
            # not supported
            return None
    except (ValueError, TypeError):
        return None

    for x509_item in x509_items:
        if fext == 'crl':
            # XXX: pyOpenSSL doesn't offer access to subject_name_hash(), so we have to call openssl direct here
            spparams = ['/usr/bin/openssl', 'crl', '-in', filename, '-hash', '-noout']
            sp = subprocess.run(spparams, capture_output=True, text=True)
            if sp.returncode != 0:
                continue
            hashval = sp.stdout.strip()
        else:
            cert = OpenSSL.crypto.load_certificate(
                OpenSSL.crypto.FILETYPE_PEM,
                x509_item.public_bytes(serialization.Encoding.PEM)
            )
            hashval = hex(cert.subject_name_hash()).lstrip('0x').zfill(8)
        yield {
            'hash': hashval,
            'target_pattern': '%s.%s%%d' % (hashval, 'r' if fext == 'crl' else ''),
            'type': 'copy' if needs_copy else 'link',
            'data': x509_item.public_bytes(serialization.Encoding.PEM) if needs_copy and x509_item else filename,
            'filename': filename
        }


def get_cert_common_name(filename):
    try:
        subject = x509.load_pem_x509_certificate(open(filename, 'rb').read()).subject
        for item in subject:
            if item.rfc4514_attribute_name == 'CN':
                return item.value
        return subject.rfc4514_string()
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


def cmd_untrusted():
    print('Listing Untrusted Certificates:')
    for filename in glob.glob('%s/*.[0-9]' % UNTRUSTDESTDIR):
        basename = os.path.basename(filename)
        cn = get_cert_common_name(filename)
        if cn:
            print("%s\t%s" % (basename, cn))
        else:
            print('Invalid certificate %s' % basename)


def cmd_rehash():
    targets = {'trusted': {}, 'untrusted': {}}
    for path in UNTRUSTEDPATH + TRUSTPATH:
        if os.path.isdir(path):
            targetname = 'trusted' if path in TRUSTPATH else 'untrusted'
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


    current_target_files = []
    changes = 0
    for target_name in targets:
        for pattern in targets[target_name]:
            for seq, record in enumerate(targets[target_name][pattern]):
                is_ut = target_name == 'untrusted'
                src_filename = record['filename']
                dst_filename = "%s/%s" % (UNTRUSTDESTDIR if is_ut else CERTDESTDIR, pattern % seq)
                if not is_ut and hash in targets['untrusted']:
                    print(
                        "Skipping untrusted certificate %s (%s/%s)" % (filename, UNTRUSTDESTDIR, pattern % seq)
                    )
                    continue

                current_target_files.append(dst_filename)
                if os.path.islink(dst_filename) and os.readlink(dst_filename) == src_filename:
                    continue # unchanged
                elif os.path.isfile(dst_filename) and open(dst_filename, 'rb').read() == record['data']:
                    continue # unchanged

                changes += 1
                if record['type'] == 'copy':
                    if os.path.islink(dst_filename):
                        os.unlink(dst_filename)
                    with open(dst_filename, 'wb') as f_out:
                        f_out.write(record['data'])
                    os.chmod(dst_filename, 0o644)
                else:
                    if os.path.isfile(dst_filename) or os.path.islink(dst_filename):
                        os.remove(dst_filename)
                    os.symlink(src_filename, dst_filename)

    for path in [UNTRUSTDESTDIR, CERTDESTDIR]:
        for filename in glob.glob('%s/*.[0-9]' % path) + glob.glob('%s/*.r[0-9]' % path):
            if filename in current_target_files:
                continue
            elif os.path.islink(filename):
                os.unlink(filename)
            else:
                os.remove(filename)
            changes += 1

    if changes == 0:
        print("certctl: No changes to trust store were made.")
    elif changes == 1:
        print("certctl: Modified 1 trust store link.")
    else:
        print("certctl: Modified %d trust store links." % changes)

    # link certs/crls to ports openssl version
    current_target_files = []
    for filename in glob.glob('%s/*.[0-9]' % CERTDESTDIR) + glob.glob('%s/*.r[0-9]' % CERTDESTDIR):
        target_filename = '/usr/local/openssl/certs/%s' % os.path.basename(filename)
        current_target_files.append(target_filename)
        if not os.path.islink(target_filename) and os.path.isfile(target_filename):
            os.remove(target_filename)
        elif os.path.islink(target_filename):
            continue
        os.symlink(filename, target_filename)

    for filename in glob.glob('/usr/local/openssl/certs/*'):
        if filename not in current_target_files:
            os.remove(filename)



if __name__ == '__main__':
    cmds =  {
        'list': cmd_list,
        'rehash': cmd_rehash,
        'untrusted': cmd_untrusted
    }
    if len(sys.argv) < 2 or sys.argv[1] not in cmds:
        script_name = os.path.basename(sys.argv[0])
        print('Manage the TLS trusted certificates on the system')
        print('%s list\n\tList trusted certificates' % script_name)
        print('%s untrusted\n\tList untrusted certificates' % script_name)
        print('%s rehash\n\tGenerate hash links for all certificates' % script_name)
    else:
        cmds[sys.argv[1]]()
