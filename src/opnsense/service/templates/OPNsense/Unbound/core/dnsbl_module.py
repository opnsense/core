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
import argparse
from threading import Lock
from collections import deque

ACTION_PASS = 0
ACTION_BLOCK = 1
ACTION_DROP = 2

SOURCE_RECURSION = 0
SOURCE_LOCAL = 1
SOURCE_LOCALDATA = 2
SOURCE_CACHE = 3

RCODE_NOERROR = 0
RCODE_NXDOMAIN = 3

class Query:
    """
    Representation of a query. Can contain both Request and Response state.
    Used to send to the DNSBL class to match on a policy.

    This class is focused on safely parsing the qstate property
    to extract all necessary data.
    """
    def __init__(self):
        self.request = None
        self.response = None

    def get_qname(self, qstate):
        qname = ''
        try:
            if qstate and qstate.qinfo and qstate.qinfo.qname_str:
                qname = qstate.qinfo.qname_str
        except AttributeError:
            pass
        return qname

    def get_qname_qinfo(self, qinfo):
        qname = ''
        try:
            if qinfo and qinfo.qname_str:
                qname = qinfo.qname_str
        except AttributeError:
            pass
        return qname

    def get_qtype(self, qstate):
        qtype = ''
        try:
            if qstate and qstate.qinfo and qstate.qinfo.qtype:
                qtype = qstate.qinfo.qtype_str
        except AttributeError:
            pass
        return qtype

    def get_qtype_qinfo(self, qinfo):
        qtype = ''
        try:
            if qinfo and qinfo.qtype_str:
                qtype = qinfo.qtype_str
        except AttributeError:
            pass
        return qtype

    def get_client(self, qstate):
        client = ('', '')
        try:
            if qstate and qstate.mesh_info and qstate.mesh_info.reply_list:
                reply_list = qstate.mesh_info.reply_list
                if reply_list.query_reply:
                    qr = reply_list.query_reply
                    client = (qr.addr, qr.family)
        except AttributeError:
            pass
        return client

    def get_client_kwargs(self, kwargs):
        client = ('', '')
        try:
            if kwargs is not None and 'repinfo' in kwargs:
                repinfo = kwargs['repinfo']
                client = (repinfo.addr, repinfo.family)
        except AttributeError:
            pass
        return client

    def set_request(self, client, type, domain):
        self.client = client[0]
        self.family = client[1]
        self.type = type
        self.domain = domain
        self.request = (uuid.uuid4(), int(time.time()), self.client, self.family, self.type, self.domain)

    def set_response(self, action, source, blocklist, rcode, resolve_time_ms, dnssec_status, ttl):
        self.action = action
        self.source = source
        self.blocklist = blocklist
        self.rcode = rcode
        self.resolve_time_ms = resolve_time_ms
        self.dnssec_status = dnssec_status
        self.ttl = ttl
        self.response = (self.action, self.source, self.blocklist, self.rcode, self.resolve_time_ms, self.dnssec_status, self.ttl)

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
        if self.stats_enabled:
            if self.pipe_fd is not None:
                os.close(self.pipe_fd)

            try:
                os.unlink(self.pipe_name)
            except:
                pass

    def log_entry(self, query: Query):
        if not self.stats_enabled:
            return

        if query.request is None or query.response is None:
            return

        entry = query.request + query.response

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

class DNSBL:
    """
    DNSBL implementation. Handles dynamically updating the blocklist as well as matching policies
    on incoming queries.
    """
    def __init__(self, dnsbl_path='/data/dnsbl.json', size_file='/data/dnsbl.size'):
        self.dnsbl_path = dnsbl_path
        self.size_file = size_file
        self.dnsbl_mtime_cache = 0
        self.dnsbl_update_time = 0
        self.dnsbl_available = False
        self.dnsbl = None

        self._update_dnsbl()

    def _dnsbl_exists(self):
        return os.path.isfile(self.dnsbl_path) and os.path.getsize(self.dnsbl_path) > 0

    def _update_dnsbl(self):
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
                with open(self.size_file, 'w') as sfile:
                    sfile.write(str(len(self.dnsbl['data'])))
                config = self.dnsbl['config']
                if mod_env:
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

    def _in_network(self, client, networks):
        if networks is None or type(networks) is not list or client is None:
            return False
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

    def policy_match(self, query: Query, qstate=None):
        self._update_dnsbl()

        if not self.dnsbl_available:
            return False

        if not query.type in ('A', 'AAAA', 'CNAME', 'HTTPS'):
            return False

        ctx = mod_env['context']

        domain = query.domain.rstrip('.')
        sub = domain
        match = None
        while match is None:
            if sub in self.dnsbl['data']:
                policy = self.dnsbl['data'][sub]
                is_full_domain = sub == domain
                if (is_full_domain) or (not is_full_domain and policy['wildcard']):
                    if self._in_network(query.client, policy.get('bypass')):
                        # allow query, but do not cache.
                        if qstate:
                            qstate.no_cache_store = 1
                        return False
                    match = policy

            if '.' not in sub or not ctx.config.get('has_wildcards', False):
                # either we have traversed all subdomains or there are no wildcards
                # in the dataset, in which case traversal is not necessary
                break
            else:
                sub = sub.split('.', maxsplit=1)[1]

        if match is not None:
            return match

        return False

class ModuleContext:
    """
    Module configuration context
    """
    def __init__(self, env):
        self.config = None
        self.env = env
        self.dst_addr = '0.0.0.0'
        self.rcode = RCODE_NOERROR
        if self.env:
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

    # rep.ttl is stored as an epoch, so convert it to remaining seconds
    ttl = (rep.ttl - int(time.time())) if rep else 0

    query = Query()
    query.set_request(query.get_client_kwargs(kwargs), query.get_qtype_qinfo(qinfo), query.get_qname_qinfo(qinfo))
    security = rep.security if rep else 0
    query.set_response(ACTION_PASS, SOURCE_CACHE, None, rcode, 0, security, ttl)
    logger.log_entry(query)
    return True

def local_cb(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    logger = mod_env['logger']

    query = Query()
    query.set_request(query.get_client_kwargs(kwargs), query.get_qtype_qinfo(qinfo), query.get_qname_qinfo(qinfo))
    security = rep.security if rep else 0
    query.set_response(ACTION_PASS, SOURCE_LOCALDATA, None, rcode, 0, security, rep.ttl if rep else 0)
    logger.log_entry(query)
    return True

def servfail_cb(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    logger = mod_env['logger']

    query = Query()
    query.set_request(query.get_client_kwargs(kwargs), query.get_qtype_qinfo(qinfo), query.get_qname_qinfo(qinfo))
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

        query = Query()
        client = query.get_client(qstate)
        qtype = query.get_qtype(qstate)
        domain = query.get_qname(qstate)
        query.set_request(client, qtype, domain)

        dnsbl = mod_env['dnsbl']
        policy_match = dnsbl.policy_match
        match = policy_match(query, qstate)
        if match:
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

            msg = DNSMessage(domain, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)

            if (qtype == 'A'):
                msg.answer.append("%s %s IN A %s" % (domain, ttl, ctx.dst_addr))

            if not msg.set_return_msg(qstate):
                qstate.ext_state[id] = MODULE_ERROR
                log_err("dnsbl_module: unable to create response for %s, dropping query" % domain)
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
    mod_env = {}

def match(inputargs):
    result = {'status': 'error'}

    if not inputargs.domain:
        result['message'] = 'No valid domain provided'
        return result

    dnsbl = DNSBL(dnsbl_path='/var/unbound/data/dnsbl.json', size_file='/var/unbound/data/dnsbl.size')
    if not dnsbl.dnsbl_available:
        result['message'] = "No blocklist available"
        return result

    if not inputargs.type in ('A', 'AAAA', 'CNAME', 'HTTPS'):
        result['message'] = "Invalid record type"
        return result

    try:
        src = ipaddress.ip_address(inputargs.src_net)
        family = 'ip4' if type(src) is ipaddress.IPv4Address else 'ip6'
    except ValueError:
        result['message'] = "%s not a valid IP or IP range" % inputargs.src_net
        return result

    query = Query()
    query.set_request((inputargs.src_net, family), inputargs.type, inputargs.domain)

    match = dnsbl.policy_match(query)
    if match:
        result = {'status': 'OK','action': 'Block','blocklist': match.get('bl'),'wildcard': match.get('wildcard')}
    else:
        result = {'status': 'OK','action': 'Pass'}

    return result

if __name__ == '__main__' and test_mode:
    # override unbound log methods
    def log_info(str):
        return

    def log_err(str):
        return

    parser = argparse.ArgumentParser()
    parser.add_argument('--src_net', help='client source, can be a single IP address or an IP range. Default 127.0.0.1', default='127.0.0.1')
    parser.add_argument('--domain', help='domain name to query')
    parser.add_argument('--type', help='query type, e.g. AAAA. Default is A', default='A')

    inputargs = parser.parse_args()

    # create an empty global context
    ctx = ModuleContext(None)
    mod_env['context'] = ctx

    result = match(inputargs)
    print(json.dumps(result))
