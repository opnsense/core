#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2021 Ad Schellevis <ad@opnsense.org>
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
    list pf states
"""
import ujson
import argparse
from lib.states import query_states


if __name__ == '__main__':
    # parse input arguments
    parser = argparse.ArgumentParser()
    parser.add_argument('--filter', help='filter results', default='')
    parser.add_argument('--label', help='label / rule id', default='')
    parser.add_argument('--limit', help='limit number of results', default='')
    parser.add_argument('--offset', help='offset results', default='')
    parser.add_argument('--sort_by', help='sort by (field asc|desc)', default='')
    inputargs = parser.parse_args()

    result = {
        'details': query_states(rule_label=inputargs.label, filter_str=inputargs.filter)
    }
    # sort results
    if inputargs.sort_by.strip() != '':
        sort_key = inputargs.sort_by.split()[0]
        sort_desc = inputargs.sort_by.split()[-1] == 'desc'
        result['details'] = sorted(
            result['details'],
            key=lambda k: str(k[sort_key]).lower() if sort_key in k else '',
            reverse=sort_desc
        )

    result['total_entries'] = len(result['details'])
    # apply offset and limit
    if inputargs.offset.isdigit():
        result['details'] = result['details'][int(inputargs.offset):]
    if inputargs.limit.isdigit() and len(result['details']) >= int(inputargs.limit):
        result['details'] = result['details'][:int(inputargs.limit)]

    result['total'] = len(result['details'])

    print(ujson.dumps(result))
