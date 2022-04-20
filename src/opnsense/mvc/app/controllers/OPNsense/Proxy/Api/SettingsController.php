<?php

/**
 *    Copyright (C) 2015 Jos Schellevis <jos@opnsense.org>
 *    Copyright (C) 2017 Fabian Franz
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
 */

namespace OPNsense\Proxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Cron\Cron;
use OPNsense\Core\Config;
use OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Proxy
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'proxy';
    protected static $internalModelClass = '\OPNsense\Proxy\Proxy';

    /**
     *
     * search remote blacklists
     * @return array
     */
    public function searchRemoteBlacklistsAction()
    {
        $this->sessionClose();
        $mdlProxy = $this->getModel();
        $grid = new UIModelGrid($mdlProxy->forward->acl->remoteACLs->blacklists->blacklist);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "filename", "url", "description"),
            "description"
        );
    }

    /**
     * retrieve remote blacklist settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getRemoteBlacklistAction($uuid = null)
    {
        return $this->getBase("blacklist", "forward.acl.remoteACLs.blacklists.blacklist", $uuid);
    }

    /**
     * update remote blacklist item
     * @param string $uuid
     * @return array result status
     * @throws \Phalcon\Filter\Validation\Exception
     */
    public function setRemoteBlacklistAction($uuid)
    {
        return $this->setBase('blacklist', 'forward.acl.remoteACLs.blacklists.blacklist', $uuid);
    }

    /**
     * add new blacklist and set with attributes from post
     * @return array
     */
    public function addRemoteBlacklistAction()
    {
        return $this->addBase('blacklist', 'forward.acl.remoteACLs.blacklists.blacklist');
    }

    /**
     * delete blacklist by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delRemoteBlacklistAction($uuid)
    {
        return $this->delBase('forward.acl.remoteACLs.blacklists.blacklist', $uuid);
    }

    /**
     * toggle blacklist by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array status
     */
    public function toggleRemoteBlacklistAction($uuid)
    {
        return $this->toggleBase('forward.acl.remoteACLs.blacklists.blacklist', $uuid);
    }

    /**
     * create new cron item for remote acl or return already available one
     * @return array status action
     */
    public function fetchRBCronAction()
    {
        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlProxy = $this->getModel();
            if ((string)$mdlProxy->forward->acl->remoteACLs->UpdateCron == "") {
                $mdlCron = new Cron();
                // update cron relation (if this doesn't break consistency)
                $uuid = $mdlCron->newDailyJob("Proxy", "proxy fetchacls", "fetch proxy acls", "1");
                $mdlProxy->forward->acl->remoteACLs->UpdateCron = $uuid;

                if ($mdlCron->performValidation()->count() == 0) {
                    $mdlCron->serializeToConfig();
                    // save data to config, do not validate because the current in memory model doesn't know about the
                    // cron item just created.
                    $mdlProxy->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();
                    $result['result'] = "new";
                    $result['uuid'] = $uuid;
                } else {
                    $result['result'] = "unable to add cron";
                }
            } else {
                $result['result'] = "existing";
                $result['uuid'] = (string)$mdlProxy->forward->acl->remoteACLs->UpdateCron;
            }
        }

        return $result;
    }

    /**
     *
     * search PAC Rule
     * @return array
     */
    public function searchPACRuleAction()
    {
        $this->sessionClose();
        return $this->searchBase('pac.rule', array("enabled", "description", "proxies", "matches"), "description");
    }

    /**
     * retrieve PAC Rule or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getPACRuleAction($uuid = null)
    {
        $this->sessionClose();
        return array("pac" => $this->getBase('rule', 'pac.rule', $uuid));
    }

    /**
     * add new PAC Rule and set with attributes from post
     * @return array
     */
    public function addPACRuleAction()
    {
        $this->pac_set_helper();
        return $this->addBase('rule', 'pac.rule');
    }

    /**
     * update PAC Rule
     * @param string $uuid
     * @return array result status
     * @throws \Phalcon\Filter\Validation\Exception
     */
    public function setPACRuleAction($uuid)
    {
        $this->pac_set_helper();
        return $this->setBase('rule', 'pac.rule', $uuid);
    }

    /**
     * toggle PAC Rule by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array status
     */
    public function togglePACRuleAction($uuid)
    {
        return $this->toggleBase('pac.rule', $uuid);
    }

    /**
     * delete PAC Rule by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delPACRuleAction($uuid)
    {
        return $this->delBase('pac.rule', $uuid);
    }

    /**
     *
     * search PAC Proxy
     * @return array
     */
    public function searchPACProxyAction()
    {
        $this->sessionClose();
        return $this->searchBase('pac.proxy', array("enabled","proxy_type", "name", "url", "description"), "description");
    }

    /**
     * retrieve PAC Proxy or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getPACProxyAction($uuid = null)
    {
        $this->sessionClose();
        return array("pac" => $this->getBase('proxy', 'pac.proxy', $uuid));
    }

    /**
     * add new PAC Proxy and set with attributes from post
     * @return array
     */
    public function addPACProxyAction()
    {
        $this->pac_set_helper();
        return $this->addBase('proxy', 'pac.proxy');
    }

    /**
     * update PAC Proxy
     * @param string $uuid
     * @return array result status
     * @throws \Phalcon\Filter\Validation\Exception
     */
    public function setPACProxyAction($uuid)
    {
        $this->pac_set_helper();
        return $this->setBase('proxy', 'pac.proxy', $uuid);
    }

    /**
     * delete PAC Proxy by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delPACProxyAction($uuid)
    {
        return $this->delBase('pac.proxy', $uuid);
    }

    /**
     * search PAC Match
     * @return array
     */
    public function searchPACMatchAction()
    {
        $this->sessionClose();
        return $this->searchBase('pac.match', array("enabled", "name", "description", "negate", "match_type"), "name");
    }

    /**
     * retrieve PAC Match or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getPACMatchAction($uuid = null)
    {
        $this->sessionClose();
        return array("pac" => $this->getBase('match', 'pac.match', $uuid));
    }

    /**
     * add new PAC Proxy and set with attributes from post
     * @return array
     */
    public function addPACMatchAction()
    {
        $this->pac_set_helper();
        return $this->addBase('match', 'pac.match');
    }

    /**
     * update PAC Rule
     * @param string $uuid
     * @return array result status
     * @throws \Phalcon\Filter\Validation\Exception
     */
    public function setPACMatchAction($uuid)
    {
        $this->pac_set_helper();
        return $this->setBase('match', 'pac.match', $uuid);
    }

    /**
     * delete PAC Match by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delPACMatchAction($uuid)
    {
        return $this->delBase('pac.match', $uuid);
    }

    /**
     * flatten post data structure
     */
    private function pac_set_helper()
    {
        if ($this->request->isPost() && $this->request->hasPost("pac")) {
            $pac_data = $this->request->getPost('pac');
            if (is_array($pac_data)) {
                foreach ($pac_data as $key => $value) {
                    $_POST[$key] = $value;
                }
            }
        }
    }
}
