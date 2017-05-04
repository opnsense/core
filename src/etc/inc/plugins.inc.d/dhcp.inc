<?php

function dhcp_syslog()
{
    $logfacilities = array();

    $logfacilities['dhcpd'] = array(
        'facility' => array('dhcpd', 'dhcrelay', 'dhclient', 'dhcp6c'),
        'remote' => 'dhcp',
        'local' => '/var/dhcpd/var/run/log',
    );

    return $logfacilities;
}

