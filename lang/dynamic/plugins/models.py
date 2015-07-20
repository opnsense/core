"""
    Copyright (c) 2015 Deciso B.V.
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

    package : translate
    function: collect model translatable text
"""
__author__ = 'Ad Schellevis'

def recursiveParseModel(xmlNode):
    for childNode in xmlNode:
        for tag in recursiveParseModel(childNode):
            yield tag

    if xmlNode.tag == 'ValidationMessage':
        yield xmlNode.text

def recursiveParseMenu(xmlNode):
    for childNode in xmlNode:
        for tag in recursiveParseMenu(childNode):
            yield tag

    if xmlNode.attrib.has_key('VisibleName'):
        yield xmlNode.attrib['VisibleName']
    else:
        yield xmlNode.tag

def getTranslations(root):
    import os
    import xml.etree.ElementTree as ET

    rootpath='%s/opnsense/mvc/app/models/'%root


    for rootdir, dirs, files in os.walk(rootpath, topdown=False):
        for name in files:
            if name.lower()[-4:] == '.xml':
                filename = '%s/%s'%(rootdir,name)
                tree = ET.parse(filename)
                rootObj = tree.getroot()
                if rootObj.tag == 'model':
                    for tag in recursiveParseModel(rootObj):
                        yield tag
                elif rootObj.tag == 'menu':
                    for tag in recursiveParseMenu(rootObj):
                        yield tag
