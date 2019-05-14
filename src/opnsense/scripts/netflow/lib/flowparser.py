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
import socket


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

    # extract definition
    field_definition = {
        'tag': 'I',
        'recv_time': '>II',
        'proto_flags_tos': 'BBBB',
        'agent_addr4': 'BBBB',
        'agent_addr6': 'BBBBBBBBBBBBBBBB',
        'src_addr4': 'BBBB',
        'src_addr6': 'BBBBBBBBBBBBBBBB',
        'dst_addr4': 'BBBB',
        'dst_addr6': 'BBBBBBBBBBBBBBBB',
        'gateway_addr4': 'BBBB',
        'gateway_addr6': 'BBBBBBBBBBBBBBBB',
        'srcdst_port': '>HH',
        'packets': '>Q',
        'octets': '>Q',
        'if_indices': '>II',
        'agent_info': '>IIIHH',
        'flow_times': '>II',
        'as_info': 'IIBBH',
        'flow_engine_info': 'HHII'
    }

    def __init__(self, filename):
        self._filename = filename

    @staticmethod
    def calculate_size(fmt):
        fmts = {'B': 1, 'H': 2, 'I': 4, 'Q': 8}
        result = 0
        for key in fmt:
            if key in fmts:
                result += fmts[key]
        return result

    def _parse_binary(self, raw_data, data_fields):
        """ parse binary record
        :param raw_data: binary data record
        :param data_fields: field bitmask, provided by header
        :return: dict
        """
        raw_data_idx = 0
        raw_record = dict()
        for idx in range(len(self.field_definition_order)):
            if pow(2, idx) & data_fields:
                fieldname = self.field_definition_order[idx]
                if fieldname in self.field_definition:
                    fsize = self.calculate_size(self.field_definition[fieldname])
                    content = struct.unpack(self.field_definition[fieldname], raw_data[raw_data_idx:raw_data_idx + fsize])
                    raw_record[fieldname] = content[0] if len(content) == 1 else content
                    raw_data_idx += fsize

        return raw_record

    def __iter__(self):
        """ iterate flowd log file
        :return:
        """
        with open(self._filename, 'rb') as flowh:
            while True:
                # header [version, len_words, reserved, fields]
                hdata = flowh.read(8)
                if hdata == b'':
                    break
                header = struct.unpack('BBHI', hdata)
                record = self._parse_binary(
                    raw_data=flowh.read(header[1] * 4),
                    data_fields=socket.ntohl(header[3])
                )
                record['sys_uptime_ms'] = record['agent_info'][0]
                record['netflow_ver'] = record['agent_info'][3]
                record['recv_sec'] = record['recv_time'][0]
                record['recv_usec'] = record['recv_time'][1]
                if 'proto_flags_tos' in record:
                    record['tcp_flags'] = record['proto_flags_tos'][0]
                    record['protocol'] = record['proto_flags_tos'][1]
                    record['tos'] = record['proto_flags_tos'][2]
                if 'flow_times' in record:
                    record['flow_start'] = record['flow_times'][0]
                    record['flow_finish'] = record['flow_times'][1]
                # concat ipv4/v6 fields into field without [4,6]
                for key in self.field_definition_order:
                    if key in record:
                        if key.endswith('4'):
                            record[key[:-1]] = '.'.join(str(x) for x in record[key])
                        elif key.endswith('6'):
                            record[key[:-1]] = ':'.join(str(x) for x in record[key])

                # calculated values
                record['flow_end'] = record['recv_sec'] - (record['sys_uptime_ms'] - record['flow_finish']) / 1000.0
                record['duration_ms'] = (record['flow_finish'] - record['flow_start'])
                record['flow_start'] = record['flow_end'] - record['duration_ms'] / 1000.0

                yield record
