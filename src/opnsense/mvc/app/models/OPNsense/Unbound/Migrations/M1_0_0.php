<?php

/*
 * Copyright (C) 2021 Deciso B.V.
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

namespace OPNsense\Unbound\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Base\FieldTypes\BooleanField;
use OPNsense\Base\FieldTypes\NetworkField;
use OPNsense\Base\FieldTypes\PortField;
use OPNsense\Core\Config;

class M1_0_0 extends BaseModelMigration
{
    /**
     * Migrate older models into shared model
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (empty($config->OPNsense->unboundplus)) {
            return;
        }

        $mdlNode = $config->OPNsense->unboundplus;

        foreach (['dnsbl', 'miscellaneous'] as $strip) {
            if (!empty($mdlNode->$strip)) {
                $model->$strip->setAttributeValue('version', null);
            }
        }

        if (!empty($mdlNode->miscellaneous->dotservers)) {
            foreach (explode(',', (string)$mdlNode->miscellaneous->dotservers) as $dot) {
                $dotNode = $model->dots->dot->add();
                $dotData = explode('@', $dot, 2);
                $dotContent = [ 'enabled' => 1, 'server' => $dotData[0] ];
                if (!empty($dotData[1])) {
                    $dotContent['port'] = $dotData[1];
                }
                $dotNode->setNodes($dotContent);
            }
        }
    }
}
