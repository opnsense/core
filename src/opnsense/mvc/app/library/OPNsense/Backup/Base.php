<?php

/*
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2018-2021 Franco Fichtner <franco@opnsense.org>
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

namespace OPNsense\Backup;

/**
 * Backup stub file, contains shared logic for all backup strategies
 * @package OPNsense\Backup
 */
abstract class Base
{
    /**
     * encrypt+encode base64
     * @param string $data to encrypt
     * @param string $pass passphrase to use
     * @param string $tag
     * @return string base64 encoded crypted data
     */
    public function encrypt($data, $pass, $tag = 'config.xml')
    {
        /* current encryption defaults, change as needed */
        $cipher = 'aes-256-gcm';
        $hash = 'sha512';
        $pbkdf2 = '100000';

        $output = $this->opensslEncrypt($data, $pass, $cipher, $hash, $pbkdf2);
        if (!is_null($output)) {
            $version = trim(shell_exec('opnsense-version -Nv'));
            $result = "---- BEGIN {$tag} ----\n";
            $result .= "Version: {$version}\n";
            $result .= "Cipher: " . strtoupper($cipher) . "\n";
            $result .= "PBKDF2: " . $pbkdf2 . "\n";
            $result .= "Hash: " . strtoupper($hash) . "\n\n";
            $result .= chunk_split($output, 76, "\n");
            $result .= "---- END {$tag} ----\n";
            return $result;
        }
        syslog(LOG_ERR, 'Failed to encrypt data!');
        return null;
    }

    /**
     * decrypt base64 encoded data
     * @param string $data to decrypt
     * @param string $pass passphrase to use
     * @param string $tag
     * @return string data
     */
    public function decrypt($data, $pass, $tag = 'config.xml')
    {
        $data = explode("\n", $data);

        /* pre-21.7 compat defaults, do not change */
        $cipher = 'aes-256-cbc';
        $hash = 'md5';
        $pbkdf2 = null;

        foreach ($data as $key => $val) {
            if (strpos($val, ':') !== false) {
                list($header, $value) = explode(':', $val);
                $value = trim($value);
                switch (strtolower(trim($header))) {
                    case 'cipher':
                        $cipher = strtolower($value);
                        break;
                    case 'hash':
                        $hash = strtolower($value);
                        break;
                    case 'pbkdf2':
                        $pbkdf2 = $value;
                        break;
                    default:
                        /* skip unknown */
                        break;
                }
                unset($data[$key]);
            } elseif (strpos($val, "---- BEGIN {$tag} ----") !== false) {
                unset($data[$key]);
            } elseif (strpos($val, "---- END {$tag} ----") !== false) {
                unset($data[$key]);
            }
        }

        $data = implode('', $data);
        $output = $this->opensslDecrypt($data, $pass, $cipher, $hash, $pbkdf2);
        if (!is_null($output)) {
            return $output;
        }
        syslog(LOG_ERR, 'Failed to decrypt data!');
        return null;
    }

    /**
     * set model properties
     * @param OPNsense\Base\BaseModel $mdl model to set properties to
     * @param array $properties named
     */
    protected function setModelProperties($mdl, $properties)
    {
        foreach ($properties as $key => $value) {
            $node = $mdl->getNodeByReference($key);
            $node_class = get_class($node);
            if ($node_class == "OPNsense\Base\FieldTypes\BooleanField") {
                $node->setValue(empty($value) ? "0" : "1");
            } else {
                $node->setValue($value);
            }
        }
    }

    /**
     * validate model and return simple array with validation messages
     * @param OPNsense\Base\BaseModel $mdl model to set properties to
     * @return array
     */
    protected function validateModel($mdl)
    {
        $result = array();
        foreach ($mdl->performValidation() as $validation_message) {
            $result[] = (string)$validation_message;
        }
        return $result;
    }

    private function keyAndIV(string $hashAlgo, string $password, string $salt, ?int $iterations, int $ivLength): array
    {
        // AES-256 keys are always 32 bytes
        $keyLength = 32;
        if (!is_null($iterations)) {
            $key = hash_pbkdf2($hashAlgo, $password, $salt, $iterations, $keyLength + $ivLength, true);
        } else {
            // Prior to version XXX ?
            $key = $temp = '';
            while (strlen($key) < $keyLength + $ivLength) {
                $temp = md5($temp . $password . $salt, true);
                $key .= $temp;
            }

        }
        $iv = substr($key, $keyLength, $ivLength);
        $key = substr($key, 0, $keyLength);
        return [$key, $iv];
    }

    private function opensslDecrypt(string $data, string $password, string $cipher, string $hashAlgo, ?int $iterations): ?string
    {
        if ($cipher === 'aes-256-gcm') {
            $saltOffset = 0;
            $saltLength = 16;
            $tagLength = 16;
        } elseif ($cipher === 'aes-256-cbc') {
            // Prior to version XXX ?
            $saltOffset = 8; // skip b'Salted__'
            $saltLength = 8;
            $tagLength = 0;
        }
        $data = base64_decode($data);
        if (!isset($saltOffset) || strlen($data) < $saltOffset + $saltLength + $tagLength) {
            // unknown cipher or not enough data
            return null;
        }
        $salt = substr($data, $saltOffset, $saltLength);
        $tag = substr($data, $saltOffset + $saltLength, $tagLength);
        $data = substr($data, $saltOffset + $saltLength + $tagLength);
        [$key, $iv] = $this->keyAndIV($hashAlgo, $password, $salt, $iterations, openssl_cipher_iv_length($cipher));
        $result = openssl_decrypt(
            $data,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        return $result === false ? null : $result;
    }

    private function opensslEncrypt(string $data, string $password, string $cipher, string $hashAlgo, int $iterations): ?string
    {
        $salt = random_bytes(16);
        [$key, $iv] = $this->keyAndIV($hashAlgo, $password, $salt, $iterations, openssl_cipher_iv_length($cipher));
        $result = openssl_encrypt(
            $data,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        return $result === false ? null : base64_encode($salt . $tag . $result);
    }
}
