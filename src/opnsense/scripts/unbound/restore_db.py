#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Deciso B.V.
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
import sys
import os

sys.path.insert(0, "/usr/local/opnsense/site-python")
from duckdb_helper import restore_database

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--backup_dir', help='backup directory', default='/var/cache/unbound.duckdb')
    parser.add_argument('--targetdb', help='duckdb filename', default='/var/unbound/data/unbound.duckdb')
    inputargs = parser.parse_args()
    if os.path.isfile("%s/load.sql" % inputargs.backup_dir):
        if os.path.isfile('/var/run/unbound_logger.pid'):
            pid = open('/var/run/unbound_logger.pid').read().strip()
            try:
                os.kill(int(pid), 9)
            except ProcessLookupError:
                pass
        if os.path.isfile(inputargs.targetdb):
            os.unlink(inputargs.targetdb)
        if restore_database(inputargs.backup_dir, inputargs.targetdb):
            print("restored")
        else:
            print("unable to restore")
    else:
        print("no backup found")
