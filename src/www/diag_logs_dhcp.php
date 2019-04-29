<?php

$logfile = '/var/log/dhcpd.log';
$logclog = true;

function clear_hook()
{
    killbyname('dhcpd');
    plugins_configure('dhcp');
}

$service_hook = 'dhcpd';

require_once 'diag_logs_template.inc';
