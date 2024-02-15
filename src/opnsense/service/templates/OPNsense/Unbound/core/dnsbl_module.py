"""
    Copyright (c) 2022-2023 Deciso B.V.
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
import dns
import dns.name
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

def obj_path_exists(obj, path):
    if obj and isinstance(path, str):
        for ref in path.split('.'):
            if hasattr(obj, ref):
                obj = getattr(obj, ref)
            else:
                return False
        return True
    return False

class Query:
    """
    Representation of a query. Can contain both Request and Response state.
    Used to send to the DNSBL class to match on a policy.

    This class is focused on safely parsing the qstate property
    to extract all necessary data.
    """
    def __init__(self, cb_kwargs={}, cb_qinfo=None, qstate=None, client='', family='', type='' ,domain=''):
        self._created_at = int(time.time())
        self._client = client
        self._family = family
        self._domain = domain
        self._type = type
        self._action = None
        self._source = None
        self._blocklist = None
        self._rcode = None
        self._resolve_time_ms = None
        self._dnssec_status  = None
        self._ttl  = None
        if obj_path_exists(cb_qinfo, 'qname_str'):
            self._domain = cb_qinfo.qname_str
        if obj_path_exists(cb_qinfo, 'qtype_str'):
            self._type = cb_qinfo.qtype_str

        if cb_kwargs is not None and 'repinfo' in cb_kwargs\
                and obj_path_exists(cb_kwargs['repinfo'], 'addr') and obj_path_exists(cb_kwargs['repinfo'], 'family'):
            self._client = cb_kwargs['repinfo'].addr
            self._family = cb_kwargs['repinfo'].family
        if obj_path_exists(qstate, 'qinfo'):
            if obj_path_exists(qstate.qinfo, 'qname_str'):
                self._domain = qstate.qinfo.qname_str
            if obj_path_exists(qstate.qinfo, 'qtype_str'):
                self._type = qstate.qinfo.qtype_str
            if obj_path_exists(qstate, 'mesh_info.reply_list.query_reply.addr')\
                    and obj_path_exists(qstate, 'mesh_info.reply_list.query_reply.family'):
                self._client = qstate.mesh_info.reply_list.query_reply.addr
                self._family = qstate.mesh_info.reply_list.query_reply.family

    @property
    def client(self):
        return self._client

    @property
    def type(self):
        return self._type

    @type.setter
    def type(self, value):
        self._type = value

    @property
    def domain(self):
        return self._domain

    @domain.setter
    def domain(self, value):
        self._domain = value

    @property
    def request(self):
        return (self._created_at, self._client, self._family, self._type, self._domain)

    @property
    def response(self):
        return (
            self._action,
            self._source,
            self._blocklist,
            self._rcode,
            self._resolve_time_ms,
            self._dnssec_status,
            self._ttl
        )

    def set_response(self, action, source, blocklist, rcode, resolve_time_ms, dnssec_status, ttl):
        self._action = action
        self._source = source
        self._blocklist = blocklist
        self._rcode = rcode
        self._resolve_time_ms = resolve_time_ms
        self._dnssec_status = dnssec_status
        self._ttl = ttl
        return self


class Logger:
    """
    Handles logging by creating a fifo and sending data to a listening process (if there is one)
    """
    def __init__(self):
        self._pipe_name = '/data/dns_logger'
        self._pipe_fd = None
        self._pipe_timer = 0
        self.stats_enabled = os.path.exists('/data/stats')
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
                if mod_env and type(self.dnsbl.get('config')) is dict:
                    mod_env['context'].set_config(self.dnsbl['config'])
            except (json.decoder.JSONDecodeError, KeyError) as e:
                if not self.dnsbl:
                    log_err("dnsbl_module: unable to bootstrap blocklist, this is likely due to a corrupted \
                            file. Please re-apply the blocklist settings.")
                    self.dnsbl_available = False
                    return
                else:
                    log_err("dnsbl_module: error parsing blocklist: %s, reusing last known list" % e)

        # translate source nets after loading the list, so we can easily match if in network
        # enforce our data structure to contain a "source_net" for every domain
        if 'data' in self.dnsbl and type(self.dnsbl['data']) is dict:
            for key in self.dnsbl['data']:
                if type(self.dnsbl['data'][key]) is dict:
                    source_nets = []
                    if 'source_net' in self.dnsbl['data'][key]:
                        if type(self.dnsbl['data'][key]['source_net']) is list:
                            for item in self.dnsbl['data'][key]['source_net']:
                                try:
                                    source_nets.append(ipaddress.ip_network(item, False))
                                except ValueError:
                                    log_err("dnsbl_module: unparsable network %s in %s" % (key, item))
                    self.dnsbl['data'][key]['source_net'] = source_nets

        self.dnsbl_available = True

    def _in_network(self, client, networks):
        if not networks:
            return True
        try:
            src_address = ipaddress.ip_address(client)
        except ValueError:
            # when no valid source address could be found, we won't be able to match a policy either
            log_err('dnsbl_module: unable to parse client source: %s' % traceback.format_exc().replace('\n', ' '))
            return False

        for network in networks:
            if src_address in network:
                return True

        return False

    def policy_match(self, query: Query, qstate=None):
        self._update_dnsbl()

        if not self.dnsbl_available:
            return False

        if not query.type in ('A', 'AAAA', 'CNAME', 'HTTPS'):
            return False

        domain = query.domain.rstrip('.').lower()
        sub = domain
        match = None
        while match is None:
            if sub in self.dnsbl['data']:
                policy = self.dnsbl['data'][sub]
                is_full_domain = sub == domain
                if (is_full_domain) or (not is_full_domain and policy['wildcard']):
                    if self._in_network(query.client, policy.get('source_net')):
                        match = policy
                    else:
                        # allow query, but do not cache.
                        if qstate and hasattr(qstate, 'no_cache_store'):
                            qstate.no_cache_store = 1
                        return False

            if '.' not in sub or not mod_env['context'].config.get('has_wildcards', False):
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
    mod_env['logger'].log_entry(
        Query(kwargs, qinfo).set_response(
            action=ACTION_PASS,
            source=SOURCE_CACHE,
            blocklist=None,
            rcode=rcode,
            resolve_time_ms=0,
            dnssec_status=rep.security if rep else 0,
            # rep.ttl is stored as an epoch, so convert it to remaining seconds
            ttl=(rep.ttl - int(time.time())) if rep else 0
        )
    )
    return True

def local_cb(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    mod_env['logger'].log_entry(
        Query(kwargs, qinfo).set_response(
            action=ACTION_PASS,
            source=SOURCE_LOCALDATA,
            blocklist=None,
            rcode=rcode,
            resolve_time_ms=0,
            dnssec_status=rep.security if rep else 0,
            ttl=rep.ttl if rep else 0
        )
    )
    return True

def servfail_cb(qinfo, qstate, rep, rcode, edns, opt_list_out, region, **kwargs):
    mod_env['logger'].log_entry(
        Query(kwargs, qinfo).set_response(
            action=ACTION_DROP,
            source=SOURCE_LOCAL,
            blocklist=None,
            rcode=RCODE_SERVFAIL,
            resolve_time_ms=0,
            dnssec_status=rep.security if rep else 0,
            ttl=rep.ttl if rep else 0
        )
    )
    return True

def init_standard(id, env):
    ctx = ModuleContext(env)
    mod_env['context'] = ctx
    mod_env['logger'] = Logger()
    mod_env['dnsbl'] = DNSBL()

    if mod_env['logger'].stats_enabled:
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

def set_answer_block(qstate, qdata, query, bl=None):
    ctx = mod_env['context']
    dnssec_status = sec_status_secure if ctx.dnssec_enabled else sec_status_unchecked
    logger = mod_env['logger']

    if ctx.rcode == RCODE_NXDOMAIN:
        # exit early
        qstate.return_rcode = RCODE_NXDOMAIN
        if logger.stats_enabled:
            query.set_response(ACTION_BLOCK, SOURCE_LOCAL, bl, ctx.rcode,
                        time_diff_ms(qdata['start_time']), dnssec_status, 0)
            mod_env['logger'].log_entry(query)
        return True

    ttl = 3600
    msg = DNSMessage(query.domain, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
    if (query.type == 'A'):
        msg.answer.append("%s %s IN A %s" % (query.domain, ttl, ctx.dst_addr))
    if not msg.set_return_msg(qstate):
        log_err("dnsbl_module: unable to create response for %s, dropping query" % query.domain)
        if logger.stats_enabled:
            query.set_response(ACTION_DROP, SOURCE_LOCAL, bl, RCODE_SERVFAIL,
                time_diff_ms(qdata['start_time']), dnssec_status, 0)
            mod_env['logger'].log_entry(query)
        return False
    if ctx.dnssec_enabled:
        qstate.return_msg.rep.security = dnssec_status

    if logger.stats_enabled:
        query.set_response(ACTION_BLOCK, SOURCE_LOCAL, bl, ctx.rcode,
            time_diff_ms(qdata['start_time']), dnssec_status, ttl)
        mod_env['logger'].log_entry(query)
    return True

def operate(id, event, qstate, qdata):
    if event == MODULE_EVENT_NEW:
        qdata['start_time'] = time.time()

        query = Query(qstate=qstate)

        match = mod_env['dnsbl'].policy_match(query, qstate)
        if match:
            if not set_answer_block(qstate, qdata, query, match.get('bl')):
                qstate.ext_state[id] = MODULE_ERROR
                return True

            qstate.ext_state[id] = MODULE_FINISHED
            return True
        else:
            qdata['query'] = query
            qstate.ext_state[id] = MODULE_WAIT_MODULE
            return True

    if event == MODULE_EVENT_MODDONE:
        # Iterator finished, show response (if any)
        logger = mod_env['logger']
        qstate.ext_state[id] = MODULE_FINISHED
        if 'query' in qdata and 'start_time' in qdata:
            query = qdata['query']

            if obj_path_exists(qstate, 'return_msg.rep'):
                r = qstate.return_msg.rep
                dnssec = r.security if r.security else sec_status_unchecked
                rcode = (r.flags & 0xF) if r.flags else RCODE_SERVFAIL
                ttl = r.ttl if r.ttl else 0

                # if the count of RRsets > 1, then there are at least two different answer types.
                # this is most likely a CNAME, check if it is and refers to a fqdn that we should block
                if (obj_path_exists(r, 'an_numrrsets') and r.an_numrrsets > 1) and obj_path_exists(r, 'rrsets'):
                    for i in range(r.an_numrrsets):
                        rrset = r.rrsets[i]
                        if obj_path_exists(rrset, 'rk') and obj_path_exists(rrset, 'entry.data'):
                            rrset_key = rrset.rk
                            data = rrset.entry.data
                            if (obj_path_exists(rrset_key, 'type_str') and obj_path_exists(data, 'count')) and rrset_key.type_str == 'CNAME':
                                # there might be multiple CNAMEs in the RRset
                                for j in range(data.count):
                                    # temporarily change the queried domain name to the CNAME alias so we can apply our policy on it.
                                    # after we're done we change it back to the original query so as to not confuse users
                                    # looking at the logged queries. We do however change the type to CNAME if a match is found
                                    # to indicate that a CNAME was the reason for blocking this domain.
                                    tmp = query.domain
                                    query.domain = dns.name.from_wire(data.rr_data[j], 2)[0].to_text(omit_final_dot=True)
                                    match = mod_env['dnsbl'].policy_match(query, qstate)
                                    query.domain = tmp
                                    if match:
                                        # the iterator module has already resolved the answer and cached it,
                                        # make sure we remove it from the cache in order to block future queries for the same domain
                                        if obj_path_exists(qstate, 'return_msg.qinfo'):
                                            invalidateQueryInCache(qstate, qstate.return_msg.qinfo)
                                        query.type = 'CNAME'
                                        if not set_answer_block(qstate, qdata, query, match.get('bl')):
                                            qstate.ext_state[id] = MODULE_ERROR
                                        # block and exit on any match
                                        return True

                if logger.stats_enabled:
                    query.set_response(ACTION_PASS, SOURCE_RECURSION, None, rcode, time_diff_ms(qdata['start_time']), dnssec, ttl)
                    logger.log_entry(query)

        return True

    if event == MODULE_EVENT_PASS:
        qstate.ext_state[id] = MODULE_WAIT_MODULE
        return True

    log_err("dnsbl_module: bad event. Query was %s" % qstate.qinfo.qname_str)
    qstate.ext_state[id] = MODULE_ERROR
    return True


def arg_parse_is_json_file(filename):
    try:
        json.load(open(filename))
    except FileNotFoundError:
        raise argparse.ArgumentTypeError("non existing file")
    except json.JSONDecodeError:
        # in cases where a file exists, but we're unable to decode it (e.g. the file is empty),
        # we should assume the blocklist has no entries.
        pass
    except:
        raise argparse.ArgumentTypeError("No blocklist available")

    return filename

try:
    import unboundmodule
    test_mode = False
except ImportError:
    test_mode = True
    mod_env = {}



if __name__ == '__main__' and test_mode:
    """ Command line blocklist test mode
    """
    # override unbound log methods
    def log_info(str):
        return

    def log_err(str):
        return

    parser = argparse.ArgumentParser()
    parser.add_argument(
        '--src',
         help='client source address. Default 127.0.0.1',
         default='127.0.0.1'
    )
    parser.add_argument('--domain', help='domain name to query', required=True)
    parser.add_argument(
        '--type',
        help='query type, e.g. AAAA. Default is A',
        default='A',
        choices=['A', 'AAAA', 'CNAME', 'HTTPS']
    )
    parser.add_argument(
        '--dnsbl_path',
        help='blocklist json input',
        default='/var/unbound/data/dnsbl.json',
        type=arg_parse_is_json_file
    )

    inputargs = parser.parse_args()

    # create an empty global context
    mod_env['context'] = ModuleContext(None)

    dnsbl = DNSBL(dnsbl_path=inputargs.dnsbl_path, size_file='/dev/null')
    match = dnsbl.policy_match(
        Query(
            client=inputargs.src,
            family='ip6' if inputargs.src.count(':') else 'ip4',
            type=inputargs.type ,
            domain=inputargs.domain
        )
    )
    if match:
        src_nets = match.get('source_net', [])
        for i in range(len(src_nets)):
            src_nets[i] = str(src_nets[i])
        match['source_net'] = src_nets
        msg = {'status': 'OK','action': 'Block','policy': match}
        print(json.dumps(msg))
    else:
        print(json.dumps({'status': 'OK','action': 'Pass'}))
