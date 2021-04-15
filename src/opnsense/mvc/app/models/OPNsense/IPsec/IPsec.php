<?php

/*
 * Copyright (C) 2019 Pascal Mathis <mail@pascalmathis.com>
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
                    (string)$keyPair->keyType . '-public'
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
                    (string)$keyPair->keyType . '-private'
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
     * @return array
     */
    public function parseCryptographicKey($keyString, $keyType)
    {
        // Attempt to load key with correct type
        if ($keyType === 'rsa-public') {
            $key = openssl_pkey_get_public($keyString);
        } elseif ($keyType === 'rsa-private') {
            $key = openssl_pkey_get_private($keyString);
        } else {
            throw new \InvalidArgumentException(sprintf(
                gettext('Unsupported key type: %s'),
                $keyType
            ));
        }

        // Ensure that key has been successfully loaded
        if ($key === false) {
            throw new \InvalidArgumentException(sprintf(
                gettext('Could not load potentially invalid %s key: %s'),
                $keyType,
                openssl_error_string()
            ));
        }

        // Attempt to fetch key details
        $keyDetails = openssl_pkey_get_details($key);
        if ($keyDetails === false) {
            throw new \RuntimeException(sprintf(
                gettext('Could not fetch details for %s key: %s'),
                $keyType,
                openssl_error_string()
            ));
        }

        // Verify given public key is valid for usage with Strongswan
        if ($keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new \InvalidArgumentException(sprintf(
                gettext('Unsupported OpenSSL key type [%d] for %s key, expected RSA.'),
                $keyDetails['type'],
                $keyType
            ));
        }

        // Fetch sanitized PEM representation of key
        if ($keyType === 'rsa-private') {
            if (!openssl_pkey_export($key, $keySanitized, null)) {
                throw new \RuntimeException(sprintf(
                    gettext('Could not generate sanitized %s key in PEM format: %s'),
                    $keyType,
                    openssl_error_string()
                ));
            }
        } else {
            $keySanitized = $keyDetails['key'];
        }

        // Calculate fingerprint for the public key (when a private key was given, its public key is calculated)
        $keyUnwrapped = trim(preg_replace('/\\+s/', '', preg_replace(
            '~^-----BEGIN(?:[A-Z]+ )? PUBLIC KEY-----([A-Za-z0-9+/=\\s]+)-----END(?:[A-Z]+ )? PUBLIC KEY-----$~m',
            '\\1',
            $keyDetails['key']
        )));
        $keyFingerprint = substr(chunk_split(hash('sha1', base64_decode($keyUnwrapped)), 2, ':'), 0, -1);

        return [
            'resource' => $key,
            'size' => $keyDetails['bits'],
            'fingerprint' => $keyFingerprint,
            'type' => $keyType,
            'pem' => $keySanitized
        ];
    }
}
