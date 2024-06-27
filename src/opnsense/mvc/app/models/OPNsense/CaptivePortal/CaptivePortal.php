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

namespace OPNsense\CaptivePortal;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * Class CaptivePortal
 * @package OPNsense\CaptivePortal
 */
class CaptivePortal extends BaseModel
{
    /**
     * retrieve zone by number
     * @param string $zoneid zone number
     * @return null|BaseField zone details
     */
    public function getByZoneID(string $zoneid)
    {
        foreach ($this->zones->zone->iterateItems() as $zone) {
            if ($zoneid === (string)$zone->zoneid) {
                return $zone;
            }
        }
        return null;
    }

    /**
     * check if module is enabled
     * @return bool is the captive portal enabled (1 or more active zones)
     */
    public function isEnabled()
    {
        foreach ($this->zones->zone->iterateItems() as $zone) {
            if ((string)$zone->enabled == "1") {
                return true;
            }
        }
        return false;
    }

    /**
     * find template by name or return a new object
     * @param $name template name
     * @return mixed
     */
    public function getTemplateByName($name)
    {
        foreach ($this->templates->template->iterateItems() as $template) {
            if ((string)$template->name === $name) {
                return $template;
            }
        }
        $newItem = $this->templates->template->Add();
        $newItem->name = $name;
        $newItem->fileid = uniqid();
        return $newItem;
    }
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        // validate changed instances
        foreach ($this->zones->zone->iterateItems() as $zone) {
            if (!$validateFullModel && !$zone->isFieldChanged()) {
                continue;
            }
            $key = $zone->__reference;
            if (!empty((string)$zone->interfaces_inbound) && !empty((string)$zone->interfaces)) {
                $ifs_inbound = array_filter(explode(',', $zone->interfaces_inbound));
                $ifs = array_filter(explode(',', $zone->interfaces));
                $overlap = array_intersect($ifs_inbound, $ifs);
                if (!empty($overlap)) {
                    $messages->appendMessage(
                        new Message(
                            sprintf(
                                gettext("Inbound interfaces may not overlap with zone interfaces (%s)"),
                                implode(',', $overlap)
                            ),
                            $key . ".interfaces_inbound"
                        )
                    );
                }
            }
        }
        return $messages;
    }
}
