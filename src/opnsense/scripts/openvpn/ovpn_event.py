#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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

import argparse
import sys
import os
import subprocess


def main(params):
    if params.common_name:
        os.environ['common_name'] = params.common_name
    if params.auth_control_file:
        os.environ['auth_control_file'] = params.auth_control_file
    if params.server:
        os.environ["auth_server"] = params.server
    # script action
    cmd_path = "%s/" % os.path.dirname(__file__)
    if params.script_type == 'user-pass-verify':
        os.environ["auth_method"] = params.auth_method
        if params.auth_method == 'via-file' and len(params.args) > 0:
            # user/password file to read (via-file), arg1
            os.environ["auth_file"] = params.args[0]
        if params.defer:
            os.environ["auth_defer"] = 'true'
            if os.fork() != 0:
                # When deferred, openvpn expects exit code 2 (see help --auth-user-pass-verify)
                sys.exit(2)

        # dispatch user_pass_verify
        sys.exit(subprocess.run("%s/user_pass_verify.php" % cmd_path).returncode)
    elif  params.script_type == 'tls-verify':
        if len(params.args) > 0:
            os.environ["certificate_depth"] = params.args[0]
        sys.exit(subprocess.run("%s/tls_verify.php" % cmd_path).returncode)
    elif params.script_type == 'client-connect':
        # Temporary file used for the profile specified by client-connect
        if len(params.args) > 0:
            os.environ["config_file"] = params.args[0]
        sys.exit(subprocess.run("%s/client_connect.php" % cmd_path).returncode)
    elif params.script_type == 'client-disconnect':
        sys.exit(subprocess.run("%s/client_disconnect.sh" % cmd_path).returncode)
    elif params.script_type == 'learn-address':
        if os.fork() == 0:
            sys.exit(subprocess.run(
                ['/usr/local/opnsense/scripts/filter/update_tables.py', '--types', 'authgroup']
            ).returncode)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument(
        '--common_name',
        help='common name [cn], defaults to "common_name" in environment',
        default=os.environ.get('common_name', None)
    )
    parser.add_argument(
        '--script_type',
        help='script_type (event), defaults to "script_type" in environment',
        default=os.environ.get('script_type', None),
        choices=['user-pass-verify', 'tls-verify', 'client-connect', 'client-disconnect']
    )
    parser.add_argument(
        '--auth_control_file',
        help='auth control file location, defaults to "auth_control_file" in environment',
        default=os.environ.get('auth_control_file', None)
    )
    parser.add_argument(
        '--auth_method',
        help='auth method (via-env or via-file)',
        default='via-env',
        choices=['via-env', 'via-file']
    )
    parser.add_argument('--defer', help='defer action (when supported)', default=False, action="store_true")
    parser.add_argument('server', help='openvpn server id to use, authentication settings are configured per server')
    parser.add_argument('args',  nargs='*',  help='script arguments specified by openvpn')

    main(parser.parse_args())
