{% if helpers.exists('OPNsense.IDS.general') and OPNsense.IDS.general.enabled|default("0") == "1" %}
suricata_enable="YES"

{% if OPNsense.IDS.general.ips|default("0") == "1" %}
# IPS mode, switch to netmap

{% for intfName in OPNsense.IDS.general.interfaces.split(',') %}
{%   if loop.index == 1 %}
suricata_startup_flags="--netmap  --pidfile /var/run/suricata_{{helpers.getNodeByTag('interfaces.'+intfName).if}}.pid"
{%   endif %}
{% endfor %}

{% else %}

# IDS mode, pcap live mode
{% set addFlags=[] %}
{% for intfName in OPNsense.IDS.general.interfaces.split(',') %}
{%   if loop.index == 1 %}
{# enable first interface #}
suricata_interface="{{helpers.getNodeByTag('interfaces.'+intfName).if}}"
{%   else %}
{#   store additional interfaces to addFlags #}
{%      do addFlags.append(helpers.getNodeByTag('interfaces.'+intfName).if) %}
{%   endif %}
{% endfor %}
{#   append additional interfaces #}
suricata_flags="-D {%
   for intf in addFlags
%} -i {{ intf }}  {% endfor
%} "

{% endif %}

{% else %}
suricata_enable="NO"
{% endif %}

suricata_opnsense_bootup_run="/usr/local/opnsense/scripts/suricata/setup.sh"
