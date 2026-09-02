<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Core\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;

class FirmwareField extends BaseListField
{
    /**
     * @var string field contains the XML path to use
     */
    protected $internalPath = 'mirrors.mirror';

    /**
     * set the path in the XML to read the values from
     * @param $value string dotted path
     */
    public function setPath($value)
    {
        $this->internalPath = $value;
    }

    /**
     * @var string if custom input is allowed
     */
    protected $internalCustom = false;

    /**
     * set to allow custom values in this field
     * @param $value boolean value 0/1
     */
    public function setCustom($value)
    {
        if (trim(strtoupper($value)) == 'Y') {
            $this->internalCustom = true;
        } else {
            $this->internalCustom = false;
        }
    }

    protected function actionPostLoadingEvent()
    {
        $new_list = [];

        $key = $this->internalPath == 'mirrors.mirror' ? 'url' : 'name';

        foreach (glob(__DIR__ . "/../repositories/*.xml") as $xml) {
            $repositoryXml = simplexml_load_file($xml);
            if ($repositoryXml === false || $repositoryXml->getName() != 'firmware') {
                syslog(LOG_ERR, 'unable to parse firmware file ' . $xml);
            } else {
                $node = $repositoryXml;

                foreach (explode('.', $this->internalPath) as $path) {
                    if (isset($node->$path)) {
                        $node = $node->$path;
                    } else {
                        $node = null;
                        break;
                    }
                }

                if (!is_null($node)) {
                    foreach ($node as $value) {
                        $new_list[(string)$value->$key] = (string)$value->description;
                    }
                }
            }
        }

        if ($this->internalCustom) {
            $custom = $this->getValue();
            if (!in_array($custom, $new_list)) {
                $custom = '';
            }
            /* this construct is difficult as it also overwrites '(default)' */
            $new_list[$custom] = '(custom)';
        }

        $this->internalOptionList = $this->setStaticOptions($new_list);
    }
}
