#!/usr/bin/env python3
"""
    Copyright (c) 2024 Sheridan Computers Limited
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
import bectl, argparse, json

if not bectl.is_file_system_zfs():
    print("Unsupported file system")
    exit(0)

parser = argparse.ArgumentParser()
parser.add_argument('be_source', help='name of boot environment to rename')
parser.add_argument('be_dest', help='new name for boot environment')
inputargs = parser.parse_args()

be_source = inputargs.be_source
be_dest = inputargs.be_dest
if bectl.rename_be(be_source, be_dest):
    print(json.dumps({"status": "ok", "result": "Boot environment renamed"}))
else:
    print(json.dumps({"status": "failed", "result": "Error: Could not rename boot environment"}))
