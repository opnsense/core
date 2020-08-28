#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2019 Ad Schellevis <ad@opnsense.org>
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
import os
import time
import calendar
import ujson
import random
import argparse
from lib import load_config
import lib.aggregates

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', '--config', help='configuration yaml', default=None)
    parser.add_argument('--provider', default='FlowInterfaceTotals')
    parser.add_argument('--resolution', type=int, required=True)
    parser.add_argument('--start_time', type=int, required=True)
    parser.add_argument('--end_time', type=int, required=True)
    parser.add_argument('--key_fields', required=True)
    parser.add_argument('--sample', default='')
    cmd_args = parser.parse_args()
    configuration = load_config(cmd_args.config)

    timeseries = dict()
    if cmd_args.sample == '':
        # fetch all measurements from selected data provider
        dimension_keys = list()
        for agg_class in lib.aggregates.get_aggregators():
            if cmd_args.provider == agg_class.__name__:
                obj = agg_class(cmd_args.resolution, database_dir=configuration.database_dir)
                for record in obj.get_timeserie_data(cmd_args.start_time, cmd_args.end_time,
                                                     cmd_args.key_fields.split(',')):
                    record_key = []
                    for key_field in cmd_args.key_fields.split(','):
                        if key_field in record and record[key_field] is not None:
                            record_key.append(record[key_field])
                        else:
                            record_key.append('')
                    record_key = ','.join(record_key)
                    start_time_stamp = calendar.timegm(record['start_time'].timetuple())
                    if start_time_stamp not in timeseries:
                        timeseries[start_time_stamp] = dict()
                    timeseries[start_time_stamp][record_key] = {'octets': record['octets'],
                                                                'packets': record['packets'],
                                                                'resolution': cmd_args.resolution}
                    if record_key not in dimension_keys:
                        dimension_keys.append(record_key)

        # When there's no data found, collect keys from the running configuration to render empty results
        if len(dimension_keys) == 0 and os.path.isfile('/usr/local/etc/netflow.conf'):
            tmp = open('/usr/local/etc/netflow.conf').read()
            if tmp.find('netflow_interfaces="') > -1:
                for intf in tmp.split('netflow_interfaces="')[-1].split('"')[0].split():
                    dimension_keys.append('%s,in' % intf)
                    dimension_keys.append('%s,out' % intf)

        # make sure all timeslices for every dimension key exist (resample collected data)
        start_time = cmd_args.start_time
        while start_time < time.time():
            if start_time not in timeseries:
                timeseries[start_time] = dict()
            for dimension_key in dimension_keys:
                if dimension_key not in timeseries[start_time]:
                    timeseries[start_time][dimension_key] = {
                        'octets': 0,
                        'packets': 0,
                        'resolution': cmd_args.resolution
                    }
            start_time += cmd_args.resolution
    else:
        # generate sample data for given keys
        timeseries = dict()
        start_time = cmd_args.start_time
        while start_time < time.time():
            timeseries[start_time] = dict()
            for key in cmd_args.sample.split('~'):
                timeseries[start_time][key] = {
                    'octets': (random.random() * 10000000),
                    'packets': (random.random() * 10000000),
                    'resolution': cmd_args.resolution
                }
            start_time += cmd_args.resolution

    print (ujson.dumps(timeseries))
