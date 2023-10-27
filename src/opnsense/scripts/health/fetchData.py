#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2023 Ad Schellevis <ad@opnsense.org>
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
    fetch xmldata from rrd tool, but only if filename is valid (with or without .rrd extension)

"""
import sys
import glob
import subprocess
import os.path
import json
from xml.etree import ElementTree

def get_filename(fname):
    rrd_reports_dir = '/var/db/rrd'
    # suffix rrd if not already in request
    if fname.split('.')[-1] != 'rrd':
        fname += '.rrd'
    # scan rrd directory for requested file
    for rrdFilename in glob.glob('%s/*.rrd' % rrd_reports_dir):
        if os.path.basename(rrdFilename).lower() == fname.lower():
            return rrdFilename

def element_to_dict(elem):
    dict_result = {}
    if not len(elem):
        return elem.text

    for item in elem:
        if item.tag in dict_result:
            if type(dict_result[item.tag]) != list:
                dict_result[item.tag] = [dict_result[item.tag]]
            dict_result[item.tag].append(element_to_dict(item))
        else:
            dict_result[item.tag] = element_to_dict(item)

    return dict_result


if len(sys.argv) > 1:
    result = {}
    filename = get_filename(sys.argv[1])
    if filename:
        sp = subprocess.run(['/usr/local/bin/rrdtool', 'dump', filename], capture_output=True, text=True)
        ruleData = element_to_dict(ElementTree.fromstring(sp.stdout))
        if type(ruleData.get('rra')) is list:
            result['sets'] = []
            for ifld in ['step', 'lastupdate']:
                result[ifld] = int(ruleData[ifld]) if ifld in ruleData and ruleData[ifld].isdigit() else 0
            for rra_idx, rra in  enumerate(ruleData['rra']):
                if rra.get('cf') == 'AVERAGE':
                    record = {'ds': []}
                    ds_count = len(ruleData['ds'])
                    for ds in (ruleData['ds'] if type(ruleData['ds']) is list else [ruleData['ds']]):
                        record['ds'].append({
                            'key': ds['name'].strip() if ds.get('name') else '',
                            'values': []
                        })
                    for ifld in ['pdp_per_row']:
                        record[ifld] = int(rra[ifld]) if ifld in rra and rra[ifld].isdigit() else 0
                    record['step_size'] = record['pdp_per_row'] * result['step']
                    if 'database' in rra and 'row' in rra['database']:
                        last_ts = int(result['lastupdate'] / record['step_size']) *  record['step_size']
                        first_ts = last_ts - ((len(rra['database']['row'])-1) * record['step_size'])
                        record['recorded_time'] = last_ts - first_ts
                        for idx, row in enumerate(rra['database']['row']):
                            this_ts = first_ts + (record['step_size'] * idx)
                            for vidx, v in enumerate(row['v'] if type(row['v']) is list else [row['v']]):
                                if ds_count >= vidx:
                                    record['ds'][vidx]['values'].append([
                                        this_ts * 1000,
                                        float(v) if v not in ['NaN', 'inf'] else 0
                                    ])

                    result['sets'].append(record)

    print(json.dumps(result))
