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

    script to fetch all suricata rule information into a single json object with the following contents:
        rules : all relevant metadata from the rules including the default enabled or disabled state
        total_rows: total rowcount for this selection
        parameters: list of parameters used
"""

import sys
sys.path.insert(0, "/usr/local/opnsense/site-python")
import ujson
from lib.rulecache import RuleCache
from params import update_params

# Because rule parsing isn't very useful when the rule definitions didn't change we create a single json file
# to hold the last results (combined with creation date and number of files).
if __name__ == '__main__':
    rc = RuleCache()
    if rc.is_changed():
        rc.create()

    # load parameters, ignore validation here the search method only processes valid input
    parameters = {'limit': '0', 'offset': '0', 'sort_by': '', 'filter': ''}
    update_params(parameters)
    # rename, filter tag to filter_txt
    parameters['filter_txt'] = parameters['filter']
    del parameters['filter']

    # dump output
    result = rc.search(**parameters)
    result['parameters'] = parameters
    print(ujson.dumps(result))
