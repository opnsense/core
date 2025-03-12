"""
    Copyright (c) 2018 Ad Schellevis <ad@opnsense.org>
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
import sys

SECONDS_PER_DAY = 60*60*24

def load_config(config_yaml=None):
    """ setup configuration object
    :param config_yaml:
    :return:
    """
    if config_yaml:
        import yaml
        cnf_input = yaml.safe_load(open(config_yaml, 'r'))
    else:
        cnf_input = dict()

    result = Config(**cnf_input)
    sys.path.insert(0, result.library_path)

    return result


class Config(object):
    """ Simple configuration wrapper for our netflow scripts, containing our defaults
    """
    library_path = '/usr/local/opnsense/site-python'
    pid_filename = '/var/run/flowd_aggregate.pid'
    flowd_source = '/var/log/flowd.log'
    database_dir = '/var/netflow'
    single_pass = False
    history = {
        'FlowInterfaceTotals': {
            30: SECONDS_PER_DAY, # 24 hours
            300: SECONDS_PER_DAY*7, # 7 days
            3600: SECONDS_PER_DAY*31, # 31 days
            86400: SECONDS_PER_DAY*365 # 365 days
        },
        'FlowDstPortTotals': {
            300: 60*60, # 1 hour
            3600: SECONDS_PER_DAY, # 24 hours
            86400: SECONDS_PER_DAY*365 # 365 days
        },
        'FlowSourceAddrTotals': {
            300: 60*60, # 1 hour
            3600: SECONDS_PER_DAY, # 24 hours
            86400: SECONDS_PER_DAY*365 # 365 days
        },
        'FlowSourceAddrDetails': {
            86400: SECONDS_PER_DAY*62 # 62 days
        }
    }

    def __init__(self, **kwargs):
        for key in kwargs:
            if hasattr(self, key):
                setattr(self, key, kwargs[key])
