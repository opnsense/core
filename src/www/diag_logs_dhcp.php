<?php

$logfile = '/var/log/dhcpd.log';
$logclog = true;

function clear_hook()
{
	killbyname('dhcpd');
	services_dhcpd_configure();
}

require_once 'services.inc';
$service_hook = 'dhcpd';

require_once 'diag_logs_template.inc';
