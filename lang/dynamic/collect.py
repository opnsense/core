#!/usr/bin/env python2.7

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
"""
__author__ = 'Ad Schellevis'

import os.path
import glob
import importlib
import sys

if len(sys.argv) < 2:
    print 'Usage: collect.py rootdir'
    sys.exit(1)

# get source location (root of core package)
OPNsenseRoot=sys.argv[1] + '/src'

# create target location
targetPath=sys.argv[1] + '/lang/dynamic/helpers'

if len(glob.glob(targetPath)) == 0:
    os.mkdir(targetPath)

# load default output template
templateText = open('%s/template.txt'%'/'.join(os.path.realpath(__file__).split('/')[:-1]),'r').read()

for filename in glob.glob('%s/plugins/*.py'%'/'.join(os.path.realpath(__file__).split('/')[:-1])):
    modulename = os.path.basename(filename)[:-3]
    lang = importlib.import_module('plugins.%s'%modulename)
    if hasattr(lang,'getTranslations'):
        # open filehandle for collected plugin
        fOut=open('%s/%s.php'%(targetPath,modulename),'w')
        fOut.write(templateText)

        # collect and sort tags
        translations = list()
        for textValue in lang.getTranslations(OPNsenseRoot):
            translations.append(textValue)
        translations.sort()

        # fill with gettext tags
        for textValue in translations:
            line="echo gettext('%s');\n"%(unicode(textValue).replace("'","\\'"))
            fOut.write(line)

        fOut.close()
