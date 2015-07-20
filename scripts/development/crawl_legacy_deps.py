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
    Generate dependency maps for legacy files.
    To use this script, please install graphviz package ( pkg install graphviz )
"""
import time
import os
import os.path

# set source and target directories
target_directory = '/tmp/legacy/'
src_root = '/usr/local/'


class DependancyCrawler(object):
    """ Legacy dependancy crawler and grapher
    """
    def __init__(self):
        self._all_dependancies = {}

    def fetch_php(self, src_filename):
        # create a new list for this base filename
        # to avoid too much complexity, we will assume that filenames are unique.
        base_filename = os.path.basename(src_filename)
        self._all_dependancies[base_filename] = []

        source_data = open(src_filename).read()
        # fetch all include, include_once, require, require_once statements and
        # add dependancies to object dependancy list.
        for tag in ('include', 'require'):
            data = source_data
            while True:
                startpos = data.find(tag)
                if startpos == -1:
                    break
                else:
                    strlen = data[startpos:].find(';')
                    if strlen > -1:
                        # parse (single) statement, check if this could be an include type command
                        dep_stmt = data[startpos-1:strlen+startpos]
                        if dep_stmt[0] in (' ', '\n'):
                            dep_stmt = dep_stmt[1:].replace("'", '"')
                            if dep_stmt.find('\n') == -1 and dep_stmt.count('"') == 2:
                                dep_filename = dep_stmt.split('"')[1]
                                if dep_filename not in self._all_dependancies[base_filename]:
                                    self._all_dependancies[base_filename].append(dep_filename)
                        data = data[strlen+startpos:]

    def crawl(self, root, analyse_dirs=('www', 'etc', 'captiveportal', 'sbin')):
        """ Crawl through legacy code
        :param root: start crawling at
        :param analyse_dirs: only analyse these directories
        :return: None
        """
        for analyse_dir in analyse_dirs:
            analyse_dir = ('%s/%s' % (root, analyse_dir)).replace('//', '/')
            for wroot, wdirs, wfiles in os.walk(analyse_dir):
                for src_filename in wfiles:
                    src_filename = '%s/%s' % (wroot, src_filename)
                    if src_filename.split('.')[-1] in ('php', 'inc','class') \
                            or open(src_filename).read(1024).find('/bin/php') > -1:
                        self.fetch_php(src_filename)

    def get_total_files(self):
        """ get total number of analysed files
        :return: int
        """
        return len(self._all_dependancies)

    def get_total_dependancies(self):
        """ get total number of dependencies
        :return: int
        """
        count = 0
        for src_filename in self._all_dependancies:
            count += len(self._all_dependancies[src_filename])
        return count

    def get_files(self):
        """ retrieve all analysed files as iterator (ordered by name)
        :return: iterator
        """
        for src_filename in sorted(self._all_dependancies):
            yield src_filename

    def trace(self, src_filename, parent_filename=None, result=None, level=0):
        """ trace dependencies (recursive)
        :param src_filename:
        :param parent_filename:
        :param result:
        :param level:
        :return:
        """
        if result is None:
            result = {}
        if src_filename not in result:
            result[src_filename] = {'level': level, 'dup': list(), 'parent': parent_filename}
        else:
            result[src_filename]['dup'].append(parent_filename)
            return

        if src_filename in self._all_dependancies:
            for dependency in self._all_dependancies[src_filename]:
                self.trace(dependency, src_filename, result, level=level+1)

        return result

    def file_info(self, src_filename):
        """ retrieve file info, like maximum recursive depth and number of duplicate dependencies
        :param src_filename:
        :return:
        """
        result = {'levels': 0,'dup_count':0}
        if src_filename in self._all_dependancies:
            data = self.trace(src_filename)
            for dep_filename in data:
                if data[dep_filename]['level'] > result['levels']:
                    result['levels'] = data[dep_filename]['level']
                result['dup_count'] += len(data[dep_filename]['dup'])

        return result

    def generate_dot(self, filename_to_inspect):
        """ convert trace data to do graph
        :param filename_to_inspect: source filename to generate graph for
        :return: string (dot) data
        """
        trace_data = self.trace(filename_to_inspect)
        result = list()
        result.append('digraph dependencies {')
        result.append('\toverlap=scale;')
        nodes = {}
        for level in range(100):
            for src_filename in trace_data:
                if trace_data[src_filename]['level'] == level:
                    if trace_data[src_filename]['parent'] is not None:
                        result.append('\tedge [color=black style=filled];')
                        result.append('\t"%s" -> "%s" [weight=%d];' % (trace_data[src_filename]['parent'],
                                                                       src_filename, trace_data[src_filename]['level']))
                        if len(trace_data[src_filename]['dup']) > 0:
                            for target in trace_data[src_filename]['dup']:
                                result.append('\tedge [color=red style=dotted];')
                                result.append('\t"%s" -> "%s";' % (target, src_filename))

                    if trace_data[src_filename]['parent'] is None:
                        nodes[src_filename] = '[shape=Mdiamond]'
                    elif len(trace_data[src_filename]['dup']) > 0:
                        nodes[src_filename] = '[shape=box,style=filled,color=".7 .3 1.0"]'
                    else:
                        nodes[src_filename] = '[shape=box]'

        for node in nodes:
            result.append('\t"%s" %s;' % (node, nodes[node]))

        result.append('}')
        return '\n'.join(result)

    @staticmethod
    def generate_index_html(filelist):
        html_body = "<html><head><title></title></head><body><table><tr><th>Name</th></tr>\n%s</body>"
        html_row = '<tr><td><a href="%s">%s</a></td></tr>\n'
        html = html_body % ('\n'.join(map(lambda x: html_row % (x, x), sorted(filelist))))

        return html

# create target directory if not existing
if not os.path.exists(target_directory):
    os.mkdir(target_directory)

# start crawling
crawler = DependancyCrawler()
print '[%.2f] started ' % (time.time())
crawler.crawl(src_root)
print '[%.2f] collected %d dependancies in %d files' % (time.time(),
                                                        crawler.get_total_dependancies(),
                                                        crawler.get_total_files())

# generate graphs
generated_files = list()
for filename in crawler.get_files():
    file_stats = crawler.file_info(filename)
    if file_stats['levels'] > 1:
        print '[%.2f] ... writing %s' % (time.time(), filename)
        dot_filename = ('%s/%s.dot' % (target_directory, filename)).replace('//', '/')
        target_filename = dot_filename.replace('.dot', '.png')
        open(dot_filename, 'w').write(crawler.generate_dot(filename))
        os.system('/usr/local/bin/dot -Tpng %s -o %s ' % (dot_filename, target_filename))
        generated_files.append(os.path.basename(target_filename))
    else:
        # not interested, item has no children.
        print '[%.2f] ... skip %s' % (time.time(), filename)

# write a simple index page for our generated files
open(('%s/index.html' % target_directory).replace('//', '/'), 'w').write(crawler.generate_index_html(generated_files))
print '[%.2f] done (all results in %s)' % (time.time(), target_directory)
