<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
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
namespace OPNsense\Proxy\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Proxy\Proxy;
use \OPNsense\Core\Config;

/**
 * Class SettingsController
 * @package OPNsense\Proxy
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve proxy settings
     * @return array
     */
    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlProxy = new Proxy();

            // Define array for selected interfaces
            $selopt = array();

            // Get ConfigObject
            $configObj = Config::getInstance()->object();
            // Iterate over all interfaces configuration
            // TODO: replace for <interfaces> helper
            foreach ($configObj->interfaces->children() as $key => $value) {
                // Check if interface is enabled, if tag is <enable/> treat as enabled.
                if (isset($value->enable) && $value->enable != '0') {
                    // Check if interface has static ip
                    if ($value->ipaddr != 'dhcp') {
                        if ($value->descr == '') {
                            $description = strtoupper($key); // Use interface name as description if none is given
                        } else {
                            $description = $value->descr;
                        }
                        $selopt[$key] = (string)$description; // Add Interface to selectable options.
                    }
                }
            }

            $mdlProxy->forward->interfaces->setSelectOptions($selopt);
            $result['proxy'] = $mdlProxy->getNodes();
        }

        return $result;
    }


    /**
     *
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->hasPost("proxy")) {
            // load model and update with provided data
            $mdlProxy = new Proxy();
            $mdlProxy->setNodes($this->request->getPost("proxy"));

            // perform validation
            $valMsgs = $mdlProxy->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["proxy.".$msg->getField()] = $msg->getMessage();
            }

            // serialize model to config
            if ($valMsgs->count() == 0) {
                $mdlProxy->serializeToConfig();
            }

        }


        // save config if validated correctly
        if (!array_key_exists("validations", $result)) {
            $cnf = Config::getInstance();
            $cnf->save();
            $result["result"] = "saved";
        }



        return $result;

    }
}
