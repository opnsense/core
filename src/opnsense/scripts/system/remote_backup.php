#!/usr/local/bin/php
<?php

require_once('script/load_phalcon.php');

use OPNsense\Backup\BackupFactory;

/* add random delay in seconds */
$random_delay = !empty($argv[1]) ? $argv[1] : null;
if (!empty($random_delay)) {
    sleep(random_int(0, $random_delay));
}

$backupFact = new BackupFactory();
foreach ($backupFact->listProviders() as $classname => $provider) {
    if ($provider['handle']->isEnabled()) {
        $provider['handle']->backup();
    }
}
