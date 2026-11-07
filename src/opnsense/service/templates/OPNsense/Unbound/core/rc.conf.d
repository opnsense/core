unbound_config="/var/unbound/unbound.conf"
# OPNsense prepares the trust anchor before invoking the rc script.
start_precmd="unbound_startfib"
. /usr/local/opnsense/scripts/service/supervisor.subr.sh
supervisor_enable
# Keep unbound in the foreground so daemon(8) supervises the service process.
unbound_flags="-d"
