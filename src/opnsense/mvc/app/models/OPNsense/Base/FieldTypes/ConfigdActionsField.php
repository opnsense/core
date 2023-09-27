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
 * Class ConfigdActionsField list configurable configd actions
 * @package OPNsense\Base\FieldTypes
 */
class ConfigdActionsField extends BaseListField
{
    /**
     * @var array collected options
     */
    private static $internalStaticOptionList = array();

    /**
     * @var array filters to use on the configd selection
     */
    private $internalFilters = array();

    /**
     * @var string key to use for option selections, to prevent excessive reloading
     */
    private $internalCacheKey = '*';

    /**
     * generate validation data (list of known configd actions)
     */
    protected function actionPostLoadingEvent()
    {
        if (!isset(self::$internalStaticOptionList[$this->internalCacheKey])) {
            self::$internalStaticOptionList[$this->internalCacheKey] = array();

            $backend = new Backend();
            $service_tempfile = "/tmp/configdmodelfield.data";

            // check configd daemon for list of available actions, cache results as long as configd is not restarted
            if (!file_exists($service_tempfile) || filemtime($service_tempfile) < $backend->getLastRestart()) {
                $response = $backend->configdRun("configd actions json", false, 20);
                $actions = json_decode($response, true);
                if (is_array($actions)) {
                    file_put_contents($service_tempfile, $response);
                } else {
                    $actions = array();
                }
            } else {
                $actions = json_decode(file_get_contents($service_tempfile), true);
                if (!is_array($actions)) {
                    $actions = array();
                }
            }

            foreach ($actions as $key => $value) {
                // use filters to determine relevance
                $isMatched = true;
                foreach ($this->internalFilters as $filterKey => $filterData) {
                    if (isset($value[$filterKey])) {
                        $fieldData = $value[$filterKey];
                        if (!preg_match($filterData, $fieldData)) {
                            $isMatched = false;
                        }
                    }
                }
                if ($isMatched) {
                    if (!isset($value['description']) || $value['description'] == '') {
                        self::$internalStaticOptionList[$this->internalCacheKey][$key] = $key;
                    } else {
                        self::$internalStaticOptionList[$this->internalCacheKey][$key] = $value['description'];
                    }
                }
            }
            natcasesort(self::$internalStaticOptionList[$this->internalCacheKey]);
        }
        $this->internalOptionList = self::$internalStaticOptionList[$this->internalCacheKey];
    }

    /**
     * set filters to use (in regex) per field, all tags are combined
     * and cached for the next object using the same filters
     * @param $filters filters to use
     */
    public function setFilters($filters)
    {
        if (is_array($filters)) {
            $this->internalFilters = $filters;
            $this->internalCacheKey = md5(serialize($this->internalFilters));
        }
    }
}
