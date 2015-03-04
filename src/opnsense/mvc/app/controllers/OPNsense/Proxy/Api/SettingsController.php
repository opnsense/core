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
use \OPNsense\Proxy\General;
use \OPNsense\Core\Config;

/**
 * Class SettingsController
 * @package OPNsense\Proxy
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve general settings
     * @return array
     */
    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlGeneral = new General();
            $result['general'] = $mdlGeneral->getNodes();
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
        if ($this->request->hasPost("general")) {
            // load model and update with provided data
            $mdlGeneral = new General();
            $mdlGeneral->setNodes($this->request->getPost("general"));

            // perform validation
            $valMsgs = $mdlGeneral->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["general.".$msg->getField()] = $msg->getMessage();
            }

            // serialize model to config
            if ($valMsgs->count() == 0) {
                $mdlGeneral->serializeToConfig();
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
