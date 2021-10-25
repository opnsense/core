<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Diagnostics;

use OPNsense\Base\IndexController;

/**
 * Class FirewallController
 * @package OPNsense\Diagnostics
 */
class FirewallController extends IndexController
{
    /**
     * firewall live log view
     */
    public function logAction()
    {
        $this->view->pick('OPNsense/Diagnostics/fw_log');
    }
    /**
     * firewall statistical view
     */
    public function statsAction()
    {
        $this->view->pick('OPNsense/Diagnostics/fw_stats');
    }
    /**
     * firewall states
     */
    public function statesAction()
    {
        $this->view->pick('OPNsense/Diagnostics/fw_states');
    }
    /**
     * firewall pftop
     */
    public function pfTopAction()
    {
        $this->view->pick('OPNsense/Diagnostics/fw_pftop');
    }

    /**
     *
     */
    public function statisticsAction()
    {
      $this->view->tabs = [
          [
            "name" => "info",
            "caption" => gettext("info"),
            "endpoint" => "/api/diagnostics/firewall/pf_statistcs/info"
          ],
          [
            "name" => "memory",
            "caption" => gettext("memory"),
            "endpoint" => "/api/diagnostics/firewall/pf_statistcs/memory"
          ],
          [
            "name" => "timeouts",
            "caption" => gettext("timeouts"),
            "endpoint" => "/api/diagnostics/firewall/pf_statistcs/timeouts"
          ],
          [
            "name" => "interfaces",
            "caption" => gettext("interfaces"),
            "endpoint" => "/api/diagnostics/firewall/pf_statistcs/interfaces"
          ],
          [
            "name" => "rules",
            "caption" => gettext("rules"),
            "endpoint" => "/api/diagnostics/firewall/pf_statistcs/rules"
          ]
      ];
      $this->view->default_tab = "info";
      $this->view->pick('OPNsense/Diagnostics/treeview');
    }
}
