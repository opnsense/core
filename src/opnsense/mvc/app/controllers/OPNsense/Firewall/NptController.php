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
namespace OPNsense\Firewall;

class NptController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/Firewall/filter');
        $this->view->ruleController = "npt";
        $this->view->gridFields = [
            [
                'id' => 'enabled', 'formatter' => 'rowtoggle' ,'width' => '6em', 'heading' => gettext('Enabled')
            ],
            [
                'id' => 'sequence','width' => '9em', 'heading' => gettext('Sequence')
            ],
            [
                'id' => 'source_net', 'heading' => gettext('Internal IPv6 Prefix')
            ],
            [
                'id' => 'destination_net', 'heading' => gettext('External IPv6 Prefix')
            ],
            [
                'id' => 'trackif', 'heading' => gettext('Track if')
            ],
            [
                'id' => 'description', 'heading' => gettext('Description')
            ]
        ];

        $this->view->formDialogFilterRule = $this->getForm("dialogNptRule");
    }
}
