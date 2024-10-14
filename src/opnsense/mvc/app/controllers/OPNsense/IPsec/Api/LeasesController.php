<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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
 * Class LeasesController
 * @package OPNsense\IPsec\Api
 */
class LeasesController extends ApiControllerBase
{
    /**
     * Search leases
     * @return array
     */
    public function searchAction()
    {
        $pools = $this->request->get('pool');
        $filter_funct = null;
        if (!empty($pools)) {
            $filter_funct = function ($record) use ($pools) {
                return in_array($record['pool'], $pools);
            };
        }
        $data = json_decode((new Backend())->configdRun('ipsec list leases'), true);
        $records = (!empty($data) && !empty($data['leases'])) ? $data['leases'] : [];

        return $this->searchRecordsetBase($records, null, null, $filter_funct);
    }

    /**
     * list pools
     * @return array
     */
    public function poolsAction()
    {
        $data = json_decode((new Backend())->configdRun('ipsec list leases'), true);
        return (!empty($data) && !empty($data['pools'])) ? $data : ['pools' => []];
    }
}
