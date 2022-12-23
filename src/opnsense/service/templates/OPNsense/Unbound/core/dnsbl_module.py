"""
    Copyright (c) 2022 Deciso B.V.
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

import os
import json
import time
import errno
import uuid
from threading import Lock
from collections import deque

ACTION_PASS = 0
ACTION_BLOCK = 1
ACTION_DROP = 2

SOURCE_RECURSION = 0
SOURCE_LOCAL = 1
SOURCE_LOCALDATA = 2
SOURCE_CACHE = 3

def time_diff_ms(start):
    return round((time.time() - start) * 1000)

class ModuleContext:
    def __init__(self, env):
        self.env = env
        self.dnsbl_path = '/data/dnsbl.json'
        self.dst_addr = '0.0.0.0'
        self.rcode = RCODE_NOERROR
        self.dnsbl_mtime_cache = 0
        self.dnsbl_update_time = 0
        self.dnsbl_available = False
        self.log_update_time = time.time()
        self.dnssec_enabled = 'validator' in self.env.cfg.module_conf
        self.stats_enabled = os.path.exists('/data/stats')

        self.pipe_name = '/data/dns_logger'
        self.pipe_fd = None
        self.pipe_timer = 0
        self.lock = Lock()
        self.pipe_buffer = deque(maxlen=100000) # buffer to hold qdata as long as a backend is not present

        self.update_dnsbl(self.log_update_time)
        if self.stats_enabled:
            self.create_pipe_rdv()

    def dnsbl_exists(self):
        return os.path.isfile(self.dnsbl_path) and os.path.getsize(self.dnsbl_path) > 0

    def load_dnsbl(self):
        with open(self.dnsbl_path, 'r') as f:
            try:
                mod_env['dnsbl'] = json.load(f)
                log_info('dnsbl_module: blocklist loaded. length is %d' % len(mod_env['dnsbl']['data']))
                with open('/data/dnsbl.size', 'w') as sfile:
                    sfile.write(str(len(mod_env['dnsbl']['data'])))
                config = mod_env['dnsbl']['config']
                self.dst_addr = config.get('dst_addr', '0.0.0.0')
                self.rcode = RCODE_NXDOMAIN if config.get('rcode') == 'NXDOMAIN' else RCODE_NOERROR
            except json.decoder.JSONDecodeError as e:
                if not 'dnsbl' in mod_env:
                    log_err("dnsbl_module: unable to bootstrap blocklist, this is likely due to a corrupted \
                            file. Please re-apply the blocklist settings.")
                    self.dnsbl_available = False
                    return
                else:
                    log_err("dnsbl_module: error parsing blocklist: %s, reusing last known list" % e)

        self.dnsbl_available = True

    # Defines the rendezvous point, but does not open it.
    # Subsequent calls to log_entry will attempt to open the pipe if necessary while being throttled
    # by a default timer
    def create_pipe_rdv(self):
        if os.path.exists(self.pipe_name):
            os.unlink(self.pipe_name)
        os.mkfifo(self.pipe_name)

    def try_open_pipe(self):
        try:
            # try to obtain the fd in a non-blocking manner and catch the ENXIO exception
            # if the other side is not listening.
            self.pipe_fd = os.open(self.pipe_name, os.O_NONBLOCK | os.O_WRONLY)
        except OSError as e:
            if e.errno == errno.ENXIO:
                log_info("dnsbl_module: no logging backend found.")
                self.pipe_fd = None
                return False
            else:
                raise

        return True

    def log_entry(self, *args):
        if not self.stats_enabled:
            return

        entry = (uuid.uuid4(), *args)
        self.pipe_buffer.append(entry)
        if self.pipe_fd is None:
            if (time.time() - self.pipe_timer) > 10:
                self.pipe_timer = time.time()
                log_info("dnsbl_module: attempting to open pipe")
                if not self.try_open_pipe():
                    return
                log_info("dnsbl_module: successfully opened pipe")
            else:
                return

        with self.lock:
            l = None
            try:
                while len(self.pipe_buffer) > 0:
                    l = self.pipe_buffer.popleft()
                    res = "{} {} {} {} {} {} {} {} {} {} {} {} {}\n".format(*l)
                    os.write(self.pipe_fd, res.encode())
            except (BrokenPipeError, BlockingIOError) as e:
                if e.__class__.__name__ == 'BrokenPipeError':
                    log_info("dnsbl_module: Logging backend closed connection. Closing pipe and continuing.")
                    os.close(self.pipe_fd)
                    self.pipe_fd = None
                self.pipe_buffer.appendleft(l)

    def update_dnsbl(self, t):
        if (t - self.dnsbl_update_time) > 60:
            self.dnsbl_update_time = t
            if not self.dnsbl_exists():
                self.dnsbl_available = False
                return
            fstat = os.stat(self.dnsbl_path).st_mtime
            if fstat != self.dnsbl_mtime_cache:
                self.dnsbl_mtime_cache = fstat
                log_info("dnsbl_module: updating blocklist.")
                self.load_dnsbl()

    def filter_query(self, id, qstate, qdata):
        t = int(qdata['start_time'])
        self.update_dnsbl(t)
        qname = qstate.qinfo.qname_str
        qtype = qstate.qinfo.qtype
        qtype_str = qstate.qinfo.qtype_str

        reply_list = qstate.mesh_info.reply_list
        if reply_list and reply_list.query_reply:
            client = reply_list.query_reply
            info = (t, client.addr if hasattr(client, 'addr') else None,
                    client.family if hasattr(client, 'family') else None, qtype_str, qname)
        else:
            info = (t, None, None, qtype_str, qname)

        domain = qname.rstrip('.')

        rr_types = (RR_TYPE_A, RR_TYPE_AAAA, RR_TYPE_CNAME)

        if self.dnsbl_available and qtype in rr_types and domain in mod_env['dnsbl']['data']:
            qstate.return_rcode = self.rcode
            blocklist = mod_env['dnsbl']['data'][domain].get('bl')
            dnssec_status = sec_status_secure if self.dnssec_enabled else sec_status_unchecked
            ttl = 3600

            if self.rcode == RCODE_NXDOMAIN:
                # exit early
                self.log_entry(*(info), ACTION_BLOCK, SOURCE_LOCAL, blocklist,
                    self.rcode, time_diff_ms(qdata['start_time']), dnssec_status, 0)
                qstate.ext_state[id] = MODULE_FINISHED
                return True

            msg = DNSMessage(qname, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)

            if (qtype == RR_TYPE_A) or (qtype == RR_TYPE_ANY):
                msg.answer.append("%s %s IN A %s" % (qname, ttl, self.dst_addr))

            if not msg.set_return_msg(qstate):
                qstate.ext_state[id] = MODULE_ERROR
                log_err("dnsbl_module: unable to create response for %s, dropping query" % qname)
                self.log_entry(*(info), ACTION_DROP, SOURCE_LOCAL, blocklist,
                    RCODE_SERVFAIL, time_diff_ms(qdata['start_time']), dnssec_status, 0)
                return True

            if self.dnssec_enabled:
                qstate.return_msg.rep.security = dnssec_status

            self.log_entry(*(info), ACTION_BLOCK, SOURCE_LOCAL, blocklist,
                self.rcode, time_diff_ms(qdata['start_time']), dnssec_status, ttl)
            qstate.ext_state[id] = MODULE_FINISHED
        else:
            # Pass the query to validator/iterator and log the query when it's done
            qdata['query'] = (*(info), ACTION_PASS, SOURCE_RECURSION, None)
            qstate.ext_state[id] = MODULE_WAIT_MODULE

        return True

def cache_cb(qinfo, qstate, rep, rcode, edns, opt_list_out,
                           region, **kwargs):
    ctx = mod_env['context']
    client = kwargs['repinfo']

    # rep.ttl is stored as an epoch, so convert it to remaining seconds
    ttl = rep.ttl - int(time.time())

    info = (int(time.time()), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str)
    ctx.log_entry(*info, ACTION_PASS, SOURCE_CACHE, None, rcode, 0, rep.security, ttl)
    return True

def local_cb(qinfo, qstate, rep, rcode, edns, opt_list_out,
                           region, **kwargs):
    ctx = mod_env['context']
    client = kwargs['repinfo']

    info = (int(time.time()), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str)
    ctx.log_entry(*info, ACTION_PASS, SOURCE_LOCALDATA, None, rcode, 0, rep.security, rep.ttl)
    return True

def servfail_cb(qinfo, qstate, rep, rcode, edns, opt_list_out,
                              region, **kwargs):
    ctx = mod_env['context']
    client = kwargs['repinfo']

    info = (int(time.time()), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str)
    ctx.log_entry(*info, ACTION_DROP, SOURCE_LOCAL, None, RCODE_SERVFAIL, 0, rep.security, rep.ttl)
    return True

def init_standard(id, env):
    ctx = ModuleContext(env)
    mod_env['context'] = ctx

    if not register_inplace_cb_reply_cache(cache_cb, env, id):
        return False

    if not register_inplace_cb_reply_local(local_cb, env, id):
        return False

    if not register_inplace_cb_reply_servfail(servfail_cb, env, id):
        return False

    return True

def deinit(id):
    ctx = mod_env['context']

    if ctx.pipe_fd is not None:
        os.close(ctx.pipe_fd)

    if ctx.stats_enabled:
        try:
            os.unlink(ctx.pipe_name)
        except:
            pass

    return True

def inform_super(id, qstate, superqstate, qdata):
    return True

def operate(id, event, qstate, qdata):
    if event == MODULE_EVENT_NEW:
        ctx = mod_env['context']
        qdata['start_time'] = time.time()
        return ctx.filter_query(id, qstate, qdata)

    if event == MODULE_EVENT_MODDONE:
        # Iterator finished, show response (if any)
        if 'query' in qdata and 'start_time' in qdata:
            ctx = mod_env['context']
            dnssec = sec_status_unchecked
            rcode = RCODE_SERVFAIL
            ttl = 0

            if qstate.return_msg:
                r = qstate.return_msg.rep
                dnssec = r.security
                rcode = r.flags & 0xF
                ttl = r.ttl

            ctx.log_entry(*qdata['query'], rcode, time_diff_ms(qdata['start_time']), dnssec, ttl)

        qstate.ext_state[id] = MODULE_FINISHED
        return True

    if event == MODULE_EVENT_PASS:
        qstate.ext_state[id] = MODULE_WAIT_MODULE
        return True

    log_err("dnsbl_module: bad event. Query was %s" % qstate.qinfo.qname_str)
    qstate.ext_state[id] = MODULE_ERROR
    return True


try:
    import unboundmodule
    test_mode = False
except ImportError:
    test_mode = True

if __name__ == '__main__' and test_mode:
    # Runs when executed from the command line as opposed to embedded in Unbound. For future reference
    exit()
