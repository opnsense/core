squid_enable={% if OPNsense.proxy.general.enabled|default("0") == "1" %}YES{% else %}NO{% endif %}

squid_opnsense_bootup_run="/usr/local/opnsense/scripts/proxy/setup.sh"

