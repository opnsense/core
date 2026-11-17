<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\ContainerField;

class ScheduleRangeContainerField extends ContainerField
{
    private function publicFields($values, $prefix = '')
    {
        foreach (['month', 'day', 'hour'] as $field) {
            unset($values[$prefix . $field]);
        }
        return $values;
    }

    public function getNodes()
    {
        return $this->publicFields(parent::getNodes());
    }

    public function getNodeContent()
    {
        return $this->publicFields(parent::getNodeContent());
    }

    public function getFlatNodes()
    {
        return $this->publicFields(parent::getFlatNodes(), $this->__reference . '.');
    }

    public function setNodes($values)
    {
        parent::setNodes($this->publicFields($values));
    }
}

class ScheduleRangeField extends ArrayField
{
    public function newContainerField($ref, $tagname)
    {
        $node = new ScheduleRangeContainerField($ref, $tagname);
        $parentmodel = $this->getParentModel();
        $node->setParentModel($parentmodel);
        return $node;
    }
}
