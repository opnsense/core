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

use OPNsense\Core\Config;

/**
 * Class LegacyLinkField field, read only referal to a config item in the legacy configuration.
 * @package OPNsense\Base\FieldTypes
 */
class LegacyLinkField extends BaseField
{

    /**
     * @var null source referral (e.g. root.node.item)
     */
    private $internalSourceLink = null;

    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * reflect getter to (legacy) config data
     * {@inheritdoc}
     */
    public function __toString()
    {
        if (!empty($this->internalSourceLink)) {
            $cnf = Config::getInstance()->object();
            foreach (explode(".", $this->internalSourceLink) as $fieldname) {
                if (isset($cnf->$fieldname)) {
                    $cnf = $cnf->$fieldname;
                } else {
                    $cnf = (string)null;
                    break;
                }
            }
            return (string)$cnf;
        }
        return (string)null;
    }


    /**
     * read only reference, keep content empty
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        return;
    }

    /**
     * set source location for this node
     * @param string $source reference like root.node.item
     */
    public function setSource($source)
    {
        $this->internalSourceLink = $source;
    }
}
