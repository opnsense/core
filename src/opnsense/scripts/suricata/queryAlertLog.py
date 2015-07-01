#!/usr/local/bin/python2.7
"""
    Copyright (c) 2015 Ad Schellevis

    part of OPNsense (https://www.opnsense.org/)

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
    query suricata alert log
"""
import os.path
import re
import sre_constants
import shlex
import ujson
from lib.log import reverse_log_reader
from lib.params import updateParams
from lib import suricata_alert_log

# handle parameters
parameters = {'limit':'0','offset':'0', 'filter':'','fileid':''}
updateParams(parameters)

# choose logfile by number
if parameters['fileid'].isdigit():
    suricata_log = '%s.%d'%(suricata_alert_log,int(parameters['fileid']))
else:
    suricata_log = suricata_alert_log

if parameters['limit'].isdigit():
    limit = int(parameters['limit'])
else:
    limit = 0

if parameters['offset'].isdigit():
    offset = int(parameters['offset'])
else:
    offset = 0


data_filters = {}
data_filters_comp = {}
for filter in shlex.split(parameters['filter']):
    filterField = filter.split('/')[0]
    if filter.find('/') > -1:
        data_filters[filterField] = '/'.join(filter.split('/')[1:])
        filter_regexp = data_filters[filterField]
        filter_regexp = filter_regexp.replace('*', '.*')
        filter_regexp = filter_regexp.lower()
        try:
            data_filters_comp[filterField] = re.compile(filter_regexp)
        except sre_constants.error:
            # remove illegal expression
            #del data_filters[filterField]
            data_filters_comp[filterField] = re.compile('.*')

# filter one specific log line
if 'filepos' in data_filters and data_filters['filepos'].isdigit():
    log_start_pos = int(data_filters['filepos'])
else:
    log_start_pos = None

# query suricata eve log
result = {'filters':data_filters,'rows':[],'total_rows':0,'origin':suricata_log.split('/')[-1]}
if os.path.exists(suricata_log):
    for line in reverse_log_reader(filename=suricata_log, start_pos=log_start_pos):
        try:
            record = ujson.loads(line['line'])
        except ValueError:
            # can not handle line
            record = {}

        # only process valid alert items
        if 'alert' in record:
            # add position in file
            record['filepos'] = line['pos']
            # flatten structure
            record['alert_sid'] = record['alert']['signature_id']
            record['alert'] = record['alert']['signature']

            # use filters on data (using regular expressions)
            do_output = True
            for filterKeys in data_filters:
                filter_hit = False
                for filterKey in filterKeys.split(','):
                    if record.has_key(filterKey) and data_filters_comp[filterKeys].match(('%s'%record[filterKey]).lower()):
                        filter_hit = True

                if not filter_hit:
                    do_output = False
            if do_output:
                result['total_rows'] += 1
                if (len(result['rows']) < limit or limit == 0) and result['total_rows'] >= offset:
                    result['rows'].append(record)
                elif result['total_rows'] > offset + limit:
                    # do not fetch data until end of file...
                    break

        # only try to fetch one line when filepos is given
        if log_start_pos != None:
            break

# output results
print(ujson.dumps(result))
