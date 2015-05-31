{% set cp_zones = [] %}
{% if helpers.exists('captiveportal') %}
{%      for cp_key,cp_item in captiveportal.iteritems()  %}
{%          if cp_item.enable|default("0") == '1' %}
{%              do cp_zones.append(cp_key) %}
{%          endif %}
{%      endfor %}
{% endif %}
firewall_enable="{% if OPNsense.TrafficShaper.enabled|default("0") == "1" or cp_zones %}YES{% else %}NO{% endif %}"
firewall_script="/usr/local/etc/rc.ipfw"
dummynet_enable="YES"
