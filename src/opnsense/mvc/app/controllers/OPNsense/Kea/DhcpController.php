<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Kea;

class DhcpController extends \OPNsense\Base\IndexController
{
    /**
     * {@inheritdoc}
     */
    protected function templateJSIncludes()
    {
        return array_merge(parent::templateJSIncludes(), [
            '/ui/js/moment-with-locales.min.js'
        ]);
    }

    public function ctrlAgentAction()
    {
        $this->view->pick('OPNsense/Kea/ctrl_agent');
        $this->view->formGeneralSettings = $this->getForm("agentSettings");
    }

    public function v4Action()
    {
        $this->view->pick('OPNsense/Kea/dhcpv4');
        $this->view->formGeneralSettings = $this->getForm("generalSettings4");
        $this->view->formDialogSubnet = $this->getForm("dialogSubnet4");
        $this->view->formDialogReservation = $this->getForm("dialogReservation4");
        $this->view->formDialogPeer = $this->getForm("dialogPeer4");
    }

    public function leases4Action()
    {
        $this->view->pick('OPNsense/Kea/leases4');
    }
}
