{% if not helpers.empty('OPNsense.unboundplus.dnsbl.enabled') and not helpers.empty('OPNsense.unboundplus.dnsbl.whitelists')%}
{{ OPNsense.unboundplus.dnsbl.whitelists|default("") }}
{% endif %}
