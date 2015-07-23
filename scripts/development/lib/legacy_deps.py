"""
    Copyright (c) 2015 Ad Schellevis
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
    Crawler class to find module (require/include) dependencies
"""
import os
import os.path

class DependancyCrawler(object):
    """ Legacy dependency crawler and grapher
    """
    def __init__(self, root):
        """ init
        :param root: start crawling at
        :return:
        """
        self._all_dependencies = {}
        self._all_dependencies_src = {}
        self._all_functions = {}
        self._exclude_deps = ['/usr/local/opnsense/mvc/app/config/config.php']
        self.root = root

    def get_dependency_by_src(self, src_filename):
        """ dependencies are stored by a single name, this method maps a filename back to it's name
                usually the basename of the file.
        :param src_filename:
        :return:
        """
        if src_filename in self._all_dependencies_src:
            return self._all_dependencies_src[src_filename]
        else:
            return None

    def fetch_php_modules(self, src_filename):
        # create a new list for this base filename
        base_filename = os.path.basename(src_filename)
        if base_filename in self._all_dependencies:
            base_filename = '%s__%s' % (src_filename.split('/')[-2], base_filename)
        self._all_dependencies[base_filename] = []
        self._all_dependencies_src[src_filename] = base_filename

        source_data = open(src_filename).read()
        # fetch all include, include_once, require, require_once statements and
        # add dependencies to object dependency list.
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
                                if dep_filename not in self._all_dependencies[base_filename]:
                                    if dep_filename not in self._exclude_deps:
                                        self._all_dependencies[base_filename].append(dep_filename)
                        data = data[strlen+startpos:]

    def fetch_php_functions(self, src_filename):
        """ find php functions
        :param src_filename:
        :return:
        """
        base_filename = os.path.basename(src_filename)
        if base_filename in self._all_functions:
            base_filename = '%s__%s' % (src_filename.split('/')[-2], base_filename)

        function_list = []
        for line in open(src_filename,'r').read().split('\n'):
            if line.find('function ') > -1 and line.find('(') > -1:
                if line.find('*') > -1 and line.find('function') > line.find('*'):
                    continue
                function_nm = line.split('(')[0].strip().split(' ')[-1].strip()
                function_list.append(function_nm)

        self._all_functions[base_filename] = function_list

    def find_files(self, analyse_dirs=('etc','www', 'captiveportal', 'sbin')):
        """
        :param analyse_dirs: directories to analyse
        :return:
        """
        for analyse_dir in analyse_dirs:
            analyse_dir = ('%s/%s' % (self.root, analyse_dir)).replace('//', '/')
            for wroot, wdirs, wfiles in os.walk(analyse_dir):
                for src_filename in wfiles:
                    src_filename = '%s/%s' % (wroot, src_filename)
                    if src_filename.split('.')[-1] in ('php', 'inc','class') \
                            or open(src_filename).read(1024).find('/bin/php') > -1:
                        yield src_filename

    def crawl(self):
        """ Crawl through legacy code
        :param analyse_dirs: only analyse these directories
        :return: None
        """
        for src_filename in self.find_files():
            self.fetch_php_modules(src_filename)
            self.fetch_php_functions(src_filename)

    def where_used(self, src):
        """
        :param src: source object name (base name)
        :return: dictionary containing files and functions
        """
        where_used_lst={}
        for src_filename in self.find_files():
            data = open(src_filename,'r').read().replace('\n',' ').replace('\t',' ').replace('@',' ')
            use_list = []
            for function in self._all_functions[src]:
                if data.find(' %s(' % (function)) > -1 or \
                                data.find('!%s ' % (function)) > -1 or \
                                data.find('!%s(' % (function)) > -1 or \
                                data.find('(%s(' % (function)) > -1 or \
                                data.find('(%s ' % (function)) > -1 or \
                                data.find(' %s ' % (function)) > -1:
                    use_list.append(function)

            if len(use_list) > 0:
                where_used_lst[src_filename] = sorted(use_list)

        return where_used_lst

    def get_total_files(self):
        """ get total number of analysed files
        :return: int
        """
        return len(self._all_dependencies)

    def get_total_dependencies(self):
        """ get total number of dependencies
        :return: int
        """
        count = 0
        for src_filename in self._all_dependencies:
            count += len(self._all_dependencies[src_filename])
        return count

    def get_files(self):
        """ retrieve all analysed files as iterator (ordered by name)
        :return: iterator
        """
        for src_filename in sorted(self._all_dependencies):
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

        if src_filename in self._all_dependencies:
            for dependency in self._all_dependencies[src_filename]:
                self.trace(dependency, src_filename, result, level=level+1)

        return result

    def file_info(self, src_filename):
        """ retrieve file info, like maximum recursive depth and number of duplicate dependencies
        :param src_filename:
        :return:
        """
        result = {'levels': 0,'dup_count':0}
        if src_filename in self._all_dependencies:
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
