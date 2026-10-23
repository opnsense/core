{% if not helpers.empty('OPNsense.Hostwatch.general.enabled') %}
hostwatch_enable="YES"
hostwatch_flags="-c -S"
{%  if helpers.empty('OPNsense.Hostwatch.general.promisc') %}
hostwatch_flags="${hostwatch_flags} -p"
{%  endif %}
{%  if not helpers.empty('OPNsense.Hostwatch.general.expire4_interval') %}
hostwatch_flags="${hostwatch_flags} -E '{{OPNsense.Hostwatch.general.expire4_interval}}'"
{%  endif %}
{%  if not helpers.empty('OPNsense.Hostwatch.general.expire6_interval') %}
hostwatch_flags="${hostwatch_flags} -e '{{OPNsense.Hostwatch.general.expire6_interval}}'"
{%  endif %}
hostwatch_pidfile="/var/run/hostwatch/hostwatch.pid"
{%  if not helpers.empty('OPNsense.Hostwatch.general.skip_nets') %}
hostwatch_skip_nets="{{ OPNsense.Hostwatch.general.skip_nets|replace(',', ' ')}}"
{%  endif %}
{%  if not helpers.empty('OPNsense.Hostwatch.general.interface') %}
hostwatch_interfaces="{{ helpers.physical_interfaces(OPNsense.Hostwatch.general.interface.split(','))|join(' ')}}"
{%  endif %}
hostwatch_setup="/usr/local/opnsense/scripts/interfaces/setup_hostwatch.sh"
{%  if 'hostwatch' in (OPNsense.Service.supervisor|default('')).split(',') %}
. /usr/local/opnsense/scripts/service/supervisor.subr.sh
supervisor_restart_count="{{ OPNsense.Service.restart_count|default('5') }}"
supervisor_restart_delay="{{ OPNsense.Service.restart_delay|default('10') }}"
supervisor_enable
# Keep hostwatch in the foreground so daemon(8) supervises the service process.
hostwatch_flags="${hostwatch_flags} -f"
{%  endif %}
{% else %}
hostwatch_enable="NO"
{% endif %}
