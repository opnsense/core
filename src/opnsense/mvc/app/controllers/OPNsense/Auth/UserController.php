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

namespace OPNsense\Auth;

class UserController extends \OPNsense\Base\IndexController
{
    protected function templateJSIncludes()
    {
        $result = parent::templateJSIncludes();
        $result[] = '/ui/js/moment-with-locales.min.js';
        $result[] = '/ui/js/jquery.qrcode.js';
        $result[] = '/ui/js/qrcode.js';
        $result[] = '/ui/js/bootstrap-datepicker.min.js';

        return $result;
    }

    protected function templateCssIncludes()
    {
        $result = parent::templateCssIncludes();
        $result[] = '/ui/css/bootstrap-datepicker3.min.css';
        return $result;
    }

    public function indexAction()
    {
        $this->view->formDialogEditUser = $this->getForm("dialogUser");
        $this->view->formGridUser = $this->getFormGrid("dialogUser");
        $this->view->pick('OPNsense/Auth/user');
    }
}
