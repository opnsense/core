#!/usr/local/bin/python3

"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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

"""
import ujson
import os
import re
from lib import ProxyTemplates
target_directory = "/usr/local/etc/squid/errors/local"

if __name__ == '__main__':
    proxy_templates = ProxyTemplates()

    # install error_pages into target_directory
    if not os.path.isdir(target_directory):
        os.mkdir(target_directory)
    for filename, data in proxy_templates.templates(proxy_templates.overlay_enabled()):
        if filename.endswith('.html'):
            match = re.search(b'(<!--[\s]*EMBED:start.*?EMBED:end[\s]*-->)', data, re.DOTALL)
            if match:
                inline_css = list()
                for href in re.findall(b"(href[\s]*=[\s]*[\"|'])(.*?)([\"|'])" ,match.group(0)):
                    href_content = proxy_templates.get_file(href[1].decode(), proxy_templates.overlay_enabled())
                    if href_content:
                        inline_css.append(b'<style type="text/css">\n%s\n</style>' % href_content)
                data = b"%s%s%s" % (data[0:match.start()], b"\n".join(inline_css), data[match.end():])
                with open("%s/%s" % (target_directory, os.path.splitext(filename)[0]), "wb") as target_fh:
                    target_fh.write(data)
    print(ujson.dumps({
        'overlay_status': proxy_templates.get_overlay_status()
    }))
