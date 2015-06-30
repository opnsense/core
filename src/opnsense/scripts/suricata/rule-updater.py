#!/usr/local/bin/python2.7
"""
    Copyright (c) 2015 Ad Schellevis

    part of OPNsense (https://www.opnsense.org/)

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
    update suricata rules
"""
import os
from ConfigParser import ConfigParser
from lib import metadata
from lib import downloader

if __name__ == '__main__':
    # load list of configured rules from generated config
    enabled_rulefiles=[]
    updater_conf='/usr/local/etc/suricata/rule-updater.config'
    target_directory='/usr/local/etc/suricata/rules/'
    if os.path.exists(updater_conf):
        cnf = ConfigParser()
        cnf.read(updater_conf)
        for section in cnf.sections():
            if cnf.has_option(section,'enabled') and cnf.getint(section,'enabled') == 1:
                enabled_rulefiles.append(section.strip())

    # download / remove rules
    md = metadata.Metadata()
    dl = downloader.Downloader(target_dir=target_directory)
    for rule in md.list_rules():
        if 'url' in rule['source']:
            download_proto=str(rule['source']['url']).split(':')[0].lower()
            if dl.is_supported(download_proto):
                if rule['filename'] not in enabled_rulefiles:
                    try:
                        # remove configurable but unselected file
                        os.remove(('%s/%s'%(target_directory, rule['filename'])).replace('//', '/'))
                    except:
                        pass
                else:
                    url = ('%s/%s'%(rule['source']['url'],rule['filename']))
                    dl.download(proto=download_proto, url=url)

