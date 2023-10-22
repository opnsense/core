{% if not helpers.empty('OPNsense.IDS.general.enabled') %}
suricata_setup="/usr/local/opnsense/scripts/suricata/setup.sh"
suricata_enable="YES"
{% if not helpers.empty('OPNsense.IDS.general.verbosity') %}
suricata_flags="-D -{{OPNsense.IDS.general.verbosity}}"
{% endif %}
{% if OPNsense.IDS.general.ips|default("0") == "1" %}
# IPS mode, switch to netmap
suricata_netmap="YES"
{% else %}
# IDS mode, pcap live mode
{% set addFlags=[] %}
{%   for intfName in OPNsense.IDS.general.interfaces.split(',') %}
{#     store additional interfaces to addFlags #}
{%     do addFlags.append(helpers.physical_interface(intfName)) %}
{%   endfor %}
suricata_interface="{{ addFlags|join(' ') }}"
{% endif %}
{% else %}
suricata_enable="NO"
{% endif %}
