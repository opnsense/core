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
    fetch top usage from data provider
"""
import time
import datetime
import os
import sys
import ujson
sys.path.insert(0, "/usr/local/opnsense/site-python")
from lib.parse import parse_flow
import lib.aggregates
import params


app_params = {'start_time': '',
              'end_time': '',
              'key_fields': '',
              'value_field': '',
              'filter': '',
              'max_hits': '',
              'provider': ''
              }
params.update_params(app_params)

# handle input parameters
valid_params = False
if app_params['start_time'].isdigit():
    start_time = int(app_params['start_time'])
    if app_params['end_time'].isdigit():
        end_time = int(app_params['end_time'])
        if app_params['max_hits'].isdigit():
            max_hits = int(app_params['max_hits'])
            if app_params['key_fields']:
                key_fields = app_params['key_fields'].split(',')
                if app_params['value_field']:
                    value_field = app_params['value_field']
                    valid_params = True
data_filter=app_params['filter']

timeseries=dict()
if valid_params:
    # collect requested top
    result = dict()
    for agg_class in lib.aggregates.get_aggregators():
        if app_params['provider'] == agg_class.__name__:
            # provider may specify multiple resolutions, we need to find the one most likely to serve the
            # beginning of our timeframe
            resolutions = sorted(agg_class.resolutions())
            history_per_resolution = agg_class.history_per_resolution()
            for resolution in resolutions:
                if (resolution in history_per_resolution \
                  and time.time() - history_per_resolution[resolution] <= start_time ) \
                  or resolutions[-1] == resolution:
                    selected_resolution = resolution
                    break
            obj = agg_class(selected_resolution)
            result = obj.get_top_data(start_time, end_time, key_fields, value_field, data_filter, max_hits)
    print (ujson.dumps(result))
else:
    print ('missing parameters :')
    tmp = list()
    for key in app_params:
        tmp.append('/%s %s' % (key, app_params[key]))
    print ('  %s %s'%(sys.argv[0], ' '.join(tmp)))
    print ('')
    print ('  start_time : start time (seconds since epoch)')
    print ('  end_time : end timestamp (seconds since epoch)')
    print ('  key_fields : key field(s)')
    print ('  value_field : field to sum')
    print ('  filter : apply filter <field>=value')
    print ('  provider : data provider classname')
    print ('  max_hits : maximum number of hits (+1 for rest of data)')
    print ('  sample : if provided, use these keys to generate sample data (e.g. em0,em1,em2)')
