<?php

/*
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2018 Franco Fichtner <franco@opnsense.org>
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
     * @var string[] openssl arguments by encryption method
     */
    private $openssl_arguments = [
        "M0" => "-aes-256-cbc -md md5", /* backwards-compatible default */
        "M1" => "-aes-256-cbc -pbkdf2 -iter 100000 -md SHA512",
    ];

    /**
     * encrypt+encode base64
     * @param string $data to encrypt
     * @param string $pass passphrase to use
     * @param string $tag
     * @param int $encryption_method
     * @return string base64 encoded crypted data
     */
    public function encrypt($data, $pass, $tag = 'config.xml', $encryption_method = "M1")
    {
        $file = tempnam(sys_get_temp_dir(), 'php-encrypt');
        @unlink("{$file}.enc");

        file_put_contents("{$file}.dec", $data);
        exec(sprintf(
            '/usr/local/bin/openssl enc -e ' . $this->openssl_arguments[$encryption_method] . ' -in %s -out %s -pass pass:%s',
            escapeshellarg("{$file}.dec"),
            escapeshellarg("{$file}.enc"),
            escapeshellarg($pass)
        ));
        @unlink("{$file}.dec");

        if (file_exists("{$file}.enc")) {
            $version = trim(shell_exec('opnsense-version -Nv'));
            $result = "---- BEGIN {$tag} ----\n";
            $result .= "Version: {$version}\n";
            if ($encryption_method === "M0") {
                $result .= "Cipher: AES-256-CBC\n";
                $result .= "Hash: MD5\n\n";
            } else {
                $result .= "Encryption: $encryption_method\n\n";
            }
            $result .= chunk_split(base64_encode(file_get_contents("{$file}.enc")), 76, "\n");
            $result .= "---- END {$tag} ----\n";
            @unlink("{$file}.enc");
            return $result;
        } else {
            syslog(LOG_ERR, 'Failed to encrypt data!');
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
        $encryption_method = "M0"; /* default for backward compatibility */

        foreach ($data as $key => $val) {
            if (strpos($val, "Encryption: ") === 0) {
                /* honor encryption preamble field */
                $encryption_method = explode(" ", $val, 2)[1];
                if (!array_key_exists($encryption_method, $this->openssl_arguments)) {
                    syslog(
                        LOG_ERR,
                        'Cannot decrypt with unknown encryption method "' . $encryption_method . '"');
                    return null;
                }
                unset($data[$key]);
            } elseif (strpos($val, ':') !== false) {
                /* ignore other preamble fields */
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
                '/usr/local/bin/openssl enc -d ' . $this->openssl_arguments[$encryption_method] . ' -in %s -out %s -pass pass:%s',
                escapeshellarg("{$file}.enc"),
                escapeshellarg("{$file}.dec"),
                escapeshellarg($pass)
            ),
            $output,
            $retval
        );
        @unlink("{$file}.enc");

        if (file_exists("{$file}.dec") && !$retval) {
            $result = file_get_contents("{$file}.dec");
            @unlink("{$file}.dec");
            return $result;
        } else {
            syslog(LOG_ERR, 'Failed to decrypt data!');
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
