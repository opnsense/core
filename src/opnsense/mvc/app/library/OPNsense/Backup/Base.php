<?php
/**
 *    Copyright (C) 2018 Deciso B.V.
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
     * @return string base64 encoded crypted data
     */
    public function encrypt($data, $pass)
    {
        $file = tempnam(sys_get_temp_dir(), 'php-encrypt');
        @unlink($file);

        file_put_contents("{$file}.dec", $data);
        exec(sprintf(
            '/usr/local/bin/openssl enc -e -aes-256-cbc -md md5 -in %s -out %s -pass pass:%s',
            escapeshellarg("{$file}.dec"),
            escapeshellarg("{$file}.enc"),
            escapeshellarg($pass)
        ));
        @unlink("{$file}.dec");

        if (file_exists("{$file}.enc")) {
            $result = file_get_contents("{$file}.enc");
            @unlink("{$file}.enc");
            return base64_encode($result);
        } else {
            syslog(LOG_ERR, 'Failed to encrypt data!');
            return null;
        }
    }

    /**
     * decrypt base64 encoded data
     * @param string $data to decrypt
     * @param string $pass passphrase to use
     * @return string data
     */
    public function decrypt($data, $pass)
    {
        $file = tempnam(sys_get_temp_dir(), 'php-encrypt');
        @unlink($file);

        file_put_contents("{$file}.dec", base64_decode($data));
        exec(sprintf(
            '/usr/local/bin/openssl enc -d -aes-256-cbc -md md5 -in %s -out %s -pass pass:%s',
            escapeshellarg("{$file}.dec"),
            escapeshellarg("{$file}.enc"),
            escapeshellarg($pass)
        ));
        @unlink("{$file}.dec");

        if (file_exists("{$file}.enc")) {
            $result = file_get_contents("{$file}.enc");
            @unlink("{$file}.enc");
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
