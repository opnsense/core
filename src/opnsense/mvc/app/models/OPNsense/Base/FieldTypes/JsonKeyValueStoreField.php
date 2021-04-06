<?php

/*
 * Copyright (C) 2015-2019 Deciso B.V.
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

namespace OPNsense\Base\FieldTypes;

use OPNsense\Core\Backend;

/**
 * Class JsonKeyValueStoreField, use a json encoded file as selection list
 * @package OPNsense\Base\FieldTypes
 */
class JsonKeyValueStoreField extends BaseListField
{
    /**
     * @var null source field
     */
    private $internalSourceField = null;

    /**
     * @var null source file pattern
     */
    private $internalSourceFile = null;

    /**
     * @var bool automatically select all when none is selected
     */
    private $internalSelectAll = false;

    /**
     * @var string action to send to configd to populate the provided source
     */
    private $internalConfigdPopulateAct = "";

    /**
     * @var int execute configd command only when file is older then TTL (seconds)
     */
    private $internalConfigdPopulateTTL = 3600;

    /**
     * @var bool sort by value (default is by key)
     */
     private $internalSortByValue = false;

    /**
     * @param string $value source field, pattern for source file
     */
    public function setSourceField($value)
    {
        $this->internalSourceField = basename($this->internalParentNode->$value);
    }

    /**
     * @param string $value optionlist content to use
     */
    public function setSourceFile($value)
    {
        $this->internalSourceFile = $value;
    }

    /**
     * @param string $value automatically select all when none is selected
     */
    public function setSelectAll($value)
    {
        if (strtoupper(trim($value)) == 'Y') {
            $this->internalSelectAll = true;
        } else {
            $this->internalSelectAll = false;
        }
    }

    /**
     * @param string $value configd action to run
     */
    public function setConfigdPopulateAct($value)
    {
        $this->internalConfigdPopulateAct = $value;
    }

    /**
     * @param string $value set TTL for config action
     */
    public function setConfigdPopulateTTL($value)
    {
        if (is_numeric($value)) {
            $this->internalConfigdPopulateTTL = $value;
        }
    }

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
                if (is_file($sourcefile)) {
                    $sourcehandle = fopen($sourcefile, "r+");
                } else {
                    $sourcehandle = fopen($sourcefile, "w");
                }
                if (flock($sourcehandle, LOCK_EX)) {
                    // execute configd action when provided
                    $stat = fstat($sourcehandle);
                    $muttime = $stat['size'] == 0 ? 0 : $stat['mtime'];
                    if (time() - $muttime > $this->internalConfigdPopulateTTL) {
                        $act = $this->internalConfigdPopulateAct;
                        $backend = new Backend();
                        $response = $backend->configdRun($act, false, 20);
                        if (!empty($response) && json_decode($response) !== null) {
                            // only store parsable results
                            fseek($sourcehandle, 0);
                            ftruncate($sourcehandle, 0);
                            fwrite($sourcehandle, $response);
                            fflush($sourcehandle);
                        }
                    }
                }
                flock($sourcehandle, LOCK_UN);
                fclose($sourcehandle);
            }
            if (is_file($sourcefile)) {
                $data = json_decode(file_get_contents($sourcefile), true);
                if ($data != null) {
                    $this->internalOptionList = $data;
                    if ($this->internalSelectAll && $this->internalValue == "") {
                        $this->internalValue = implode(',', array_keys($this->internalOptionList));
                    }
                }
            }
        }
    }

    /**
     * change default sorting order (value vs key)
     * @param $value boolean value Y/N
     */
    public function setSortByValue($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalSortByValue = true;
        } else {
            $this->internalSortByValue = false;
        }
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        // set sorting by key (default) or value
        if ($this->internalSortByValue) {
            natcasesort($this->internalOptionList);
        } else {
            ksort($this->internalOptionList);
        }
        return parent::getNodeData();
    }

}
