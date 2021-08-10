<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
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
 */

namespace OPNsense\Diagnostics\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;

/**
 * Class DnsController
 * @package OPNsense\Diagnostics
 */
class DnsController extends ApiControllerBase
{

    /**
     * perform a reverse dns lookup
     * @return array
     */
    public function reverseLookupAction()
    {
        if ($this->request->isGet() && $this->request->has('address')) {
            $this->sessionClose(); // long running action, close session
            if (is_array($this->request->get('address'))) {
                $address = $this->request->get('address');
            } else {
                $address = array($this->request->get('address'));
            }
            $result = array();
            foreach ($address as $addr) {
                if (!empty(filter_var($addr, FILTER_VALIDATE_IP))) {
                    $result[$addr] = gethostbyaddr($addr);
                } else {
                    $result[$addr] = $addr;
                }
            }
            return $result;
        } else {
            return null;
        }
    }
}
