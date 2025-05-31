{% if (
    not helpers.empty('OPNsense.Kea.dhcp4.general.interfaces') and not helpers.empty('OPNsense.Kea.dhcp4.general.enabled')
    ) or (
    not helpers.empty('OPNsense.Kea.dhcp6.general.interfaces') and not helpers.empty('OPNsense.Kea.dhcp6.general.enabled')
) %}
kea_enable="YES"
kea_setup="/usr/local/sbin/pluginctl -c kea_sync"
{% else %}
kea_enable="NO"
{% endif %}
