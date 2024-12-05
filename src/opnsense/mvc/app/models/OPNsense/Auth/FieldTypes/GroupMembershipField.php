<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Auth\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Config;

class GroupMembershipField extends BaseListField
{
    protected $internalIsContainer = false;
    private static $groups = null;
    private static $memberList = null;

    protected function actionPostLoadingEvent()
    {
        $this_uid = (string)$this->getParentNode()->uid;
        if (self::$groups === null) {
            self::$groups = [];
            self::$memberList = [];
            foreach (Config::getInstance()->object()->system->children() as $tag => $node) {
                if ($tag == 'group') {
                    self::$groups[(string)$node->gid] = (string)$node->name;
                    foreach ($node->member as $value) {
                        foreach (explode(',', $value) as $uid) {
                            if (!isset(self::$memberList[$uid])) {
                                self::$memberList[$uid] = [];
                            }
                            self::$memberList[$uid][] = (string)$node->gid;
                        }
                    }
                }
            }
        }
        $this->internalOptionList = self::$groups;
        if (isset(self::$memberList[$this_uid])) {
            $this->internalValue = implode(',', self::$memberList[$this_uid]);
        }
    }
}
