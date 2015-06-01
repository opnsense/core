{% set cp_zones = [] %}
{% if helpers.exists('captiveportal') %}
{%      for cp_key,cp_item in captiveportal.iteritems()  %}
{%          if cp_item.enable|default("0") == '1' %}
{%              do cp_zones.append(cp_key) %}
{%          endif %}
{%      endfor %}
{% endif %}
{# collect enabled #}
{% set shapers = [] %}
{% if helpers.exists('OPNsense.TrafficShaper') %}
{%     if helpers.exists('OPNsense.TrafficShaper.pipes.pipe') %}
{%         for pipe in helpers.toList('OPNsense.TrafficShaper.pipes.pipe') %}
{%             if pipe.enabled|default('0') == '1' %}
{%                 do shapers.append(cp_key) %}
{%             endif%}
{%         endfor%}
{%     endif %}
{% endif %}
firewall_enable="{% if shapers or cp_zones %}YES{% else %}NO{% endif %}"
firewall_script="/usr/local/etc/rc.ipfw"
dummynet_enable="YES"
