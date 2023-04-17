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
import tempfile
import traceback
import subprocess
from .. import syslog_error
from .base import BaseAction


class Action(BaseAction):
    def execute(self, parameters, message_uuid):
        super().execute(parameters, message_uuid)
        try:
            script_command = self._cmd_builder(parameters)
        except TypeError as e:
            return str(e)

        try:
            with tempfile.NamedTemporaryFile() as error_stream:
                with tempfile.NamedTemporaryFile() as output_stream:
                    subprocess.check_call(script_command, env=self.config_environment, shell=True,
                                          stdout=output_stream, stderr=error_stream)
                    output_stream.seek(0)
                    error_stream.seek(0)
                    script_output = output_stream.read()
                    script_error_output = error_stream.read()
                    if len(script_error_output) > 0:
                        syslog_error('[%s] Script action stderr returned "%s"' % (
                            message_uuid, script_error_output.strip()[:255]
                        ))
                    return script_output.decode()
        except Exception as script_exception:
            syslog_error('[%s] Script action failed with %s at %s' % (
                message_uuid, script_exception, traceback.format_exc()
            ))
            return 'Execute error'
