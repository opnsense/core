#!/usr/local/bin/php
<?php

/**
 *    Copyright (C) 2017 EURO-LOG AG
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

require_once("config.inc");

use OPNsense\Core\Config;
use OPNsense\Core\Shell;
use OPNsense\Monit\Monit;

$mdlMonit = new Monit();

$cfg = Config::getInstance();
$cfgObj = $cfg->object();
$shellObj = new OPNsense\Core\Shell;
$generalNode = $mdlMonit->getNodeByReference('general');

if (empty($cfgObj->OPNsense->monit->general->httpdUsername) && empty($cfgObj->OPNsense->monit->general->httpdPassword)) {
    print "Generate Monit httpd username and password\n";
    srand();
    $generalNode->setNodes(array(
         "httpdUsername" => "root",
         "httpdPassword" => substr(str_shuffle(str_repeat('0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz', 32)), rand(0, 16), rand(17, 32))
      ));
    $mdlMonit->serializeToConfig(false, true);
    $cfg->save();
}

$nodes = $mdlMonit->getNodes();
// test if Monit is already configured
if (count($nodes['service']) != 0 || count($nodes['test']) != 0) {
    exit;
}

// get number of cpus and calculate load average limits
$nCPU = array();
$shellObj->exec('/sbin/sysctl -n kern.smp.cpus', false, $nCPU);
$LoadAvg1 = $nCPU[0] * 2;
$LoadAvg5 = $nCPU[0] + ($nCPU[0] / 2);
$LoadAvg15 = $nCPU[0];

// inherit SMTP settings from System->Settings->Notifications
$generalSettings = array();
if (!empty($cfgObj->notifications->smtp->ipaddress)) {
    $generalSettings['mailserver'] = $cfgObj->notifications->smtp->ipaddress;
}
if (!empty($cfgObj->notifications->smtp->port)) {
    $generalSettings['port'] = $cfgObj->notifications->smtp->port;
}
if (!empty($cfgObj->notifications->smtp->username)) {
    $generalSettings['username'] = $cfgObj->notifications->smtp->username;
}
if (!empty($cfgObj->notifications->smtp->password)) {
    $generalSettings['password'] = $cfgObj->notifications->smtp->password;
}
if ((!empty($cfgObj->notifications->smtp->tls) && $cfgObj->notifications->smtp->tls == 1)  ||
    (!empty($cfgObj->notifications->smtp->ssl) && $cfgObj->notifications->smtp->ssl == 1)) {
    $generalSettings['ssl'] = 1;
}

$alertSettings = array();
if (!empty($cfgObj->notifications->smtp->notifyemailaddress)) {
    $alertSettings['recipient'] = $cfgObj->notifications->smtp->notifyemailaddress;
}
if (!empty($cfgObj->notifications->smtp->fromaddress)) {
    $alertSettings['format'] = 'from: ' . $cfgObj->notifications->smtp->fromaddress;
}

// define some tests
$defaultTests = array(
    array("name" => "Ping", "condition" => "failed ping", "action" => "alert"),
    array("name" => "NetworkLink", "condition" => "failed link", "action" => "alert"),
    array("name" => "NetworkSaturation", "condition" => "saturation is greater than 75%", "action" => "alert"),
    array("name" => "MemoryUsage", "condition" => "memory usage is greater than 75%", "action" => "alert"),
    array("name" => "CPUUsage", "condition" => "cpu usage is greater than 75%", "action" => "alert"),
    array("name" => "LoadAvg1", "condition" => "loadavg (1min) is greater than $LoadAvg1", "action" => "alert"),
    array("name" => "LoadAvg5", "condition" => "loadavg (5min) is greater than $LoadAvg5", "action" => "alert"),
    array("name" => "LoadAvg15", "condition" => "loadavg (15min) is greater than $LoadAvg15", "action" => "alert"),
    array("name" => "SpaceUsage", "condition" => "space usage is greater than 75%", "action" => "alert")
);

// define system service
$systemService = array(
    "enabled" => 1,
    "name" => '$HOST',
    "type" => "system",
    "tests" => ""
);

// define root filesystem service
$rootFsService = array(
    "enabled" => 1,
    "name" => "RootFs",
    "type" => "filesystem",
    "path" => "/",
    "tests" => ""
);

foreach ($defaultTests as $defaultTest) {
    $testNode = $mdlMonit->test->Add();
    $testNode->setNodes($defaultTest);
    if ($defaultTest['name'] == "MemoryUsage" ||
        $defaultTest['name'] == "CPUUsage" ||
        $defaultTest['name'] == "LoadAvg1" ||
        $defaultTest['name'] == "LoadAvg5" ) {
            $systemService['tests'] .= $testNode->getAttributes()['uuid'] . ",";
    }
    if ($defaultTest['name'] == "SpaceUsage") {
            $rootFsService['tests'] .= $testNode->getAttributes()['uuid'] . ",";
    }
}

// remove last comma from tests csv
$systemService['tests'] = substr($systemService['tests'], 0, -1);
$rootFsService['tests'] = substr($rootFsService['tests'], 0, -1);

// set general properties
$generalNode->setNodes($generalSettings);

// add an alert with (almost) default settings
$alertNode = $mdlMonit->alert->Add();
$alertNode->setNodes($alertSettings);

// add system service
$serviceNode = $mdlMonit->service->Add();
$serviceNode->setNodes($systemService);

// add root filesystem service
$rootFsNode = $mdlMonit->service->Add();
$rootFsNode->setNodes($rootFsService);

// ignore validations because ModelRelationField does not work
$mdlMonit->serializeToConfig(false, true);
$cfg->save();
