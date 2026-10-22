{% if not helpers.empty('OPNsense.IDS.general.enabled') %}
suricata_setup="/usr/local/opnsense/scripts/suricata/setup.sh"
suricata_enable="YES"
{% if not helpers.empty('OPNsense.IDS.general.supervisor') %}
. /usr/local/opnsense/scripts/service/supervisor.subr.sh
supervisor_reload_signal="USR2"
supervisor_enable
# Keep this non-empty to prevent the rc script from restoring its -D default.
suricata_flags=" "
{% else %}
suricata_flags="-D"
{% endif %}
{% if not helpers.empty('OPNsense.IDS.general.verbosity') %}
suricata_flags="${suricata_flags} -{{OPNsense.IDS.general.verbosity}}"
{% endif %}
{% if OPNsense.IDS.general.mode|default("") == "netmap" %}
# IPS mode, switch to netmap
suricata_netmap="YES"
{% elif OPNsense.IDS.general.mode|default("") == "divert" %}
# IPS mode, divert sockets
suricata_divertport="8000"
{# add rest of listeners, above adds the first #}
{% set addFlags=[] %}
{% set listeners = OPNsense.IDS.general.divert_listeners|default('1')|int %}
{%   for idx in range(1, listeners) %}
{%     do addFlags.append('-d 8000') %}
{%   endfor %}
suricata_flags="${suricata_flags} {{ addFlags|join(' ') }}"
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
