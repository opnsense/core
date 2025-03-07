{% set isEnabled=[] %}
{% if helpers.exists('OPNsense.TrafficShaper.pipes.pipe') %}
{%   for pipe in helpers.toList('OPNsense.TrafficShaper.pipes.pipe') %}
{%     if pipe.enabled|default('0') == '1' %}
{%	     do isEnabled.append(pipe) %}
{%     endif %}
{%   endfor %}
{% endif %}
dummynet_enable="YES"
dnctl_enable="{%if isEnabled %}YES{% else %}NO{% endif %}"
dnctl_rules="/usr/local/etc/dnctl.conf"
dnctl_setup="/usr/local/opnsense/scripts/shaper/setup.sh"
dnctl_skip="YES"
