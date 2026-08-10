"""
    Copyright (c) 2026 Ad Schellevis <ad@opnsense.org>
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
    --------------------------------------------------------------------------------------------------------------------
    Interface to pkg
"""
import subprocess
import sqlite3


class SimpleContainer:
    def __init__(self, payload:dict):
        self._payload = payload
        self._deps = []

    def add_dependency(self, container:"SimpleContainer"):
        self._deps.append(container)

    def __iter__(self):
        """ recursively collect all packages and their dependencies
        """
        if self._deps:
            yield self, self._deps
            for dep in self._deps:
                yield from dep
        else:
            yield self, None

    def __getattr__(self, prop):
        """ convenient wrapper access _payload properties
        """
        return self._payload[prop]

    def get_data(self):
        return self._payload


class Pkg:
    def __init__(self):
        self._pkg_map = {}
        self._index_pkg()

    @staticmethod
    def _fetch_details():
        result = {}
        dbdir = subprocess.run(['pkg', 'config', 'PKG_DBDIR'], text=True, capture_output=True).stdout.strip()
        dburi = "file:%s/local.sqlite?mode=ro" % dbdir
        con = sqlite3.connect(dburi, uri=True)
        con.row_factory = sqlite3.Row
        for row in con.execute('select name, desc, comment from packages'):
            result[row['name']] = row
        return result

    def _index_pkg(self):
        pkg_details = self._fetch_details()
        args = ['/usr/sbin/pkg', 'rquery', '%n|%v|%o|%L|%m|%l|%w']
        for line in subprocess.run(args, text=True, capture_output=True).stdout.split("\n"):
            parts = line.split('|')
            if len(parts) > 1:
                pkgname = parts[0]
                self._pkg_map[pkgname] = SimpleContainer({
                    'id': '%s:%s' % (pkgname, parts[1]),
                    'name': pkgname,
                    'packageVersion': parts[1],
                    'origin': parts[2],
                    'licenseExpression': (' %s ' % parts[5]).join([x.strip() for x in parts[3].split(',')]),
                    'maintainer': parts[4],
                    'homepage': parts[5],
                    'comment': pkg_details[pkgname]['comment'] if pkgname in pkg_details else '',
                    'description':  pkg_details[pkgname]['desc'] if pkgname in pkg_details else '',
                    'primaryPurpose': 'library' if pkgname.startswith('lib') else 'application'
                })
        args = ['/usr/sbin/pkg', 'rquery', '%n|%dn']
        for line in subprocess.run(args, text=True, capture_output=True).stdout.split("\n"):
            parts = line.split('|')
            if len(parts) == 2:
                self._pkg_map[parts[0]].add_dependency(self._pkg_map[parts[1]])

    def query(self, pkg):
        return self._pkg_map[pkg] if pkg in self._pkg_map else None
