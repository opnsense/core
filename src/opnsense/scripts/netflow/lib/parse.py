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
    parse flowd log files
"""
import flowd
import glob
import tempfile
import subprocess
import os

# define field
PARSE_FLOW_FIELDS = [
    {'check': flowd.FIELD_OCTETS, 'target': 'octets'},
    {'check': flowd.FIELD_PACKETS, 'target': 'packets'},
    {'check': flowd.FIELD_SRC_ADDR, 'target': 'src_addr'},
    {'check': flowd.FIELD_DST_ADDR, 'target': 'dst_addr'},
    {'check': flowd.FIELD_SRCDST_PORT, 'target': 'src_port'},
    {'check': flowd.FIELD_SRCDST_PORT, 'target': 'dst_port'},
    {'check': flowd.FIELD_PROTO_FLAGS_TOS, 'target': 'protocol'},
    {'check': flowd.FIELD_PROTO_FLAGS_TOS, 'target': 'tcp_flags'},
    {'check': flowd.FIELD_PROTO_FLAGS_TOS, 'target': 'tos'},
    {'check': flowd.FIELD_IF_INDICES, 'target': 'if_ndx_in'},
    {'check': flowd.FIELD_IF_INDICES, 'target': 'if_ndx_out'},
    {'check': flowd.FIELD_GATEWAY_ADDR, 'target': 'gateway_addr'},
    {'check': flowd.FIELD_FLOW_TIMES, 'target': 'netflow_ver'}]


class Interfaces(object):
    """ mapper for local interface index to interface name (1 -> em0 for example)
    """
    def __init__(self):
        """ construct local interface mapping
        """
        self._if_index = dict()
        with tempfile.NamedTemporaryFile() as output_stream:
            subprocess.call(['/sbin/ifconfig', '-l'], stdout=output_stream, stderr=open(os.devnull, 'wb'))
            output_stream.seek(0)
            if_index = 1
            for line in output_stream.read().split('\n')[0].split():
                self._if_index["%s" % if_index] = line
                if_index += 1

    def if_device(self, if_index):
        """ convert index to device (if found)
        """
        if "%s" % if_index in self._if_index:
            # found, return interface name
            return self._if_index["%s" % if_index]
        else:
            # not found, return index
            return "%s" % if_index


def parse_flow(recv_stamp, flowd_source='/var/log/flowd.log'):
    """ parse flowd logs and yield records (dict type)
    :param recv_stamp: last receive timestamp (recv)
    :param flowd_source: flowd logfile
    :return: iterator flow details
    """
    interfaces = Interfaces()
    parse_done = False
    for filename in sorted(glob.glob('%s*' % flowd_source)):
        if parse_done:
            # log file contains older data (recv_stamp), break
            break
        flog = flowd.FlowLog(filename)
        for flow in flog:
            flow_record = dict()
            if flow.has_field(flowd.FIELD_RECV_TIME):
                # receive timestamp
                flow_record['recv'] = flow.recv_sec
                if flow_record['recv'] <= recv_stamp:
                    # do not parse next flow archive (oldest reached)
                    parse_done = True
                    continue
                if flow.has_field(flowd.FIELD_FLOW_TIMES):
                    # calculate flow start, end, duration in ms
                    flow_record['flow_end'] = flow.recv_sec - (flow.sys_uptime_ms - flow.flow_finish) / 1000.0
                    flow_record['duration_ms'] = (flow.flow_finish - flow.flow_start)
                    flow_record['flow_start'] = flow_record['flow_end'] - flow_record['duration_ms'] / 1000.0
                    # handle source data
                    for flow_field in PARSE_FLOW_FIELDS:
                        if flow.has_field(flow_field['check']):
                            flow_record[flow_field['target']] = getattr(flow, flow_field['target'])
                        else:
                            flow_record[flow_field['target']] = None
                    # map interface indexes to actual interface names
                    flow_record['if_in'] = interfaces.if_device(flow_record['if_ndx_in'])
                    flow_record['if_out'] = interfaces.if_device(flow_record['if_ndx_out'])
                yield flow_record
    # send None to mark last record
    yield None
