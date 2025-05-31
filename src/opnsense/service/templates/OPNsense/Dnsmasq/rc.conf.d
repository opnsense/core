{% if not helpers.empty('dnsmasq.enable') %}
dnsmasq_enable="YES"
dnsmasq_skip="YES"
{% else %}
dnsmasq_enable="NO"
{% endif %}
