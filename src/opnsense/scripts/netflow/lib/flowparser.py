"""
    Copyright (c) 2019 Ad Schellevis <ad@opnsense.org>
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
    flowd log parser
"""
import struct
import syslog
from socket import inet_ntop, AF_INET, AF_INET6, ntohl


class FlowParser:
    # fields in order of appearance, use bitmask compare
    field_definition_order = [
        'tag',
        'recv_time',
        'proto_flags_tos',
        'agent_addr4',
        'agent_addr6',
        'src_addr4',
        'src_addr6',
        'dst_addr4',
        'dst_addr6',
        'gateway_addr4',
        'gateway_addr6',
        'srcdst_port',
        'packets',
        'octets',
        'if_indices',
        'agent_info',
        'flow_times',
        'as_info',
        'flow_engine_info'
    ]

    # extract definition, integer values are read as rawdata (not parsed)
    field_definition = {
        'tag': 'I',
        'recv_time': '>II',
        'proto_flags_tos': 'BBBB',
        'agent_addr4': 4,
        'agent_addr6': 16,
        'src_addr4': 4,
        'src_addr6': 16,
        'dst_addr4': 4,
        'dst_addr6': 16,
        'gateway_addr4': 4,
        'gateway_addr6': 16,
        'srcdst_port': '>HH',
        'packets': '>Q',
        'octets': '>Q',
        'if_indices': '>II',
        'agent_info': '>IIIHH',
        'flow_times': '>II',
        'as_info': 'IIBBH',
        'flow_engine_info': 'HHII'
    }

    def __init__(self, filename, recv_stamp=None):
        self._filename = filename
        self._recv_stamp = recv_stamp
        # cache formatter vs byte length
        self._fmt_cache = dict()
        # pre-calculate powers of 2
        self._pow = dict()
        for idx in range(len(self.field_definition_order)):
            self._pow[idx] = pow(2, idx)

    def calculate_size(self, fmt):
        if fmt not in self._fmt_cache:
            fmts = {'B': 1, 'H': 2, 'I': 4, 'Q': 8}
            self._fmt_cache[fmt] = 0
            for key in fmt:
                if key in fmts:
                    self._fmt_cache[fmt] += fmts[key]
        return self._fmt_cache[fmt]

    def _parse_binary(self, raw_data, data_fields):
        """ parse binary record
        :param raw_data: binary data record
        :param data_fields: field bitmask, provided by header
        :return: dict
        """
        raw_data_idx = 0
        raw_record = dict()
        for idx in range(len(self.field_definition_order)):
            if self._pow[idx] & data_fields:
                fieldname = self.field_definition_order[idx]
                if fieldname in self.field_definition:
                    if type(self.field_definition[fieldname]) is int:
                        fsize = self.field_definition[fieldname]
                        raw_record[fieldname] = raw_data[raw_data_idx:raw_data_idx + fsize]
                    else:
                        fsize = self.calculate_size(self.field_definition[fieldname])
                        try:
                            content = struct.unpack(
                                self.field_definition[fieldname],
                                raw_data[raw_data_idx:raw_data_idx + fsize]
                            )
                            raw_record[fieldname] = content[0] if len(content) == 1 else content
                        except struct.error as e:
                            # the flowd record doesn't appear to be as expected, log for now.
                            syslog.syslog(syslog.LOG_NOTICE, "flowparser failed to unpack %s (%s)" % (fieldname, e))
                    raw_data_idx += fsize

        return raw_record

    def __iter__(self):
        """ iterate flowd log file
        :return:
        """
        # pre-compile address formatters to save time
        with open(self._filename, 'rb') as flowh:
            while True:
                # header [version, len_words, reserved, fields]
                hdata = flowh.read(8)
                if hdata == b'':
                    break
                header = struct.unpack('BBHI', hdata)
                record = self._parse_binary(
                    raw_data=flowh.read(header[1] * 4),
                    data_fields=ntohl(header[3])
                )
                if 'recv_time' not in record or 'agent_info' not in record:
                    # XXX invalid (empty?) flow record.
                    continue
                record['recv_sec'] = record['recv_time'][0]
                if self._recv_stamp is not None and record['recv_sec'] < self._recv_stamp:
                    # self._recv_stamp can contain the last received timestamp, in which case
                    # we should not return older data. The exact timestamp will be returned, so the
                    # consumer knows it doesn't have to read other, older, flowd log files
                    continue

                record['sys_uptime_ms'] = record['agent_info'][0]
                record['netflow_ver'] = record['agent_info'][3]
                record['recv'] = record['recv_sec']
                record['recv_usec'] = record['recv_time'][1]
                record['if_ndx_in'] = -1
                record['if_ndx_out'] = -1
                record['src_port'] = 0
                record['dst_port'] = 0
                record['protocol'] = 0
                if 'proto_flags_tos' in record:
                    record['tcp_flags'] = record['proto_flags_tos'][0]
                    record['protocol'] = record['proto_flags_tos'][1]
                    record['tos'] = record['proto_flags_tos'][2]
                if 'flow_times' in record:
                    record['flow_start'] = record['flow_times'][0]
                    record['flow_finish'] = record['flow_times'][1]
                else:
                    record['flow_start'] = record['sys_uptime_ms']
                    record['flow_finish'] = record['sys_uptime_ms']

                if 'if_indices' in record:
                    record['if_ndx_in'] = record['if_indices'][0]
                    record['if_ndx_out'] = record['if_indices'][1]
                if 'srcdst_port' in record:
                    record['src_port'] = record['srcdst_port'][0]
                    record['dst_port'] = record['srcdst_port'][1]

                # concat ipv4/v6 fields into field without [4,6]
                for key in self.field_definition_order:
                    if key in record:
                        if key[-1] == '4':
                            record[key[:-1]] = inet_ntop(AF_INET, record[key])
                        elif key[-1] == '6':
                            record[key[:-1]] = inet_ntop(AF_INET6, record[key])
                # calculated values
                record['flow_end'] = record['recv_sec'] - (record['sys_uptime_ms'] - record['flow_finish']) / 1000.0
                record['duration_ms'] = (record['flow_finish'] - record['flow_start'])
                record['flow_start'] = record['flow_end'] - record['duration_ms'] / 1000.0
                if 'packets' not in record or 'octets' not in record or 'src_addr' not in record or 'dst_addr' not in record:
                    # this can't be useful data, skip record
                    continue

                yield record
