#!/usr/bin/env python2.7
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
    script to fetch all suricata rule information into a single json object with the following contents:
        timestamp : (last timestamp of all files)
        files : (number of files)
        rules : all relevant metadata from the rules including the default enabled or disabled state
"""
import os
import os.path
import glob
import json
import sys

# suricata rule settings, source directory and cache json file to use
rule_source_dir='/usr/local/etc/suricata/rules/'
rule_cache_json='%srules.json'%rule_source_dir

# all relevant metadata tags to fetch
all_metadata_tags=['sid','msg','classtype','rev','gid']

# Because rule parsing isn't very useful when the rule definitions didn't change we create a single json file
# to hold the last results (combined with creation date and number of files).
if __name__ == '__main__':
    # collect file metadata
    result_structure = {'timestamp': 0,'files': 0,'rules': []}
    all_rule_files = []
    last_mtime = 0
    for filename in glob.glob('%s*.rules'%(rule_source_dir)):
        file_mtime = os.stat(filename).st_mtime
        if file_mtime > last_mtime:
            last_mtime = file_mtime

        all_rule_files.append(filename)

    result_structure['files'] = len(all_rule_files)
    result_structure['timestamp'] = last_mtime

    # return last known info if nothing has changed
    if os.path.isfile(rule_cache_json):
        try:
            prev_rules_data=open(rule_cache_json,'rb').read()
            prev_rules = json.loads(prev_rules_data)
            if 'timestamp' in prev_rules and 'files' in prev_rules:
                if prev_rules['timestamp'] == result_structure['timestamp'] \
                        and prev_rules['files'] == result_structure['files']:
                    print (prev_rules_data)
                    sys.exit(0)
        except ValueError:
            pass

    # parse all rule files and create json cache file for all data
    for filename in all_rule_files:
        rules = []
        data = open(filename)
        for rule in data.read().split('\n'):
            if rule.find('msg:') != -1:
                record = {'enabled':True, 'source':filename.split('/')[-1]}
                if rule.strip()[0] =='#':
                    record['enabled'] = False

                rule_metadata = rule[rule.find('msg:'):-1]
                for field in rule_metadata.split(';'):
                    fieldName = field[0:field.find(':')].strip()
                    fieldContent = field[field.find(':')+1:].strip()
                    if fieldName in all_metadata_tags:
                        if fieldContent[0] == '"':
                            record[fieldName] = fieldContent[1:-1]
                        else:
                            record[fieldName] = fieldContent
                result_structure['rules'].append(record)

    open(rule_cache_json,'wb').write(json.dumps(result_structure))
    # print json data
    print(result_structure)





