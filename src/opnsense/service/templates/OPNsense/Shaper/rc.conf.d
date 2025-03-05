{% set isEnabled=[] %}
{% if helpers.exists('OPNsense.TrafficShaper.pipes.pipe') %}
{%   for pipe in helpers.toList('OPNsense.TrafficShaper.pipes.pipe') %}
{%     if pipe.enabled|default('0') == '1' %}
{%	     do isEnabled.append(pipe) %}
{%     endif %}
{%   endfor %}
{% endif %}

{% if helpers.exists('OPNsense.TrafficShaper.queues.queue') %}
{%   for queue in helpers.toList('OPNsense.TrafficShaper.queues.queue') %}
{%     if queue.enabled|default('0') == '1' %}
{%	     do isEnabled.append(queue) %}
{%     endif %}
{%   endfor %}
{% endif %}
dummynet_enable="YES"
dnctl_enable="{%if isEnabled %}YES{% else %}NO{% endif %}"
dnctl_rules="/usr/local/etc/dnctl.conf"
