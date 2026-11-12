{% if (
    not helpers.empty('OPNsense.Kea.dhcp4.general.interfaces') and not helpers.empty('OPNsense.Kea.dhcp4.general.enabled')
    ) or (
    not helpers.empty('OPNsense.Kea.dhcp6.general.interfaces') and not helpers.empty('OPNsense.Kea.dhcp6.general.enabled')
) %}
kea_supervisor_enable="YES"
{% else %}
kea_supervisor_enable="NO"
{% endif %}
# The supervisor service owns the aggregate Kea lifecycle.
kea_enable="NO"
