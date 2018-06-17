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

use \OPNsense\Base\ApiMutableModelControllerBase;

/**
 * @package OPNsense\Firewall
 */
class AliasController extends ApiMutableModelControllerBase
{

    static protected $internalModelName = 'alias';
    static protected $internalModelClass = 'OPNsense\Firewall\Alias';

    /**
     * search aliases
     * @return array search results
     * @throws \ReflectionException
     */
    public function searchItemAction()
    {
        return $this->searchBase(
            "aliases.alias",
            array('enabled', 'name', 'description'),
            "description"
        );
    }

    /**
     * Update alias with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setItemAction($uuid)
    {
        return $this->setBase("alias", "aliases.alias", $uuid);
    }

    /**
     * Add new alias and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException
     */
    public function addItemAction()
    {
        return $this->addBase("alias", "aliases.alias");
    }

    /**
     * Retrieve alias settings or return defaults for new one
     * @param $uuid item unique id
     * @return array alias content
     * @throws \ReflectionException when not bound to model
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase("alias", "aliases.alias", $uuid);
    }

    /**
     * Delete alias by uuid, save contents to tmp for removal on apply
     * @param string $uuid internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delItemAction($uuid)
    {
        return $this->delBase("aliases.alias", $uuid);
    }

    /**
     * toggle status
     * @param string $uuid id to toggled
     * @param string|null $disabled set disabled by default
     * @return array status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleItemAction($uuid, $disabled = null)
    {
        return $this->toggleBase("aliases.aliases", $uuid);
    }
}
