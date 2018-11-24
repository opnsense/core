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
    fetch timeseries from data provider
"""
import time
import datetime
import calendar
import os
import sys
import ujson
import random
sys.path.insert(0, "/usr/local/opnsense/site-python")
from lib.parse import parse_flow
import lib.aggregates
import params

# define
app_params = {'resolution': '',
              'start_time': '',
              'end_time': '',
              'key_fields': '',
              'provider': '',
              'sample': ''
              }
params.update_params(app_params)

# handle input parameters
valid_params = False
if app_params['start_time'].isdigit():
    start_time = int(app_params['start_time'])
    if app_params['end_time'].isdigit():
        end_time = int(app_params['end_time'])
        if app_params['resolution'].isdigit():
            resolution = int(app_params['resolution'])
            if app_params['key_fields']:
                key_fields = app_params['key_fields'].split(',')
                valid_params = True

timeseries=dict()
if valid_params:
    if app_params['sample'] == '':
        # fetch all measurements from selected data provider
        dimension_keys=list()
        for agg_class in lib.aggregates.get_aggregators():
            if app_params['provider'] == agg_class.__name__:
                obj = agg_class(resolution)
                for record in obj.get_timeserie_data(start_time, end_time, key_fields):
                    record_key = []
                    for key_field in key_fields:
                        if key_field in record and record[key_field] != None:
                            record_key.append(record[key_field])
                        else:
                            record_key.append('')
                    record_key = ','.join(record_key)
                    start_time_stamp = calendar.timegm(record['start_time'].timetuple())
                    if start_time_stamp not in timeseries:
                        timeseries[start_time_stamp] = dict()
                    timeseries[start_time_stamp][record_key] = {'octets': record['octets'],
                                                                'packets': record['packets'],
                                                                'resolution': resolution}
                    if record_key not in dimension_keys:
                        dimension_keys.append(record_key)

        # add first measure point if it doesn't exist in the data (graph starting point)
        if start_time not in timeseries:
            timeseries[start_time] = dict()
            for dimension_key in dimension_keys:
                timeseries[start_time][dimension_key] = {'octets': 0, 'packets': 0, 'resolution': resolution}
        # make sure all measure points exists for all given keys
        for timeserie in sorted(timeseries):
            for dimension_key in dimension_keys:
                if dimension_key not in timeseries[timeserie]:
                    timeseries[timeserie][dimension_key] = {'octets': 0, 'packets': 0, 'resolution': resolution}
    else:
        # generate sample data for given keys
        timeseries=dict()
        while start_time < time.time():
            timeseries[start_time] = dict()
            for key in app_params['sample'].split('~'):
                timeseries[start_time][key] = {'octets': (random.random() * 10000000),
                                               'packets': (random.random() * 10000000),
                                               'resolution': resolution}
            start_time += resolution

    print (ujson.dumps(timeseries))
else:
    print ('missing parameters :')
    tmp = list()
    for key in app_params:
        tmp.append('/%s %s' % (key, app_params[key]))
    print ('  %s %s'%(sys.argv[0], ' '.join(tmp)))
    print ('')
    print ('  resolution : sample rate in seconds')
    print ('  start_time : start time (seconds since epoch)')
    print ('  end_time : end timestamp (seconds since epoch)')
    print ('  key_fields : key field(s)')
    print ('  provider : data provider classname')
    print ('  sample : if provided, use these keys to generate sample data (e.g. em0,em1,em2)')
