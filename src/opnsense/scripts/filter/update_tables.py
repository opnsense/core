#!/usr/local/bin/python3

"""
    Copyright (c) 2017-2023 Ad Schellevis <ad@opnsense.org>
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
    update aliases
"""

import os
import sys
import argparse
import json
import xml.etree.cElementTree as ET
import syslog
import glob
from lib.alias import AliasParser
from lib.alias.pf import PF
from lib.alias.geoip import GEOIP


if __name__ == '__main__':
    result = {'status': 'ok'}
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', help='output type [json/text]', default='json')
    parser.add_argument('--source_conf', help='configuration xml', default='/usr/local/etc/filter_tables.conf')
    parser.add_argument('--aliases', help='aliases to update (targetted), comma separated', type=lambda x: x.split(','))
    parser.add_argument('--types', help='alias types to update (comma seperated)', type=lambda x: x.split(','))
    parser.add_argument('--quick', help='quick mode, skip size comparison', default=False, action="store_true")

    inputargs = parser.parse_args()
    syslog.openlog('firewall', facility=syslog.LOG_LOCAL4)

    # make sure our target directory exists
    if not os.path.isdir('/var/db/aliastables'):
        os.makedirs('/var/db/aliastables')

    # make sure we download geoip data if not found. Since aliases only will trigger a download when change requires it
    if not os.path.isfile('/usr/local/share/GeoIP/alias.stats'):
        GEOIP().download()

    try:
        source_tree = ET.ElementTree(file=inputargs.source_conf)
    except ET.ParseError as e:
        syslog.syslog(syslog.LOG_ERR, 'filter table parse error (%s) %s' % (str(e), inputargs.source_conf))
        sys.exit(-1)

    aliases = AliasParser(source_tree)
    aliases.read()

    # collect "to_update" list, when not set (None) we're planning to update all following normal lifetime rules.
    to_update = None
    if inputargs.aliases is not None or inputargs.types is not None:
        to_update = inputargs.aliases if inputargs.aliases is not None else []
        if inputargs.types is not None:
            for alias in aliases:
                if alias.get_type() in inputargs.types:
                    to_update.append(alias.get_name())

    use_cached = lambda x: to_update is not None and x not in to_update
    for alias in aliases:
        # determine if an alias has expired or updated and collect full chain of dependencies (so we can resolve them).
        alias_changed_or_expired = max(alias.changed(), alias.expired())
        alias_resolve_list = [alias]
        for related_alias_name in aliases.get_alias_deps(alias.get_name()):
            if related_alias_name != alias.get_name():
                rel_alias = aliases.get(related_alias_name)
                if rel_alias:
                    alias_resolve_list.append(rel_alias)
                    alias_changed_or_expired = max(alias_changed_or_expired, rel_alias.changed(), rel_alias.expired())

        if inputargs.quick and not alias_changed_or_expired and (
            alias.get_pf_addr_count() != 0 or alias.get_file_size() == 0
        ):
            # quick mode, alias not changed or expired and target pf table contains some data
            continue

        # only try to replace the contents of this alias if we're responsible for it (know how to parse)
        if alias.get_parser():
            # query alias content, includes dependencies
            alias_content = set()
            for item in alias_resolve_list:
                alias_content = alias_content.union(item.cached() if use_cached(item.get_name()) else item.resolve())

            # when the alias or any of it's dependencies has changed, generate new
            alias_filename = alias.get_filename()
            if alias_changed_or_expired or not os.path.isfile(alias_filename):
                alias_content_str = '\n'.join(sorted(alias_content))
                if  not os.path.isfile(alias_filename) or alias_content_str != alias.read_alias_file(alias_filename):
                    # read before write, only save when the contents have changed
                    open(alias_filename, 'w').write(alias_content_str)

            # use  current alias content when not trying to update a targetted list
            cnt_alias_content = len(alias_content)
            cnt_alias_pf_content = alias.get_pf_addr_count() if to_update is None else cnt_alias_content

            if (cnt_alias_content != cnt_alias_pf_content or alias_changed_or_expired):
                # if the alias is changed, expired or the one in memory has a different number of items, load table
                if cnt_alias_content == 0:
                    if cnt_alias_pf_content > 0:
                        # flush when target is empty
                        PF.flush(alias.get_name())
                else:
                    # replace table contents with collected alias
                    error_output = PF.replace(alias.get_name(), alias_filename)
                    if error_output.find('pfctl: ') > -1:
                        error_message = "Error loading alias [%s]: %s {current_size: %d, new_size: %d}" % (
                            alias.get_name(),
                            error_output.replace('pfctl: ', ''),
                            cnt_alias_pf_content,
                            cnt_alias_content,
                        )
                        result['status'] = 'error'
                        if 'messages' not in result:
                            result['messages'] = list()
                        if error_output not in result['messages']:
                            result['messages'].append(error_message)
                            syslog.syslog(syslog.LOG_NOTICE, error_message)
        else:
            # We are not resolving these aliases and are not using their contents,
            # they are not being used in any of the ones we manage, we still need to call pre_process()
            # to assure aliases may be populated using the pre processing hook (currently only interface_net)
            alias.pre_process(True)


    # cleanup removed aliases when reloading all
    if to_update is None:
        registered_aliases = [alias.get_name() for alias in aliases if alias.is_managed()]
        to_remove = list()
        to_remove_files = dict()
        for filename in glob.glob('/var/db/aliastables/*.txt'):
            aliasname = os.path.basename(filename).split('.')[0]
            if aliasname not in registered_aliases:
                if aliasname not in to_remove_files:
                    to_remove_files[aliasname] = list()
                # in order to remove files the alias should either be managed externally or not exist at all
                if aliasname not in to_remove and (filename.find('.md5.') > 0 or aliases.get(aliasname) is None):
                    # only remove files if there's a checksum
                    to_remove.append(aliasname)
                to_remove_files[aliasname].append(filename)
        for aliasname in to_remove:
            syslog.syslog(syslog.LOG_NOTICE, 'remove old alias %s' % aliasname)
            PF.remove(aliasname)
            for filename in to_remove_files[aliasname]:
                os.remove(filename)

    print (json.dumps(result))
