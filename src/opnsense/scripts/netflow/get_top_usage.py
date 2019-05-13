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
    fetch top usage from data provider
"""
import time
import argparse
import ujson
import lib.aggregates
from lib import load_config


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', '--config', help='configuration yaml', default=None)
    parser.add_argument('--provider', default='FlowInterfaceTotals')
    parser.add_argument('--start_time', type=int, required=True)
    parser.add_argument('--end_time', type=int, required=True)
    parser.add_argument('--key_fields', required=True)
    parser.add_argument('--value_field', required=True)
    parser.add_argument('--filter', default='')
    parser.add_argument('--max_hits', type=int, required=True)
    cmd_args = parser.parse_args()
    configuration = load_config(cmd_args.config)

    result = dict()
    for agg_class in lib.aggregates.get_aggregators():
        if cmd_args.provider == agg_class.__name__:
            # provider may specify multiple resolutions, we need to find the one most likely to serve the
            # beginning of our timeframe
            resolutions = sorted(agg_class.resolutions())
            history_per_resolution = agg_class.history_per_resolution()

            selected_resolution = resolutions[-1]
            for resolution in resolutions:
                if (resolution in history_per_resolution and
                    time.time() - history_per_resolution[resolution] <= cmd_args.start_time) or \
                        resolutions[-1] == resolution:
                    selected_resolution = resolution
                    break
            obj = agg_class(selected_resolution, database_dir=configuration.database_dir)
            result = obj.get_top_data(
                start_time=cmd_args.start_time,
                end_time=cmd_args.end_time,
                fields=cmd_args.key_fields.split(','),
                value_field=cmd_args.value_field,
                data_filters=cmd_args.filter,
                max_hits=cmd_args.max_hits
            )
    print (ujson.dumps(result))
