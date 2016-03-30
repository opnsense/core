"""
    Copyright (c) 2016 Ad Schellevis
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

# location of flowd logfiles to use
FLOWD_LOG_FILES = '/var/log/flowd.log*'


def parse_flow(recv_stamp):
    """ parse flowd logs and yield records (dict type)
    :param recv_stamp: last receive timestamp (recv)
    :return: iterator flow details
    """
    parse_done = False
    for filename in sorted(glob.glob(FLOWD_LOG_FILES)):
        if parse_done:
            # log file contains older data (recv_stamp), break
            break
        flog = flowd.FlowLog(filename)
        for flow in flog:
            flow_record = dict()
            if flow.has_field(flowd.FIELD_RECV_TIME):
                # receive timestamp
                flow_record['recv'] = flow.recv_sec + flow.recv_usec / 1000.0
                if flow_record['recv'] <= recv_stamp:
                    # do not parse next flow archive (oldest reached)
                    parse_done = True
                    continue
                if flow.has_field(flowd.FIELD_FLOW_TIMES):
                    # calculate flow start, end, duration in ms
                    flow_record['flow_end'] = (flow.recv_sec - flow.flow_finish / 1000.0)
                    flow_record['duration_ms'] = (flow.flow_finish - flow.flow_start)
                    flow_record['flow_start'] = flow_record['flow_end'] - flow_record['duration_ms'] / 1000.0
                    # handle source data
                    for flow_field in PARSE_FLOW_FIELDS:
                        if flow.has_field(flow_field['check']):
                            flow_record[flow_field['target']] = getattr(flow, flow_field['target'])
                        else:
                            flow_record[flow_field['target']] = None
                yield flow_record
    # send None to mark last record
    yield None
