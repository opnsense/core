#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2023 Ad Schellevis <ad@opnsense.org>
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
    list known mac prefixes
"""

import os.path
import sys
import ujson
from lib import OUI


if __name__ == '__main__':
    cache_file = '/tmp/oui.txt.json'
    result = {}
    oui_mtime = OUI().st_time
    if os.path.isfile(cache_file) and os.stat(cache_file).st_mtime == oui_mtime:
        try:
            result = ujson.loads(open(cache_file).read())
        except ValueError:
            result = {}

    if len(result) == 0:
        result = OUI().get_db()
        json_payload = ujson.dumps(result)
        open(cache_file, 'w').write(json_payload)
        os.chmod(cache_file, 0o444)
        os.utime(cache_file, (oui_mtime, oui_mtime))

        print(json_payload)
        sys.exit(0)

    print(ujson.dumps(result))
