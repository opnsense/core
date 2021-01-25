<?php

/**
 *    Copyright (C) 2017-2019 EURO-LOG AG
 *    Copyright (c) 2019 Deciso B.V.
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

namespace OPNsense\Monit\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Config;
use OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Monit
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'monit';
    protected static $internalModelClass = 'OPNsense\Monit\Monit';

    /**
     * check if changes to the monit settings were made
     * @return array result
     */
    public function dirtyAction()
    {
        $result = array('status' => 'ok');
        $result['monit']['dirty'] = $this->getModel()->configChanged();
        return $result;
    }

    /**
     * Retrieve alert settings or return defaults
     * @param $uuid item unique id
     * @return array monit alert content
     * @throws \ReflectionException when not bound to model
     */
    public function getAlertAction($uuid = null)
    {
         return $this->getBase("alert", "alert", $uuid);
    }

    /**
     * Update alert with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setAlertAction($uuid)
    {
        return $this->setBase("alert", "alert", $uuid);
    }

    /**
     * Add alert with given properties
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function addAlertAction()
    {
        return $this->addBase("alert", "alert");
    }

    /**
     * Delete alert by uuid
     * @param string $uuid internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delAlertAction($uuid)
    {
        return  $this->delBase("alert", $uuid);
    }

    /**
     * Search monit alerts
     * @return array list of found alerts
     * @throws \ReflectionException when not bound to model
     */
    public function searchAlertAction()
    {
        return $this->searchBase(
            "alert",
            array("enabled", "recipient", "noton", "events", "description"),
            "description"
        );
    }

    /**
     * Toggle alert defined by uuid (enable/disable)
     * @param $uuid alert internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleAlertAction($uuid, $enabled = null)
    {
        return $this->toggleBase("alert", $uuid, $enabled);
    }

    /**
     * Retrieve service settings or return defaults
     * @param $uuid item unique id
     * @return array monit service content
     * @throws \ReflectionException when not bound to model
     */
    public function getServiceAction($uuid = null)
    {
         return $this->getBase("service", "service", $uuid);
    }

    /**
     * Update service with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setServiceAction($uuid)
    {
        return $this->setBase("service", "service", $uuid);
    }

    /**
     * Add service with given properties
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function addServiceAction()
    {
        return $this->addBase("service", "service");
    }

    /**
     * Delete service by uuid
     * @param string $uuid internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delServiceAction($uuid)
    {
        return  $this->delBase("service", $uuid);
    }

    /**
     * Search monit services
     * @return array list of found services
     * @throws \ReflectionException when not bound to model
     */
    public function searchServiceAction()
    {
        return $this->searchBase("service", array("enabled", "name", "type", "description"), "name");
    }

    /**
     * Toggle service defined by uuid (enable/disable)
     * @param $uuid service internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleServiceAction($uuid, $enabled = null)
    {
        return $this->toggleBase("service", $uuid, $enabled);
    }

    /**
     * Retrieve test settings or return defaults
     * @param $uuid item unique id
     * @return array monit test content
     * @throws \ReflectionException when not bound to model
     */
    public function getTestAction($uuid = null)
    {
         return $this->getBase("test", "test", $uuid);
    }

    /**
     * Update test with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setTestAction($uuid)
    {
        return $this->setBase("test", "test", $uuid);
    }

    /**
     * Add test with given properties
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function addTestAction()
    {
        return $this->addBase("test", "test");
    }

    /**
     * Delete test by uuid
     * @param string $uuid internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delTestAction($uuid)
    {
        return  $this->delBase("test", $uuid);
    }

    /**
     * Search monit services
     * @return array list of found services
     * @throws \ReflectionException when not bound to model
     */
    public function searchTestAction()
    {
        return $this->searchBase("test", array("name", "condition", "action"), "name");
    }

    /**
     * Retrieve general settings
     * @return array monit general settings content
     * @throws \ReflectionException when not bound to model
     */
    public function getGeneralAction()
    {
         return ['monit' => $this->getModel()->general->getNodes(), 'result' => 'ok'];
    }
}
