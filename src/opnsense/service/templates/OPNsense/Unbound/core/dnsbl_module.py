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

    --------------------------------------------------------------------------------------
    DNSBL module. Intercepts DNS queries and applies blocklist policies on them.
    This module is comprised of several objects with their own responsibilities accessible
    from the global scope as set by Unbound.

    They are:
     - mod_env['context']: module configuration
     - mod_env['dnsbl']: blocklist data and policy logic
     - mod_env['logger']: logging mechanism
"""

import os
import json
import time
import errno
import uuid
import ipaddress
import traceback
from threading import Lock
from collections import deque
from dataclasses import dataclass, astuple, fields

ACTION_PASS = 0
ACTION_BLOCK = 1
ACTION_DROP = 2

SOURCE_RECURSION = 0
SOURCE_LOCAL = 1
SOURCE_LOCALDATA = 2
SOURCE_CACHE = 3

RR_TYPE_HTTPS = 65

@dataclass
class Request:
    uuid: uuid.UUID
    time: int
    client: str
    family: str
    type: str
    domain: str

@dataclass
class Response:
    action: int
    source: int
    blocklist: str
    rcode: int
    resolve_time_ms: int
    dnssec_status: int
    ttl: int

class Query:
    """
    Representation of a query. Can contain both Request and Response state.
    Used to send to the DNSBL class to match on a policy.

    This class is focused on safely parsing the qstate property
    to extract all necessary data. Also accepts a custom Request
    structure if qstate is not available
    """
    def __init__(self, qstate, request: Request = None):
        self.num_log_items = len(fields(Request) + fields(Response))

        if not request:
            self.qtype = qstate.qinfo.qtype
            qname = qstate.qinfo.qname_str
            qtype_str = qstate.qinfo.qtype_str
            client_addr = None
            client_family = None
            reply_list = qstate.mesh_info.reply_list
            if reply_list and reply_list.query_reply:
                client = reply_list.query_reply
                client_addr = getattr(client, 'addr')
                client_family = getattr(client, 'family')
            self.request = Request(
                uuid.uuid4(),
                int(time.time()),
                client_addr,
                client_family,
                qtype_str,
                qname
            )
        else:
            self.request = request

    def set_response(self, action, source, blocklist, rcode, resolve_time_ms, dnssec_status, ttl):
        self.response = Response(action, source, blocklist, rcode, resolve_time_ms, dnssec_status, ttl)

class DNSBL:
    """
    DNSBL implementation. Handles dynamically updating the blocklist as well as matching policies
    on incoming queries.
    """
    def __init__(self, dnsbl_path='/data/dnsbl.json'):
        self.dnsbl_path = dnsbl_path
        self.dnsbl_mtime_cache = 0
        self.dnsbl_update_time = 0
        self.dnsbl_available = False
        self.dnsbl = None

        self.update_dnsbl()

    def _dnsbl_exists(self):
        return os.path.isfile(self.dnsbl_path) and os.path.getsize(self.dnsbl_path) > 0

    def update_dnsbl(self):
        t = time.time()
        if (t - self.dnsbl_update_time) > 60:
            self.dnsbl_update_time = t
            if not self._dnsbl_exists():
                self.dnsbl_available = False
                return
            fstat = os.stat(self.dnsbl_path).st_mtime
            if fstat != self.dnsbl_mtime_cache:
                self.dnsbl_mtime_cache = fstat
                log_info("dnsbl_module: updating blocklist.")
                self._load_dnsbl()

    def _load_dnsbl(self):
        with open(self.dnsbl_path, 'r') as f:
            try:
                self.dnsbl = json.load(f)
                log_info('dnsbl_module: blocklist loaded. length is %d' % len(self.dnsbl['data']))
                with open('/data/dnsbl.size', 'w') as sfile:
                    sfile.write(str(len(self.dnsbl['data'])))
                config = self.dnsbl['config']
                if mod_env['context']:
                    mod_env['context'].set_config(config)
            except json.decoder.JSONDecodeError as e:
                if not self.dnsbl:
                    log_err("dnsbl_module: unable to bootstrap blocklist, this is likely due to a corrupted \
                            file. Please re-apply the blocklist settings.")
                    self.dnsbl_available = False
                    return
                else:
                    log_err("dnsbl_module: error parsing blocklist: %s, reusing last known list" % e)

        self.dnsbl_available = True

    def _policies_in_domain(self, domain):
        if domain in self.dnsbl['data']:
            for policy in self.dnsbl['data'][domain]['policies']:
                yield policy

    def _in_network(self, client, networks):
        if networks is None or type(networks) is not list or client is None:
            return True
        try:
            client_net = ipaddress.ip_network(client)
        except ValueError:
            log_err('dnsbl_module: unable to parse client network: %s' % traceback.format_exc().replace('\n', ' '))
            return False
        for network in networks:
            try:
                if client_net.overlaps(ipaddress.ip_network(network)):
                    return True
            except ValueError:
                log_err('dnsbl_module: unable to parse policy network: %s' % traceback.format_exc().replace('\n', ' '))

        return False

    def policy_match(self, query: Query):
        self.update_dnsbl()

        if not self.dnsbl_available:
            return False

        if not query.qtype in (RR_TYPE_A, RR_TYPE_AAAA, RR_TYPE_CNAME, RR_TYPE_HTTPS):
            return False

        ctx = mod_env['context']

        domain = query.request.domain.rstrip('.')
        sub = domain
        matches = dict()
        while len(matches) == 0:
            for policy in self._policies_in_domain(sub):
                is_full_domain = sub == domain
                if (is_full_domain) or (not is_full_domain and policy['wildcard']):
                    if not self._in_network(query.request.client, policy['source_net']):
                        continue
                    # give a higher priority to exact networks
                    priority = 0 if policy['source_net'] == '*' else 1
                    matches[priority] = policy
                    matches[priority]['bl'] = self.dnsbl['data'][sub].get('bl')

            if '.' not in sub or not ctx.config.get('has_wildcards', False):
                # either we have traversed all subdomains or there are no wildcards
                # in the dataset, in which case traversal is not necessary
                break
            else:
                sub = sub.split('.', maxsplit=1)[1]

        if len(matches) > 0:
            match = matches[sorted(matches.keys(), reverse=True)[0]]
            return match

        return False

class Logger:
    """
    Handles logging by creating a fifo and sending data to a listening process (if there is one)
    """
    def __init__(self):
        self.stats_enabled = os.path.exists('/data/stats')
        if self.stats_enabled:
            self.pipe_name = '/data/dns_logger'
            self.pipe_fd = None
            self.pipe_timer = 0
            self.lock = Lock()
            self.pipe_buffer = deque(maxlen=100000) # buffer to hold qdata as long as a backend is not present
            self.retry_timer = 10
            self._create_pipe_rdv()

    # Defines the rendezvous point, but does not open it.
    # Subsequent calls to log_entry will attempt to open the pipe if necessary while being throttled
    # by a default timer
    def _create_pipe_rdv(self):
        if os.path.exists(self.pipe_name):
            os.unlink(self.pipe_name)
        os.mkfifo(self.pipe_name)

    def _try_open_pipe(self):
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

    def close(self):
        if self.pipe_fd is not None:
            os.close(self.pipe_fd)

        if self.stats_enabled:
            try:
                os.unlink(self.pipe_name)
            except:
                pass

    def log_entry(self, query: Query):
        if not self.stats_enabled:
            return

        try:
            entry = astuple(query.request) + astuple(query.response)
        except AttributeError:
            return

        if len(entry) != query.num_log_items:
            log_err("dnsbl_module: malformed query during logging, skipping.")
            return

        self.pipe_buffer.append(entry)
        if self.pipe_fd is None:
            if (time.time() - self.pipe_timer) > self.retry_timer:
                self.pipe_timer = time.time()
                log_info("dnsbl_module: attempting to open pipe")
                if not self._try_open_pipe():
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

class ModuleContext:
    """
    Module configuration context
    """
    def __init__(self, env):
        self.config = None
        self.env = env
        self.dst_addr = '0.0.0.0'
        self.rcode = RCODE_NOERROR
        self.dnssec_enabled = 'validator' in self.env.cfg.module_conf

    def set_config(self, config):
        """
        set and parse configuration
        """
        self.config = config
        self.dst_addr = self.config.get('dst_addr', '0.0.0.0')
        self.rcode = RCODE_NXDOMAIN if self.config.get('rcode') == 'NXDOMAIN' else RCODE_NOERROR

def time_diff_ms(start):
    return round((time.time() - start) * 1000)

def cache_cb(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    logger = mod_env['logger']
    client = kwargs['repinfo']

    # rep.ttl is stored as an epoch, so convert it to remaining seconds
    ttl = (rep.ttl - int(time.time())) if rep else 0

    query = Query(qstate, request=Request(uuid.uuid4(), int(time.time()), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str))
    security = rep.security if rep else 0
    query.set_response(ACTION_PASS, SOURCE_CACHE, None, rcode, 0, security, ttl)
    logger.log_entry(query)
    return True

def local_cb(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    logger = mod_env['logger']
    client = kwargs['repinfo']

    query = Query(qstate, request=Request(uuid.uuid4(), int(time.time()), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str))
    security = rep.security if rep else 0
    query.set_response(ACTION_PASS, SOURCE_LOCALDATA, None, rcode, 0, security, rep.ttl if rep else 0)
    logger.log_entry(query)
    return True

def servfail_cb(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    logger = mod_env['logger']
    client = kwargs['repinfo']

    query = Query(qstate, request=Request(uuid.uuid4(), int(time.time()), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str))
    security = rep.security if rep else 0
    query.set_response(ACTION_DROP, SOURCE_LOCAL, None, RCODE_SERVFAIL, 0, security, rep.ttl if rep else 0)
    logger.log_entry(query)
    return True

def init_standard(id, env):
    ctx = ModuleContext(env)
    mod_env['context'] = ctx

    logger = Logger()
    dnsbl = DNSBL()

    mod_env['dnsbl'] = dnsbl
    mod_env['logger'] = logger

    if logger.stats_enabled:
        if not register_inplace_cb_reply_cache(cache_cb, env, id):
            log_err("dnsbl_module: unable to register cache reply callback")
            return False

        if not register_inplace_cb_reply_local(local_cb, env, id):
            log_err("dnsbl_module: unable to register local reply callback")
            return False

        if not register_inplace_cb_reply_servfail(servfail_cb, env, id):
            log_err("dnsbl_module: unable to register servfail reply callback")
            return False

    return True

def deinit(id):
    logger = mod_env['logger']

    logger.close()

    return True

def inform_super(id, qstate, superqstate, qdata):
    return True

def operate(id, event, qstate, qdata):
    if event == MODULE_EVENT_NEW:
        qdata['start_time'] = time.time()
        query = Query(qstate)
        dnsbl = mod_env['dnsbl']
        match = dnsbl.policy_match(query)
        if match:
            # do not cache blocked domains
            qstate.no_cache_store = 1

            ctx = mod_env['context']
            qstate.return_rcode = ctx.rcode
            bl = match.get('bl')
            dnssec_status = sec_status_secure if ctx.dnssec_enabled else sec_status_unchecked
            ttl = 3600

            logger = mod_env['logger']
            if ctx.rcode == RCODE_NXDOMAIN:
                # exit early
                query.set_response(ACTION_BLOCK, SOURCE_LOCAL, bl, ctx.rcode,
                    time_diff_ms(qdata['start_time']), dnssec_status, 0)
                logger.log_entry(query)
                qstate.ext_state[id] = MODULE_FINISHED
                return True

            msg = DNSMessage(query.request.domain, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)

            if (query.qtype == RR_TYPE_A) or (query.qtype == RR_TYPE_ANY):
                msg.answer.append("%s %s IN A %s" % (query.request.domain, ttl, ctx.dst_addr))

            if not msg.set_return_msg(qstate):
                qstate.ext_state[id] = MODULE_ERROR
                log_err("dnsbl_module: unable to create response for %s, dropping query" % query.request.domain)
                query.set_response(ACTION_DROP, SOURCE_LOCAL, bl, RCODE_SERVFAIL,
                    time_diff_ms(qdata['start_time']), dnssec_status, 0)
                logger.log_entry(query)
                return True

            if ctx.dnssec_enabled:
                qstate.return_msg.rep.security = dnssec_status

            query.set_response(ACTION_BLOCK, SOURCE_LOCAL, bl, ctx.rcode,
                    time_diff_ms(qdata['start_time']), dnssec_status, ttl)
            logger.log_entry(query)
            qstate.ext_state[id] = MODULE_FINISHED
            return True
        else:
            qdata['query'] = query
            qstate.ext_state[id] = MODULE_WAIT_MODULE
            return True

    if event == MODULE_EVENT_MODDONE:
        # Iterator finished, show response (if any)
        logger = mod_env['logger']
        if logger.stats_enabled and 'query' in qdata and 'start_time' in qdata:
            query = qdata['query']

            dnssec = sec_status_unchecked
            rcode = RCODE_SERVFAIL
            ttl = 0

            if qstate.return_msg:
                if qstate.return_msg.rep:
                    r = qstate.return_msg.rep
                    dnssec = r.security
                    rcode = r.flags & 0xF
                    ttl = r.ttl

            query.set_response(ACTION_PASS, SOURCE_RECURSION, None, rcode, time_diff_ms(qdata['start_time']), dnssec, ttl)
            logger.log_entry(query)

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
