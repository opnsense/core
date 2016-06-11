{# Macro import #}
{% from 'OPNsense/Macros/interface.macro' import physical_interface %}
{% if helpers.exists('OPNsense.IDS.general') and OPNsense.IDS.general.enabled|default("0") == "1" %}
suricata_enable="YES"
suricata_opnsense_bootup_run="/usr/local/opnsense/scripts/suricata/setup.sh"

{% if OPNsense.IDS.general.ips|default("0") == "1" %}
# IPS mode, switch to netmap
suricata_netmap=YES

{% else %}

# IDS mode, pcap live mode
{% set addFlags=[] %}
{% for intfName in OPNsense.IDS.general.interfaces.split(',') %}
{%   if loop.index == 1 %}
{# enable first interface #}
suricata_interface="{{ physical_interface(intfName) }}"
{%   else %}
{#   store additional interfaces to addFlags #}
{%      do addFlags.append(physical_interface(intfName)) %}
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
