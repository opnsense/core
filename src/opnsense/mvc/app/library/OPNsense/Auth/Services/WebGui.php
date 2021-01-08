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

namespace OPNsense\Auth\Services;

use OPNsense\Core\ACL;
use OPNsense\Core\Config;
use OPNsense\Auth\IService;

/**
 * WebGui service
 * @package OPNsense\Auth
 */
class WebGui implements IService
{
    /**
     * @var string username for the current request
     */
    private $username;

    /**
     * {@inheritdoc}
     */
    public static function aliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function supportedAuthenticators()
    {
        $result = array();
        $configObj = Config::getInstance()->object();
        if (!empty((string)$configObj->system->webgui->authmode)) {
            $result = explode(',', (string)$configObj->system->webgui->authmode);
        } else {
            $result[] = 'Local Database';
        }
        return $result;
    }

     /**
      * {@inheritdoc}
      */
    public function setUserName($username)
    {
        $this->username = $username;
    }

     /**
      * {@inheritdoc}
      */
    public function getUserName()
    {
        return $this->username;
    }

     /**
      * {@inheritdoc}
      */
    public function checkConstraints()
    {
        // no constraints
        return true;
    }
}
