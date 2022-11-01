import os
import json
import time
import errno
from threading import Lock
from collections import deque

ACTION_PASS = 0
ACTION_BLOCK = 1
ACTION_DROP = 2

RESPONSE_NEW = 0
RESPONSE_LOCAL = 1
RESPONSE_CACHE = 2
RESPONSE_SERVFAIL = 3

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

        self.pipe_name = '/data/dns_logger'
        self.pipe_fd = None
        self.pipe_timer = 0
        self.lock = Lock()
        self.pipe_buffer = deque(maxlen=100000) # buffer to hold qdata as long as a backend is not present

        self.update_dnsbl(self.log_update_time)
        self.create_pipe_rdv()

    def dnsbl_exists(self):
        return os.path.isfile(self.dnsbl_path) and os.path.getsize(self.dnsbl_path) > 0

    def load_dnsbl(self):
        with open(self.dnsbl_path, 'r') as f:
            try:
                mod_env['dnsbl'] = json.load(f)
                log_info('dnsbl_module: blocklist loaded. length is %d' % len(mod_env['dnsbl']['data']))
                config = mod_env['dnsbl']['config']
                self.dst_addr = config['dst_addr']
                self.rcode = RCODE_NXDOMAIN if config['rcode'] == 'NXDOMAIN' else RCODE_NOERROR
            except json.decoder.JSONDecodeError as e:
                if not 'dnsbl' in mod_env:
                    log_err("dnsbl_module: unable to bootstrap blocklist, this is likely due to a corrupted \
                            file. Please re-apply the blocklist settings.")
                    self.dnsbl_available = False
                    return
                else:
                    log_err("dnsbl_module: error parsing blocklist: %s, reusing last known list" % e)

        self.dnsbl_available = True

    def dnssec_enabled(self):
        return 'validator' in self.env.cfg.module_conf

    # Defines the rendezvous point and tries to open the pipe.
    # If no reader is present, subsequent calls to log_entry
    # will attempt to open the pipe if necessary while being throttled
    # by a default timer
    def create_pipe_rdv(self):
        if os.path.exists(self.pipe_name):
            os.unlink(self.pipe_name)
        os.mkfifo(self.pipe_name)
        self.try_open_pipe()

    def try_open_pipe(self):
        try:
            # try to obtain the fd in a non-blocking manner and catch the ENXIO exception
            # if the other side is not listening.
            self.pipe_fd = os.open(self.pipe_name, os.O_NONBLOCK | os.O_WRONLY)
        except OSError as e:
            if e.errno == errno.ENXIO:
                self.pipe_fd = None
                return False
            else:
                raise

        return True

    def log_entry(self, t, client, family, type, domain, action, response_type):
        kwargs = locals()
        del kwargs['self']
        self.pipe_buffer.append(kwargs)
        if self.pipe_fd is None:
            if (time.time() - self.pipe_timer) > 10:
                self.pipe_timer = time.time()
                if not self.try_open_pipe():
                    return
            else:
                return

        with self.lock:
            l = None
            try:
                while len(self.pipe_buffer) > 0:
                    l = self.pipe_buffer.popleft()
                    log = "{t} {client} {family} {type} {domain} {action} {response_type}\n".format(**l)
                    os.write(self.pipe_fd, log.encode())
            except BrokenPipeError:
                log_warn("Logging backend closed connection unexpectedly. Closing pipe and continuing.")
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

    def filter_query(self, id, qstate):
        t = time.time()
        self.update_dnsbl(t)
        qname = qstate.qinfo.qname_str
        qtype = qstate.qinfo.qtype_str
        client = qstate.mesh_info.reply_list.query_reply

        if self.dnsbl_available and qname.rstrip('.') in mod_env['dnsbl']['data']:
            qstate.return_rcode = self.rcode

            if self.rcode == RCODE_NXDOMAIN:
                # exit early
                self.log_entry(t, client.addr, client.family, qtype, qname, ACTION_BLOCK, RESPONSE_LOCAL)
                qstate.ext_state[id] = MODULE_FINISHED
                return True

            msg = DNSMessage(qname, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
            if (qtype == RR_TYPE_A) or (qtype == RR_TYPE_ANY):
                msg.answer.append("%s 3600 IN A %s" % (qname, self.dst_addr))
            if not msg.set_return_msg(qstate):
                qstate.ext_state[id] = MODULE_ERROR
                log_err("dnsbl_module: unable to create response for %s, dropping query" % qname)
                self.log_entry(t, client.addr, client.family, qtype, qname, ACTION_DROP, RESPONSE_LOCAL)
                return True

            if self.dnssec_enabled():
                qstate.return_msg.rep.security = 2
            self.log_entry(t, client.addr, client.family, qtype, qname, ACTION_BLOCK, RESPONSE_LOCAL)
            qstate.ext_state[id] = MODULE_FINISHED
        else:
            # Pass the query to validator/iterator
            self.log_entry(t, client.addr, client.family, qtype, qname, ACTION_PASS, RESPONSE_NEW)
            qstate.ext_state[id] = MODULE_WAIT_MODULE

        return True

def query_cb(qinfo, flags, qstate, addr, zone, region, **kwargs):
    # use this to determine the server(s) interrogated for a query
    # this requires more complex logic (it's called for every call to a server during recursion) so leave as-is for now

    return True

def cache_cb(qinfo, qstate, rep, rcode, edns, opt_list_out,
                           region, **kwargs):
    ctx = mod_env['context']
    client = kwargs['repinfo']
    ctx.log_entry(time.time(), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str, ACTION_PASS, RESPONSE_CACHE)
    return True

def local_cb(qinfo, qstate, rep, rcode, edns, opt_list_out,
                           region, **kwargs):
    ctx = mod_env['context']
    client = kwargs['repinfo']
    ctx.log_entry(time.time(), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str, ACTION_PASS, RESPONSE_LOCAL)
    return True

def servfail_cb(qinfo, qstate, rep, rcode, edns, opt_list_out,
                              region, **kwargs):
    ctx = mod_env['context']
    client = kwargs['repinfo']
    ctx.log_entry(time.time(), client.addr, client.family, qinfo.qtype_str, qinfo.qname_str, ACTION_DROP, RESPONSE_SERVFAIL)
    return True

def init_standard(id, env):
    ctx = ModuleContext(env)
    mod_env['context'] = ctx

    if not register_inplace_cb_query(query_cb, env, id):
        return False

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

    try:
        os.unlink(ctx.pipe_name)
    except Exception as e:
        log_err(e)

    return True

def inform_super(id, qstate, superqstate, qdata):
    return True

def operate(id, event, qstate, qdata):
    if (event == MODULE_EVENT_NEW):
        ctx = mod_env['context']
        return ctx.filter_query(id, qstate)

    if event == MODULE_EVENT_MODDONE:
        # Iterator finished, show response (if any)
        qstate.ext_state[id] = MODULE_FINISHED
        return True

    if event == MODULE_EVENT_PASS:
        qstate.ext_state[id] = MODULE_WAIT_MODULE
        return True

    log_err("pythonmod: bad event. Query was %s" % qstate.qinfo.qname_str)
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
