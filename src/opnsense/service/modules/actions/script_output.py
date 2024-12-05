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
import fcntl
import glob
import hashlib
import os
import tempfile
import time
import traceback
import subprocess
from .. import syslog_error
from .base import BaseAction


class Action(BaseAction):
    temp_prefix = 'tmpcfd_'
    cached_results = None
    def execute(self, parameters, message_uuid, *args, **kwargs):
        super().execute(parameters, message_uuid, *args, **kwargs)
        try:
            script_command = self._cmd_builder(parameters)
            script_hash = hashlib.sha256(script_command.encode()).hexdigest() if self.cache_ttl else None
        except TypeError as e:
            return str(e)

        if Action.cached_results is None:
            # cache cleanup on startup (first executed script_output action)
            for filename in glob.glob("%s/%s*"% (tempfile.gettempdir(), Action.temp_prefix)):
                os.remove(filename)
            Action.cached_results = {}
        elif self.cache_ttl is not None and len(Action.cached_results) > 0:
            # cache expire
            now = time.time()
            for key in list(Action.cached_results.keys()):
                if Action.cached_results[key]['expire'] < now:
                    if os.path.isfile(Action.cached_results[key]['filename']):
                        os.remove(Action.cached_results[key]['filename'])
                    del Action.cached_results[key]

        try:
            if script_hash in Action.cached_results and os.path.isfile(Action.cached_results[script_hash]['filename']):
                with open(Action.cached_results[script_hash]['filename']) as output_stream:
                    fcntl.flock(output_stream, fcntl.LOCK_EX)
                    output_stream.seek(0)
                    return output_stream.read()
            with tempfile.NamedTemporaryFile() as error_stream:
                tparm = {'prefix': Action.temp_prefix, 'delete': script_hash is None}
                with tempfile.NamedTemporaryFile(**tparm) as output_stream:
                    fcntl.flock(output_stream, fcntl.LOCK_EX)
                    if script_hash:
                        Action.cached_results[script_hash] = {
                            'filename': output_stream.name,
                            'expire': time.time() + self.cache_ttl
                        }
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
                    return script_output
        except Exception as script_exception:
            syslog_error('[%s] Script action failed with %s at %s' % (
                message_uuid, script_exception, traceback.format_exc()
            ))
            return 'Execute error'
