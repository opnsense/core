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
import bectl, argparse, json, hashlib, uuid

def create_uuid_from_string(val: str) -> str:
    hex_string = hashlib.md5(val.encode("UTF-8")).hexdigest()
    return str(uuid.UUID(hex=hex_string))

if not bectl.is_file_system_zfs():
    print("Unsupported file system")
    exit(0)

parser = argparse.ArgumentParser()
parser.add_argument('--name', help='name of boot environment to find', required = False)
parser.add_argument('--uuid', help = 'uuid of boot environment', required = False)
input_args = parser.parse_args()

if input_args.name is None and input_args.uuid is None:
    print('{"status": "failed", "error": "--name or --uuid must be specified"}')
    exit(0)

if input_args.name is not None:
    match_on = "name"
    search_term = input_args.name
else:
    match_on = "uuid"
    search_term = input_args.uuid

be_match = None
boot_environments = bectl.get_be_list()
for bootenv in boot_environments:
    prop = bootenv.split("\t")
    be = {
        "uuid": create_uuid_from_string(prop[0]),
        "name": prop[0],
        "active": prop[1],
        "mountpoint": prop[2],
        "size": prop[3],
        "created": prop[4]
    }

    if (be[match_on] == search_term):
        be_match=be
        break

if be_match is not None:
    print(json.dumps(be_match))
else:
    print('{"status": "failed"}')
