<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

    protected function actionPostLoadingEvent()
    {
        if (empty(self::$internalCacheOptionList)) {
            self::$internalCacheOptionList['default'] = gettext('default');
            // sort commmonly used on top (ref https://wiki.strongswan.org/projects/strongswan/wiki/CipherSuiteExamples)
            self::$internalCacheOptionList['aes192gcm16-ecp384'] = 'aes192gcm16-ecp384';
            self::$internalCacheOptionList['aes256gcm16-ecp521'] = 'aes256gcm16-ecp521';
            self::$internalCacheOptionList['aes256gcm16-aes128gcm16-ecp384-ecp256'] = 'aes256gcm16-aes128gcm16-ecp384-ecp256';
            self::$internalCacheOptionList['aes128gcm16-ecp256'] = 'aes128gcm16-ecp256';
            self::$internalCacheOptionList['aes128gcm16-x25519'] = 'aes128gcm16-x25519';
            self::$internalCacheOptionList['aes128gcm16-aesxcbc-x25519'] = 'aes128gcm16-aesxcbc-x25519';
            self::$internalCacheOptionList['aes192-sha384-ecp384'] = 'aes192-sha384-ecp384';
            self::$internalCacheOptionList['aes256-sha512-ecp521'] = 'aes256-sha512-ecp521';
            self::$internalCacheOptionList['aes128-sha256-sha1'] = 'aes128-sha256-sha1';
            self::$internalCacheOptionList['aes128-sha256-modp2048s256'] = 'aes128-sha256-modp2048s256';
            self::$internalCacheOptionList['aes128-sha1-modp1024s160'] = 'aes128-sha1-modp1024s160';
            self::$internalCacheOptionList['aes256-aes128-sha384-sha256-ecp384-ecp256'] = 'aes256-aes128-sha384-sha256-ecp384-ecp256';
            self::$internalCacheOptionList['aes128ctr-aesxcbc-x25519'] = 'aes128ctr-aesxcbc-x25519';
            self::$internalCacheOptionList['aes128ccm12-x25519'] = 'aes128ccm12-x25519';
            self::$internalCacheOptionList['aes128ccm12-aesxcbc-x25519'] = 'aes128ccm12-aesxcbc-x25519';
            self::$internalCacheOptionList['aes128gmac-x25519'] = 'aes128gmac-x25519';
            self::$internalCacheOptionList['aes128-sha256-x25519'] = 'aes128-sha256-x25519';
            self::$internalCacheOptionList['aes128-aesxcbc-x25519'] = 'aes128-aesxcbc-x25519';
            self::$internalCacheOptionList['aes192-sha384-x25519'] = 'aes192-sha384-x25519';
            self::$internalCacheOptionList['aes256-sha512-x25519'] = 'aes256-sha512-x25519';
            self::$internalCacheOptionList['aes128-sha256-ecp256'] = 'aes128-sha256-ecp256';

            self::$internalCacheOptionList['null-sha256-x25519'] = sprintf(
                gettext('%s (testing only!)'),
                'null-sha256-x25519'
            );
            foreach (['aes128', 'aes192', 'aes256', 'aes128gcm16', 'aes192gcm16', 'aes256gcm16'] as $encalg) {
                foreach (['sha256', 'sha384', 'sha512', 'aesxcbc'] as $intalg) {
                    foreach (
                        [
                        'modp2048', 'modp3072', 'modp4096', 'modp6144', 'modp8192', 'ecp224',
                        'ecp256', 'ecp384', 'ecp521', 'ecp224bp', 'ecp256bp', 'ecp384bp', 'ecp512bp',
                        'x25519', 'x448'] as $dhgroup
                    ) {
                        $cipher = "{$encalg}-{$intalg}-{$dhgroup}";
                        if (!isset(self::$internalCacheOptionList[$cipher])) {
                            self::$internalCacheOptionList[$cipher] = $cipher;
                        }
                    }
                }
            }
        }
        $this->internalOptionList = self::$internalCacheOptionList;
    }
}
