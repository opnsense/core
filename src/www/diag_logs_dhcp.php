<?php

$logfile = '/var/log/dhcpd.log';
$logclog = true;

function clear_hook()
{
    killbyname('dhcpd');
    services_dhcpd_configure();
}

$service_hook = 'dhcpd';

require_once 'diag_logs_template.inc';
