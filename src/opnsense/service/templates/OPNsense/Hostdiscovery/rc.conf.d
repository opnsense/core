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
{% else %}
hostwatch_enable="NO"
{% endif %}
