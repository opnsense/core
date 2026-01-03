<?php

/**
 *    Copyright (C) 2015-2025 Deciso B.V.
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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Auth\AuthenticationFactory;

/**
 * Class VoucherController
 * @package OPNsense\CaptivePortal
 */
class VoucherController extends ApiControllerBase
{
    /**
     * list voucher providers (authenticators of type "voucher")
     * @return array list of auth providers
     */
    public function listProvidersAction()
    {
        $result = [];
        foreach ((new AuthenticationFactory())->listServers() as $authName => $authProps) {
            if ($authProps['type'] == 'voucher') {
                $result[] = $authName;
            }
        }
        return $result;
    }

    /**
     * list voucher groups
     * @param string $provider name of authentication provider
     * @return array list of registered vouchers
     */
    public function listVoucherGroupsAction($provider)
    {
        $auth = (new AuthenticationFactory())->get(urldecode($provider));
        if ($auth != null && method_exists($auth, 'listVoucherGroups')) {
            return $auth->listVoucherGroups();
        } else {
            return [];
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
        $auth = (new AuthenticationFactory())->get(urldecode($provider));
        if ($auth != null && method_exists($auth, 'listVouchers')) {
            return $auth->listVouchers(urldecode($group));
        } else {
            return [];
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
            $auth = (new AuthenticationFactory())->get(urldecode($provider));
            if ($auth != null && method_exists($auth, 'dropVoucherGroup')) {
                $auth->dropVoucherGroup(urldecode($group));
                return array("status" => "drop");
            }
        }
        return ["status" => "error"];
    }

    /**
     * drop expired vouchers from group
     * @param string $provider auth provider
     * @param string $group group name
     * @return array status
     */
    public function dropExpiredVouchersAction($provider, $group)
    {
        if ($this->request->isPost()) {
            $auth = (new AuthenticationFactory())->get(urldecode($provider));
            if ($auth != null && method_exists($auth, 'dropExpired')) {
                return array("status" => "drop", "count" => $auth->dropExpired(urldecode($group)));
            }
        }
        return ["status" => "error"];
    }


    /**
     * generate new vouchers
     * @param string $provider auth provider
     * @return array generated vouchers
     */
    public function generateVouchersAction($provider)
    {
        $response = ["status" => "error"];
        if ($this->request->isPost()) {
            $auth = (new AuthenticationFactory())->get(urldecode($provider));
            if ($auth != null && method_exists($auth, 'generateVouchers')) {
                $count = $this->request->getPost('count', 'int', 0);
                $validity = $this->request->getPost('validity', 'int', 0);
                $expirytime = $this->request->getPost('expirytime', 'int', 0);
                $vouchergroup = $this->request->getPost('vouchergroup', 'striptags', '---');
                // remove characters which are known to provide issues when using in the url
                foreach (array("&", "#") as $skip_chars) {
                    $vouchergroup = str_replace($skip_chars, "", $vouchergroup);
                }
                if ($count > 0 && $count <= 10000 && $validity > 0) {
                    return $auth->generateVouchers($vouchergroup, $count, $validity, $expirytime);
                }
            }
        }
        return $response;
    }


    /**
     * expire a voucher
     * @param string $provider auth provider
     * @return array status
     */
    public function expireVoucherAction($provider)
    {
        $response = ["status" => "error"];
        $username = $this->request->getPost('username', 'string', null);
        if ($this->request->isPost() && $username != null) {
            $auth = (new AuthenticationFactory())->get(urldecode($provider));
            if ($auth != null && method_exists($auth, 'expireVoucher')) {
                $auth->expireVoucher($username);
                $response['status'] = 'ok';
            }
        }
        return $response;
    }
}
