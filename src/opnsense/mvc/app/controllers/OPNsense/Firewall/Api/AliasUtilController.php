<?php
/**
 *    Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\Firewall\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

/**
 * @package OPNsense\Firewall
 */
class AliasUtilController extends ApiControllerBase
{
    /**
     * list active alias tables
     * @return array alias names
     */
    public function aliasesAction()
    {
        $this->sessionClose();
        $backend = new Backend();
        $result = json_decode($backend->configdRun("filter list tables json"));
        if ($result !== null) {
            // return sorted (case insensitive)
            natcasesort($result);
            $result = array_values($result);
        }
        return $result;
    }

    /**
     * list alias table
     * @param string $alias name to list
     * @return array alias contents
     */
    public function listAction($alias)
    {
        $this->sessionClose();
        $backend = new Backend();
        $entries = json_decode($backend->configdpRun("filter list table", array($alias, "json")));
        sort($entries);
        return $entries;
    }

    /**
     * update bogons table
     * @return array status
     */
    public function update_bogonsAction()
    {
        $this->sessionClose();
        $backend = new Backend();
        $backend->configdRun("filter update bogons");
        return array("status" => "done");
    }

    /**
     * flush alias table
     * @param string $alias name to flush
     * @return array status
     */
    public function flushAction($alias)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $backend = new Backend();
            $backend->configdpRun("filter delete table", array($alias, "ALL"));
            return array("status" => "done");
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * delete item from alias table
     * @param string $alias name
     * @return array status
     */
    public function deleteAction($alias)
    {
        if ($this->request->isPost() && $this->request->hasPost("address")) {
            $this->sessionClose();
            $backend = new Backend();
            $backend->configdpRun("filter delete table", array($alias, $this->request->getPost("address")));
            return array("status" => "done");
        } else {
            return array("status" => "failed");
        }
    }
}
