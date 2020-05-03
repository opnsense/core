{% if not helpers.empty('OPNsense.unboundplus.dnsbl.enabled') and not helpers.empty('OPNsense.unboundplus.dnsbl.type') %}
unbound_dnsbl="{{ OPNsense.unboundplus.dnsbl.type }}"
{% endif %}
