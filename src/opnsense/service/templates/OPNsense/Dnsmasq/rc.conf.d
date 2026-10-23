{% if not helpers.empty('dnsmasq.enable') %}
dnsmasq_enable="YES"
dnsmasq_skip="YES"
{% if 'dnsmasq' in (OPNsense.Service.supervisor|default('')).split(',') %}
. /usr/local/opnsense/scripts/service/supervisor.subr.sh
supervisor_restart_count="{{ OPNsense.Service.restart_count|default('5') }}"
supervisor_restart_delay="{{ OPNsense.Service.restart_delay|default('10') }}"
supervisor_enable
# Keep dnsmasq in the foreground so daemon(8) supervises the service process.
dnsmasq_flags="-k"
{% endif %}
{% else %}
dnsmasq_enable="NO"
{% endif %}
