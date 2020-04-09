{%  if helpers.exists('OPNsense.unboundplus.dnsbl.enabled') and OPNsense.unboundplus.dnsbl.enabled == '1' %}
{%      if helpers.exists('OPNsense.unboundplus.dnsbl.lists') and OPNsense.unboundplus.dnsbl.lists != '' %}
{{ OPNsense.unboundplus.dnsbl.lists|default("") }}
{%      endif %}
{%  endif %}
