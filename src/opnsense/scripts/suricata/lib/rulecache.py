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

    shared module for suricata scripts, handles the installed rules cache for easy access
"""

import os
import os.path
import glob
import sqlite3
import shlex
import fcntl
from configparser import ConfigParser
from lib import rule_source_directory


class RuleCache(object):
    """
    """

    def __init__(self):
        # suricata rule settings, source directory and cache json file to use
        self.cachefile = '%srules.sqlite' % rule_source_directory
        self._rule_fields = ['sid', 'msg', 'classtype', 'rev', 'gid', 'source', 'enabled', 'reference', 'action']
        self._rule_defaults = {'classtype': '##none##'}

    @staticmethod
    def list_local():
        all_rule_files = []
        for filename in glob.glob('%s*.rules' % rule_source_directory):
            all_rule_files.append(filename)

        return all_rule_files

    @staticmethod
    def list_local_changes():
        # parse OPNsense rule config
        rule_config_fn = ('%s../rules.config' % rule_source_directory)
        rule_config_mtime = os.stat(rule_config_fn).st_mtime
        rule_updates = {}
        if os.path.exists(rule_config_fn):
            cnf = ConfigParser()
            cnf.read(rule_config_fn)
            for section in cnf.sections():
                if section[0:5] == 'rule_':
                    sid = section[5:]
                    rule_updates[sid] = {'mtime': rule_config_mtime}
                    for rule_item in cnf.items(section):
                        rule_updates[sid][rule_item[0]] = rule_item[1]
        return rule_updates


    def list_rules(self, filename):
        """ generator function to list rule file content including metadata
        :param filename:
        :return:
        """
        with open(filename, 'r') as f_in:
            for rule in f_in:
                rule_info_record = {'rule': rule.strip(), 'metadata': None}
                if rule.find('msg:') != -1:
                    # define basic record
                    record = {'enabled': True, 'source': filename.split('/')[-1]}
                    if rule.strip()[0] == '#':
                        record['enabled'] = False
                        record['action'] = rule.replace('#', '').strip().split()[0]
                    else:
                        record['action'] = rule.strip().split(' ')[0]

                    rule_metadata = rule[rule.find('msg:'):-1]
                    for field in rule_metadata.split(';'):
                        fieldname = field[0:field.find(':')].strip()
                        fieldcontent = field[field.find(':') + 1:].strip()
                        if fieldname in self._rule_fields:
                            if fieldcontent[0] == '"':
                                content = fieldcontent[1:-1]
                            else:
                                content = fieldcontent

                            if fieldname in record:
                                # if same field repeats, put items in list
                                if type(record[fieldname]) != list:
                                    record[fieldname] = [record[fieldname]]
                                record[fieldname].append(content)
                            else:
                                record[fieldname] = content

                    for rule_field in self._rule_fields:
                        if rule_field not in record:
                            if rule_field in self._rule_defaults:
                                record[rule_field] = self._rule_defaults[rule_field]
                            else:
                                record[rule_field] = None

                    # perform type conversions
                    for fieldname in record:
                        if type(record[fieldname]) == list:
                            record[fieldname] = '\n'.join(record[fieldname])

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
                if last_mtime == results[0][0] and len(all_rule_files) == results[0][1] and table_count == 3:
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
        cur.execute("""create table rules (sid number, msg TEXT, classtype TEXT,
                                           rev INTEGER, gid INTEGER, reference TEXT,
                                           enabled BOOLEAN, action text, source TEXT)""")
        cur.execute("create table local_rule_changes(sid number primary key, action text, last_mtime number)")
        last_mtime = 0
        all_rule_files = self.list_local()
        for filename in all_rule_files:
            file_mtime = os.stat(filename).st_mtime
            if file_mtime > last_mtime:
                last_mtime = file_mtime
            rules = []
            for rule_info_record in self.list_rules(filename=filename):
                if rule_info_record['metadata'] is not None:
                    rules.append(rule_info_record['metadata'])

            cur.executemany('insert into rules(%(fieldnames)s) '
                            'values (%(fieldvalues)s)' % {'fieldnames': (','.join(self._rule_fields)),
                                                          'fieldvalues': ':' + (',:'.join(self._rule_fields))}, rules)
        cur.execute('INSERT INTO stats (timestamp,files) VALUES (?,?) ', (last_mtime, len(all_rule_files)))
        db.commit()
        # release lock
        fcntl.flock(lock, fcntl.LOCK_UN)

    def update_local_changes(self):
        """ read local rules.config containing changes on installed ruleset and update to "local_rule_changes" table
        """
        if os.path.exists(self.cachefile):
            db = sqlite3.connect(self.cachefile)
            cur = db.cursor()
            cur.execute('select max(last_mtime) from local_rule_changes')
            last_mtime = cur.fetchall()[0][0]
            rule_config_mtime = os.stat(('%s../rules.config' % rule_source_directory)).st_mtime
            if rule_config_mtime != last_mtime:
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
                    sql_params = (sid, local_changes[sid]['action'], local_changes[sid]['mtime'])
                    cur.execute('insert into local_rule_changes(sid, action, last_mtime) values (?,?,?)', sql_params)
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
            sql = """select *
                     from (
                         select rules.*, case when rc.action is null then rules.action else rc.action end installed_action
                         from rules
                         left join local_rule_changes rc on rules.sid = rc.sid
                     ) a
                     """
            sql_filters = {}
            additional_search_fields = ['installed_action']
            for filtertag in shlex.split(filter_txt):
                fieldnames = filtertag.split('/')[0]
                searchcontent = '/'.join(filtertag.split('/')[1:])
                if len(sql_filters) > 0:
                    sql += ' and ( '
                else:
                    sql += ' where ( '
                for fieldname in [x.lower().strip() for x in fieldnames.split(',')]:
                    if fieldname in self._rule_fields or fieldname in additional_search_fields:
                        if fieldname != fieldnames.split(',')[0].strip():
                            sql += ' or '
                        if searchcontent.find('*') == -1:
                            sql += 'cast(' + fieldname + " as text) like :" + fieldname + " "
                        else:
                            sql += 'cast(' + fieldname + " as text) like '%'|| :" + fieldname + " || '%' "
                        sql_filters[fieldname] = searchcontent.replace('*', '')
                    else:
                        # not a valid fieldname, add a tag to make sure our sql statement is valid
                        sql += ' 1 = 1 '
                sql += ' ) '

            # apply sort order (if any)
            sql_sort = []
            for sortField in sort_by.split(','):
                if sortField.split(' ')[0] in self._rule_fields or sortField.split(' ')[0] in additional_search_fields:
                    if sortField.split(' ')[-1].lower() == 'desc':
                        sql_sort.append('%s desc' % sortField.split()[0])
                    else:
                        sql_sort.append('%s asc' % sortField.split()[0])

            # count total number of rows
            cur.execute('select count(*) from (%s) a' % sql, sql_filters)
            result['total_rows'] = cur.fetchall()[0][0]

            if len(sql_sort) > 0:
                sql += ' order by %s' % (','.join(sql_sort))

            if str(limit) != '0' and str(limit).isdigit():
                sql += ' limit %s' % limit
                if str(offset) != '0' and str(offset).isdigit():
                    sql += ' offset %s' % offset

            # fetch results
            cur.execute(sql, sql_filters)
            while True:
                row = cur.fetchone()
                if row is None:
                    break

                record = {}
                for fieldNum in range(len(cur.description)):
                    record[cur.description[fieldNum][0]] = row[fieldNum]
                result['rows'].append(record)

        return result

    def list_class_types(self):
        """
        :return: list of installed classtypes
        """
        result = []
        if os.path.exists(self.cachefile):
            db = sqlite3.connect(self.cachefile)
            cur = db.cursor()
            cur.execute('SELECT DISTINCT classtype FROM rules')
            for record in cur.fetchall():
                result.append(record[0])

            return sorted(result)
        else:
            return result
