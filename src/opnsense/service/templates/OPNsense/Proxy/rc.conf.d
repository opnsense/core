squid_enable={% if OPNsense.proxy.general.enabled|default("0") == "1" %}YES{% else %}NO{% endif %}
