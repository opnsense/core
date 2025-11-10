"""
    Copyright (c) 2022-2025 Deciso B.V.
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
import errno
import os
import time
import uuid
from threading import Lock
from collections import deque
from . import Query

try:
    # create log_info() function when not started within unbound
    from unboundmodule import log_info
except ImportError:
    def log_info(msg):
        return


class Logger:
    """
    Handles logging by creating a fifo and sending data to a listening process (if there is one)
    """
    def __init__(self, path='/data'):
        self._pipe_name = '%s/dns_logger' % path.rstrip('/')
        self._pipe_fd = None
        self._pipe_timer = 0
        self.stats_enabled = os.path.exists('%s/stats' % path.rstrip('/'))
        if self.stats_enabled:
            self._lock = Lock()
            self._pipe_buffer = deque(maxlen=100000) # buffer to hold qdata as long as a backend is not present
            self._retry_timer = 10
            self._create_pipe_rdv()

    # Defines the rendezvous point, but does not open it.
    # Subsequent calls to log_entry will attempt to open the pipe if necessary while being throttled
    # by a default timer
    def _create_pipe_rdv(self):
        if os.path.exists(self._pipe_name):
            os.unlink(self._pipe_name)
        os.mkfifo(self._pipe_name)

    def _try_open_pipe(self):
        try:
            # try to obtain the fd in a non-blocking manner and catch the ENXIO exception
            # if the other side is not listening.
            self._pipe_fd = os.open(self._pipe_name, os.O_NONBLOCK | os.O_WRONLY)
        except OSError as e:
            if e.errno == errno.ENXIO:
                log_info("dnsbl_module: no logging backend found.")
                self._pipe_fd = None
                return False
            else:
                raise

        return True

    def close(self):
        if self.stats_enabled:
            with self._lock:
                if self._pipe_fd is not None:
                    os.close(self._pipe_fd)
                try:
                    os.unlink(self._pipe_name)
                except:
                    pass

    def log_entry(self, query: Query):
        if not self.stats_enabled:
            return
        self._pipe_buffer.append((uuid.uuid4(),) + query.request + query.response)
        if self._pipe_fd is None:
            if (time.time() - self._pipe_timer) > self._retry_timer:
                self._pipe_timer = time.time()
                log_info("dnsbl_module: attempting to open pipe")
                if not self._try_open_pipe():
                    return
                log_info("dnsbl_module: successfully opened pipe")
            else:
                return

        with self._lock:
            l = None
            try:
                while len(self._pipe_buffer) > 0:
                    l = self._pipe_buffer.popleft()
                    res = "{}|{}|{}|{}|{}|{}|{}|{}|{}|{}|{}|{}|{}\n".format(*['' if x is None else x for x in l])
                    os.write(self._pipe_fd, res.encode())
            except (BrokenPipeError, BlockingIOError, TypeError) as e:
                if e.__class__.__name__ == 'BrokenPipeError':
                    log_info("dnsbl_module: Logging backend closed connection. Closing pipe and continuing.")
                    os.close(self._pipe_fd)
                    self._pipe_fd = None
                self._pipe_buffer.appendleft(l)
