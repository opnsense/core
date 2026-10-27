#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2020 Ad Schellevis <ad@opnsense.org>
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

    Install suricata ruleset into opnsense.rules directory
"""
import os
import glob
import os.path
import shutil
import lib.rulecache
from lib import metadata
from lib import rule_source_directory

if __name__ == '__main__':
    RuleCache = lib.rulecache.RuleCache()

    rule_target_dir = ('%s../opnsense.rules' % rule_source_directory)
    rule_yaml_list = ('%s../installed_rules.yaml' % rule_source_directory)

    # parse OPNsense rule config
    rule_updates = RuleCache.list_local_changes()

    # create target rule directory if not existing
    if not os.path.exists(rule_target_dir):
        os.mkdir(rule_target_dir, 0o755)

    # install ruleset
    all_installed_files = []
    for filename in RuleCache.list_local():
        output_data = []
        for rule_info_record in RuleCache.list_rules(filename=filename):
            # default behavior, do not touch rule, only copy to output
            rule = rule_info_record['rule']
            # change rule if in rule updates
            if rule_info_record['metadata'] is not None and 'sid' in rule_info_record['metadata'] \
                    and rule_info_record['metadata']['sid'] in rule_updates:
                # search last comment marker
                for i in range(len(rule_info_record['rule'])):
                    if rule[i] not in ['#', ' ']:
                        break

                # generate altered rule
                if 'enabled' in rule_updates[rule_info_record['metadata']['sid']]:
                    # enabled / disabled in configuration
                    if not rule_updates[rule_info_record['metadata']['sid']]['enabled']:
                        rule = ('#%s' % rule[i:])
                    else:
                        rule = rule[i:]
                if 'action' in rule_updates[rule_info_record['metadata']['sid']]:
                    # (new) action in configuration
                    flowbits_noalert = rule_info_record['rule'].replace(' ', '').find('flowbits:noalert;') > -1
                    new_action = rule_updates[rule_info_record['metadata']['sid']]['action']
                    if flowbits_noalert:
                        # prevent flowbits:noalert from being dropped
                        pass
                    elif rule[0] == '#':
                        rule = '#%s %s' % (new_action, ' '.join(rule.split(' ')[1:]))
                    else:
                        rule = '%s %s' % (new_action, ' '.join(rule.split(' ')[1:]))

            output_data.append(rule)

        # write data to file
        installed_filename = filename.split('/')[-1]
        all_installed_files.append(installed_filename)
        open('%s/%s' % (rule_target_dir, installed_filename), 'w').write('\n'.join(output_data))

    # flush all written rule filenames into yaml file
    with open(rule_yaml_list, 'w') as f_out:
        f_out.write('%YAML 1.1\n')
        f_out.write('---\n')
        f_out.write('rule-files:\n')
        for installed_file in all_installed_files:
            f_out.write(' - %s\n' % installed_file)

    # install downloaded datasets next to the rule files that load them
    _, rule_properties = metadata.load_rule_config()
    for rule in metadata.Metadata().list_rules(rule_properties):
        # XXX: stage legacy required companion files until plugins declare type="dataset".
        # is_legacy_dataset() check can be removed in 27.1
        if (rule['type'] != 'dataset' and not metadata.is_legacy_dataset(rule)) or rule['deprecated']:
            continue
        installed_filename = rule['filename']
        filename = os.path.join(rule_source_directory, installed_filename)
        if os.path.isfile(filename) and not os.path.islink(filename):
            all_installed_files.append(installed_filename)
            with open(filename, 'rb') as f_in:
                marker = b'#@opnsense_download_hash:'
                # read to determine if marker is present, we cap the amount read
                # since json-format datasets may contain large single-line json objects
                first_line = f_in.readline(1024)
                with open(os.path.join(rule_target_dir, installed_filename), 'wb') as f_out:
                    if not (first_line.startswith(marker) and first_line.endswith(b'\n')):
                        f_out.write(first_line)
                    shutil.copyfileobj(f_in, f_out)

    # cleanup unused files in rule_target_dir, since it's only meant for staging.
    for filename in glob.glob("%s/*" % rule_target_dir):
        if os.path.basename(filename) not in all_installed_files and \
                (os.path.isfile(filename) or os.path.islink(filename)):
            os.remove(filename)

    # import local changes
    RuleCache.create()
