<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
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
namespace OPNsense\CaptivePortal\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Auth\AuthenticationFactory;

/**
 * Class VoucherController
 * @package OPNsense\CaptivePortal
 */
class VoucherController extends ApiControllerBase
{
    /**
     * list voucher providers (authenticators of type "voucher")
     * @return list of auth providers
     */
    public function listProvidersAction()
    {
        $result = array();
        $authFactory = new AuthenticationFactory();
        foreach ($authFactory->listServers() as $authName => $authProps) {
            if ($authProps['type'] == 'voucher') {
                $result[] = $authName;
            }
        }
        return $result;
    }

    /**
     * list voucher groups
     * @param string $provider name of authentication provider
     * @return list of registered vouchers
     */
    public function listVoucherGroupsAction($provider)
    {
        $authFactory = new AuthenticationFactory();
        $auth = $authFactory->get($provider);
        if ($auth != null && method_exists($auth, 'listVoucherGroups')) {
            return $auth->listVoucherGroups();
        } else {
            return array();
        }
    }

    /**
     * list vouchers
     * @param string $provider auth provider
     * @param string $group group name
     * @return array vouchers within this group
     */
    public function listVouchersAction($provider, $group)
    {
        $authFactory = new AuthenticationFactory();
        $auth = $authFactory->get($provider);
        if ($auth != null && method_exists($auth, 'listVouchers')) {
            return $auth->listVouchers($group);
        } else {
            return array();
        }
    }

    /**
     * drop a voucher group
     * @param string $provider auth provider
     * @param string $group group name
     * @return array status
     */
    public function dropVoucherGroupAction($provider, $group)
    {
        if ($this->request->isPost()) {
            $authFactory = new AuthenticationFactory();
            $auth = $authFactory->get($provider);
            if ($auth != null && method_exists($auth, 'dropVoucherGroup')) {
                $auth->dropVoucherGroup($group);
                return array("status" => "drop");
            }
        }
        return array("status" => "error");
    }


    /**
     * generate new vouchers
     * @param string $provider auth provider
     * @return array generated vouchers
     */
    public function generateVouchersAction($provider)
    {
        if ($this->request->isPost()) {
            $authFactory = new AuthenticationFactory();
            $auth = $authFactory->get($provider);
            if ($auth != null && method_exists($auth, 'generateVouchers')) {
                $count = $this->request->getPost('count', 'int', 0);
                $validity = $this->request->getPost('validity', 'int', 0);
                $vouchergroup = $this->request->getPost('vouchergroup', 'striptags', '---');
                if ($count > 0 && $validity > 0) {
                    return $auth->generateVouchers($vouchergroup, $count, $validity);
                }
            }
        }
        return array();
    }

}
