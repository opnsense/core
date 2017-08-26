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
    data aggregator loader
"""
import sys
import os
import glob
from lib.aggregate import BaseFlowAggregator

def get_aggregators():
    """ collect and return available aggregators
        :return: list of class references
    """
    result = list()
    for filename in glob.glob('%s/*.py'%os.path.dirname(__file__)):
        filename_base = os.path.basename(filename)
        if filename_base[0:2] != '__':
            module_name = 'lib.aggregates.%s' % '.'.join(filename_base.split('.')[:-1])
            __import__(module_name)
            for clsname in dir(sys.modules[module_name]):
                clshandle = getattr(sys.modules[module_name], clsname)
                if type(clshandle) == type and issubclass(clshandle, BaseFlowAggregator):
                    if hasattr(clshandle, 'target_filename') and clshandle.target_filename is not None:
                        result.append(clshandle)
    return result
