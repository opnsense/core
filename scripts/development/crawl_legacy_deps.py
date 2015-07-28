#!/usr/local/bin/python2.7

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

    Generate dependency maps for legacy files.
    To use this script, please install graphviz package ( pkg install graphviz )
"""

import time
import os
import os.path
from lib.legacy_deps import DependancyCrawler

# set source and target directories
target_directory = '/tmp/legacy/'
src_root = '/usr/local/'

# create target directory if not existing
if not os.path.exists(target_directory):
    os.mkdir(target_directory)

# start crawling
crawler = DependancyCrawler(src_root)
print '[%.2f] started ' % (time.time())
crawler.crawl()
print '[%.2f] collected %d dependancies in %d files' % (time.time(),
                                                        crawler.get_total_dependencies(),
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
