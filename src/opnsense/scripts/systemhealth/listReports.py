#!/usr/local/bin/python3

"""
    Copyright (c) 2015 Ad Schellevis <ad@opnsense.org>
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
    return a list of all available rrd files including additional definition data

"""
import re
import glob
import xml.etree.ElementTree
import os.path
import ujson

rrd_definition_dir = '%s/definitions' % os.path.dirname(os.path.abspath(__file__))
rrd_reports_dir = '/var/db/rrd'

# query all rrd files available and initialize with empty definition
result = {}
for filename in glob.glob('%s/*.rrd' % rrd_reports_dir):
    rrdFilename = os.path.basename(filename).split('.')[0]
    # determine topic and item name, fixes naming issues
    if rrdFilename.split('-')[0] == 'system' and rrdFilename.find('-') > -1:
        topic = rrdFilename.split('-')[0]
        itemName = '-'.join(rrdFilename.split('-')[1:])
    elif rrdFilename.find('-') == -1:
        # set topic for items without one
        topic = 'services'
        itemName = rrdFilename.split('.')[0]
    else:
        topic = rrdFilename.split('-')[-1]
        itemName = '-'.join(rrdFilename.split('-')[:-1])
    result[rrdFilename] = {'title': '','y-axis_label': '', 'field_units': {},
                           'topic': topic, 'itemName': itemName, 'filename': os.path.basename(filename)}

# scan all definition files
for filename in glob.glob('%s/*.xml' % rrd_definition_dir):
    rrdFilename = os.path.basename(filename).split('.')[0]
    try:
        ruleXML = xml.etree.ElementTree.fromstring(open(filename).read())
        rrdDef = {}
        if ruleXML.tag == 'systemhealth':
            # only parse systemhealth items
            for child in ruleXML:
                if len(list(child)) == 0:
                    # single items
                    rrdDef[child.tag] = child.text
                else:
                    # named list items
                    rrdDef[child.tag] = {}
                    for subchild in child:
                        rrdDef[child.tag][subchild.tag] = subchild.text
        else:
            rrdDef['__msg'] = 'xml metadata not valid'
    except xml.etree.ElementTree.ParseError:
        # no valid xml, always return a valid (empty) data set
        rrdDef = {'__msg': 'xml metadata load error'}

    # link definition data to rrd file, use property file-match in xml to determine it's targets
    if 'file-match' in rrdDef:
        # remove file-match property from actual result
        file_match = rrdDef['file-match']
        del rrdDef['file-match']
        for rrdFilename in result:
            if re.search(file_match, rrdFilename) is not None:
                for fieldname in rrdDef:
                    result[rrdFilename][fieldname] = rrdDef[fieldname]

print(ujson.dumps(result))
