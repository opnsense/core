<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

namespace OPNsense\Diagnostics;

use OPNsense\Base\IndexController;

/**
 * @inherit
 */
class LogController extends IndexController
{
    public function renderPage($module, $scope)
    {
        $this->view->pick('OPNsense/Diagnostics/log');
        $this->view->module = htmlspecialchars($module, ENT_QUOTES | ENT_HTML401);
        $this->view->scope = htmlspecialchars($scope, ENT_QUOTES | ENT_HTML401);
        $this->view->service = '';
        $this->view->default_log_severity = 'Warning';

        $service = $module == 'core' ? $scope : $module;

        /* XXX manually hook up known services and log severities for now */
        switch ($service) {
            case 'filter':
                $this->view->default_log_severity = 'Informational';
                break;
            case 'ipsec':
                $this->view->service = 'ipsec';
                break;
            case 'resolver':
                $this->view->service = 'unbound';
                break;
            case 'suricata':
                $this->view->service = 'ids';
                break;
            case 'squid':
                $this->view->service = 'proxy';
                break;
            case 'dhcpd':
                $this->view->service = 'dhcpv4';
                break;
            case 'system':
                $this->view->default_log_severity = 'Notice';
                break;
            default:
                /* no service API at the moment */
                break;
        }
    }

    public function __call($name, $arguments)
    {
        if (substr($name, -6) == 'Action') {
            $scope = count($arguments) > 0 ? $arguments[0] : "core";
            $module = substr($name, 0, strlen($name) - 6);
            return $this->renderPage($module, $scope);
        }
    }
}
