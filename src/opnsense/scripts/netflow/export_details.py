#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2025 Ad Schellevis <ad@opnsense.org>
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
    fetch detailed data from provider for specified timeserie
"""
import argparse
import time
import datetime
import pytz
import sys
import lib.aggregates


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--provider', default='FlowInterfaceTotals')
    parser.add_argument('--resolution', type=int, required=True)
    parser.add_argument('--start_time', type=int, required=True)
    parser.add_argument('--end_time', type=int, required=True)
    cmd_args = parser.parse_args()

    # calculate time offset between localtime and utc
    now_timestamp = time.time()
    time_offset = datetime.datetime.fromtimestamp(now_timestamp) - datetime.datetime.utcfromtimestamp(now_timestamp)

    for agg_class in lib.aggregates.get_aggregators():
        if cmd_args.provider == agg_class.__name__:
            if cmd_args.resolution in agg_class.resolutions():
                # found provider and resolution, start spooling data
                obj = agg_class(cmd_args.resolution)
                rownum=0
                column_names = dict()
                for record in obj.get_data(cmd_args.start_time, cmd_args.end_time):
                    if rownum == 0:
                        column_names = list(record.keys())
                        # dump heading
                        print (','.join(column_names))
                    line = list()
                    for item in column_names:
                        if not record[item]:
                            line.append("")
                        if type(record[item]) == datetime.datetime:
                            # dates are stored in utc, return in timezone configured on this machine
                            record[item] = record[item].replace(tzinfo=pytz.utc) + time_offset
                            line.append(record[item].strftime('%Y/%m/%d %H:%M:%S'))
                        elif type(record[item]) == float:
                            line.append('%.4f' % record[item])
                        elif type(record[item]) == int:
                            line.append('%d' % record[item])
                        else:
                            line.append(record[item])
                    print (','.join(line))
                    rownum += 1
