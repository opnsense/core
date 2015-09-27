#!/usr/local/bin/python2.7

"""
    Copyright (c) 2015 Deciso B.V. - Ad Schellevis
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
    fetch xmldata from rrd tool, but only if filename is valid (with or without .rrd extension)

"""
import sys
import glob
import tempfile
import subprocess
import os.path

rrd_reports_dir = '/var/db/rrd'
if len(sys.argv) > 1:
    filename = sys.argv[1]
    # suffix rrd if not already in request
    if filename.split('.')[-1] != 'rrd':
        filename += '.rrd'

    # scan rrd directory for requested file
    for rrdFilename in glob.glob('%s/*.rrd' % rrd_reports_dir):
        if os.path.basename(rrdFilename) == filename:
            with tempfile.NamedTemporaryFile() as output_stream:
                subprocess.check_call(['/usr/local/bin/rrdtool', 'dump', rrdFilename],
                                                      stdout=output_stream, stderr=subprocess.STDOUT)
                output_stream.seek(0)
                print (output_stream.read())
            break
