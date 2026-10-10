<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis <cspartalis@potatonetworks.com>
 * Copyright (C) 2016-2025 Deciso B.V.
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

namespace OPNsense\Auth;

require_once 'base32/Base32.php';

/**
 * RFC 6238 TOTP: Time-Based One-Time Password Authenticator
 * @package OPNsense\Auth
 */
trait TOTP
{
    /**
     * @var int time window in seconds (software uses 30, some hardware tokens use 60)
     */
    private $timeWindow = 30;

    /**
     * @var int key length (6,8)
     */
    private $otpLength = 6;

    /**
     * @var int number of seconds the clocks (local, remote) may differ
     */
    private $graceperiod = 10;

    /**
     * @var bool token after password
     */
    private $passwordFirst = false;

    /**
     * @var bool last authenticateFirstFactor() call matched a token code joined to the password
     */
    private $firstFactorComposed = false;

    /**
     * use graceperiod and timeWindow to calculate which moments in time we should check
     * @return array timestamps
     */
    private function timesToCheck()
    {
        $result = [];
        if ($this->graceperiod > $this->timeWindow) {
            $step = $this->timeWindow;
            $start = -1 * floor($this->graceperiod  / $this->timeWindow) * $this->timeWindow;
        } else {
            $step = $this->graceperiod;
            $start = -1 * $this->graceperiod;
        }
        $now = time();
        for ($count = $start; $count <= $this->graceperiod; $count += $step) {
            $result[] = $now + $count;
            if ($this->graceperiod == 0) {
                // special case, we expect the clocks to match 100%, so step and target are both 0
                break;
            }
        }
        return $result;
    }

    /**
     * @param int $moment timestamp
     * @param string $secret secret to use
     * @return calculated token code
     */
    private function calculateToken($moment, $secret)
    {
        // calculate binary 8 character time for provided window
        $binary_time = pack("N", (int)($moment / $this->timeWindow));
        $binary_time = str_pad($binary_time, 8, chr(0), STR_PAD_LEFT);

        // Generate the hash using the SHA1 algorithm
        $hash = hash_hmac('sha1', $binary_time, $secret, true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
                ((ord($hash[$offset + 0]) & 0x7f) << 24 ) |
                ((ord($hash[$offset + 1]) & 0xff) << 16 ) |
                ((ord($hash[$offset + 2]) & 0xff) << 8 ) |
                (ord($hash[$offset + 3]) & 0xff)
            ) % pow(10, $this->otpLength);


        $otp = str_pad($otp, $this->otpLength, "0", STR_PAD_LEFT);
        return $otp;
    }

    /**
     * return current token code
     * @param $base32seed secret to use
     * @return string token code
     */
    public function testToken($base32seed)
    {
        $otp_seed = \Base32\Base32::decode($base32seed);
        return $this->calculateToken(time(), $otp_seed);
    }

    /**
     * authenticate TOTP RFC 6238
     * @param string $secret secret seed to use
     * @param string $code provided code
     * @return bool is valid
     */
    private function authTOTP($secret, $code)
    {
        foreach ($this->timesToCheck() as $moment) {
            if ($code === $this->calculateToken($moment, $secret)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool token after password
     */
    public function isPasswordFirst()
    {
        return $this->passwordFirst;
    }

    /**
     * check if the user has a one-time password seed configured
     * @param string $username username to check
     * @return bool
     */
    public function hasOTP($username)
    {
        $userObject = $this->getUser($username);
        return $userObject != null && !empty($userObject->otp_seed);
    }

    /**
     * split a composed secret into its password and token code parts according to the
     * configured token order, inverse of composeLoginSecret()
     * @param string $secret composed secret, its length must exceed the token length
     * @return array password and token code
     */
    private function splitLoginSecret($secret)
    {
        $pwLength = strlen($secret) - $this->otpLength;
        $pwStart = $this->passwordFirst ? 0 : $this->otpLength;
        $otpStart = $this->passwordFirst ? $pwLength : 0;
        return [substr($secret, $pwStart, $pwLength), substr($secret, $otpStart, $this->otpLength)];
    }

    /**
     * combine password and token code into the composed secret _authenticate() expects,
     * inverse of splitLoginSecret()
     * @param string $password user password
     * @param string $otp_code token code
     * @return string composed secret
     */
    public function composeLoginSecret($password, $otp_code)
    {
        return $this->passwordFirst ? $password . $otp_code : $otp_code . $password;
    }

    /**
     * authenticate the first factor (password) of a token authentication sequence, refusing
     * users without a token seed as in the single request flow (_authenticate). Accepted and
     * refused passwords spend the same sequence time, the caller only reveals the outcome
     * after the token step, so response time may not reveal it either.
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool
     */
    public function authenticateFirstFactor($username, $password)
    {
        $this->firstFactorComposed = false;
        return $this->timedAuthenticate(function () use ($username, $password) {
            if (!$this->hasOTP($username)) {
                return false;
            }
            if (parent::_authenticate($username, $password)) {
                return true;
            }
            // keep accepting the single request form, a token code joined to the password,
            // both checks share one timed sequence so response time does not tell them apart
            if ($this->_authenticate($username, $password)) {
                $this->firstFactorComposed = true;
                return true;
            }
            return false;
        }, true);
    }

    /**
     * @return bool true when the last authenticateFirstFactor() call matched a token code joined
     *              to the password, both factors are verified and no token step should follow
     */
    public function isFirstFactorComposed()
    {
        return $this->firstFactorComposed;
    }

    /**
     * authenticate user one-time password only (second factor verification), enforcing
     * the same failed attempt penalty as a full authenticate() sequence
     * @param string $username username to authenticate
     * @param string $otp_code one-time password code
     * @param bool $first_factor_passed outcome of the password step, when refused the token is
     *                                  still checked but the sequence fails in the same time
     * @return bool
     */
    public function authenticateOTP($username, $otp_code, $first_factor_passed = true)
    {
        return $this->timedAuthenticate(function () use ($username, $otp_code, $first_factor_passed) {
            $userObject = $this->getUser($username);
            if ($userObject != null && !empty($userObject->otp_seed)) {
                $otp_seed = \Base32\Base32::decode($userObject->otp_seed);
                if ($this->authTOTP($otp_seed, $otp_code)) {
                    return $first_factor_passed;
                }
            }
            return false;
        });
    }

    /**
     * run password policy checks on bare password (for Step 1 check)
     * @param string $username username to check
     * @param string $password bare password
     * @return bool
     */
    public function shouldChangePasswordStep1($username, $password = null)
    {
        return parent::shouldChangePassword($username, $password);
    }

    /**
     * authenticate user against otp key stored in local database
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool authentication status
     */
    protected function _authenticate($username, $password)
    {
        $userObject = $this->getUser($username);
        if ($userObject != null && !empty($userObject->otp_seed)) {
            if (strlen($password) > $this->otpLength) {
                // split otp token code and userpassword
                list($userPassword, $code) = $this->splitLoginSecret($password);
                $otp_seed = \Base32\Base32::decode($userObject->otp_seed);
                if ($this->authTOTP($otp_seed, $code)) {
                    // token valid, do parents auth
                    return parent::_authenticate($username, $userPassword);
                }
            }
        }
        return false;
    }

    /**
     * check if the user should change his or her password
     * @param string $username username to check
     * @param string $password password to check
     * @return boolean
     */
    public function shouldChangePassword($username, $password = null)
    {
        if ($password != null && strlen($password) > $this->otpLength) {
            /* deconstruct password according to settings */
            list($password) = $this->splitLoginSecret($password);
        }

        return parent::shouldChangePassword($username, $password);
    }

    /**
     * set TOTP specific connector properties
     * @param array $config connection properties
     */
    public function setTOTPProperties($config)
    {
        if (!empty($config['timeWindow'])) {
            $this->timeWindow = $config['timeWindow'];
        }
        if (!empty($config['otpLength'])) {
            $this->otpLength = $config['otpLength'];
        }
        if (!empty($config['graceperiod'])) {
            $this->graceperiod = $config['graceperiod'];
        }
        if (!empty($config['passwordFirst'])) {
            $this->passwordFirst = true;
        }
    }

    /**
     * retrieve TOTP specific configuration options
     * @return array
     */
    private function getTOTPConfigurationOptions()
    {
        $fields = [];
        $fields["otpLength"] = [];
        $fields["otpLength"]["name"] = gettext("Token length");
        $fields["otpLength"]["type"] = "dropdown";
        $fields["otpLength"]["default"] = 6;
        $fields["otpLength"]["options"] = [];
        $fields["otpLength"]["options"]["6"] = "6";
        $fields["otpLength"]["options"]["8"] = "8";
        $fields["otpLength"]["help"] = gettext("Token length to use");
        $fields["otpLength"]["validate"] = function ($value) {
            if (!in_array($value, array(6,8))) {
                return array(gettext("Only token lengths of 6 or 8 characters are supported"));
            } else {
                return [];
            }
        };
        $fields["timeWindow"] = [];
        $fields["timeWindow"]["name"] = gettext("Time window");
        $fields["timeWindow"]["type"] = "text";
        $fields["timeWindow"]["default"] = null;
        $fields["timeWindow"]["help"] = gettext("The time period in which the token will be valid," .
            " default is 30 seconds.");
        $fields["timeWindow"]["validate"] = function ($value) {
            if (!empty($value) && filter_var($value, FILTER_SANITIZE_NUMBER_INT) != $value) {
                return array(gettext("Please enter a valid time window in seconds"));
            } else {
                return [];
            }
        };
        $fields["graceperiod"] = [];
        $fields["graceperiod"]["name"] = gettext("Grace period");
        $fields["graceperiod"]["type"] = "text";
        $fields["graceperiod"]["default"] = null;
        $fields["graceperiod"]["help"] = gettext("Time in seconds in which this server and the token may differ," .
            " default is 10 seconds. Set higher for a less secure easier match.");
        $fields["graceperiod"]["validate"] = function ($value) {
            if (!empty($value) && filter_var($value, FILTER_SANITIZE_NUMBER_INT) != $value) {
                return array(gettext("Please enter a valid grace period in seconds"));
            } else {
                return [];
            }
        };
        $fields["passwordFirst"] = [];
        $fields["passwordFirst"]["name"] = gettext("Reverse token order");
        $fields["passwordFirst"]["help"] = gettext("Checking this box requires the token after the password. Default requires the token before the password.");
        $fields["passwordFirst"]["type"] = "checkbox";
        $fields["passwordFirst"]["validate"] = function ($value) {
            return [];
        };

        return $fields;
    }
}
