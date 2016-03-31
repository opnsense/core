#!/usr/local/bin/python2.7
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
    Aggregate flowd data for reporting
"""
import time
import datetime
import os
import sys
from lib.parse import parse_flow
from lib.aggregate import BaseFlowAggregator, AggMetadata
import lib.aggregates

# init metadata (progress maintenance)
metadata = AggMetadata()

# register aggregate classes to stream data to
stream_agg_objects = list()
resolutions = [60, 60*5]
for agg_class in lib.aggregates.get_aggregators():
    for resolution in resolutions:
        stream_agg_objects.append(agg_class(resolution))

# parse flow data and stream to registered consumers
prev_recv=metadata.last_sync()
for flow_record in parse_flow(prev_recv):
    if flow_record is None or prev_recv != flow_record['recv']:
        # commit data on receive timestamp change or last record
        for stream_agg_object in stream_agg_objects:
            stream_agg_object.commit()
        metadata.update_sync_time(prev_recv)
    if flow_record is not None:
        # send to aggregator
        for stream_agg_object in stream_agg_objects:
            stream_agg_object.add(flow_record)
        prev_recv = flow_record['recv']
