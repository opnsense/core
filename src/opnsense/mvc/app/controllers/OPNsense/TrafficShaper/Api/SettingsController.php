<?php

/**
 *    Copyright (C) 2015-2017 Deciso B.V.
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

namespace OPNsense\TrafficShaper\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\TrafficShaper\TrafficShaper;
use OPNsense\Core\Config;

/**
 * Class SettingsController Handles settings related API actions for the Traffic Shaper
 * @package OPNsense\TrafficShaper
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ts';
    protected static $internalModelClass = '\OPNsense\TrafficShaper\TrafficShaper';
    protected static $internalModelUseSafeDelete = true;

    /**
     * Retrieve pipe settings or return defaults
     * @param $uuid item unique id
     * @return array traffic shaper pipe content
     * @throws \ReflectionException when not bound to model
     */
    public function getPipeAction($uuid = null)
    {
        return $this->getBase("pipe", "pipes.pipe", $uuid);
    }

    /**
     * Update  pipe with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setPipeAction($uuid)
    {
        return $this->setBase("pipe", "pipes.pipe", $uuid);
    }

    /**
     * Add new pipe and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \OPNsense\Base\ValidationException when field validations fail
     */
    public function addPipeAction()
    {
        return $this->addBase("pipe", "pipes.pipe", [
            "origin" => "TrafficShaper",
            "number" => (new TrafficShaper())->newPipeNumber()
        ]);
    }

    /**
     * Delete pipe by uuid
     * @param string $uuid internal id
     * @return array save status
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delPipeAction($uuid)
    {
        return  $this->delBase("pipes.pipe", $uuid);
    }


    /**
     * Toggle pipe defined by uuid (enable/disable)
     * @param $uuid user defined rule internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function togglePipeAction($uuid, $enabled = null)
    {
        return $this->toggleBase("pipes.pipe", $uuid, $enabled);
    }

    /**
     * Search traffic shaper pipes
     * @return array list of found pipes
     * @throws \ReflectionException when not bound to model
     */
    public function searchPipesAction()
    {
        return $this->searchBase("pipes.pipe", null, "number");
    }


    /**
     * Search traffic shaper queues
     * @return array list of found queues
     * @throws \ReflectionException when not bound to model
     */
    public function searchQueuesAction()
    {
        return $this->searchBase("queues.queue", null, "number");
    }

    /**
     * Retrieve queue settings or return defaults
     * @param $uuid item unique id
     * @return array traffic shaper queue content
     * @throws \ReflectionException when not bound to model
     */
    public function getQueueAction($uuid = null)
    {
        return $this->getBase("queue", "queues.queue", $uuid);
    }

    /**
     * Update queue with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setQueueAction($uuid)
    {
        return $this->setBase("queue", "queues.queue", $uuid);
    }

    /**
     * Add new queue and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     */
    public function addQueueAction()
    {
        return $this->addBase("queue", "queues.queue", [
            "origin" => "TrafficShaper",
            "number" => (new TrafficShaper())->newQueueNumber()
        ]);
    }
    /**
     * Delete queue by uuid
     * @param string $uuid internal id
     * @return array save status
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delQueueAction($uuid)
    {
        return  $this->delBase("queues.queue", $uuid);
    }

    /**
     * Toggle queue defined by uuid (enable/disable)
     * @param $uuid user defined rule internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleQueueAction($uuid, $enabled = null)
    {
        return $this->toggleBase("queues.queue", $uuid, $enabled);
    }


    /**
     * Search traffic shaper rules
     * @return array list of found rules
     * @throws \ReflectionException when not bound to model
     */
    public function searchRulesAction()
    {
        return $this->searchBase("rules.rule", null, "sequence");
    }

    /**
     * Retrieve rule settings or return defaults for new rule
     * @param $uuid item unique id
     * @return array traffic shaper rule content
     * @throws \ReflectionException when not bound to model
     */
    public function getRuleAction($uuid = null)
    {
        $fetchmode = $this->request->has("fetchmode") ? $this->request->get("fetchmode") : null;
        $result = $this->getBase("rule", "rules.rule", $uuid);
        if ($uuid === null || $fetchmode == 'copy') {
            $result["rule"]["sequence"] = (string)((new TrafficShaper())->getMaxRuleSequence() + 1);
        }
        return $result;
    }

    /**
     * Update rule with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setRuleAction($uuid)
    {
        return $this->setBase("rule", "rules.rule", $uuid);
    }

    /**
     * Add new rule and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \OPNsense\Base\ValidationException when field validations fail
     */
    public function addRuleAction()
    {
        return $this->addBase('rule', 'rules.rule', [ "origin" => "TrafficShaper"]);
    }
    /**
     * Delete rule by uuid
     * @param string $uuid internal id
     * @return array save status
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delRuleAction($uuid)
    {
        return  $this->delBase("rules.rule", $uuid);
    }

    /**
     * Toggle rule defined by uuid (enable/disable)
     * @param $uuid user defined rule internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \OPNsense\Base\ValidationException when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("rules.rule", $uuid, $enabled);
    }
}
