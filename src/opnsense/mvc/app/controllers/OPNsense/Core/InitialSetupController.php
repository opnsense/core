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

namespace OPNsense\Core;

use OPNsense\Core\Config;

class InitialSetupController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        /**
         * XXX: Eventually we might want to move this out of the way, but "mark" wizard as started on enter
         */
        if (isset(Config::getInstance()->object()->trigger_initial_wizard)) {
            unset(Config::getInstance()->object()->trigger_initial_wizard);
            Config::getInstance()->save();
        }
        $this->view->all_tabs = [
            'step_0' => [
                'title' => gettext('Welcome'),
                'message' => gettext(
                    'This wizard will guide you through the initial system configuration. ' .
                    'The wizard may be stopped at any time by clicking the logo image at the top of the screen.'
                )
            ],
            'step_1' => [
                'title' => gettext('General Information'),
                'form' => $this->getForm('wizard_general_info')
            ],
            'step_2' => [
                'title' => gettext('Network [WAN]'),
                'form' => $this->getForm('wizard_network_wan')
            ],
            'step_3' => [
                'title' => gettext('Network [LAN]'),
                'form' => $this->getForm('wizard_network_lan')
            ],
            'step_4' => [
                'title' => gettext('Set initial password'),
                'form' => $this->getForm('wizard_root_password')
            ],
            'step_final' => [
                'title' => gettext('Finish'),
                'message' => gettext(
                    'This is the last step in the wizard, click apply to reconfigure the firewall.'
                )
            ],
        ];
        $this->view->pick('OPNsense/Core/initial_setup');
    }
}
