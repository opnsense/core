<?php
/**
 *    Copyright (C) 2016 IT-assistans Sverige AB
 *    Copyright (C) 2016 Deciso B.V.
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
namespace OPNsense\Base;


/**
 * Class ApiMutableTableModelControllerBase, inherit this class to implement
 * an API that exposes a model for tables.
 * @package OPNsense\Base
 */
abstract class ApiMutableTableModelControllerBase extends ApiControllerBase
{
    static protected $modelPathPrefix = '';
    private function getNodes() {
        $ref = static::$modelPathPrefix
             + static::$internalModelName;
        return $this->getModel()->getNodeByReference($ref);
    }
    private function getNodeByUUID($uuid) {
        return $this->getNodes()->$uuid;
    }
    public function getItemAction($uuid = null) {
        $mdl = $this->getModel();
        if ($uuid != null) {
            $node = getNodeByUUID($uuid);
            if ($node != null) {
                // return node
                return array(static::$internalModelName => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = getNodes()->add();
            return array(static::$internalModelName => $node->getNodes());
        }
        return array();
    }
    public function setItemAction($uuid) {
        // FIXME To be implemented
    }
    public function addItemAction() {
        // FIXME To be implemented
    }
    public function delItemAction($uuid) {
        // FIXME To be implemented
    }
    public function toggleItemAction($uuid, $enabled = null) {
        // FIXME To be implemented
    }
    public function searchItemsAction() {
        // FIXME To be implemented
    }
}
