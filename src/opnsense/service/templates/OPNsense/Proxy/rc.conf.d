{% if helpers.exists('OPNsense.proxy.general.enabled') and OPNsense.proxy.general.enabled|default("0") == "1" %}
squid_enable=YES
squid_opnsense_bootup_run="/usr/local/opnsense/scripts/proxy/setup.sh"
squid_var_script="/usr/local/opnsense/scripts/proxy/setup.sh"
{% else %}
squid_enable=NO
{% endif %}
