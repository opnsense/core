{% if helpers.exists('OPNsense.proxy.general.enabled') and OPNsense.proxy.general.enabled|default("0") == "1" %}
squid_enable=YES
squid_opnsense_bootup_run="/usr/local/opnsense/scripts/proxy/setup.sh"
{%   if helpers.exists('system.authserver') %}
{%     for server in helpers.toList('system.authserver') %}
{%       if server.name == OPNsense.proxy.forward.authentication.method %}
{%         if server.type == "ssoproxyad" %}
squid_krb5_ktname="/usr/local/etc/ssoproxyad/PROXY.keytab"
{%         endif %}
{%       endif %}
{%     endfor %}
{%   endif %}
{% else %}
squid_enable=NO
{% endif %}
