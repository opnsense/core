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

    Install suricata ruleset into opnsense.rules directory
"""
import os
import glob
import os.path
import lib.rulecache
from lib import rule_source_directory

if __name__ == '__main__':
    RuleCache = lib.rulecache.RuleCache()

    rule_target_dir = ('%s../opnsense.rules' % rule_source_directory)
    rule_yaml_list = ('%s../installed_rules.yaml' % rule_source_directory)

    rule_config_fn = ('%s../rules.config' % rule_source_directory)
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
            # change rule if in rule rule updates
            if rule_info_record['metadata'] is not None and 'sid' in rule_info_record['metadata'] \
                    and rule_info_record['metadata']['sid'] in rule_updates:
                # search last comment marker
                for i in range(len(rule_info_record['rule'])):
                    if rule[i] not in ['#', ' ']:
                        break

                # generate altered rule
                if 'enabled' in rule_updates[rule_info_record['metadata']['sid']]:
                    # enabled / disabled in configuration
                    if (rule_updates[rule_info_record['metadata']['sid']]['enabled']) == '0':
                        rule = ('#%s' % rule[i:])
                    else:
                        rule = rule[i:]
                if 'action' in rule_updates[rule_info_record['metadata']['sid']]:
                    # (new) action in configuration
                    new_action = rule_updates[rule_info_record['metadata']['sid']]['action']
                    if rule[0] == '#':
                        rule = '#%s %s' % (new_action, ' '.join(rule.split(' ')[1:]))
                    else:
                        rule = '%s %s' % (new_action, ' '.join(rule.split(' ')[1:]))

            output_data.append(rule)

        # write data to file
        all_installed_files.append(filename.split('/')[-1])
        open('%s/%s' % (rule_target_dir, filename.split('/')[-1]), 'w').write('\n'.join(output_data))

    # flush all written rule filenames into yaml file
    with open(rule_yaml_list, 'w') as f_out:
        f_out.write('%YAML 1.1\n')
        f_out.write('---\n')
        f_out.write('rule-files:\n')
        for installed_file in all_installed_files:
            f_out.write(' - %s\n' % installed_file)

    # cleanup unused files in rule_target_dir, since it's only meant for staging.
    for filename in glob.glob("%s/*.rules" % rule_target_dir):
        if os.path.basename(filename) not in  all_installed_files:
            os.remove(filename)

    # import local changes (if changed)
    RuleCache.create()
