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

namespace OPNsense\Syslog\Api;

use Phalcon\Filter;
use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for the Syslog module
 * @package OPNsense\IDS
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'syslog';
    protected static $internalModelClass = '\OPNsense\Syslog\Syslog';

    /**
     * Search syslog destinations
     * @return array list of found rules
     * @throws \ReflectionException when not bound to model
     */
    public function searchDestinationsAction()
    {
        return $this->searchBase(
            "destinations.destination",
            array("enabled", "description", "transport", "program", "level", "facility", "hostname", "port"),
            "description"
        );
    }

    /**
     * Retrieve destination settings or return defaults for a new one
     * @param $uuid item unique id
     * @return array destination content
     * @throws \ReflectionException when not bound to model
     */
    public function getDestinationAction($uuid = null)
    {
        return $this->getBase("destination", "destinations.destination", $uuid);
    }

    /**
     * Update destination with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setDestinationAction($uuid)
    {
        return $this->setBase("destination", "destinations.destination", $uuid);
    }

    /**
     * Add new destination and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \Phalcon\Validation\Exception when field validations fail
     */
    public function addDestinationAction()
    {
        return $this->addBase('destination', 'destinations.destination');
    }
    /**
     * Delete destination by uuid
     * @param string $uuid internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delDestinationAction($uuid)
    {
        return  $this->delBase("destinations.destination", $uuid);
    }

    /**
     * Toggle destination defined by uuid (enable/disable)
     * @param $uuid user defined rule internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleDestinationAction($uuid, $enabled = null)
    {
        return $this->toggleBase("destinations.destination", $uuid, $enabled);
    }
}
