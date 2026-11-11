"""
    Copyright (c) 2025 Deciso B.V.
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
import subprocess
import ipaddress


class PFError(RuntimeError):
    pass


class PF(object):
    def __init__(self):
        pass

    @staticmethod
    def _is_ipv6(address):
        try:
            ipaddress.IPv6Address(address)
            return True
        except (ValueError, AttributeError):
            return False

    @staticmethod
    def _run(command):
        result = subprocess.run(command, capture_output=True, text=True)
        if result.returncode != 0:
            raise PFError("%s failed: %s" % (command[0], result.stderr.strip()))
        return result

    @staticmethod
    def list_table(zoneid):
        pfctl_cmd = ['/sbin/pfctl', '-t', f'__captiveportal_zone_{zoneid}', '-T', 'show']
        for line in PF._run(pfctl_cmd).stdout.split('\n'):
            # split('\n') on an empty string will return an empty string, we need to make sure to suppress these
            tmp = line.strip()
            if len(tmp) > 0:
                yield tmp

    @staticmethod
    def add_to_table(zoneid, address):
        PF._run(['/sbin/pfctl', '-t', f'__captiveportal_zone_{zoneid}', '-T', 'add', address])

    @staticmethod
    def remove_from_table(zoneid, address):
        commands = [
            ['/sbin/pfctl', '-t', f'__captiveportal_zone_{zoneid}', '-T', 'del', address],
            # kill associated states to and from this host
            ['/sbin/pfctl', '-k', f'{address}'],
        ]
        # Use appropriate wildcard based on IP version
        wildcard = '::/0' if PF._is_ipv6(address) else '0.0.0.0/0'
        commands.append(['/sbin/pfctl', '-k', wildcard, '-k', f'{address}'])

        errors = []
        for command in commands:
            try:
                PF._run(command)
            except PFError as error:
                errors.append(str(error))
        if errors:
            raise PFError('; '.join(errors))
