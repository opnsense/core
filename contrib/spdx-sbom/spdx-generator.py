#!/usr/local/bin/python3

"""
    Copyright (c) 2026 Ad Schellevis <ad@opnsense.org>
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
import json
from lib.pkg import Pkg
from lib.spdx import *


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('pkg', help='package name to inspect')
    inputargs = parser.parse_args()
    pkg = Pkg().query(inputargs.pkg)
    doc = SpdxDocument(
        SpdxSoftwareSbom(SpdxPackage(**pkg.get_data()))
    )

    for frm, to in pkg:
        pkg = doc.add_object(SpdxPackage(**frm.get_data()))
        lic = doc.add_object(
            SpdxLicenseExpression(**{
                'id': frm.licenseExpression.replace(' ', '_').lower(),
                'licenseExpression': frm.licenseExpression
            })
        )
        doc.add_object(
            SpdxRelationship(**{
                'relationshipType': 'hasConcludedLicense',
                'id': '%s_lic' % frm.id,
                'from': pkg.spdxId,
                'to': [lic.spdxId]
            })
        )

        if to:
            doc.add_object(
                SpdxRelationship(**{
                    'relationshipType': 'dependsOn',
                    'id': '%s-%s' % (frm.id, '_'.join([x.id for x in to])),
                    'from': pkg.spdxId,
                    'to': [SpdxPackage.spdx_id_constructor(x.id) for x in to]
                })
            )

    print(json.dumps(doc.dump(), indent=4))
