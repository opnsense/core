<?php

/*
 * Copyright (C) 2019 Pascal Mathis <mail@pascalmathis.com>
 * Copyright (C) 2022 Manuel Faux <mfaux@conf.at>
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

namespace OPNsense\IPsec;

use Phalcon\Messages\Message;
use OPNsense\Base\BaseModel;

/**
 * Class IPsec
 * @package OPNsense\IPsec
 */
class IPsec extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $keyPairs = [];

        foreach ($this->getFlatNodes() as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                $tagName = $node->getInternalXMLTagName();
                $parentNode = $node->getParentNode();
                $parentKey = $parentNode->__reference;
                $parentTagName = $parentNode->getInternalXMLTagName();

                if ($parentTagName === 'keyPair' && in_array($tagName, ['keyType', 'privateKey', 'publicKey'])) {
                    $keyPairs[$parentKey] = $parentNode;
                }
            }
        }

        foreach ($keyPairs as $key => $node) {
            $this->validateKeyPair($key, $node, $messages);
        }

        return $messages;
    }

    /**
     * Validates a keyPair instance within a model. This method does change the model contents by replacing the public
     * and private key contents with a sanitized representation as well as storing the key size and fingerprint.
     * @param $nodeKey string Fully-qualified key of the keyPair instance within a model
     * @param $keyPair \OPNsense\Base\FieldTypes\BaseField Field instance of a keyPair
     * @param $messages \Phalcon\Messages\Messages Validation message group
     */
    private function validateKeyPair($nodeKey, $keyPair, $messages)
    {
        $publicKey = $privateKey = null;
        if (empty((string)$keyPair->keyType)) {
            return;
        }

        // Validate public key
        if (!empty((string)$keyPair->publicKey)) {
            try {
                $publicKey = $this->parseCryptographicKey(
                    (string)$keyPair->publicKey,
                    (string)$keyPair->keyType,
                    'public'
                );
            } catch (\Exception $e) {
                $messages->appendMessage(new Message($e->getMessage(), $nodeKey . '.publicKey'));
            }
        }

        // Validate private key
        if (!empty((string)$keyPair->privateKey)) {
            try {
                $privateKey = $this->parseCryptographicKey(
                    (string)$keyPair->privateKey,
                    (string)$keyPair->keyType,
                    'private'
                );
            } catch (\Exception $e) {
                $messages->appendMessage(new Message($e->getMessage(), $nodeKey . '.privateKey'));
            }
        }

        // Compare SHA1 fingerprint of public and private keys to check if they belong to each other
        if ($publicKey && $privateKey) {
            if ($publicKey['fingerprint'] !== $privateKey['fingerprint']) {
                $messages->appendMessage(new Message(
                    gettext('This private key does not belong to the given public key.'),
                    $nodeKey . '.privateKey'
                ));
            }
        }

        // Store sanitized representation of keys and cache key statistics
        $keyPair->publicKey = $publicKey ? $publicKey['pem'] : (string)$keyPair->publicKey;
        $keyPair->privateKey = $privateKey ? $privateKey['pem'] : (string)$keyPair->privateKey;
        $keyPair->keySize = $publicKey ? $publicKey['size'] : 0;
        $keyPair->keyFingerprint = $publicKey ? $publicKey['fingerprint'] : '';
    }

    /**
     * Parse a cryptographic key of a given type using OpenSSL and return an array of informational data.
     * @param $keyString string
     * @param $keyType string
     * @param $keyPart string
     * @return array
     */
    private function parseCryptographicKey($keyString, $keyType, $keyPart)
    {
        if (in_array($keyType, ['rsa', 'ecdsa'])) {
            return $this->parseCryptographicKey_php($keyString, $keyType, $keyPart);
        }
        else if (in_array($keyType, ['ed25519', 'ed448'])) {
            return $this->parseCryptographicKey_openssl($keyString, $keyType, $keyPart);
        }
        else {
            throw new \InvalidArgumentException(sprintf(
                gettext('Unsupported key type: %s'),
                strtoupper($keyType)
            ));
        }
    }

    /**
     * Parse a cryptographic key of a given type using PHP's OpenSSL wrapper and return an array
     * of informational data.
     * @param $keyString string
     * @param $keyType string
     * @param $keyPart string
     * @return array
     */
    private function parseCryptographicKey_php($keyString, $keyType, $keyPart) {
        // Attempt to load key with correct type
        if ($keyPart == 'public') {
            $key = openssl_pkey_get_public($keyString);
        } elseif ($keyPart == 'private') {
            $key = openssl_pkey_get_private($keyString);
        } else {
            throw new \InvalidArgumentException(sprintf(
                gettext('Unsupported key type: %s-%s'),
                $keyType, $keyPart
            ));
        }

        // Ensure that key has been successfully loaded
        if ($key === false) {
            throw new \InvalidArgumentException(sprintf(
                gettext('Could not load potentially invalid %s-%s key: %s'),
                $keyType, $keyPart,
                openssl_error_string()
            ));
        }

        // Attempt to fetch key details
        $keyDetails = openssl_pkey_get_details($key);
        if ($keyDetails === false) {
            throw new \RuntimeException(sprintf(
                gettext('Could not fetch details for %s-%s key: %s'),
                $keyType, $keyPart,
                openssl_error_string()
            ));
        }

        // Verify given public key is valid for usage with Strongswan
        if ($keyDetails['type'] !== OPENSSL_KEYTYPE_RSA && $keyDetails['type'] !== OPENSSL_KEYTYPE_EC) {
            throw new \InvalidArgumentException(sprintf(
                gettext('Unsupported OpenSSL key type [%d] for %s-%s key, expected RSA or EC.'),
                $keyDetails['type'],
                $keyType, $keyPart
            ));
        }

        // Verify that key type matches with actual passed key
        if ($keyType == 'rsa' && $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new \RuntimeException(sprintf(
                gettext('Passed key is not a RSA key, but a %s key.'),
                $keyDetails['type'] === OPENSSL_KEYTYPE_EC ? 'ECDSA' : 'unknown'
            ));
        }
        else if ($keyType == 'ecdsa' && $keyDetails['type'] !== OPENSSL_KEYTYPE_EC) {
            throw new \RuntimeException(sprintf(
                gettext('Passed key is not a ECDSA key, but a %s key.'),
                $keyDetails['type'] === OPENSSL_KEYTYPE_RSA ? 'RSA' : 'unknown'
            ));
        }

        // Fetch sanitized PEM representation of key
        if ($keyPart == 'private') {
            if (!openssl_pkey_export($key, $keySanitized, null)) {
                throw new \RuntimeException(sprintf(
                    gettext('Could not generate sanitized %s-%s key in PEM format: %s'),
                    $keyType, $keyPart,
                    openssl_error_string()
                ));
            }
        } else {
            $keySanitized = $keyDetails['key'];
        }

        $keyFingerprint = $this->calculateFingerprint($keyDetails['key']);
        return [
            'resource' => $key,
            'size' => $keyDetails['bits'],
            'fingerprint' => $keyFingerprint,
            'type' => $keyType,
            'pem' => $keySanitized
        ];
    }

    /**
     * Parse a cryptographic key of a given type using OpenSSL directly (without the PHP's wrapper
     * functions) and return an array of informational data. This function is required as Ed25519
     * and Ed448 keys are currently not supported by PHP 7.
     * @param $keyString string
     * @param $keyType string
     * @param $keyPart string
     * @return array
     */
    private function parseCryptographicKey_openssl($keyString, $keyType, $keyPart) {
        $descs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $openssl = null;
        $pipes = [];

        // Attempt to load key with correct type
        if ($keyPart == 'public') {
            $openssl = proc_open('/usr/bin/openssl pkey -inform PEM -text -pubin', $descs, $pipes);
        } elseif ($keyPart == 'private') {
            $openssl = proc_open('/usr/bin/openssl pkey -inform PEM -text -pubout', $descs, $pipes);
        } else {
            throw new \InvalidArgumentException(sprintf(
                gettext('Unsupported key type: %s-%s'),
                $keyType, $keyPart
            ));
        }

        // Process passed key and read openssl's outputs and return value
        fwrite($pipes[0], $keyString);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $retcode = proc_close($openssl);

        // Ensure that key has been successfully loaded
        if ($retcode != 0) {
            throw new \InvalidArgumentException(sprintf(
                gettext('Could not load potentially invalid %s-%s key.'),
                $keyType, $keyPart
            ));
        }

        $keyDetails = [];
        // Attempt to fetch key details
        if (!preg_match('/^(\S+)\s(Public|Private)-Key:/m', $stdout, $matches)) {
            throw new \RuntimeException(sprintf(
                gettext('Could not fetch details for %s-%s key.'),
                $keyType, $keyPart
            ));
        }
        $keyDetails['type'] = $matches[1];
        switch ($keyDetails['type']) {
            case "ED25519":
                $keyDetails['bits'] = 255;
                break;
            case "ED448":
                $keyDetails['bits'] = 448;
                break;
        }

        // Attempt to fetch public key
        if (!preg_match('~^-----BEGIN(?:[A-Z]+ )? PUBLIC KEY-----([A-Za-z0-9+/=\s]+)-----END(?:[A-Z]+ )? PUBLIC KEY-----~', $stdout, $matches)) {
            throw new \RuntimeException(sprintf(
                gettext('Could not fetch public key for %s-%s key.'),
                $keyType, $keyPart
            ));
        }
        $keyDetails['key'] = $matches[0];

        // Verify that key type matches with actual passed key
        if (strtoupper($keyType) != $keyDetails['type']) {
            throw new \RuntimeException(sprintf(
                gettext('Passed key is not a %s key, but a %s key.'),
                strtoupper($keyType), $keyDetails['type']
            ));
        }

        // Fetch sanitized PEM representation of key
        if ($keyPart == 'private') {
            $openssl = proc_open('/usr/bin/openssl pkey -inform PEM -text', $descs, $pipes);

            // Process passed key and read openssl's outputs and return value
            fwrite($pipes[0], $keyString);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $retcode = proc_close($openssl);

            // Ensure that key has been successfully loaded
            if ($retcode != 0) {
                throw new \InvalidArgumentException(sprintf(
                    gettext('Could not generate sanitized %s-%s key in PEM format.'),
                    $keyType, $keyPart
                ));
            }

            // Attempt to fetch sanitized key
            if (!preg_match('~^-----BEGIN(?:[A-Z]+ )? PRIVATE KEY-----([A-Za-z0-9+/=\s]+)-----END(?:[A-Z]+ )? PRIVATE KEY-----~', $stdout, $matches)) {
                throw new \RuntimeException(sprintf(
                    gettext('Could not fetch sanitized key for %s-%s key.'),
                    $keyType, $keyPart
                ));
            }
            $keySanitized = $matches[0];
        } else {
            $keySanitized = $keyDetails['key'];
        }

        $keyFingerprint = $this->calculateFingerprint($keyDetails['key']);
        return [
            'size' => $keyDetails['bits'],
            'fingerprint' => $keyFingerprint,
            'type' => $keyType,
            'pem' => $keySanitized
        ];
    }

    /**
     * Calculate fingerprint for the public key (when a private key was given, its public key
     * is calculated)
     * @param $key string
     * @return string
     */
    private function calculateFingerprint($key) {
        $keyUnwrapped = trim(preg_replace('/\\+s/', '', preg_replace(
            '~^-----BEGIN(?:[A-Z]+ )? PUBLIC KEY-----([A-Za-z0-9+/=\\s]+)-----END(?:[A-Z]+ )? PUBLIC KEY-----$~m',
            '\\1',
            $key
        )));
        return substr(chunk_split(hash('sha1', base64_decode($keyUnwrapped)), 2, ':'), 0, -1);
    }
}
