<?php

$logfile = '/var/log/dhcpd.log';
$logclog = true;

function clear_hook()
{
	killbyname('dhcpd');
	services_dhcpd_configure();
}

require_once 'services.inc';
$shortcut_section = 'dhcp';

require_once 'diag_logs_template.inc';
