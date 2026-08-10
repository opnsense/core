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
    --------------------------------------------------------------------------------------------------------------------
    Hightly simplified wrapper around the SPDX format used to generate software bill of materials (SBOM)
    More information about the format can be found at https://spdx.github.io/spdx-spec/v3.0.1/
"""
from .spdx_agent import SpdxAgent
from .spdx_creation_info import SpdxCreationInfo
from .spdx_element import SpdxElement
from .spdx_license import SpdxLicenseExpression
from .spdx_package import SpdxPackage
from .spdx_relationship import SpdxRelationship


class SpdxSoftwareSbom:
    @staticmethod
    def spdx_type():
        return 'software_Sbom'

    def get_data(self):
        # XXX: temp static block
        return {
            'spdxId': 'urn:opnsense:sbom',
            'type': 'software_Sbom',
            'creationInfo': '_:b0',
            'profileConformance': [
                'core',
                'software'
            ],
            'rootElement': self._rootElement.spdxId
        }

    def __init__(self, root:SpdxElement):
        self._rootElement = root


class SpdxDocument:
    def __init__(self, root:SpdxElement):
        self._contents = {
            '@id': SpdxCreationInfo(), # XXX: replace
            'urn:opnsense:sbom': root  # XXX: static
        }
        # XXX static data from SpdxCreationInfo
        self.add_object(SpdxAgent(spdxId='urn:opnsense:organization:opnsense', name='OPNsense'))

    def add_object(self, obj:SpdxElement):
        self._contents[obj.spdxId] = obj
        return obj

    def get_data(self):
        # XXX: temp static block
        return {
            'spdxId': 'urn:opnsense:document',
            'type': 'SpdxDocument',
            'creationInfo': '_:b0',
            'profileConformance': [
                'core',
                'software'
            ],
            'rootElement': 'urn:opnsense:sbom'
        }

    def dump(self):
        items = [self.get_data()]
        for item in self._contents.values():
            items.append(item.get_data())

        return {
            '@context': 'https://spdx.org/rdf/3.0.1/spdx-context.jsonld',
            '@graph': items
        }
