"""
    Copyright (c) 2016-2018 Ad Schellevis <ad@opnsense.org>
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
    data aggregator type
"""
from . import BaseFlowAggregator


class FlowSourceAddrTotals(BaseFlowAggregator):
    """ collect source totals
    """
    target_filename = 'src_addr_%06d.sqlite'
    agg_fields = ['if', 'src_addr', 'direction']

    @classmethod
    def resolutions(cls):
        """
        :return: list of sample resolutions
        """
        return [300, 3600, 86400]

    @classmethod
    def history_per_resolution(cls):
        """
        :return: dict sample resolution / expire time (seconds)
        """
        # only save daily totals for a longer period of time, we probably only want to answer questions like
        # "top usage over the last 30 seconds, 5 minutes, etc.."
        return {
            300: 3600,
            3600: 86400,
            86400: cls.seconds_per_day(365)
        }

    def __init__(self, resolution, database_dir='/var/netflow'):
        """
        :param resolution: sample resolution (seconds)
        :return: None
        """
        super(FlowSourceAddrTotals, self).__init__(resolution, database_dir)

    def add(self, flow):
        # most likely service (destination) port
        flow['if'] = flow['if_in']
        flow['direction'] = 'in'
        super(FlowSourceAddrTotals, self).add(flow)
        flow['src_addr'] = flow['dst_addr']
        flow['if'] = flow['if_out']
        flow['direction'] = 'out'
        super(FlowSourceAddrTotals, self).add(flow)


class FlowSourceAddrDetails(BaseFlowAggregator):
    """ collect source details on a daily resolution
    """
    target_filename = 'src_addr_details_%06d.sqlite'
    agg_fields = ['if', 'direction', 'src_addr', 'dst_addr', 'service_port', 'protocol']

    @classmethod
    def resolutions(cls):
        """
        :return: list of sample resolutions
        """
        return [86400]

    @classmethod
    def history_per_resolution(cls):
        """
        :return: dict sample resolution / expire time (seconds)
        """
        return {
            86400: cls.seconds_per_day(62)
        }

    def __init__(self, resolution, database_dir='/var/netflow'):
        """
        :param resolution: sample resolution (seconds)
        :return: None
        """
        super(FlowSourceAddrDetails, self).__init__(resolution, database_dir)

    def add(self, flow):
        # most likely service (destination) port
        flow['service_port'] = min(flow['dst_port'], flow['src_port'])
        flow['if'] = flow['if_in']
        flow['direction'] = 'in'
        super(FlowSourceAddrDetails, self).add(flow)
        # swap source and destination addresses for outgoing traffic
        src_addr = flow['dst_addr']
        flow['dst_addr'] = flow['src_addr']
        flow['src_addr'] = src_addr
        flow['if'] = flow['if_out']
        flow['direction'] = 'out'
        super(FlowSourceAddrDetails, self).add(flow)
