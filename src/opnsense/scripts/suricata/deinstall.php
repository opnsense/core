#!/usr/local/bin/php

<?php

require_once('script/load_phalcon.php');

use \OPNsense\Core\Config;
use \OPNsense\IDS\IDS;
use \OPNsense\Core\Backend;

if ($argc < 2) {
    echo "deinstall <rules_file>";
    return 1;
}

$xml = simplexml_load_file($argv[1]);
$mdlIDS = new IDS();

foreach ($xml->files->file as $name) {
    foreach ($mdlIDS->files->file->getChildren() as $file) {
        if ($name->__toString() == $file->filename->__toString()) {
            $file->enabled = "0";
            break;
        }
    }
}

$mdlIDS->serializeToConfig();
Config::getInstance()->save();

$backend = new Backend();
$backend->configdRun('template reload OPNsense/IDS');
$backend->configdRun("ids update");
