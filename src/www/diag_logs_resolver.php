<?php

$logfile = '/var/log/resolver.log';
$logclog = false;

$service_hook = array('dnsmasq', 'unbound');

require_once 'diag_logs_template.inc';
