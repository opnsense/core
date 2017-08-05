#!/usr/local/bin/python2.7

"""
    Copyright (c) 2016 Ad Schellevis <ad@opnsense.org>
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
    fetch flowd aggregate metadata
"""
import sys
import ujson
import datetime
from lib.aggregate import AggMetadata
import lib.aggregates

result = dict()

# load global metadata
metadata = AggMetadata()
result['last_sync'] = metadata.last_sync()
# fetch aggregators
result['aggregators'] = dict()
for agg_class in lib.aggregates.get_aggregators():
    result['aggregators'][agg_class.__name__] = {'resolutions': list()}
    for resolution in agg_class.resolutions():
        result['aggregators'][agg_class.__name__]['resolutions'].append(resolution)

# output result
if len(sys.argv) > 1 and 'json' in sys.argv:
    # json format
    print(ujson.dumps(result))
else:
    # plain text format
    print ('Flowd aggregations [last sync %s]' %
        datetime.datetime.fromtimestamp(result['last_sync']).strftime('%Y-%m-%d %H:%M:%S')
    )
    print ('\nAggregators:')
    for aggregator in result['aggregators']:
        print ('  %s' % aggregator)
        for resolution in result['aggregators'][aggregator]['resolutions']:
            print ('    + %d seconds' % resolution)
