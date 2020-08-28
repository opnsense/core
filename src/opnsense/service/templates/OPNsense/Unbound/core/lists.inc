{%  if not helpers.empty('OPNsense.unboundplus.dnsbl.enabled') and not helpers.empty('OPNsense.unboundplus.dnsbl.lists') %}
{{ OPNsense.unboundplus.dnsbl.lists|default("") }}
{%  endif %}
