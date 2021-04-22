# $FreeBSD$
#
# System-wide .cshrc file for csh(1).
{% if not helpers.empty('system.autologout')%}
set -r autologout={{system.autologout}}
{% endif %}
