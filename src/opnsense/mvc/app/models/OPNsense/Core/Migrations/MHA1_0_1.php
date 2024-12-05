<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Core\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Hasync;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class MHA1_0_1 extends BaseModelMigration
{
    /**
     * Remove pfsyncenabled by folding it into the pfsyncinterface setting
     * @param $model
     */
    public function run($model)
    {
        if (!($model instanceof Hasync)) {
            return;
        }

        $src = Config::getInstance()->object()->hasync;

        /* duplicated effort from 1.0.0 since that was functional on early 24.7.x */
        if (empty($src->pfsyncenabled)) {
            /* disabe via pfsyncinterface if not set */
            $model->pfsyncinterface = null;
        } else {
            /* may need to disable if previous value is no longer available */
            $model->pfsyncinterface->normalizeValue();
        }
    }
}
