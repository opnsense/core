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

namespace OPNsense\Dnsmasq;

class SettingsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->generalForm = $this->getForm("general");
        $this->view->formDialogEditHostOverride = $this->getForm("dialogHostOverride");
        $this->view->formGridHostOverride = $this->getFormGrid("dialogHostOverride", "host");
        $this->view->formDialogEditDomainOverride = $this->getForm("dialogDomainOverride");
        $this->view->formGridDomainOverride = $this->getFormGrid("dialogDomainOverride", "domain");
        $this->view->formDialogEditDHCPtag = $this->getForm("dialogDHCPtag");
        $this->view->formGridDHCPtag = $this->getFormGrid("dialogDHCPtag", "tag");
        $this->view->formDialogEditDHCPrange = $this->getForm("dialogDHCPrange");
        $this->view->formGridDHCPrange = $this->getFormGrid("dialogDHCPrange", "range");
        $this->view->formDialogEditDHCPoption = $this->getForm("dialogDHCPoption");
        $this->view->formGridDHCPoption = $this->getFormGrid("dialogDHCPoption", "option");
        $this->view->formDialogEditDHCPboot = $this->getForm("dialogDHCPboot");
        $this->view->formGridDHCPboot = $this->getFormGrid("dialogDHCPboot", "boot");

        $this->view->pick('OPNsense/Dnsmasq/settings');
    }
}
