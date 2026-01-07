#!/usr/local/bin/python3

"""
    Copyright (c) 2020-2025 Ad Schellevis <ad@opnsense.org>
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
from blocklists import BlocklistParser

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="Manage blocklist")
    subparsers = parser.add_subparsers(dest="command", required=True)

    # modify subcommand
    modify_parser = subparsers.add_parser("modify", help="Modify blocklist")
    modify_parser.add_argument("--uuid", help="policy UUID")
    modify_parser.add_argument("--domain", help="Domain name")
    modify_parser.add_argument(
        "--action",
        choices=["block", "allow"],
        help="Action to perform"
    )

    # update subcommand
    subparsers.add_parser("update", help="Update blocklist")

    args = parser.parse_args()

    parser_obj = BlocklistParser()

    if args.command == "modify":
        parser_obj.modify_blocklist(args.uuid, args.domain, args.action)
    elif args.command == "update":
        parser_obj.update_blocklist()
