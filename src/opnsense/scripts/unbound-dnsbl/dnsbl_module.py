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

    --------------------------------------------------------------------------------------
    DNSBL module. Intercepts DNS queries and applies blocklist policies on them.
    This module is comprised of several objects with their own responsibilities accessible
    from the global scope as set by Unbound.

    They are:
     - mod_env['context']: module configuration
     - mod_env['dnsbl']: blocklist data and policy logic
     - mod_env['logger']: logging mechanism
"""
import time
import dns
import dns.name
import unboundmodule
sys.path.insert(0, "/unbound-dnsbl/")
from lib import Query, ModuleContext
from lib.dnsbl import DNSBL
from lib.log import Logger
from lib.utils import obj_path_exists


ACTION_PASS = 0
ACTION_BLOCK = 1
ACTION_DROP = 2

SOURCE_RECURSION = 0
SOURCE_LOCAL = 1
SOURCE_LOCALDATA = 2
SOURCE_CACHE = 3

RCODE_NOERROR = 0
RCODE_NXDOMAIN = 3

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
    mod_env['dnsbl'] = DNSBL(ctx)

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
    mod_env['logger'].close()
    return True

def inform_super(id, qstate, superqstate, qdata):
    return True

def set_answer_block(qstate, qdata, query, match):
    ctx = mod_env['context']
    dnssec_status = sec_status_secure if ctx.dnssec_enabled else sec_status_unchecked
    logger = mod_env['logger']
    rcode = match.get('rcode')
    dst_addr = match.get('address')
    bl = match.get('bl')

    if rcode == RCODE_NXDOMAIN:
        # exit early
        qstate.return_rcode = RCODE_NXDOMAIN
        if logger.stats_enabled:
            query.set_response(ACTION_BLOCK, SOURCE_LOCAL, bl, rcode,
                        time_diff_ms(qdata['start_time']), dnssec_status, 0)
            mod_env['logger'].log_entry(query)
        return True

    ttl = 3600
    # XXX AAAA
    msg = DNSMessage(query.domain, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
    if (query.type == 'A') or (query.type == 'CNAME'):
        msg.answer.append("%s %s IN A %s" % (query.domain, ttl, dst_addr))
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
        query.set_response(ACTION_BLOCK, SOURCE_LOCAL, bl, rcode,
            time_diff_ms(qdata['start_time']), dnssec_status, ttl)
        mod_env['logger'].log_entry(query)
    return True

def operate(id, event, qstate, qdata):
    if event == MODULE_EVENT_NEW:
        qdata['start_time'] = time.time()

        query = Query(qstate=qstate)

        match = mod_env['dnsbl'].policy_match(query, qstate)
        if match:
            if not set_answer_block(qstate, qdata, query, match):
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
                                    # looking at the logged queries.
                                    tmp = query.domain
                                    query.domain = dns.name.from_wire(data.rr_data[j], 2)[0].to_text(omit_final_dot=True)
                                    match = mod_env['dnsbl'].policy_match(query, qstate, tmp)
                                    query.domain = tmp
                                    if match:
                                        # the iterator module has already resolved the answer and cached it,
                                        # make sure we remove it from the cache in order to block future queries for the same domain
                                        if obj_path_exists(qstate, 'return_msg.qinfo'):
                                            invalidateQueryInCache(qstate, qstate.return_msg.qinfo)
                                        # change the type to CNAME if a match is found to indicate
                                        # that a CNAME was the reason for blocking this domain.
                                        query.type = 'CNAME'
                                        if not set_answer_block(qstate, qdata, query, match):
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
