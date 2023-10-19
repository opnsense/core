<?php

/*
 * Copyright (C) 2022-2023 Deciso B.V.
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

namespace OPNsense\IPsec\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;

/**
 * @package OPNsense\Base\FieldTypes
 */
class IPsecProposalField extends BaseListField
{
    private static $internalCacheOptionList = [];

    private static function commonOptions()
    {
        /* group and cipher order, when set to null an auto generated description will be used */
        return [
            gettext('Internal') => [
                'default' => gettext('default')
            ],
            gettext('Commonly used AES') => [
                'aes256-sha256-modp2048' => null,
                'aes256-sha512-modp2048' => null,
                'aes128-sha256-modp2048' => null,
                'aes128-sha512-modp2048' => null,
                'aes256-sha256-modp4096' => null,
                'aes256-sha512-modp4096' => null,
                'aes256-sha256-ecp521' => null,
                'aes256-sha512-ecp521' => null,
            ],
            gettext('Commonly used AES with Galois/Counter Mode') => [
                'aes256gcm16-modp2048' => null,
                'aes256gcm16-ecp521' => null,
                'aes256gcm16-x25519' => 'aes256gcm16-curve25519 [DH31, Modern EC]',
                'aes256gcm16-x448' => 'aes256gcm16-curve448 [DH32, Modern EC]',
                'aes128gcm16-modp2048' => null,
                'aes128gcm16-ecp521' => null,
                'aes128gcm16-x25519' => 'aes128gcm16-curve25519 [DH31, Modern EC]',
                'aes128gcm16-x448' => 'aes128gcm16-curve448 [DH32, Modern EC]',
            ],
            gettext('Commonly used, but insecure cipher suites') => [
                'aes256-sha1-modp2048' => 'aes256-sha1-modp2048 [DH14]',
                'aes128-sha1-modp2048' => 'aes128-sha1-modp2048 [DH14]',
                'aes256-sha1-modp4096' => 'aes256-sha1-modp4096 [DH16]',
                'aes256-sha1-ecp521' => 'aes256-sha1-ecp521 [DH21, NIST EC]',
                'aes256-sha512-modp1024' => 'aes256-sha512-modp1024 [DH2]',
                'null-sha256-x25519' => gettext('null-sha256-x25519 (testing only, no encryption!)')
            ]
        ];
    }

    protected function actionPostLoadingEvent()
    {
        if (empty(self::$internalCacheOptionList)) {
            /**
             *  Build cipher suite options, for more information, we refer to the following documents:
             *  https://wiki.strongswan.org/projects/strongswan/wiki/CipherSuiteExamples
             *  https://wiki.strongswan.org/projects/strongswan/wiki/SecurityRecommendations/50
             */
            foreach (self::commonOptions() as $group => $ciphers) {
                foreach ($ciphers as $cipher => $description) {
                    self::$internalCacheOptionList[$cipher] = ['value' => $description, 'optgroup' => $group];
                }
            }

            $dhgroups = [
                'modp2048' => 'DH14',
                'modp3072' => 'DH15',
                'modp4096' => 'DH16',
                'modp6144' => 'DH17',
                'modp8192' => 'DH18',
                'ecp224' => 'DH26, NIST EC',
                'ecp256' => 'DH19, NIST EC',
                'ecp384' => 'DH20, NIST EC',
                'ecp521' => 'DH21, NIST EC',
                'ecp224bp' => 'DH27, Brainpool EC',
                'ecp256bp' => 'DH28, Brainpool EC',
                'ecp384bp' => 'DH29, Brainpool EC',
                'ecp512bp' => 'DH30, Brainpool EC',
                'x25519' => 'DH31, Modern EC',
                'x448' => 'DH32, Modern EC'
            ];

            foreach (['aes128', 'aes192', 'aes256', 'aes128gcm16', 'aes192gcm16', 'aes256gcm16'] as $encalg) {
                foreach (['sha256', 'sha384', 'sha512', 'aesxcbc'] as $intalg) {
                    foreach ($dhgroups as $dhgroup => $descr) {
                        if (strpos($encalg, 'gcm') !== false) {
                            /** GCM includes hashing */
                            $cipher = "{$encalg}-{$dhgroup}";
                        } else {
                            $cipher = "{$encalg}-{$intalg}-{$dhgroup}";
                        }
                        if (empty(self::$internalCacheOptionList[$cipher])) {
                            self::$internalCacheOptionList[$cipher] = [
                                'value' => $cipher . " [{$descr}]",
                                'optgroup' => gettext('Miscellaneous')
                            ];
                        } elseif (empty(self::$internalCacheOptionList[$cipher]['value'])) {
                            self::$internalCacheOptionList[$cipher]['value'] = $cipher . " [{$descr}]";
                        }
                    }
                }
            }
        }

        $this->internalOptionList = self::$internalCacheOptionList;
    }
}
