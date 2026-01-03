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
import time
import re
import ipaddress
from .utils import obj_path_exists
RCODE_NOERROR = 0
RCODE_NXDOMAIN = 3
try:
    # create log_info(), log_err() function when not started within unbound
    from unboundmodule import log_info, log_err
except ImportError:
    def log_info(msg):
        return
    def log_err(msg):
        return


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


class ModuleContext:
    """
    Module configuration context
    """
    def __init__(self, env):
        self.config = None
        self.has_wildcards = False
        self.env = env
        if self.env:
            self.dnssec_enabled = 'validator' in self.env.cfg.module_conf

    def set_config(self, config):
        """
        set and parse configuration
        """
        self.config = config

        for idx, cfg in self.config.items():
            if idx == 'general':
                self.has_wildcards = cfg.get('has_wildcards', False)
                continue
            self.config[idx]['address'] = cfg.get('address', '0.0.0.0')
            self.config[idx]['rcode'] = RCODE_NXDOMAIN if cfg.get('rcode') == 'NXDOMAIN' else RCODE_NOERROR
            self.config[idx]['pass_regex'] = None

            passlist = cfg.get('passlist', None)
            if passlist:
                # when a pass/white list is offered, we need to be absolutely sure we can use the regex.
                # compile and skip when invalid.
                try:
                    self.config[idx]['pass_regex'] = re.compile(passlist, re.IGNORECASE)
                except re.error:
                    log_err("dnsbl_module: unable to compile regex in global_passlist_regex")

            # translate source nets after loading the list, so we can easily match if in network
            # enforce our data structure to contain a "source_net" for every domain
            if 'source_nets' in cfg and type(cfg['source_nets']) is list:
                source_nets = []
                for item in cfg['source_nets']:
                    try:
                        source_nets.append(ipaddress.ip_network(item, False))
                    except ValueError:
                        log_err("dnsbl_module: unparsable network %s" % item)
                self.config[idx]['source_nets'] = source_nets

    def get_config(self, idx):
        if idx and idx in self.config:
            return self.config[idx]

        return None
