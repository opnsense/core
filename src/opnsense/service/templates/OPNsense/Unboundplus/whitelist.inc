{%   if helpers.exists('OPNsense.unboundplus.dnsbl.enabled') and OPNsense.unboundplus.dnsbl.enabled == '1' %}
{%     if helpers.exists('OPNsense.unboundplus.dnsbl.whitelists') and OPNsense.unboundplus.dnsbl.whitelists != '' %}
{{ OPNsense.unboundplus.dnsbl.whitelists|default("") }}
{%     endif %}
{%   endif %}
