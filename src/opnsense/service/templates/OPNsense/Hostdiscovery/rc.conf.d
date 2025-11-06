{% if not helpers.empty('OPNsense.Hostwatch.general.enabled') %}
hostwatch_enable="YES"
hostwatch_flags="{% if helpers.empty('OPNsense.Hostwatch.general.promisc') %} -p {% endif %} -c -S -u hostd -g hostd"
hostwatch_pidfile="/var/run/hostwatch/hostwatch.pid"
{%   if  not helpers.empty('OPNsense.Hostwatch.general.skip_nets') %}
hostwatch_skip_nets="{{ OPNsense.Hostwatch.general.skip_nets|replace(',', ' ')}}"
{%   endif %}
{%   if  not helpers.empty('OPNsense.Hostwatch.general.interface') %}
hostwatch_interfaces="{{ helpers.physical_interfaces(OPNsense.Hostwatch.general.interface.split(','))|join(' ')}}"
{%   endif %}
hostwatch_setup="/usr/local/opnsense/scripts/interfaces/setup_hostwatch.sh"
{% else %}
hostwatch_enable="NO"
{% endif %}
