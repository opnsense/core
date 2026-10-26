{% if not helpers.empty('dnsmasq.enable') %}
dnsmasq_enable="YES"
dnsmasq_skip="YES"
. /usr/local/opnsense/scripts/service/supervisor.subr.sh
supervisor_enable
# Keep dnsmasq in the foreground so daemon(8) supervises the service process.
dnsmasq_flags="-k"
{% else %}
dnsmasq_enable="NO"
{% endif %}
