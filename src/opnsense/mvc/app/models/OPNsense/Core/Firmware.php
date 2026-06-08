<?php

/*
 * Copyright (C) 2021-2026 Deciso B.V.
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

namespace OPNsense\Core;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * Class Firmware
 * @package OPNsense\Core
 */
class Firmware extends BaseModel
{
    /**
     * helper function to list firmware mirror and flavour options
     * @return array
     */
    public function getRepositoryOptions()
    {
        $families = $families_has_subscription = [];
        $flavours = $flavours_has_subscription = [];
        $mirrors = $mirrors_has_subscription = [];

        foreach (glob(__DIR__ . "/repositories/*.xml") as $xml) {
            $repositoryXml = simplexml_load_file($xml);
            if ($repositoryXml === false || $repositoryXml->getName() != 'firmware') {
                syslog(LOG_ERR, 'unable to parse firmware file ' . $xml);
            } else {
                if (isset($repositoryXml->mirrors->mirror)) {
                    foreach ($repositoryXml->mirrors->mirror as $mirror) {
                        $mirrors[(string)$mirror->url] = (string)$mirror->description;
                        $attr = $mirror->attributes();
                        if (isset($attr->has_subscription) && strtolower($attr->has_subscription) == "true") {
                            $mirrors_has_subscription[] = (string)$mirror->url;
                        }
                    }
                }
                if (isset($repositoryXml->flavours->flavour)) {
                    foreach ($repositoryXml->flavours->flavour as $flavour) {
                        $flavours[(string)$flavour->name] = (string)$flavour->description;
                        $attr = $flavour->attributes();
                        if (isset($attr->has_subscription) && strtolower($attr->has_subscription) == "true") {
                            $flavours_has_subscription[] = (string)$flavour->name;
                        }
                    }
                }
                if (isset($repositoryXml->families->family)) {
                    foreach ($repositoryXml->families->family as $family) {
                        $families[(string)$family->name] = (string)$family->description;
                        $attr = $family->attributes();
                        if (isset($attr->has_subscription) && strtolower($attr->has_subscription) == "true") {
                            $families_has_subscription[] = (string)$family->name;
                        }
                    }
                }
            }
        }
        return [
            /* provide a full set of data even though the frontend does not use it */
            'families' => $families,
            'families_has_subscription' => $families_has_subscription,
            'flavours' => $flavours,
            'flavours_has_subscription' => $flavours_has_subscription,
            'mirrors' => $mirrors,
            'mirrors_has_subscription' => $mirrors_has_subscription,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = true)
    {
        $validOptions = $this->getRepositoryOptions();

        $messages = parent::performValidation($validateFullModel);

        if (!isset($validOptions['families'][$this->type->getValue()])) {
            $messages->appendMessage(new Message(gettext('Unable to set invalid firmware release type'), 'type'));
        }

        /* in custom mirrors we emulate has_subscription="true" but make it optional */
        $validate_subscription = !isset($validOptions['mirrors'][$this->mirror->getValue()]);
        $require_subscription = false;

        /* subscription is required, but fall through to actual validation */
        if (in_array($this->mirror->getValue(), $validOptions['mirrors_has_subscription'])) {
            if ($this->subscription->isEmpty()) {
                $messages->appendMessage(new Message(gettext('A valid subscription is required for this firmware mirror'), 'subscription'));
            } else {
                $validate_subscription = true;
                $require_subscription = true;
            }
        }

        if ($validate_subscription && !$this->subscription->isEmpty()) {
            if (
                /* technically correct subscription key */
                !preg_match('/^[a-z0-9]{8}(-[a-z0-9]{4}){3}-[a-z0-9]{12}$/i', $this->subscription->getValue()) &&
                /* mocked subscription key to prompt the user to input the right one */
                'FILL-IN-YOUR-LICENSE-HERE' != $this->subscription->getValue() &&
                /* the special key to unlock local mirroring */
                'local' != $this->subscription->getValue()
            ) {
                $messages->appendMessage(new Message(gettext('A valid subscription is required for this firmware mirror'), 'subscription'));
            }
            if (!preg_match('/\//', $this->flavour->getValue())) {
                if (!in_array($this->type->getValue(), $validOptions['families_has_subscription'])) {
                    $messages->appendMessage(new Message(sprintf(gettext('Subscription requires the following type: %s'), $validOptions['families'][$validOptions['families_has_subscription'][0]]), 'type'));
                }
            }
        } elseif (!$require_subscription && !$this->subscription->isEmpty()) {
            $messages->appendMessage(new Message(gettext('Subscription cannot be set for non-subscription firmware mirror'), 'subscription'));
        }

        return $messages;
    }
}
