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
import subprocess
from .. import syslog_error
from .base import BaseAction


class Action(BaseAction):
    def execute(self, parameters, message_uuid, *args, **kwargs):
        super().execute(parameters, message_uuid, *args, **kwargs)
        try:
            script_command = self._cmd_builder(parameters)
        except TypeError as e:
            return str(e)

        try:
            exit_status = subprocess.call(script_command, env=self.config_environment, shell=True)
            # send response
            if exit_status == 0:
                return 'OK'
            else:
                syslog_error('[%s] returned exit status %d' % (message_uuid, exit_status))
                return 'Error (%d)' % exit_status
        except Exception as script_exception:
            syslog_error('[%s] Script action failed with %s at %s' % (
                message_uuid,
                script_exception,
                traceback.format_exc()
            ))
            return 'Execute error'
