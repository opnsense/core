<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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

namespace OPNsense\Base\FieldTypes;

/**
 * Protocol field type
 * @package OPNsense\Base\FieldTypes
 */
class ProtocolField extends BaseListField
{
    /**
     * @var array cached collected protocols
     */
    private static $internalStaticOptionList = array();

    /**
     * generate validation data (list of protocols)
     */
    protected function actionPostLoadingEvent()
    {
        /* IPv6 extension headers are skipped by the packet filter, we cannot police them */
        $ipv6_ext = array('IPV6-ROUTE', 'IPV6-FRAG', 'IPV6-OPTS', 'IPV6-NONXT', 'MOBILITY-HEADER');
        if (empty(self::$internalStaticOptionList)) {
            self::$internalStaticOptionList = array('any' => gettext('any'));
            foreach (explode("\n", file_get_contents('/etc/protocols')) as $line) {
                if (substr($line, 0, 1) != "#") {
                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) >= 4 && $parts[1] > 0) {
                        $protocol = trim(strtoupper($parts[0]));
                        if (!in_array($protocol, $ipv6_ext) && !isset(self::$internalStaticOptionList[$protocol])) {
                            self::$internalStaticOptionList[$protocol] = $protocol;
                        }
                    }
                }
            }
        }
        $this->internalOptionList = self::$internalStaticOptionList;
    }
}
