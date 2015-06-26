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
    shared module for suricata scripts, handles the installed rules cache for easy access
"""
import os
import os.path
import glob
import sqlite3
import shlex

class RuleCache(object):
    """
    """
    def __init__(self):
        # suricata rule settings, source directory and cache json file to use
        self.rule_source_dir = '/usr/local/etc/suricata/rules/'
        self.cachefile = '%srules.sqlite'%self.rule_source_dir
        self._rule_fields = ['sid','msg','classtype','rev','gid','source','enabled','reference']
        self._rule_defaults = {'classtype':'##none##'}

    def listLocal(self):
        all_rule_files=[]
        for filename in glob.glob('%s*.rules'%(self.rule_source_dir)):
            all_rule_files.append(filename)

        return all_rule_files

    def listRules(self, filename):
        """ generator function to list rule file content including metadata
        :param filename:
        :return:
        """
        data = open(filename)
        for rule in data.read().split('\n'):
            rule_info_record = {'rule':rule, 'metadata':None}
            if rule.find('msg:') != -1:
                # define basic record
                record = {'enabled':True, 'source':filename.split('/')[-1]}
                if rule.strip()[0] =='#':
                    record['enabled'] = False

                rule_metadata = rule[rule.find('msg:'):-1]
                for field in rule_metadata.split(';'):
                    fieldName = field[0:field.find(':')].strip()
                    fieldContent = field[field.find(':')+1:].strip()
                    if fieldName in self._rule_fields:
                        if fieldContent[0] == '"':
                            content = fieldContent[1:-1]
                        else:
                            content = fieldContent

                        if fieldName in record:
                            # if same field repeats, put items in list
                            if type(record[fieldName]) != list:
                                record[fieldName] = [record[fieldName]]
                            record[fieldName].append(content)
                        else:
                            record[fieldName] = content

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

    def isChanged(self):
        """ check if rules on disk are probably different from rules in cache
        :return: boolean
        """
        if os.path.exists(self.cachefile):
            last_mtime = 0
            all_rule_files = self.listLocal()
            for filename in all_rule_files:
                file_mtime = os.stat(filename).st_mtime
                if file_mtime > last_mtime:
                    last_mtime = file_mtime

            try:
                db = sqlite3.connect(self.cachefile)
                cur = db.cursor()
                cur.execute('select max(timestamp), max(files) from stats')
                results = cur.fetchall()
                if last_mtime == results[0][0] and len(all_rule_files) == results[0][1]:
                    return False
            except sqlite3.DatabaseError:
                # if some reason the cache is unreadble, continue and report changed
                pass
        return True

    def create(self):
        """ create new cache
        :return: None
        """
        if os.path.exists(self.cachefile):
            os.remove(self.cachefile)

        db = sqlite3.connect(self.cachefile)
        cur = db.cursor()
        cur.execute('create table stats (timestamp number, files number)')
        cur.execute("""create table rules (sid number, msg text, classtype text,
                                           rev integer, gid integer,reference text,
                                           enabled boolean,source text)""")

        last_mtime=0
        all_rule_files = self.listLocal()
        for filename in all_rule_files:
            file_mtime = os.stat(filename).st_mtime
            if file_mtime > last_mtime:
                last_mtime = file_mtime
            rules = []
            for rule_info_record in self.listRules(filename=filename):
                if rule_info_record['metadata'] is not None:
                    rules.append(rule_info_record['metadata'])

            cur.executemany('insert into rules(%(fieldnames)s) '
                            'values (%(fieldvalues)s)'%{'fieldnames':(','.join(self._rule_fields)),
                                                        'fieldvalues':':'+(',:'.join(self._rule_fields))}, rules)
        cur.execute('insert into stats (timestamp,files) values (?,?) ',(last_mtime,len(all_rule_files)))
        db.commit()

    def search(self, limit, offset, filter, sort_by):
        """ search installed rules
        :param limit: limit number of rows
        :param offset: limit offset
        :param filter: text to search, used format fieldname1,fieldname2/searchphrase include % to match on a part
        :param sort: order by, list of fields and possible asc/desc parameter
        :return: dict
        """
        result = {'rows':[]}
        db = sqlite3.connect(self.cachefile)
        cur = db.cursor()

        # construct query including filters
        sql = 'select * from rules '
        sql_filters = {}

        for filtertag in shlex.split(filter):
            fieldnames = filtertag.split('/')[0]
            searchcontent = '/'.join(filtertag.split('/')[1:])
            if len(sql_filters) > 0:
                sql += ' and ( '
            else:
                sql += ' where ( '
            for fieldname in map(lambda x: x.lower().strip(), fieldnames.split(',')):
                if fieldname in self._rule_fields:
                    if fieldname != fieldnames.split(',')[0].strip():
                        sql += ' or '
                    if searchcontent.find('*') == -1:
                        sql += 'cast('+fieldname + " as text) like :"+fieldname+" "
                    else:
                        sql += 'cast('+fieldname + " as text) like '%'|| :"+fieldname+" || '%' "
                    sql_filters[fieldname] = searchcontent.replace('*', '')
                else:
                    # not a valid fieldname, add a tag to make sure our sql statement is valid
                    sql += ' 1 = 1 '
            sql += ' ) '

        # apply sort order (if any)
        sql_sort =[]
        for sortField in sort_by.split(','):
            if sortField.split(' ')[0] in self._rule_fields:
                if sortField.split(' ')[-1].lower() == 'desc':
                    sql_sort.append('%s desc'%sortField.split()[0])
                else:
                    sql_sort.append('%s asc'%sortField.split()[0])

        # count total number of rows
        cur.execute('select count(*) from (%s) a'%sql, sql_filters)
        result['total_rows'] = cur.fetchall()[0][0]

        if len(sql_sort) > 0:
            sql += ' order by %s'%(','.join(sql_sort))

        if str(limit) != '0' and str(limit).isdigit():
            sql += ' limit %s'%(limit)
            if str(offset) != '0' and str(offset).isdigit():
                sql += ' offset %s'%(offset)

        # fetch results
        cur.execute(sql,sql_filters)
        while True:
            row = cur.fetchone()
            if row is None:
                break

            record = {}
            for fieldNum in range(len(cur.description)):
                record[cur.description[fieldNum][0]] = row[fieldNum]
            result['rows'].append(record)

        return result

    def listClassTypes(self):
        """
        :return: list of installed classtypes
        """
        result = []
        db = sqlite3.connect(self.cachefile)
        cur = db.cursor()
        cur.execute('select distinct classtype from rules')
        for record in cur.fetchall():
            result.append(record[0])

        return sorted(result)

