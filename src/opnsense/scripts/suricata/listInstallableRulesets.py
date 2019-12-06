#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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

    list downloadable/installable suricata rules, see metadata/rules/*.xml
"""

import os
import os.path
import ujson
from lib import metadata
from lib import rule_source_directory

md = metadata.Metadata()
if __name__ == '__main__':
    # collect all installable rules indexed by (target) filename
    # (filenames should be unique)
    items = dict()
    for rule in md.list_rules():
        if not rule['required'] and not rule['deprecated']:
            items[rule['filename']] = rule
            rule_filename = ('%s/%s' % (rule_source_directory, rule['filename'])).replace('//', '/')
            if os.path.exists(rule_filename):
                items[rule['filename']]['modified_local'] = os.stat(rule_filename).st_mtime
            else:
                items[rule['filename']]['modified_local'] = None
    result = {'items': items, 'count': len(items)}
    result['properties'] = md.list_rule_properties()
    print(ujson.dumps(result))
