<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Dnsmasq\FieldTypes;

use OPNsense\Base\FieldTypes\HostnameField;

class AliasesField extends HostnameField
{
    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        if (is_a($value, 'SimpleXMLElement') && isset($value->item)) {
            /* auto convert to simple text blob */
            $tmp = [];
            $comments = [];
            foreach ($value->item as $child) {
                if (empty((string)$child->domain)) {
                    continue;
                }
                if (!empty((string)$child->host)) {
                    $fqdn = sprintf("%s.%s", $child->host, $child->domain);
                } else {
                    $fqdn = (string)$child->domain;
                }
                $tmp[] = $fqdn;
                if (!empty((string)$child->description)) {
                    $comments[] = sprintf("[%s] %s", $fqdn, $child->description);
                }
            }
            $this->getParentNode()->comments = implode("\n", $comments);
            return parent::setValue(implode(",", $tmp));
        } elseif (!empty($value)) {
            /* update only */
            return parent::setValue($value);
        }
    }
}
