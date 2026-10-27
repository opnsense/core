#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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

    update/download suricata rules
"""

import os
import sys
import syslog
import fcntl
from lib import metadata
from lib import downloader
from lib import rule_source_directory

# check for a running update process, this may take a while so it's better to check...
try:
    lck = open('/tmp/suricata-rule-updater.py', 'w+')
    fcntl.flock(lck, fcntl.LOCK_EX | fcntl.LOCK_NB)
except IOError:
    # already running, exit status 99
    sys.exit(99)

if __name__ == '__main__':
    # load list of configured rules from generated config
    enabled_rulefiles, rule_properties = metadata.load_rule_config()

    # download / remove rules
    md = metadata.Metadata()
    dl = downloader.Downloader(target_dir=rule_source_directory)
    available_rules = list(md.list_rules(rule_properties))
    selected_sources = {
        rule['metadata_source'] for rule in available_rules
        if not rule['required'] and not rule['deprecated'] and rule['filename'] in enabled_rulefiles
    }
    for rule in available_rules:
        if 'url' in rule['source']:
            if dl.is_supported(url=rule['source']['url']):
                full_path = os.path.join(rule_source_directory, rule['filename'])
                # XXX: keep legacy required companion files until plugins declare type="dataset". Remove in 27.1
                legacy_dataset = metadata.is_legacy_dataset(rule)
                if rule['required']:
                    # Required files follow package selection; missing legacy companions are restored as before.
                    if rule['metadata_source'] in selected_sources or \
                            (legacy_dataset and not os.path.isfile(full_path)):
                        enabled_rulefiles.add(rule['filename'])
                if rule['filename'] not in enabled_rulefiles or rule['deprecated']:
                    if os.path.isfile(full_path) and (not legacy_dataset or rule['deprecated']):
                        os.remove(full_path)
                else:
                    if ('username' in rule['source'] and 'password' in rule['source']):
                        auth = (rule['source']['username'], rule['source']['password'])
                    else:
                        auth = None
                    # when metadata supports versioning, check if either version or settings changed before download
                    remote_hash = dl.fetch_version_hash(check_url=rule['version_url'],
                                                        auth=auth, headers=rule['http_headers'])
                    if remote_hash is None or remote_hash != dl.installed_file_hash(rule['filename']):
                        dl.download(url=rule['url'], url_filename=rule['url_filename'],
                                    filename=rule['filename'], auth=auth,
                                    headers=rule['http_headers'], version=remote_hash)
                    else:
                        syslog.syslog(syslog.LOG_NOTICE, 'download skipped %s, same version' % rule['filename'])

    # cleanup: match all installed rulesets against the configured ones and remove uninstalled rules
    md_filenames = [x['filename'] for x in available_rules]
    for filename in enabled_rulefiles:
        full_path = ('%s/%s' % (rule_source_directory, filename)).replace('//', '/')
        if filename not in md_filenames and os.path.isfile(full_path):
            os.remove(full_path)
