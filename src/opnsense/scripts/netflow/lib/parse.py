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
import glob
import tempfile
import subprocess
import os
import re
from lib.flowparser import FlowParser


class Interfaces(object):
    """ mapper for local interface index to interface name (1 -> em0 for example)
    """
    def __init__(self):
        """ construct local interface mapping
        """
        self._if_index = dict()
        sp = subprocess.run(['/usr/local/sbin/ifinfo'], capture_output=True, text=True)
        interfaces = re.findall(r"Interface ([^ ]+)", sp.stdout)

        for index, interface in enumerate(interfaces, start=1):
            self._if_index["%s" % index] = interface

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
        for flow_record in FlowParser(filename, recv_stamp):
            if flow_record['recv_sec'] <= recv_stamp:
                # do not parse next flow archive (oldest reached)
                parse_done = True
                continue
            # map interface indexes to actual interface names
            flow_record['if_in'] = interfaces.if_device(flow_record['if_ndx_in'])
            flow_record['if_out'] = interfaces.if_device(flow_record['if_ndx_out'])

            yield flow_record
    # send None to mark last record
    yield None
