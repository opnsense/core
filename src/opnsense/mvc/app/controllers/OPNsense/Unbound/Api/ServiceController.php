<?php

/*
 * Copyright (C) 2019 Michael Muenz <m.muenz@gmail.com>
 * Copyright (C) 2020 Deciso B.V.
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

namespace OPNsense\Unbound\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Unbound\Unbound';
    /* XXX for legacy template refresh only */
    //protected static $internalServiceTemplate = 'OPNsense/Unbound/*';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'unbound';

    public function dnsblAction()
    {
        $backend = new Backend();
        /* XXX currently hardcoded to not cause side effect of $internalServiceTemplate use */
        $backend->configdRun('template reload OPNsense/Unbound/*');
        $response = $backend->configdRun(static::$internalServiceName . ' dnsbl');
        return array('status' => $response);
    }

    /**
     * Only used on the general page to account for resolver_configure and dhcp hooks
     * since these check if unbound is enabled.
     */
    public function reconfigureGeneralAction()
    {
        $backend = new Backend();
        $backend->configdRun('dns reload');
        $result = $this->reconfigureAction();
        $backend->configdRun('dhcpd restart');
        return $result;
    }
}
