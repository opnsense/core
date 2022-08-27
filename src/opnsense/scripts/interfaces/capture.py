#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Ad Schellevis <ad@opnsense.org>
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

"""

TEMP_DIR = '/tmp/captures/'

import argparse
import glob
import os

if __name__ == '__main__':
    result = dict()
    parser = argparse.ArgumentParser()
    parser.add_argument('--job', help='job id')
    parser.add_argument('action', help='action to perfom', choices=['list', 'start', 'stop', 'remove'])
    cmd_args = parser.parse_args()

    if os.path.exists(TEMP_DIR):
        all_jobs = glob.glob("%s*.json" % TEMP_DIR)
        print(all_jobs)
