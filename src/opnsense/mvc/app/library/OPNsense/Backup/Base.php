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
        $file = tempnam(sys_get_temp_dir(), 'php-encrypt');
        @unlink("{$file}.enc");

        /* current encryption defaults, change as needed */
        $cipher = 'aes-256-cbc';
        $hash = 'sha512';
        $pbkdf2 = '100000';

        file_put_contents("{$file}.dec", $data);
        exec(
            sprintf(
                '/usr/local/bin/openssl enc -e -%s -md %s -pbkdf2 -iter %s -in %s -out %s -pass pass:%s',
                escapeshellarg($cipher),
                escapeshellarg($hash),
                escapeshellarg($pbkdf2),
                escapeshellarg("{$file}.dec"),
                escapeshellarg("{$file}.enc"),
                escapeshellarg($pass)
            ),
            $unused,
            $retval
        );
        @unlink("{$file}.dec");

        if (file_exists("{$file}.enc") && !$retval) {
            $version = trim(shell_exec('opnsense-version -Nv'));
            $result = "---- BEGIN {$tag} ----\n";
            $result .= "Version: {$version}\n";
            $result .= "Cipher: " . strtoupper($cipher) . "\n";
            $result .= "PBKDF2: " . $pbkdf2 . "\n";
            $result .= "Hash: " . strtoupper($hash) . "\n\n";
            $result .= chunk_split(base64_encode(file_get_contents("{$file}.enc")), 76, "\n");
            $result .= "---- END {$tag} ----\n";
            @unlink("{$file}.enc");
            return $result;
        } else {
            syslog(LOG_ERR, 'Failed to encrypt data!');
            @unlink("{$file}.enc");
            return null;
        }
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
        $file = tempnam(sys_get_temp_dir(), 'php-encrypt');
        @unlink("{$file}.dec");

        $data = explode("\n", $data);

        /* pre-21.7 compat defaults, do not change */
        $cipher = 'aes-256-cbc';
        $hash = 'md5';
        $pbkdf2 = null;

        foreach ($data as $key => $val) {
            if (strpos($val, ':') !== false) {
                list ($header, $value) = explode(':', $val);
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

        $data = implode("\n", $data);

        file_put_contents("{$file}.enc", base64_decode($data));
        exec(
            sprintf(
                '/usr/local/bin/openssl enc -d -%s -md %s %s -in %s -out %s -pass pass:%s',
                escapeshellarg($cipher),
                escapeshellarg($hash),
                $pbkdf2 === null ? '' : '-pbkdf2 -iter=' . escapeshellarg($pbkdf2),
                escapeshellarg("{$file}.enc"),
                escapeshellarg("{$file}.dec"),
                escapeshellarg($pass)
            ),
            $unused,
            $retval
        );
        @unlink("{$file}.enc");

        if (file_exists("{$file}.dec") && !$retval) {
            $result = file_get_contents("{$file}.dec");
            @unlink("{$file}.dec");
            return $result;
        } else {
            syslog(LOG_ERR, 'Failed to decrypt data!');
            @unlink("{$file}.dec");
            return null;
        }
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
}
