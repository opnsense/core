<?php

/**
 *    Copyright (C) 2021 Deciso B.V.
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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * @package OPNsense\Firewall
 */
class CategoryController extends ApiMutableModelControllerBase
{

    protected static $internalModelName = 'category';
    protected static $internalModelClass = 'OPNsense\Firewall\Category';

    /**
     * search categories
     * @return array search results
     * @throws \ReflectionException
     */
    public function searchItemAction()
    {
        return $this->searchBase("categories.category", array('name', 'auto', 'color'), "name");
    }

    /**
     * Update category with given properties
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setItemAction($uuid)
    {
        $node = $this->getModel()->getNodeByReference('categories.category.' . $uuid);
        $old_name = $node != null ? (string)$node->name : null;
        if ($old_name !== null && $this->request->isPost() && $this->request->hasPost("category")) {
            $new_name = $this->request->getPost("category")['name'];
            if ($new_name != $old_name) {
                // replace categories, setBase() will synchronise the changes to disk
                $this->getModel()->refactor($old_name, $new_name);
            }
        }
        return $this->setBase("category", "categories.category", $uuid);
    }

    /**
     * Add new category and set with attributes from post
     * @return array save result + validation output
     * @throws \OPNsense\Base\ModelException when not bound to model
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function addItemAction()
    {
        return $this->addBase("category", "categories.category");
    }

    /**
     * Retrieve category settings or return defaults for new one
     * @param $uuid item unique id
     * @return array category content
     * @throws \ReflectionException when not bound to model
     */
    public function getItemAction($uuid = null)
    {
        return $this->getBase("category", "categories.category", $uuid);
    }

    /**
     * Delete alias by uuid, save contents to tmp for removal on apply
     * @param string $uuid internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     * @throws \OPNsense\Base\UserException when unable to delete
     */
    public function delItemAction($uuid)
    {
        Config::getInstance()->lock();
        $node = $this->getModel()->getNodeByReference('categories.category.' . $uuid);
        $node_name = $node != null ? (string)$node->name : null;
        if ($node_name != null && $this->getModel()->isUsed($node_name)) {
            $message = gettext("Cannot delete a category which is still in use.");
            throw new \OPNsense\Base\UserException($message, gettext("Category in use"));
        }
        return $this->delBase("categories.category", $uuid);
    }
}
