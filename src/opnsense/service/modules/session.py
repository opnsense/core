
"""
    Copyright (c) 2014-2023 Ad Schellevis <ad@opnsense.org>
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
    function: session handling and authorisation
"""
import platform
import struct
import socket
import pwd
import grp


class xucred:
    def __init__(self, connection):
        self._user = None
        self._groups = set()
        if platform.system() == 'FreeBSD':
            # xucred structure defined in : https://man.freebsd.org/cgi/man.cgi?query=unix&sektion=4
            # XU_NGROUPS is 16
            xucred_fmt = '2ih16iP'
            tmp = connection.getsockopt(0, socket.LOCAL_PEERCRED, struct.calcsize(xucred_fmt))
            tmp = tuple(struct.unpack(xucred_fmt, tmp))
            self.cr_version = tmp[0]
            self.cr_uid = tmp[1]
            self.cr_ngroups = tmp[2]
            self.cr_groups = tmp[3:18]
            self.cr_pid = tmp[19]
            tmp = pwd.getpwuid(self.cr_uid)
            if tmp:
                self._user = tmp.pw_name
            for idx, item in enumerate(self.cr_groups):
                if idx < self.cr_ngroups:
                    tmp = grp.getgrgid(item)
                    if tmp:
                        self._groups.add(tmp.gr_name)

    def get_groups(self):
        return self._groups

    def get_user(self):
        return self._user


def get_session_context(connection):
    """
    :param instr: string with optional tags [field.$$]
    :return: xucred
    """

    return xucred(connection)
