{% if helpers.exists('OPNsense.IDS.general') and OPNsense.IDS.general.enabled|default("0") == "1" %}
suricata_opnsense_bootup_run="/usr/local/opnsense/scripts/suricata/setup.sh"
suricata_enable="YES"
{% if OPNsense.IDS.general.ips|default("0") == "1" %}
suricata_netmap="YES"
{% else %}
# IDS mode, pcap live mode
suricata_flags="-D --pcap"
{% else %}
suricata_enable="NO"
{% endif %}
{% endif %}
