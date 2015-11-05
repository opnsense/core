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

namespace OPNsense\Auth;

use OPNsense\Core\Config;

/**
 * Class Voucher user database connector
 * @package OPNsense\Auth
 */
class Voucher implements IAuthConnector
{
    /**
     * @var null reference id
     */
    private $refid = null;

    /**
     * @var null database handle
     */
    private $dbHandle = null;

    /**
     * @var int password length to use
     */
    private $passwordLength = 10;

    /**
     * @var int username length
     */
    private $usernameLength = 8;

    /**
     * @var array internal list of authentication properties (returned by radius auth)
     */
    private $lastAuthProperties = array();

    /**
     * open database
     */
    private function openDatabase()
    {
        $db_path = '/conf/vouchers_' . $this->refid . '.db';
        $this->dbHandle = new \SQLite3($db_path);
        $results = $this->dbHandle->query('select count(*) cnt from sqlite_master');
        $row = $results->fetchArray();
        if ($row['cnt'] == 0) {
            // new database, setup
            $sql_create = "
                create table vouchers (
                      username      varchar2  -- username
                    , password      varchar2  -- user password (crypted)
                    , vouchergroup  varchar2  -- group of vouchers
                    , validity      integer   -- voucher credits
                    , starttime     integer   -- voucher start at
                    , vouchertype   varchar2  -- (not implemented) voucher type
                    , primary key (username)
                );
                create index idx_voucher_group on vouchers(vouchergroup);
            ";
            $this->dbHandle->exec($sql_create);
        }
    }

    /**
     * check if username does already exist
     * @param string $username username
     * @return bool
     */
    private function userNameExists($username)
    {
        $stmt = $this->dbHandle->prepare('select count(*) cnt from vouchers where username = :username');
        $stmt->bindParam(':username', $username);
        $result = $stmt->execute();
        $row = $result->fetchArray();
        if ($row['cnt'] == 0) {
            return false;
        } else {
            return true;
        }
    }

    private function setStartTime($username, $starttime)
    {
        $stmt = $this->dbHandle->prepare('
                                update vouchers
                                set    starttime = :starttime
                                where username = :username
                                ');
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':starttime', $starttime);
        $stmt->execute();
    }

    /**
     * set connector properties
     * @param array $config connection properties
     */
    public function setProperties($config)
    {
        // fetch unique id for this authenticator
        if (array_key_exists('refid', $config)) {
            $this->refid = $config['refid'];
        } else {
            $this->refid = 'default';
        }
        $this->openDatabase();
    }

    /**
     * generate new vouchers and store in voucher database
     * @param string $vouchergroup voucher groupname
     * @param int $count number of vouchers to generate
     * @param int $validity time (in seconds)
     * @param int $starttime valid from
     * @return array list of generated vouchers
     */
    public function generateVouchers($vouchergroup, $count, $validity, $starttime = null)
    {
        $response = array();
        if ($this->dbHandle != null) {
            // list of characters to skip for random generator
            $doNotUseChr = array('<', '>', '&');

            // create map of random readable characters
            $characterMap = '';
            while (strlen($characterMap) < 256) {
                $random_bytes = openssl_random_pseudo_bytes(10000);
                for ($i = 0; $i < strlen($random_bytes); $i++) {
                    $chr_ord = ord($random_bytes[$i]);
                    if ($chr_ord >= 33 and $chr_ord <= 125 and !in_array($random_bytes[$i], $doNotUseChr)) {
                        $characterMap .= $random_bytes[$i] ;
                    }
                }
            }

            // generate new vouchers
            $vouchersGenerated = 0;
            while ($vouchersGenerated < $count) {
                $generatedUsername = '';
                $random_bytes = openssl_random_pseudo_bytes($this->usernameLength);
                for ($j=0; $j < strlen($random_bytes); $j++) {
                    $generatedUsername .= $characterMap[ord($random_bytes[$j])];
                }
                $generatedPassword = '';
                $random_bytes = openssl_random_pseudo_bytes($this->passwordLength);
                for ($j=0; $j < strlen($random_bytes); $j++) {
                    $generatedPassword .= $characterMap[ord($random_bytes[$j])];
                }

                if (!$this->userNameExists($generatedUsername)) {
                    $vouchersGenerated++;
                    // save user, hash password first
                    $generatedPasswordHash = crypt($generatedPassword, '$6$');
                    $stmt = $this->dbHandle->prepare('
                                insert into vouchers(username, password, vouchergroup, validity, starttime)
                                values (:username, :password, :vouchergroup, :validity, :starttime)
                                ');
                    $stmt->bindParam(':username', $generatedUsername);
                    $stmt->bindParam(':password', $generatedPasswordHash);
                    $stmt->bindParam(':vouchergroup', $vouchergroup);
                    $stmt->bindParam(':validity', $validity);
                    $stmt->bindParam(':starttime', $starttime);
                    $stmt->execute();

                    $row = array('username' => $generatedUsername,
                        'password' => $generatedPassword,
                        'vouchergroup' => $vouchergroup,
                        'validity' => $validity,
                        'starttime' => $starttime
                    );
                    $response[] = $row;
                }
            }
        }
        return $response;
    }

    /**
     * drop all vouchers from voucher a voucher group
     * @param string $vouchergroup group name
     */
    public function dropVoucherGroup($vouchergroup)
    {
        $stmt = $this->dbHandle->prepare('
                                delete
                                from vouchers
                                where vouchergroup = :vouchergroup
                                ');
        $stmt->bindParam(':vouchergroup', $vouchergroup);
        $stmt->execute();
    }

    /**
     * list all voucher groups
     * @return array
     */
    public function listVoucherGroups()
    {
        $response = array();
        $stmt = $this->dbHandle->prepare('select distinct vouchergroup from vouchers');
        $result = $stmt->execute();
        while ($row = $result->fetchArray()) {
            $response[] = $row['vouchergroup'];
        }
        return $response;
    }

    /**
     * list vouchers in group
     * @param $vouchergroup voucher group name
     * @return array
     */
    public function listVouchers($vouchergroup)
    {
        $response = array();
        $stmt = $this->dbHandle->prepare('
                  select username, validity, starttime, vouchergroup
                  from vouchers
                  where vouchergroup = :vouchergroup');
        $stmt->bindParam(':vouchergroup', $vouchergroup);
        $result = $stmt->execute();
        while ($row = $result->fetchArray()) {
            $record = array();
            $record['username'] = $row['username'];
            $record['validity'] = $row['validity'];
            # always calculate a starttime, if not registered yet, use now.
            $record['starttime'] = empty($row['starttime']) ? time() : $row['starttime'] ;
            $record['endtime'] = $record['starttime'] + $row['validity'];

            if (empty($row['starttime'])) {
                $record['state'] = 'unused';
            } elseif (time() < $record['endtime']) {
                $record['state'] = 'valid';
            } else {
                $record['state'] = 'expired';
            }

            $response[] = $record;
        }
        return $response;
    }

    /**
     * return session info
     * @return array mixed named list of authentication properties
     */
    public function getLastAuthProperties()
    {
        return $this->lastAuthProperties;
    }

    /**
     * authenticate user against voucher database
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool authentication status
     */
    public function authenticate($username, $password)
    {
        $stmt = $this->dbHandle->prepare('
            select username, password,validity, starttime
            from vouchers
            where username = :username
         ');
        $stmt->bindParam(':username', $username);
        $result = $stmt->execute();
        $row = $result->fetchArray();
        if ($row != null) {
            $passwd = crypt($password, (string)$row['password']);
            if ($passwd == (string)$row['password']) {
                // correct password, check validity
                if ($row['starttime'] == null) {
                    // initial login, set starttime for counter
                    $row['starttime'] = time();
                    $this->setStartTime($username, $row['starttime']);
                }
                if (time() - $row['starttime'] < $row['validity']) {
                    $this->lastAuthProperties['session_timeout'] = $row['validity'] - (time() - $row['starttime']) ;
                    return true;
                }
            }
        }
        return false;
    }
}
