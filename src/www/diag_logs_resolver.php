<?php

$logfile = '/var/log/resolver.log';
$logclog = true;

require_once 'services.inc';
$shortcut_section = array('dnsmasq', 'unbound');

require_once 'diag_logs_template.inc';
