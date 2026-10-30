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
$errors = [];
foreach ($backupFact->listProviders() as $classname => $provider) {
    if ($provider['handle']->isEnabled()) {
        try {
            $provider['handle']->backup();
        } catch (Exception $e) {
            $filesInBackup = array();
            $errors[] = $e->getMessage();
        }
    }
}

if (!empty($errors)) {
    echo "The following errors where encountered during backup:\n";
    echo "-----------------------------------------------------\n";
    foreach ($errors as $error) {
        echo $error . "\n";
    }
    exit(1);
}
