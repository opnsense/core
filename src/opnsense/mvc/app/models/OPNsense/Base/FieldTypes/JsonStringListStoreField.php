<?php

/**
 *    Copyright (C) 2015-2019 Deciso B.V.
 *    Copyright (C) 2020 Fabian Franz
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

namespace OPNsense\Base\FieldTypes;


use OPNsense\Core\Backend;

class JsonStringListStoreField extends JsonKeyValueStoreField
{

    /**
     * populate selection data
     */
    protected function actionPostLoadingEvent()
    {
        if ($this->internalSourceFile != null) {
            if ($this->internalSourceField != null) {
                $sourcefile = sprintf($this->internalSourceFile, $this->internalSourceField);
            } else {
                $sourcefile = $this->internalSourceFile;
            }
            if (!empty($this->internalConfigdPopulateAct)) {
                // execute configd action when provided
                if (!is_file($sourcefile)) {
                    $muttime = 0;
                } else {
                    $stat = stat($sourcefile);
                    // ignore empty files
                    $muttime = $stat['size'] == 0 ? 0 : $stat['mtime'];
                }
                if (time() - $muttime > $this->internalConfigdPopulateTTL) {
                    $act = $this->internalConfigdPopulateAct;
                    $backend = new Backend();
                    $response = $backend->configdRun($act, false, 20);
                    if (!empty($response) && json_decode($response) !== null) {
                        // only store parsable results
                        file_put_contents($sourcefile, $response);
                    }
                }
            }
            if (is_file($sourcefile)) {
                $data = json_decode(file_get_contents($sourcefile), false);
                if ($data != null) {
                    $this->internalOptionList = $this->convert_to_hash($data);
                    if ($this->internalSelectAll && $this->internalValue == "") {
                        $this->internalValue = implode(',', $data);
                    }
                }
            }
        }
    }

    private function convert_to_hash($elements) {
        $arr = array();
        foreach ($elements as $element) {
            $arr[$element] = $element;
        }
        return $arr;
    }

}
