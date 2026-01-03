{% set cp_zones = [] %}
{% if helpers.exists('OPNsense.captiveportal.zones.zone') %}
{%      for cp_item in helpers.toList('OPNsense.captiveportal.zones.zone')  %}
{%          if cp_item.enabled|default("0") == '1' %}
{%              do cp_zones.append(cp_key) %}
{%          endif %}
{%      endfor %}
{% endif %}
{# collect enabled #}
{% set rules = [] %}
{% if helpers.exists('OPNsense.TrafficShaper') %}
{%     if helpers.exists('OPNsense.TrafficShaper.rules.rule') %}
{%         for rule in helpers.toList('OPNsense.TrafficShaper.rules.rule') %}
{%           if rule.enabled|default("0") == '1' %}
{%             do rules.append(rule) %}
{%           endif %}
{%         endfor%}
{%     endif %}
{% endif %}
firewall_enable="{% if cp_zones or rules %}YES{% else %}NO{% endif %}"
firewall_script="/usr/local/etc/rc.ipfw"
ipfw_skip="YES"
