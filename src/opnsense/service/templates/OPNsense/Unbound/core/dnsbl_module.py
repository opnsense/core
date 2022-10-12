import os
import json
import time

class ModuleContext:
    def __init__(self, env):
        self.env = env
        self.dnsbl_path = '/data/dnsbl.json'
        self.dst_addr = '0.0.0.0'
        self.rcode = RCODE_NOERROR
        self.dnsbl_mtime_cache = 0
        self.dnsbl_update_time = 0
        self.dnsbl_available = False

        self.update_dnsbl()

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

    def update_dnsbl(self):
        if (time.time() - self.dnsbl_update_time) > 60:
            self.dnsbl_update_time = time.time()
            if not self.dnsbl_exists():
                self.dnsbl_available = False
                return
            fstat = os.stat(self.dnsbl_path).st_mtime
            if fstat != self.dnsbl_mtime_cache:
                self.dnsbl_mtime_cache = fstat
                log_info("dnsbl_module: updating blocklist.")
                self.load_dnsbl()

    def filter_query(self, id, qstate):
        self.update_dnsbl()
        qname = qstate.qinfo.qname_str
        if self.dnsbl_available and qname.rstrip('.') in mod_env['dnsbl']['data']:
            qstate.return_rcode = self.rcode

            if self.rcode == RCODE_NXDOMAIN:
                # exit early
                qstate.ext_state[id] = MODULE_FINISHED
                return True

            qtype = qstate.qinfo.qtype
            msg = DNSMessage(qname, RR_TYPE_A, RR_CLASS_IN, PKT_QR | PKT_RA | PKT_AA)
            if (qtype == RR_TYPE_A) or (qtype == RR_TYPE_ANY):
                msg.answer.append("%s 3600 IN A %s" % (qname, self.dst_addr))
            if not msg.set_return_msg(qstate):
                qstate.ext_state[id] = MODULE_ERROR
                log_err("dnsbl_module: unable to create response for %s, dropping query" % qname)
                return True

            if self.dnssec_enabled():
                qstate.return_msg.rep.security = 2
            qstate.ext_state[id] = MODULE_FINISHED
        else:
            # Pass the query to validator/iterator
            qstate.ext_state[id] = MODULE_WAIT_MODULE

        return True

def init_standard(id, env):
    ctx = ModuleContext(env)
    mod_env['context'] = ctx
    return True

def deinit(id):
    return True

def inform_super(id, qstate, superqstate, qdata):
    return True

def operate(id, event, qstate, qdata):
    if (event == MODULE_EVENT_NEW) or (event == MODULE_EVENT_PASS):
        ctx = mod_env['context']
        return ctx.filter_query(id, qstate)

    if event == MODULE_EVENT_MODDONE:
        # Iterator finished, show response (if any)
        qstate.ext_state[id] = MODULE_FINISHED
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
