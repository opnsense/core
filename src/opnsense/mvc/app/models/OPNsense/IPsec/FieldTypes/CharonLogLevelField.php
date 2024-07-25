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
class CharonLogLevelField extends BaseListField
{
    /**
     * {@inheritdoc}
     */
    protected $internalDefaultValue = "1";

    /**
     * {@inheritdoc}
     */
    protected $internalValue = "1";

    /**
     * {@inheritdoc}
     */
    protected $internalIsRequired = true;

    /**
     * {@inheritdoc}
     */
    protected function actionPostLoadingEvent()
    {
        $this->internalOptionList = [
            "-1" => gettext("Absolutely silent"),
            "0" => gettext("Very basic auditing logs, (e.g. SA up/SA down)"),
            "1" => gettext("Generic control flow with errors (default)"),
            "2" => gettext("More detailed debugging control flow"),
            "3" => gettext("Including RAW data dumps in hex"),
            "4" => gettext("Also include sensitive material in dumps, e.g. keys"),
        ];

        return parent::actionPostLoadingEvent();
    }
}
