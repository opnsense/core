<?php

$logfile = '/var/log/resolver.log';
$logclog = true;

$service_hook = array('dnsmasq', 'unbound');

require_once 'diag_logs_template.inc';
