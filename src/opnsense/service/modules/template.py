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

    package : configd
    function: template handler, generate configuration files using templates
"""

import os
import os.path
import glob
import stat
import syslog
import collections
import traceback
import copy
import codecs
import jinja2
from .addons import template_helpers
from . import syslog_error, syslog_notice

__author__ = 'Ad Schellevis'


class Template(object):
    def __init__(self, target_root_directory="/"):
        """ constructor
        :return:
        """
        # init config (config.xml) data
        self._config = {}

        # set target root
        self._target_root_directory = target_root_directory

        # setup jinja2 environment
        self._template_dir = os.path.dirname(os.path.abspath(__file__)) + '/../templates/'
        self._j2_env = jinja2.Environment(loader=jinja2.FileSystemLoader(self._template_dir), trim_blocks=True,
                                          extensions=["jinja2.ext.do", "jinja2.ext.loopcontrols"])
        # register additional filters
        self._j2_env.filters['decode_idna'] = lambda x:x.decode('idna')
        self._j2_env.filters['encode_idna'] = self._encode_idna

    @staticmethod
    def _encode_idna(x):
        """ encode string to idna, preserve leading dots
        """
        try:
            tmp = b''.join([b''.join([b'.' for x in range(len(x) - len(x.lstrip('.')))]), x.lstrip('.').encode('idna')])
            return tmp.decode()
        except UnicodeError:
            # return source when unable to decode
            return x

    def list_module(self, module_name):
        """ list single module content
        :param module_name: module name in dot notation ( company.module )
        :return: dictionary with module data
        """
        result = {'+TARGETS': dict(), '+CLEANUP_TARGETS': dict()}
        file_path = '%s/%s' % (self._template_dir, module_name.replace('.', '/'))
        target_sources = ['%s/+TARGETS' % file_path]
        if os.path.exists('%s/+TARGETS.D' % file_path):
            for filename in sorted(glob.glob('%s/+TARGETS.D/*.TARGET' % file_path)):
                target_sources.append(filename)

        for target_source in target_sources:
            if os.path.exists(target_source):
                with open(target_source, 'r') as fhandle:
                    for line in fhandle.read().split('\n'):
                        parts = line.split(':')
                        if len(parts) > 1 and parts[0].strip()[0] != '#':
                            source_file = parts[0].strip()
                            target_name = parts[1].strip()
                            if target_name in list(result['+TARGETS'].values()):
                                syslog_notice("template overlay %s with %s" % (
                                    target_name, os.path.basename(target_source)
                                ))
                            result['+TARGETS'][source_file] = target_name
                            if len(parts) == 2:
                                result['+CLEANUP_TARGETS'][source_file] = target_name
                            elif parts[2].strip() != "":
                                result['+CLEANUP_TARGETS'][source_file] = parts[2].strip()
        return result

    def list_modules(self):
        """ traverse template directory and list all modules
        the template directory is structured like Manufacturer/Module/config_files

        :return: list (dict) of registered modules
        """
        result = list()
        for root, dirs, files in os.walk(self._template_dir):
            if root.count('/') > self._template_dir.count('/'):
                module_name = root.replace(self._template_dir, '')
                result.append(module_name)

        return result

    def set_config(self, config_data):
        """ set config data
        :param config_data: config data as dictionary/list structure
        :return: None
        """
        if type(config_data) in (dict, collections.OrderedDict):
            self._config = config_data
        else:
            # no data given, reset
            self._config = {}

    @staticmethod
    def __find_string_tags(instr):
        """
        :param instr: string with optional tags [field.$$]
        :return:
        """
        retval = []
        for item in instr.split('['):
            if item.find(']') > -1:
                retval.append(item.split(']')[0])

        return retval

    def __find_filters(self, tags):
        """ match tags to config and construct a dictionary which we can use to construct the output filenames
        :param tags: list of tags [xmlnode.xmlnode.%.xmlnode,xmlnode]
        :return: dictionary containing key (tagname) value {existing node key, value}
        """
        result = {}
        for tag in tags:
            result[tag] = {}
            # first step, find wildcard to replace ( if any )
            # ! we only support one wildcard per tag at the moment, should be enough for most situations
            config_ptr = self._config
            target_keys = []
            for xmlNodeName in tag.split('.'):
                if xmlNodeName in config_ptr:
                    config_ptr = config_ptr[xmlNodeName]
                elif xmlNodeName == '%':
                    if type(config_ptr) in (collections.OrderedDict, dict):
                        target_keys = list(config_ptr)
                    else:
                        target_keys = [str(x) for x in range(len(config_ptr))]
                else:
                    # config pointer is reused when the match is exact, so we need to reset it here
                    # if the tag was not found.
                    config_ptr = None
                    break
            if len(target_keys) == 0:
                # single node, only used for string replacement in output name.
                result[tag] = {tag: config_ptr}
            else:
                # multiple node's, find all nodes
                for target_node in target_keys:
                    config_ptr = self._config
                    str_wildcard_loc = len(tag.split('%')[0].split('.'))
                    filter_target = []
                    for xmlNodeName in tag.replace('%', target_node).split('.'):
                        if xmlNodeName in config_ptr:
                            if type(config_ptr[xmlNodeName]) in (collections.OrderedDict, dict):
                                if str_wildcard_loc >= len(filter_target):
                                    filter_target.append(xmlNodeName)
                                if str_wildcard_loc == len(filter_target):
                                    result[tag]['.'.join(filter_target)] = xmlNodeName

                                config_ptr = config_ptr[xmlNodeName]
                            elif type(config_ptr[xmlNodeName]) in (list, tuple):
                                if str_wildcard_loc >= len(filter_target):
                                    filter_target.append(xmlNodeName)
                                    filter_target.append(target_node)
                                config_ptr = config_ptr[xmlNodeName][int(target_node)]
                            else:
                                # fill in node value
                                result[tag]['.'.join(filter_target)] = config_ptr[xmlNodeName]

        return result

    @staticmethod
    def _create_directory(filename):
        """ create directory
        :param filename: create path for filename ( if not existing )
        :return: None
        """
        fparts = []
        for fpart in filename.strip().split('/')[:-1]:
            fparts.append(fpart)
            if len(fpart) > 1:
                tmppart = '/'.join(fparts)
                if os.path.isfile(tmppart):
                    os.remove(tmppart)
                if not os.path.exists(tmppart):
                    os.mkdir(tmppart)

    def _generate(self, module_name, create_directory=True):
        """ generate configuration files for one section using bound config and template data

        :param module_name: module name in dot notation ( company.module )
        :param create_directory: automatically create directories to place template output in ( if not existing )
        :return: list of generated output files
        """
        result = []
        module_data = self.list_module(module_name)
        for src_template in list(module_data['+TARGETS']):
            target = module_data['+TARGETS'][src_template]

            target_filename_tags = self.__find_string_tags(target)
            target_filters = self.__find_filters(target_filename_tags)
            result_filenames = {target: {}}
            for target_filter in list(target_filters):
                for key in list(target_filters[target_filter]):
                    for filename in list(result_filenames):
                        if target_filters[target_filter][key] is not None \
                                and filename.find('[%s]' % target_filter) > -1:
                            new_filename = filename.replace('[%s]' % target_filter, target_filters[target_filter][key])
                            new_filename = new_filename.replace('//', '/')
                            result_filenames[new_filename] = copy.deepcopy(result_filenames[filename])
                            result_filenames[new_filename][key] = target_filters[target_filter][key]

            template_filename = '%s/%s' % (module_name.replace('.', '/'), src_template)
            # parse template, make sure issues can be traced back to their origin
            try:
                j2_page = self._j2_env.get_template(template_filename)
            except jinja2.exceptions.TemplateSyntaxError as templExc:
                raise Exception("%s %s %s" % (module_name, template_filename, templExc))

            for filename in list(result_filenames):
                if not (filename.find('[') != -1 and filename.find(']') != -1):
                    # copy config data
                    cnf_data = copy.deepcopy(self._config)
                    cnf_data['TARGET_FILTERS'] = result_filenames[filename]

                    # link template helpers
                    self._j2_env.globals['helpers'] = template_helpers.Helpers(cnf_data)

                    # make sure we're only rendering output once
                    if filename not in result:
                        # render page and write to disc
                        try:
                            content = j2_page.render(cnf_data)
                        except Exception as render_exception:
                            # push exception with context if anything fails
                            raise Exception("%s %s %s" % (module_name, template_filename, render_exception))

                        # prefix filename with defined root directory
                        filename = ('%s/%s' % (self._target_root_directory, filename)).replace('//', '/')
                        if create_directory:
                            # make sure the target directory exists
                            self._create_directory(filename)

                        f_out = codecs.open(filename, 'wb', encoding="utf-8")
                        f_out.write(content)
                        # Check if the last character of our output contains an end-of-line, if not copy it in if
                        # it was in the original template.
                        # It looks like Jinja sometimes isn't consistent on placing this last end-of-line in.
                        if len(content) > 1 and content[-1] != '\n':
                            src_file = '%s%s' % (self._template_dir, template_filename)
                            src_file_handle = open(src_file, 'rb')
                            src_file_handle.seek(-1, os.SEEK_END)
                            last_bytes_template = src_file_handle.read()
                            src_file_handle.close()
                            if last_bytes_template in (b'\n', b'\r'):
                                f_out.write('\n')
                        f_out.close()
                        # copy root permissions, without exec
                        root_perm = stat.S_IMODE(os.lstat(os.path.dirname(filename)).st_mode)
                        os.chmod(filename, root_perm & (~stat.S_IXGRP & ~stat.S_IXUSR & ~stat.S_IXOTH))

                        result.append(filename)

        return result

    def iter_modules(self, module_name):
        """
        :param module_name: module name in dot notation ( company.module ), may use wildcards
        :return: templates matching paterns
        """
        for template_name in sorted(self.list_modules()):
            wildcard_pos = module_name.find('*')
            do_generate = False
            if wildcard_pos > -1 and module_name[:wildcard_pos] == template_name[:wildcard_pos]:
                # wildcard match
                do_generate = True
            elif wildcard_pos == -1 and module_name == template_name:
                # direct match
                do_generate = True
            elif wildcard_pos == -1 and len(module_name) < len(template_name) \
                    and '%s.' % module_name == template_name[0:len(module_name) + 1]:
                # match child item
                do_generate = True

            if do_generate:
                yield template_name

    def generate(self, module_name, create_directory=True):
        """
        :param module_name: module name in dot notation ( company.module ), may use wildcards
        :param create_directory: automatically create directories to place template output in ( if not existing )
        :return: list of generated output files or None if template not found
        """
        result = None
        for template_name in self.iter_modules(module_name):
            wildcard_pos = module_name.find('*')
            if result is None:
                result = list()
            syslog_notice("generate template container %s" % template_name)
            try:
                for filename in self._generate(template_name, create_directory):
                    result.append(filename)
            except Exception as render_exception:
                if wildcard_pos > -1:
                    # log failure, but proceed processing when doing a wildcard search
                    syslog_error('error generating template %s : %s' % (
                        template_name, traceback.format_exc()
                    ))
                else:
                    raise render_exception

        return result

    def cleanup(self, module_name):
        """
        :param module_name: module name in dot notation ( company.module ), may use wildcards
        :return: list of removed files or None if template not found
        """
        result = list()
        for template_name in self.iter_modules(module_name):
            syslog_notice("cleanup template container %s" % template_name)
            module_data = self.list_module(module_name)
            for src_template in list(module_data['+CLEANUP_TARGETS']):
                target = module_data['+CLEANUP_TARGETS'][src_template]
                for filename in glob.glob(target):
                    os.remove(filename)
                    result.append(filename)
        return result
