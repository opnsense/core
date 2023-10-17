{% if not helpers.empty('OPNsense.Kea.dhcp4.general.interfaces') and not helpers.empty('OPNsense.Kea.dhcp4.general.enabled') %}
kea_enable="YES"
{% else %}
kea_enable="NO"
{% endif %}