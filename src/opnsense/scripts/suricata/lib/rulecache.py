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

    shared module for suricata scripts, handles the installed rules cache for easy access
"""

import os
import os.path
import glob
import sqlite3
import shlex
import fcntl
import csv
import collections
from configparser import ConfigParser
from lib import rule_source_directory


class RuleCache(object):
    """
    """

    def __init__(self):
        # suricata rule settings, source directory and cache json file to use
        self.cachefile = '%srules.sqlite' % rule_source_directory
        self._rule_fields = ['sid', 'msg', 'rev', 'gid', 'source', 'enabled', 'reference', 'action']

    @staticmethod
    def list_local():
        all_rule_files = []
        for filename in glob.glob('%s*.rules' % rule_source_directory):
            all_rule_files.append(filename)

        return all_rule_files

    def list_local_changes(self):
        rule_updates = {}
        # parse policies
        configured_policies = self.parse_policies()
        policy_config_fn = ('%s../rule-policies.config' % rule_source_directory)
        policy_config_mtime = os.stat(policy_config_fn).st_mtime if os.path.exists(policy_config_fn) else 0
        for filename in self.list_local():
            rule_filename = os.path.basename(filename)
            for rule in self.list_rules(filename):
                if rule['metadata']:
                    rule['metadata']['rulesets'] = rule_filename
                    rule_orig_action = rule['metadata']['action']
                    if 'enabled' not in rule['metadata'] or not rule['metadata']['enabled']:
                        rule['metadata']['action'] = 'disable'
                    policy_match = None
                    for policy_id in configured_policies:
                        is_matched = True
                        for section_name in configured_policies[policy_id]:
                            if not section_name.startswith('__'):
                                src_pointer = rule['metadata']
                                src_section = section_name
                                if section_name.startswith('metadata_'):
                                    # step into metadata
                                    src_pointer = src_pointer['metadata']
                                    src_section = section_name.split('metadata_', 1)[1]
                                rule_data = src_pointer[src_section] if src_section in src_pointer else None
                                if rule_data not in configured_policies[policy_id][section_name]:
                                    is_matched = False
                        if is_matched:
                            policy_match = policy_id
                            break
                    if policy_match:
                        local_change = {
                            'mtime': policy_config_mtime,
                            'policy_id': configured_policies[policy_match]['__policy_id__']
                        }
                        local_change['action'] = rule_orig_action
                        if configured_policies[policy_match]['__target_action__'] == 'disable':
                            local_change['enabled'] = False
                        elif configured_policies[policy_match]['__target_action__'] == 'default':
                            local_change['enabled'] = rule['metadata']['enabled']
                        else:
                            local_change['enabled'] = True
                            local_change['action'] = configured_policies[policy_match]['__target_action__']
                        rule_updates[rule['metadata']['sid']] = local_change

        # parse OPNsense rule config (XXX: migrate to policy)
        rule_config_fn = ('%s../rules.config' % rule_source_directory)
        if os.path.exists(rule_config_fn):
            cnf = ConfigParser()
            cnf.read(rule_config_fn)
            for section in cnf.sections():
                if section[0:5] == 'rule_':
                    sid = section[5:]
                    rule_updates[sid] = {'mtime': policy_config_mtime, 'policy_id': None}
                    for rule_item in cnf.items(section):
                        rule_updates[sid][rule_item[0]] = rule_item[1]
        return rule_updates

    @staticmethod
    def parse_policies():
        policies = dict()
        policy_config_fn = ('%s../rule-policies.config' % rule_source_directory)
        if os.path.exists(policy_config_fn):
            cnf = ConfigParser()
            cnf.read(policy_config_fn)
            for section in cnf.sections():
                if cnf.get(section, 'enabled') == '1':
                    policy_id = "%05d-%s" % (cnf.getint(section, 'prio'), section)
                    policy_content = dict()
                    if cnf.has_option(section, 'content'):
                        for content_item in cnf.get(section, 'content').split(','):
                            tmp = content_item.split('.', 1)
                            if len(tmp) > 1:
                                section_name = 'metadata_%s' % tmp[0]
                                if section_name not in policy_content:
                                    policy_content[section_name] = list()
                                policy_content[section_name].append(tmp[1])
                    for copy_option in ['rulesets', 'action']:
                        if cnf.has_option(section, copy_option) and cnf.get(section, copy_option).strip() != '':
                            policy_content[copy_option] = cnf.get(section, copy_option).split(',')
                    # copy non filter fields
                    for opt in cnf.options(section):
                        if opt.startswith('__'):
                            policy_content[opt] = cnf.get(section, opt)
                    policies[policy_id] = policy_content

        return collections.OrderedDict([(k, policies[k]) for k in sorted(policies.keys())])

    def list_rules(self, filename):
        """ generator function to list rule file content including metadata
        :param filename:
        :return:
        """
        with open(filename, 'r') as f_in:
            source_filename = filename.split('/')[-1]
            for rule in f_in:
                rule_info_record = {'rule': rule.strip(), 'metadata': None}
                msg_pos = rule.find('msg:')
                if msg_pos != -1:
                    # define basic record
                    record = {
                        'enabled': rule[0:20].strip()[0] != '#',
                        'source': source_filename,
                        'sid': None,
                        'rev': None,
                        'gid': None,
                        'msg': None,
                        'reference': None,
                        'classtype': '##none##',
                        'action': rule[0:20].replace('#', '').strip().split()[0],
                        'metadata': dict()
                    }
                    rule_metadata = rule[msg_pos:-1]
                    for section in list(csv.reader([rule_metadata], delimiter=";", escapechar="\\"))[0]:
                        sep = section.find(':')
                        if sep > 0:
                            prop = section[0:sep].strip()
                            value = section[sep+1:].strip(' "')
                            if prop == 'metadata':
                                for mdtag in list(csv.reader([value], delimiter=","))[0]:
                                    parts = mdtag.split(maxsplit=1)
                                    record['metadata'][parts[0]] = parts[1] if len(parts) > 1 else None
                            else:
                                record[prop] = value

                    rule_info_record['metadata'] = record

                yield rule_info_record

    def is_changed(self):
        """ check if rules on disk are probably different from rules in cache
        :return: boolean
        """
        if os.path.exists(self.cachefile):
            last_mtime = 0
            all_rule_files = self.list_local()
            for filename in all_rule_files:
                file_mtime = os.stat(filename).st_mtime
                if file_mtime > last_mtime:
                    last_mtime = file_mtime

            try:
                db = sqlite3.connect(self.cachefile)
                cur = db.cursor()
                cur.execute("select count(*) from sqlite_master WHERE type='table'")
                table_count = cur.fetchall()[0][0]
                cur.execute('SELECT max(timestamp), max(files) FROM stats')
                results = cur.fetchall()
                if last_mtime == results[0][0] and len(all_rule_files) == results[0][1] and table_count == 5:
                    return False
            except sqlite3.DatabaseError:
                # if some reason the cache is unreadble, continue and report changed
                pass
        return True

    def create(self):
        """ create new cache
        :return: None
        """
        # lock create process
        lock = open(self.cachefile + '.LCK', 'w')
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except IOError:
            # other process is already creating the cache, wait, let the other process do it's work and return.
            fcntl.flock(lock, fcntl.LOCK_EX)
            fcntl.flock(lock, fcntl.LOCK_UN)
            return

        # remove existing DB
        if os.path.exists(self.cachefile):
            os.remove(self.cachefile)

        db = sqlite3.connect(self.cachefile)
        db.text_factory = lambda x: str(x, 'utf-8', 'ignore')
        cur = db.cursor()

        cur.execute("create table stats (timestamp number, files number)")
        cur.execute("""create table rules (sid INTEGER, msg TEXT,
                                           rev INTEGER, gid INTEGER, reference TEXT,
                                           enabled BOOLEAN, action text, source TEXT)""")
        cur.execute("create table rule_properties(sid INTEGER, property text, value text) ")
        cur.execute("""create table local_rule_changes(sid number primary key, policy text,
                                                       action text, enabled BOOLEAN, last_mtime number)""")
        last_mtime = 0
        all_rule_files = self.list_local()
        rules_sql = 'insert into rules(%(fieldnames)s) values (%(fieldvalues)s)' % {
            'fieldnames': (','.join(self._rule_fields)),
            'fieldvalues': ':' + (',:'.join(self._rule_fields))
        }
        rule_prop_sql = 'insert into rule_properties(sid, property, value) values (:sid, :property, :value)'
        for filename in all_rule_files:
            file_mtime = os.stat(filename).st_mtime
            if file_mtime > last_mtime:
                last_mtime = file_mtime
            rules = list()
            rule_properties = list()
            for rule_info_record in self.list_rules(filename=filename):
                if rule_info_record['metadata'] is not None:
                    rules.append(rule_info_record['metadata'])
                    for prop in ['classtype']:
                        rule_properties.append({
                            "sid": rule_info_record['metadata']['sid'],
                            "property": prop,
                            "value": rule_info_record['metadata'][prop]
                        })
                    for prop in rule_info_record['metadata']['metadata']:
                        rule_properties.append({
                            "sid": rule_info_record['metadata']['sid'],
                            "property": prop,
                            "value": rule_info_record['metadata']['metadata'][prop]
                        })

            cur.executemany(rules_sql, rules)
            cur.executemany(rule_prop_sql, rule_properties)
        cur.execute('INSERT INTO stats (timestamp,files) VALUES (?,?) ', (last_mtime, len(all_rule_files)))
        cur.execute("""
                create table metadata_histogram as
                select distinct property, value, count(*) number_of_rules
                from  rule_properties
                where property not in ('created_at', 'updated_at')
                group by property, value
        """)
        db.commit()
        # release lock
        fcntl.flock(lock, fcntl.LOCK_UN)
        # import local changes (if changed)
        self.update_local_changes()


    def update_local_changes(self):
        """ read local rules.config containing changes on installed ruleset and update to "local_rule_changes" table
        """
        if os.path.exists(self.cachefile):
            db = sqlite3.connect(self.cachefile)
            cur = db.cursor()
            # make sure only one process is updating this table
            lock = open(self.cachefile + '.LCK', 'w')
            try:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except IOError:
                # other process is already creating the cache, wait, let the other process do it's work and return.
                fcntl.flock(lock, fcntl.LOCK_EX)
                fcntl.flock(lock, fcntl.LOCK_UN)
                return
            # delete and insert local changes
            cur.execute('delete from local_rule_changes')
            local_changes = self.list_local_changes()
            for sid in local_changes:
                cur.execute(
                    'insert into local_rule_changes(sid, action, enabled, last_mtime, policy) values (?,?,?,?,?)',
                    (sid, local_changes[sid]['action'], local_changes[sid]['enabled'], local_changes[sid]['mtime'],
                     local_changes[sid]['policy_id'])
                )
            db.commit()
            # release lock
            fcntl.flock(lock, fcntl.LOCK_UN)


    def search(self, limit, offset, filter_txt, sort_by):
        """ search installed rules
        :param limit: limit number of rows
        :param offset: limit offset
        :param filter_txt: text to search, used format fieldname1,fieldname2/searchphrase include % to match on a part
        :param sort_by: order by, list of fields and possible asc/desc parameter
        :return: dict
        """
        result = {'rows': []}
        if os.path.exists(self.cachefile):
            db = sqlite3.connect(self.cachefile)
            cur = db.cursor()

            # construct query including filters
            sql_parameters = {}
            sql_filters = []
            prop_values = []
            rule_search_fields = ['msg', 'sid', 'source', 'installed_action', 'installed_status']
            for filtertag in shlex.split(filter_txt):
                fieldnames, searchcontent = filtertag.split('/', maxsplit=1)
                sql_item = []
                for fieldname in [x.lower().strip() for x in fieldnames.split(',')]:
                    if fieldname in rule_search_fields:
                        # construct query string for rule table fields
                        if fieldname != fieldnames.split(',')[0].strip():
                            sql_item.append(' or ')
                        if searchcontent.find('*') == -1:
                            sql_item.append('cast(' + fieldname + " as text) like :" + fieldname + " ")
                        else:
                            sql_item.append('cast(' + fieldname + " as text) like '%'|| :" + fieldname + " || '%' ")
                        sql_parameters[fieldname] = searchcontent.replace('*', '')
                    else:
                        # property value combinations per rule are queried from the rule_properties table
                        pfieldnm = "property_%d" % len(prop_values)
                        vfieldnm = "value_%d" % len(prop_values)
                        if searchcontent.find('*') == -1:
                            prop_values.append("property = :%s and value like :%s " % (pfieldnm, vfieldnm))
                        else:
                            prop_values.append("property = :%s and value like  '%'|| :%s  || '%'" % (pfieldnm, vfieldnm))
                        sql_parameters[pfieldnm] = fieldname
                        sql_parameters[vfieldnm] = searchcontent.replace('*', '')

                if len(sql_item) > 0:
                    sql_filters.append("".join(sql_item))

            sql = """select *
                     from (
                         select rules.*,
                                case when rc.action is null then rules.action else rc.action end installed_action,
                                case
                                    when case when rc.enabled is null then rules.enabled else rc.enabled end = true
                                    then 'enabled'
                                    else  'disabled'
                                end installed_status
                         from rules
                  """

            if len(prop_values) > 0:
                sql_1 = "select sid, count(*) from rule_properties where " + " or ".join(prop_values)
                sql_1 += " group by sid"
                sql_1 += " having count(*) = %d " % len(prop_values)
                sql += "inner join (%s) p on p.sid = rules.sid " % sql_1
            sql += "left join local_rule_changes rc on rules.sid = rc.sid ) a"

            if len(sql_filters) > 0:
                sql += ' where ' + " and ".join(list(map(lambda x:" (%s)"%x, sql_filters)))

            # apply sort order (if any)
            sql_sort = []
            for sortField in sort_by.split(','):
                if sortField.split(' ')[0] in rule_search_fields:
                    if sortField.split(' ')[-1].lower() == 'desc':
                        sql_sort.append('%s desc' % sortField.split()[0])
                    else:
                        sql_sort.append('%s asc' % sortField.split()[0])

            # count total number of rows
            cur.execute('select count(*) from (%s) a' % sql, sql_parameters)
            result['total_rows'] = cur.fetchall()[0][0]

            if len(sql_sort) > 0:
                sql += ' order by %s' % (','.join(sql_sort))

            if str(limit) != '0' and str(limit).isdigit():
                sql += ' limit %s' % limit
                if str(offset) != '0' and str(offset).isdigit():
                    sql += ' offset %s' % offset

            # fetch results
            cur.execute(sql, sql_parameters)
            all_sids = []
            for row in cur.fetchall():
                record = {}
                for fieldNum in range(len(cur.description)):
                    record[cur.description[fieldNum][0]] = row[fieldNum]
                result['rows'].append(record)
                if record['sid']:
                    all_sids.append("%d" % record['sid'])

            # extend with collected metadata attributes
            cur.execute("select * from rule_properties where sid in (%s) order by sid" %
                ",".join(all_sids)
            )
            rule_props = dict()
            for row in cur.fetchall():
                if row[0] not in rule_props:
                    rule_props[row[0]] = dict()
                rule_props[row[0]][row[1]] = row[2]

            for record in result['rows']:
                if record['sid'] in rule_props:
                    for fieldname in rule_props[record['sid']]:
                        record[fieldname] = rule_props[record['sid']][fieldname]
        return result

    def list_metadata(self):
        """
        :return: dictionary with known metadata types and values
        """
        result = {}
        if os.path.exists(self.cachefile):
            db = sqlite3.connect(self.cachefile)
            cur = db.cursor()
            cur.execute('SELECT property, value FROM metadata_histogram order by property, value')
            for record in cur.fetchall():
                if record[0] not in result:
                    result[record[0]] = list()
                result[record[0]].append(record[1])
        return result
