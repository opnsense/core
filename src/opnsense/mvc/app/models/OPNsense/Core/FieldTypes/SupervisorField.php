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

use OPNsense\Base\FieldTypes\OptionField;
use OPNsense\Core\File;

class SupervisorField extends OptionField
{
    protected function actionPostLoadingEvent()
    {
        if ($this->hasStaticOptions()) {
            $this->internalOptionList = $this->getStaticOptions();
            return parent::actionPostLoadingEvent();
        }

        $supervisors = [];
        foreach (File::getFiles('/usr/local/etc/supervisor.d', 'conf') as $filename) {
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $config = parse_ini_file($filename, true);
            if (
                is_array($config) &&
                preg_match('/^[a-zA-Z0-9_.-]+$/', $name) &&
                !empty($config['service']['description'])
            ) {
                $supervisors[$name] = gettext($config['service']['description']);
            }
        }
        natcasesort($supervisors);
        $this->internalOptionList = $this->setStaticOptions($supervisors);

        return parent::actionPostLoadingEvent();
    }
}
