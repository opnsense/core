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
import signal
import glob
sys.path.insert(0, "/usr/local/opnsense/site-python")
from lib.parse import parse_flow
from lib.aggregate import AggMetadata
import lib.aggregates
from daemonize import Daemonize


MAX_FILE_SIZE_MB=10
MAX_LOGS=10


def aggregate_flowd():
    """ aggregate collected flowd data
    :return: None
    """
    # init metadata (progress maintenance)
    metadata = AggMetadata()

    # register aggregate classes to stream data to
    stream_agg_objects = list()
    resolutions = [60, 60*5]
    for agg_class in lib.aggregates.get_aggregators():
        for resolution in agg_class.resolutions():
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

    # expire old data
    for stream_agg_object in stream_agg_objects:
        stream_agg_object.cleanup()
        del stream_agg_object
    del metadata


def check_rotate():
    """ Checks if flowd log needs to be rotated, if so perform rotate.
        We keep [MAX_LOGS] number of logs containing approx. [MAX_FILE_SIZE_MB] data, the flowd data probably contains
        more detailed data then the stored aggregates.
    :return: None
    """
    if os.path.getsize("/var/log/flowd.log")/1024/1024 > MAX_FILE_SIZE_MB:
        # max filesize reached rotate
        filenames = sorted(glob.glob('/var/log/flowd.log.*'), reverse=True)
        file_sequence = len(filenames)
        for filename in filenames:
            sequence = filename.split('.')[-1]
            if sequence.isdigit():
                if file_sequence >= MAX_LOGS:
                    os.remove(filename)
                elif int(sequence) != 0:
                    os.rename(filename, filename.replace('.%s' % sequence, '.%06d' % (int(sequence)+1)))
            file_sequence -= 1
        # rename /var/log/flowd.log
        os.rename('/var/log/flowd.log', '/var/log/flowd.log.000001')
        # signal flowd for new log file
        if os.path.isfile('/var/run/flowd.pid'):
            pid = open('/var/run/flowd.pid').read().strip()
            if pid.isdigit():
                try:
                    os.kill(int(pid), signal.SIGUSR1)
                except OSError:
                    pass


class Main(object):
    def __init__(self):
        """ construct, hook signal handler and run aggregators
        :return: None
        """
        self.running = True
        signal.signal(signal.SIGTERM, self.signal_handler)
        self.run()

    def run(self):
        """ run, endless loop, until sigterm is received
        :return: None
        """
        while self.running:
            # run aggregate
            aggregate_flowd()
            # rotate if needed
            check_rotate()
            # wait for next pass, exit on sigterm
            for i in range(120):
                if self.running:
                    time.sleep(0.5)
                else:
                    break

    def signal_handler(self, sig, frame):
        """ end (run) loop on signal
        :param sig: signal
        :pram frame: frame
        :return: None
        """
        self.running = False


if len(sys.argv) > 1 and 'console' in sys.argv[1:]:
    # command line start
    Main()
else:
    # Daemonize flowd aggregator
    daemon = Daemonize(app="flowd_aggregate", pid='/var/run/flowd_aggregate.pid', action=Main)
    daemon.start()
