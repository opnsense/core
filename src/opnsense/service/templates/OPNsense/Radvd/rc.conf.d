radvd_enable="NO"
radvd_config="/var/etc/radvd.conf"
. /usr/local/opnsense/scripts/service/supervisor.subr.sh
supervisor_enable
# Keep radvd in the foreground so daemon(8) supervises the service process.
radvd_flags="-n -m syslog"
