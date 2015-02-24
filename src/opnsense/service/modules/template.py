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
    package : configd
    function: template handler, generate configuration files using templates


"""
__author__ = 'Ad Schellevis'

import os
import os.path
import collections
import copy
import jinja2

class Template(object):

    def __init__(self,target_root_directory="/"):
        """ constructor
        :return:
        """
        # init config (config.xml) data
        self._config = {}

        # set target root
        self._target_root_directory = target_root_directory

        # setup jinja2 environment
        self._template_dir = os.path.dirname(os.path.abspath(__file__))+'/../templates/'
        self._j2_env = jinja2.Environment(loader=jinja2.FileSystemLoader(self._template_dir),trim_blocks=True)

    def _readManifest(self,filename):
        """

        :param filename: manifest filename (path/+MANIFEST)
        :return: dictionary containing manifest items
        """
        result = {}
        for line in open(filename,'r').read().split('\n'):
            parts =  line.split(':')
            if len(parts) > 1:
                result[parts[0]] = ':'.join(parts[1:])

        return result

    def _readTargets(self,filename):
        """ read raw target filename masks

        :param filename: targets filename (path/+TARGETS)
        :return: dictionary containing +TARGETS filename sets
        """
        result = {}
        for line in open(filename,'r').read().split('\n'):
            parts =  line.split(':')
            if len(parts) > 1 and parts[0].strip()[0] != '#':
                result[parts[0]] = ':'.join(parts[1:]).strip()

        return result

    def list_module(self,module_name,read_manifest=False):
        """ list single module content
        :param module_name: module name in dot notation ( company.module )
        :param read_manifest: boolean, read manifest file if it exists
        :return: dictionary with module data
        """
        result = {}
        file_path = '%s/%s'%(self._template_dir,module_name.replace('.','/'))
        if os.path.exists('%s/+MANIFEST'%file_path ) and read_manifest:
            result['+MANIFEST'] = self._readManifest('%s/+MANIFEST'%file_path)
        if os.path.exists('%s/+TARGETS'%file_path ) :
            result['+TARGETS'] = self._readTargets('%s/+TARGETS'%file_path)
        else:
            result['+TARGETS'] = {}

        return result

    def list_modules(self,read_manifest=False):
        """ traverse template directory and list all modules
        the template directory is structured like Manufacturer/Module/config_files

        :param read_manifest: boolean, read manifest file if it exists
        :return: list (dict) of registered modules
        """
        result = {}
        for root, dirs, files in os.walk(self._template_dir):
            if len(root) > len(self._template_dir):
                module_name = '.'.join(root.replace(self._template_dir,'').split('/')[:2])
                if result.has_key(module_name) == False:
                    result[module_name] = self.list_module(module_name)

        return result


    def setConfig(self,config_data):
        """ set config data
        :param config_data: config data as dictionary/list structure
        :return: None
        """
        if type(config_data) in( dict, collections.OrderedDict):
            self._config = config_data
        else:
            # no data given, reset
            self._config = {}

    def __findStringTags(self,instr):
        """
        :param instr: string with optional tags [field.$$]
        :return:
        """
        retval = []
        for item in instr.split('['):
            if item.find(']') > -1:
                retval.append(item.split(']')[0])

        return retval

    def __findFilters(self,tags):
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
                if config_ptr.has_key(xmlNodeName):
                    config_ptr = config_ptr[xmlNodeName]
                elif xmlNodeName == '%':
                    target_keys = config_ptr.keys()
                else:
                    break

            if len(target_keys) == 0 :
                # single node, only used for string replacement in output name.
                result[tag] =  {tag:config_ptr}
            else:
                # multiple node's, find all nodes
                for target_node in target_keys:
                    config_ptr = self._config
                    str_wildcard_loc = len(tag.split('%')[0].split('.'))
                    filter_target= []
                    for xmlNodeName in tag.replace('%',target_node).split('.'):
                        if config_ptr.has_key(xmlNodeName):
                            if type (config_ptr[xmlNodeName]) in (collections.OrderedDict,dict):
                                if str_wildcard_loc >= len(filter_target):
                                    filter_target.append(xmlNodeName)
                                if str_wildcard_loc == len(filter_target):
                                    result[tag]['.'.join(filter_target)] = xmlNodeName

                                config_ptr = config_ptr[xmlNodeName]
                            else:
                                # fill in node value
                                result[tag]['.'.join(filter_target)] = config_ptr[xmlNodeName]

        return result

    def _create_directory(self,filename):
        """ create directory
        :param filename: create path for filename ( if not existing )
        :return: None
        """
        fparts=[]
        for fpart in filename.strip().split('/')[:-1]:
            fparts.append(fpart)
            if len(fpart) >1:
                if os.path.exists('/'.join(fparts)) == False:
                    os.mkdir('/'.join(fparts))



    def generate(self,module_name,create_directory=True):
        """ generate configuration files using bound config and template data

        :param module_name: module name in dot notation ( company.module )
        :param create_directory: automatically create directories to place template output in ( if not existing )
        :return: list of generated output files
        """
        result=[]
        module_data = self.list_module(module_name)
        for src_template in module_data['+TARGETS'].keys():
            target =  module_data['+TARGETS'][src_template]

            target_filename_tags = self.__findStringTags(target)
            target_filters = self.__findFilters(target_filename_tags)
            result_filenames = {target:{}}
            for target_filter in target_filters.keys():
                for key in target_filters[target_filter].keys():
                    for filename in result_filenames.keys():
                        if filename.find('[%s]'%target_filter) > -1:
                            new_filename = filename.replace('[%s]'%target_filter,target_filters[target_filter][key])
                            result_filenames[new_filename] = copy.deepcopy(result_filenames[filename])
                            result_filenames[new_filename][key] = target_filters[target_filter][key]

            j2_page = self._j2_env.get_template('%s/%s'%( module_name.replace('.','/'), src_template))
            for filename in result_filenames.keys():
                if not ( filename.find('[') != -1 and filename.find(']') != -1 ) :
                    # copy config data
                    cnf_data = copy.deepcopy(self._config)
                    cnf_data['TARGET_FILTERS'] = result_filenames[filename]

                    # make sure we're only rendering output once
                    if filename not in result:
                        # render page and write to disc
                        content = j2_page.render(cnf_data)

                        # prefix filename with defined root directory
                        filename = ('%s/%s'%(self._target_root_directory, filename)).replace('//','/')
                        if create_directory:
                            # make sure the target directory exists
                            self._create_directory(filename)

                        f_out = open(filename,'wb')
                        f_out.write(content)
                        f_out.close()

                        result.append(filename)


        return result
