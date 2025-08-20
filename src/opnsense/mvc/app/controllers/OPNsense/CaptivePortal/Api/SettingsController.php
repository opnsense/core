<?php

/**
 *    Copyright (C) 2015-2025 Deciso B.V.
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

namespace OPNsense\CaptivePortal\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * Class SettingsController Handles settings related API actions for Captive Portal
 * @package OPNsense\TrafficShaper
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'zone';
    protected static $internalModelClass = '\OPNsense\CaptivePortal\CaptivePortal';

    /**
     * retrieve zone settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getZoneAction($uuid = null)
    {
        return $this->getBase("zone", "zones.zone", $uuid);
    }

    /**
     * update zone with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setZoneAction($uuid)
    {
        return $this->setBase("zone", "zones.zone", $uuid);
    }

    /**
     * add new zone and set with attributes from post
     * @return array
     */
    public function addZoneAction()
    {
        return $this->addBase("zone", "zones.zone");
    }

    /**
     * delete zone by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delZoneAction($uuid)
    {
        return  $this->delBase("zones.zone", $uuid);
    }

    /**
     * toggle zone by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array status
     */
    public function toggleZoneAction($uuid, $enabled = null)
    {
        return $this->toggleBase("zones.zone", $uuid, $enabled);
    }

    /**
     * search captive portal zones
     * @return array
     */
    public function searchZonesAction()
    {
        return $this->searchBase("zones.zone", null, "description");
    }
}
