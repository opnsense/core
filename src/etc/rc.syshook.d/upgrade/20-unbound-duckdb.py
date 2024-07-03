#!/usr/local/bin/python3
"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
import sys
import os
sys.path.insert(0, "/usr/local/opnsense/site-python")
from duckdb_helper import export_database


if os.path.isfile('/var/unbound/data/unbound.duckdb'):
    if not os.path.isfile('/var/unbound/data/stats'):
        print('Unbound DNS stats disabled')
        sys.exit(0)

    if os.path.isfile('/var/run/unbound_logger.pid'):
        pid = open('/var/run/unbound_logger.pid').read().strip()
        try:
            os.kill(int(pid), 9)
        except ProcessLookupError:
            pass

    if export_database('/var/unbound/data/unbound.duckdb', '/var/cache/unbound.duckdb', 'unbound', 'unbound'):
        print('Unbound DNS database exported successfully.')
    else:
        print('Unbound DNS database export not required.')
else:
    print('Unbound DNS database not found, no export needed.')
