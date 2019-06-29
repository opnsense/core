<?php
/*
 * Copyright (C) 2019 Fabian Franz
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

namespace OPNsense\Auth\JWT;


use OPNsense\Core\Config;

class UserIsInLocalDatabaseClaimVerifier implements ClaimVerifier
{
    private $known_users;
    private $additional_checks;

    /**
     * UserIsInLocalDatabaseClaimVerifier constructor.
     */
    public function __construct()
    {
        $config = Config::getInstance()->toArray();

        $system = $config['system'];
        $users = $system['user'];
        // fix single entry
        if (array_key_exists('name', $users)) {
            $users = array($users);
        }
        $this->known_users = $users;
        $this->additional_checks = array();
    }

    /**
     * check if the user can pass without a subject
     * can be overridden in a subclass
     *
     * @return bool true, if the user can pass
     */
    public function is_mandatory() {
        return true;
    }

    /**
     * adds an additional verifier - for example you can take the user to check it against as specific group
     * @param UserAdditionalCheck $additionalCheck instance of an additional group
     */
    public function add_additional_check(UserAdditionalCheck $additionalCheck) : void {
        if ($additionalCheck != null) {
            $this->additional_checks[] = $additionalCheck;
        }
    }

    public function verify($jwt): bool
    {
        if (!array_key_exists('sub', $jwt) || empty($jwt['sub'])) {
            return !$this->is_mandatory(); // in this case, this is mandatory
        }
        $subject = $jwt['sub'];
        foreach ($this->known_users as $user) {
            if ($subject == $user['name']) {
                return $this->performAdditionalChecksOrReturn($user, $jwt);
            }
        }
    }

    private function performAdditionalChecksOrReturn(array $user, array $claims) : bool {
        if (empty($this->additional_checks)) {
            return true;
        }

        foreach ($this->additional_checks as $additional_check) {
            if (!$additional_check->check_permission($user, $claims)) {
                return false;
            }
        }
        return true;
    }
}