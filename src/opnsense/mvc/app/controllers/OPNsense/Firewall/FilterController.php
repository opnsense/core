<?php

/*
 * Copyright (C) 2020-2025 Deciso B.V.
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
namespace OPNsense\Firewall;

class FilterController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/Firewall/filter_rule');
        $this->view->formDialogFilterRule = $this->getForm("dialogFilterRule");
        $this->view->formGridFilterRule = $this->getFormGrid('dialogFilterRule');
        $this->view->advancedFieldIds = $this->getAdvancedIds($this->view->formDialogFilterRule);
    }

    /**
     * Get an array of field IDs that have the advanced flag set to "true".
     *
     * @param array $form An array of field definitions
     * @return string list of fieldnames, comma separated for easy template usage
     */
    protected function getAdvancedIds($form)
    {
        $advancedFieldIds = [];
        $exclude = ['sequence', 'sort_order'];

        foreach ($form as $field) {
            if (!empty($field['advanced']) && $field['advanced'] == "true") {
                if (!empty($field['id'])) {
                    $tmp = explode('.', $field['id']);
                    $fieldId = $tmp[count($tmp) - 1];
                    if (!in_array($fieldId, $exclude)) {
                        $advancedFieldIds[] = $fieldId;
                    }
                }
            }
        }

        return implode(',', $advancedFieldIds);
    }
}
