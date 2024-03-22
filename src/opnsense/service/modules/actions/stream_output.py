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

import traceback
import selectors
import subprocess
import os
import signal
from .. import syslog_error, syslog_info
from .base import BaseAction


class Action(BaseAction):
    def execute(self, parameters, message_uuid, connection, *args, **kwargs):
        super().execute(parameters, message_uuid, connection, *args, **kwargs)
        try:
            script_command = self._cmd_builder(parameters)
        except TypeError as e:
            return str(e)

        self.stdout_read = 0
        def stdout_reader(stream, mask):
            data = stream.read(1024)
            self.stdout_read += len(data)
            connection.send(data)

        process = subprocess.Popen(
            script_command,
            env=self.config_environment,
            shell=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            bufsize=0,
            preexec_fn=os.setsid
        )

        selector = selectors.DefaultSelector()
        selector.register(process.stdout, selectors.EVENT_READ, stdout_reader)

        try:
            while process.poll() is None:
                timeout = True
                for key, mask in selector.select(1):
                    timeout = False
                    callback = key.data
                    try:
                        callback(key.fileobj, mask)
                    except BrokenPipeError:
                        timeout = True
                        break
                if timeout:
                    # when timeout has reached, check if the other end is still connected using getpeername()
                    # kill process when nobody is waiting for an answer
                    try:
                        connection.getpeername()
                    except OSError:
                        syslog_info('[%s] Script action terminated by other end' % message_uuid)
                        process.kill()
                        # kill child processes as well
                        try:
                            os.killpg(os.getpgid(process.pid), signal.SIGKILL)
                        except ProcessLookupError:
                            pass
                        return None

            return_code = process.wait()
            script_error_output = process.stderr.read().decode()
            if len(script_error_output) > 0:
                syslog_error('[%s] Script action stderr returned "%s" (%d)' % (
                    message_uuid, script_error_output.strip()[:255], return_code
                ))
            selector.close()
        except Exception as script_exception:
            syslog_error('[%s] Script action failed with %s at %s (bytes processed %d)' % (
                message_uuid, script_exception, traceback.format_exc(), self.stdout_read
            ))
            return 'Execute error'

        return None
