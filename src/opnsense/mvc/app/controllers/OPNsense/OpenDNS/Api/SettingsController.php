<?php

/*
 * Copyright (C) 2026 Greelan
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

namespace OPNsense\OpenDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'opendns';
    protected static $internalModelClass = '\OPNsense\OpenDNS\OpenDNS';

    /**
     * the system DNS backup is maintained by the configure script only
     * @return array
     */
    protected function getModelNodes()
    {
        $result = parent::getModelNodes();
        unset($result['backup']);
        return $result;
    }

    /**
     * update settings, leaving the system DNS backup untouched
     * @return array status / validation errors
     */
    public function setAction()
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $values = $this->request->getPost(static::$internalModelName);
            if (is_array($values)) {
                unset($values['backup']);
                $this->getModel()->setNodes($values);
            }
            $result = $this->validate();
            if (empty($result['result'])) {
                return $this->save(false, true);
            }
        }
        return $result;
    }
}
