<?php

$logfile = '/var/log/ntpd.log';
$logclog = true;

require_once 'services.inc';
$service_hook = 'ntpd';

require_once 'diag_logs_template.inc';
