#!/usr/local/bin/python2.7

"""
    Copyright (c) 2017 Ad Schellevis <ad@opnsense.org>
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
    read filter log, limit by number of records or last received digest (md5 hash of row)
"""
import os
import sys
import md5
import argparse
import ujson
import tempfile
import subprocess
sys.path.insert(0, "/usr/local/opnsense/site-python")
from log_helper import reverse_log_reader, fetch_clog
from params import update_params

filter_log = '/var/log/filter.log'

# define log layouts, every endpoint contains all options
# source : https://github.com/opnsense/ports/blob/master/opnsense/filterlog/files/description.txt
fields_general = 'rulenr,subrulenr,anchorname,ridentifier,interface,reason,action,dir,version'.split(',')
fields_ipv4 = fields_general + 'tos,ecn,ttl,id,offset,ipflags,proto,protoname,length,src,dst'.split(',')
fields_ipv4_udp = fields_ipv4 + 'srcport,dstport,datalen'.split(',')
fields_ipv4_tcp = fields_ipv4 + 'srcport,dstport,datalen,tcpflags,seq,ack,urp,tcpopts'.split(',')
fields_ipv4_carp = fields_ipv4 + 'type,ttl,vhid,version,advskew,advbase'.split(',')

fields_ipv6 = fields_general + 'class,flowlabel,hlim,protoname,proto,payload-length,src,dst'.split(',')
fields_ipv6_udp = fields_ipv6 + 'srcport,dstport,datalen'.split(',')
fields_ipv6_tcp = fields_ipv6 + 'srcport,dstport,datalen,tcpflags,seq,ack,urp,tcpopts'.split(',')
fields_ipv6_carp = fields_ipv6 + 'type,ttl,vhid,version2,advskew,advbase'.split(',')

def update_rule(target, metadata_target, ruleparts, spec):
    """ update target rule with parts in spec
        :param target: target rule
        :param metadata_target: collected metadata
        :param ruleparts: list of rule items
        :param spec: full rule specification, depending on protocol and version
    """
    while len(target) < len(spec) and len(ruleparts) > 0:
        target[spec[len(target)]] = ruleparts.pop(0)
    # full spec
    metadata_target['__spec__'] = spec

def fetch_rules_descriptions():
    """ Fetch rule descriptions from the current running config if available
        :return : rule details per line number
    """
    result = dict()
    if os.path.isfile('/tmp/rules.debug'):
        with tempfile.NamedTemporaryFile() as output_stream:
            subprocess.call(['/sbin/pfctl', '-vvPnf', '/tmp/rules.debug'], stdout=output_stream, stderr=open(os.devnull, 'wb'))
            output_stream.seek(0)
            for line in output_stream.read().strip().split('\n'):
                if line.startswith('@'):
                    line_id = line.split()[0][1:]
                    result[line_id] = {'label': ''.join(line.split(' label ')[-1:]).strip()[1:-1]}
    return result


if __name__ == '__main__':
    # read parameters
    parameters = {'limit': '0', 'digest': ''}
    update_params(parameters)
    parameters['limit'] = int(parameters['limit'])

    # parse current running config
    running_conf_descr = fetch_rules_descriptions()

    result = list()
    for record in reverse_log_reader(fetch_clog(filter_log)):
        if record['line'].find('filterlog') > -1:
            rule = dict()
            metadata = dict()
            # rule metadata (unique hash, hostname, timestamp)
            tmp = record['line'].split('filterlog:')[0].split()
            metadata['__digest__'] = md5.new(record['line']).hexdigest()
            metadata['__host__'] = tmp.pop()
            metadata['__timestamp__'] = ' '.join(tmp)
            rulep = record['line'].split('filterlog:')[1].strip().split(',')
            update_rule(rule, metadata, rulep, fields_general)

            if 'version' in rule:
                if rule['version'] == '4':
                    update_rule(rule, metadata, rulep, fields_ipv4)
                    if 'proto' in rule:
                        if rule['proto'] == '17': # UDP
                            update_rule(rule, metadata, rulep, fields_ipv4_udp)
                        elif rule['proto'] == '6': # TCP
                            update_rule(rule, metadata, rulep, fields_ipv4_tcp)
                        elif rule['proto'] == '112': # CARP
                            update_rule(rule, metadata, rulep, fields_ipv4_carp)
                elif rule['version'] == '6':
                    update_rule(rule, metadata, rulep, fields_ipv6)
                    if 'proto' in rule:
                        if rule['proto'] == '17': # UDP
                            update_rule(rule, metadata, rulep, fields_ipv6_udp)
                        elif rule['proto'] == '6': # TCP
                            update_rule(rule, metadata, rulep, fields_ipv6_tcp)
                        elif rule['proto'] == '112': # CARP
                            update_rule(rule, metadata, rulep, fields_ipv6_carp)

            rule.update(metadata)
            if 'rulenr' in rule and rule['rulenr'] in running_conf_descr:
                rule['label'] = running_conf_descr[rule['rulenr']]['label']
            result.append(rule)

            # handle exit criteria, row limit or last digest
            if parameters['limit'] != 0 and len(result) >= parameters['limit']:
                break
            elif parameters['digest'].strip() != '' and parameters['digest'] == rule['__digest__']:
                break


    print (ujson.dumps(result))
