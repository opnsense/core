{% if helpers.exists('OPNsense.unboundplus.dnsbl.enabled') and OPNsense.unboundplus.dnsbl.enabled == '1' %}
{%   if helpers.exists('OPNsense.unboundplus.dnsbl.type') and OPNsense.unboundplus.dnsbl.type != '' %}
unbound_dnsbl="{{ OPNsense.unboundplus.dnsbl.type }}"
{%   endif %}
{% endif %}
