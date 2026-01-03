<?php

/*
 * Copyright (C) 2015-2025 Deciso B.V.
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

/**
 * Class AuthenticationServerField field type to select usable authentication servers,
 * currently this is kind of a backward compatibility package to glue legacy authentication servers into the model.
 * The concept of authentication servers is not likely to change in the near future.
 * @package OPNsense\Base\FieldTypes
 */
class AuthenticationServerField extends BaseListField
{
    /**
     * @var array collected options
     */
    private static $internalStaticOptionList = [];

    /**
     * @var array filters to use on the authservers list
     */
    private array $internalFilters = [];

    /**
     * @var string optionally pass service we're requesting our servers for
     */
    private string $internalService = '';

    /**
     * @var string key to use for option selections, to prevent excessive reloading
     */
    private $internalCacheKey = '*';

    /**
     * generate validation data (list of AuthServers)
     */
    protected function actionPostLoadingEvent()
    {
        if (!isset(self::$internalStaticOptionList[$this->internalCacheKey])) {
            self::$internalStaticOptionList[$this->internalCacheKey] = [];

            $authFactory = new \OPNsense\Auth\AuthenticationFactory();
            $allAuthServers = $authFactory->listServers($this->internalService);

            foreach ($allAuthServers as $key => $value) {
                // use filters to determine relevance
                $isMatched = true;
                foreach ($this->internalFilters as $filterKey => $filterData) {
                    if (isset($value[$filterKey])) {
                        $fieldData = $value[$filterKey];
                    } else {
                        // not found, might be a boolean.
                        $fieldData = "0";
                    }

                    if (!preg_match($filterData, $fieldData)) {
                        $isMatched = false;
                    }
                }
                if ($isMatched) {
                    self::$internalStaticOptionList[$this->internalCacheKey][$key] = $value['name'];
                }
            }
            natcasesort(self::$internalStaticOptionList[$this->internalCacheKey]);
        }
        $this->internalOptionList = self::$internalStaticOptionList[$this->internalCacheKey];
    }

    /**
     * set filters to use (in regex) per field, all tags are combined
     * and cached for the next object using the same filters
     * @param array $filters filters to use
     */
    public function setFilters($filters)
    {
        if (is_array($filters)) {
            $this->internalFilters = $filters;
            $this->internalCacheKey = md5(serialize($this->internalFilters) . $this->internalService);
        }
    }

    /**
     * set service type to limit results too
     * @param string $service service name
     */
    public function setService($service)
    {
        if (is_string($service)) {
            $this->internalService = $service;
            $this->internalCacheKey = md5(serialize($this->internalFilters) . $this->internalService);
        }
    }
}
