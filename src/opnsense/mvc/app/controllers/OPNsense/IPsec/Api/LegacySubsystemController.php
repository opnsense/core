<?php

/*
 * Copyright (C) 2021 Deciso B.V.
 * Copyright (C) 2019 Pascal Mathis <mail@pascalmathis.com>
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

namespace OPNsense\IPsec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Class LegacySubsystemController
 * @package OPNsense\IPsec\Api
 */
class LegacySubsystemController extends ApiControllerBase
{
    /**
     * Returns the status of the legacy subsystem, which currently only includes a boolean specifying if the subsystem
     * is marked as dirty, which means that there are pending changes.
     * @return array
     */
    public function statusAction()
    {
        return [
            'enabled' => isset(Config::getInstance()->object()->ipsec->enable),
            'isDirty' => file_exists('/tmp/ipsec.dirty') // is_subsystem_dirty('ipsec')
        ];
    }

    /**
     * Apply the IPsec configuration using the legacy subsystem and return a message describing the result
     * @return array
     * @throws \Exception
     */
    public function applyConfigAction()
    {
        $result = ["status" => "failed"];
        if ($this->request->isPost()) {
            $bckresult = trim((new Backend())->configdRun('ipsec reconfigure'));
            if ($bckresult === 'OK') {
                $result['message'] = gettext('The changes have been applied successfully.');
                $result['status'] = "ok";
                @unlink('/tmp/ipsec.dirty');
            }
        }
    }
}
