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
namespace OPNsense\IDS;

use OPNsense\Base\BaseModel;

class IDS extends BaseModel
{
    /**
     * @var array internal list of all sid's in this object
     */
    private $sid_list = array();

    /**
     * update internal cache of sid's
     */
    private function updateSIDlist()
    {
        if (count($this->sid_list) == 0) {
            foreach ($this->rules->rule->__items as $NodeKey => $NodeValue) {
                $this->sid_list[$NodeValue->sid->__toString()] = $NodeValue;
            }
        }
    }

    /**
     * get new or existing rule
     * @param $sid
     * @return mixed
     */
    private function getRule($sid)
    {
        $this->updateSIDlist();
        if (!array_key_exists($sid, $this->sid_list)) {
            $rule = $this->rules->rule->Add();
            $rule->sid = $sid;
            $this->sid_list[$sid] = $rule;
        }
        return $this->sid_list[$sid];
    }

    /**
     * enable rule
     * @param $sid
     */
    public function enableRule($sid)
    {
        $rule = $this->getRule($sid);
        $rule->enabled = "1";
    }

    /**
     * disable rule
     * @param $sid
     */
    public function disableRule($sid)
    {
        $rule = $this->getRule($sid);
        $rule->enabled = "0";
    }

    /**
     * remove rule by sid
     * @param $sid
     */
    public function removeRule($sid)
    {
        // search and drop rule
        foreach ($this->rules->rule->__items as $NodeKey => $NodeValue) {
            if ((string)$NodeValue->sid == $sid) {
                $this->rules->rule->Del($NodeKey);
                unset($this->sid_list[$sid]);
                break;
            }
        }
    }

    /**
     * retrieve current altered rule status
     * @param $sid
     * @param $default default value
     * @return default, 0, 1 ( default, true, false)
     */
    public function getRuleStatus($sid, $default)
    {
        $this->updateSIDlist();
        if (array_key_exists($sid, $this->sid_list)) {
            return (string)$this->sid_list[$sid]->enabled;
        } else {
            return $default;
        }

    }

    /**
     * retrieve (rule) file entry from config or add a new one
     * @param string $filename list of filename to merge into config
     * @return BaseField number of appended items
     */
    public function getFileNode($filename)
    {
        foreach ($this->files->file->__items as $NodeKey => $NodeValue) {
            if ($filename == $NodeValue->filename) {
                return $NodeValue;
            }
        }
        // add a new node
        $node = $this->files->file->Add();
        $node->filename = $filename;

        return $node ;
    }
}
