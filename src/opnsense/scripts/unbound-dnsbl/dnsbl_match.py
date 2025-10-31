#!/usr/local/bin/python3
"""
    Copyright (c) 2022-2025 Deciso B.V.
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
import json

from lib import Query, ModuleContext
from lib.dnsbl import DNSBL

def arg_parse_is_json_file(filename):
    try:
        json.load(open(filename))
    except FileNotFoundError:
        raise argparse.ArgumentTypeError("non existing file")
    except json.JSONDecodeError:
        # in cases where a file exists, but we're unable to decode it (e.g. the file is empty),
        # we should assume the blocklist has no entries.
        pass
    except:
        raise argparse.ArgumentTypeError("No blocklist available")

    return filename

if __name__ == '__main__':
    """ Command line blocklist test mode
    """
    parser = argparse.ArgumentParser()
    parser.add_argument(
        '--src',
         help='client source address. Default 127.0.0.1',
         default='127.0.0.1'
    )
    parser.add_argument('--domain', help='domain name to query', required=True)
    parser.add_argument(
        '--type',
        help='query type, e.g. AAAA. Default is A',
        default='A',
        choices=['A', 'AAAA', 'CNAME', 'HTTPS']
    )
    parser.add_argument(
        '--dnsbl_path',
        help='blocklist json input',
        default='/var/unbound/data/dnsbl.json',
        type=arg_parse_is_json_file
    )

    inputargs = parser.parse_args()

    # create an empty global context
    ctx = ModuleContext(None)

    dnsbl = DNSBL(ctx, dnsbl_path=inputargs.dnsbl_path, size_file='/dev/null')
    ctx.set_config(dnsbl.dnsbl['config'])
    match = dnsbl.policy_match(
        Query(
            client=inputargs.src,
            family='ip6' if inputargs.src.count(':') else 'ip4',
            type=inputargs.type,
            domain=inputargs.domain
        )
    )
    if match:
        src_nets = match.get('source_nets', [])
        for i in range(len(src_nets)):
            src_nets[i] = str(src_nets[i])
        match['source_nets'] = src_nets
        del match['pass_regex']
        msg = {'status': 'OK','action': 'Block','policy': match}
        print(json.dumps(msg))
    else:
        print(json.dumps({'status': 'OK','action': 'Pass'}))
