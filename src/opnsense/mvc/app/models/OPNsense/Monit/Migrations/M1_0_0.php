<?php

/*
 * Copyright (C) 2017-2019 EURO-LOG AG
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Monit\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Core\Shell;

class M1_0_0 extends BaseModelMigration
{
    public function post($model)
    {
        $cfg = Config::getInstance();
        $cfgObj = $cfg->object();
        $shellObj = new Shell();

        /* get number of cpus and calculate load average limits */
        $nCPU = [];
        $shellObj->exec('/sbin/sysctl -n kern.smp.cpus', false, $nCPU);
        $LoadAvg1 = $nCPU[0] * 2;
        $LoadAvg5 = $nCPU[0] + ($nCPU[0] / 2);
        $LoadAvg15 = $nCPU[0];

        /* inherit SMTP settings from System->Settings->Notifications */
        if (!empty($cfgObj->notifications->smtp->ipaddress)) {
            $model->general->mailserver = $cfgObj->notifications->smtp->ipaddress;
        }
        if (!empty($cfgObj->notifications->smtp->port)) {
            $model->general->port = $cfgObj->notifications->smtp->port;
        }
        if (!empty($cfgObj->notifications->smtp->username)) {
            $model->general->username = $cfgObj->notifications->smtp->username;
        }
        if (!empty($cfgObj->notifications->smtp->password)) {
            $model->general->password = $cfgObj->notifications->smtp->password;
        }
        if (
            (!empty($cfgObj->notifications->smtp->tls) && $cfgObj->notifications->smtp->tls == 1)  ||
            (!empty($cfgObj->notifications->smtp->ssl) && $cfgObj->notifications->smtp->ssl == 1)
        ) {
            $model->general->ssl = 1;
        }

        $alertSettings = [];
        if (!empty($cfgObj->notifications->smtp->notifyemailaddress)) {
            $alertSettings['recipient'] = $cfgObj->notifications->smtp->notifyemailaddress;
        }
        if (!empty($cfgObj->notifications->smtp->fromaddress)) {
            $alertSettings['format'] = 'from: ' . $cfgObj->notifications->smtp->fromaddress;
        }
        $alertNode = $model->alert->Add();
        $alertNode->setNodes($alertSettings);

        /* define some tests */
        $defaultTests = [
            ["name" => "Ping", "condition" => "failed ping", "action" => "alert", "type" => "NetworkPing"],
            ["name" => "NetworkLink", "condition" => "failed link", "action" => "alert", "type" => "NetworkInterface"],
            ["name" => "NetworkSaturation", "condition" => "saturation is greater than 75%", "action" => "alert", "type" => "NetworkInterface"],
            ["name" => "MemoryUsage", "condition" => "memory usage is greater than 75%", "action" => "alert", "type" => "SystemResource"],
            ["name" => "CPUUsage", "condition" => "cpu usage is greater than 75%", "action" => "alert", "type" => "SystemResource"],
            ["name" => "LoadAvg1", "condition" => "loadavg (1min) is greater than $LoadAvg1", "action" => "alert", "type" => "SystemResource"],
            ["name" => "LoadAvg5", "condition" => "loadavg (5min) is greater than $LoadAvg5", "action" => "alert", "type" => "SystemResource"],
            ["name" => "LoadAvg15", "condition" => "loadavg (15min) is greater than $LoadAvg15", "action" => "alert", "type" => "SystemResource"],
            ["name" => "SpaceUsage", "condition" => "space usage is greater than 75%", "action" => "alert", "type" => "SpaceUsage"],
        ];

        /* define system service */
        $systemService = [
            'enabled' => 1,
            'name' => '$HOST',
            'type' => 'system',
            'tests' => '',
        ];

        /* define root filesystem service */
        $rootFsService = [
            'enabled' => 1,
            'name' => 'RootFs',
            'type' => 'filesystem',
            'path' => '/',
            'tests' => '',
        ];

        foreach ($defaultTests as $defaultTest) {
            $testNode = $model->test->add();
            $testNode->setNodes($defaultTest);
            if (
                $defaultTest['name'] == 'MemoryUsage' ||
                $defaultTest['name'] == 'CPUUsage' ||
                $defaultTest['name'] == 'LoadAvg1' ||
                $defaultTest['name'] == 'LoadAvg5'
            ) {
                $systemService['tests'] .= $testNode->getAttributes()['uuid'] . ',';
            }
            if ($defaultTest['name'] == 'SpaceUsage') {
                $rootFsService['tests'] .= $testNode->getAttributes()['uuid'] . ',';
            }
        }

        /* remove last comma from tests csv */
        $systemService['tests'] = substr($systemService['tests'], 0, -1);
        $rootFsService['tests'] = substr($rootFsService['tests'], 0, -1);

        /* add system service */
        $serviceNode = $model->service->add();
        $serviceNode->setNodes($systemService);

        /* add root filesystem service */
        $rootFsNode = $model->service->add();
        $rootFsNode->setNodes($rootFsService);
    }
}
