<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\Base;

/**
 * Class UIModelGrid Grid control support functions
 * @package OPNsense\Base
 */
class UIModelGrid
{
    /**
     * @var null|FieldTypes\ArrayField Data provider to link Grid support functions to.
     */
    private $DataField = null;

    /**
     * construct a new UIModelGrid
     * @param FieldTypes\ArrayField $DataField
     */
    public function __construct($DataField)
    {
        $this->DataField = $DataField;
    }

    /**
     * default model search
     * @param $request request variable
     * @param array $fields to collect
     * @param null|string $defaultSort default sort order
     * @param null|function $filter_funct additional filter callable
     * @param int $sort_flags sorting behavior
     * @return array
     */
    public function fetchBindRequest(
        $request,
        $fields,
        $defaultSort = null,
        $filter_funct = null,
        $sort_flags = SORT_NATURAL
    ) {
        $itemsPerPage = $request->get('rowCount', 'int', -1);
        $currentPage = $request->get('current', 'int', 1);
        $sortBy = empty($defaultSort) ? array() : array($defaultSort);
        $sortDescending = false;

        if ($request->has('sort') && is_array($request->get("sort"))) {
            $sortBy = array_keys($request->get("sort"));
            if ($request->get("sort")[$sortBy[0]] == "desc") {
                $sortDescending = true;
            }
        }

        $searchPhrase = $request->get('searchPhrase', 'string', '');
        return $this->fetch(
            $fields,
            $itemsPerPage,
            $currentPage,
            $sortBy,
            $sortDescending,
            $searchPhrase,
            $filter_funct,
            $sort_flags
        );
    }

    /**
     * Fetch data from Array type field (Base\FieldTypes\ArrayField), sorted by specified fields and optionally filtered
     * @param array $fields select fieldnames
     * @param int $itemsPerPage number of items per page
     * @param int $currentPage current selected page
     * @param array $sortBy sort by fieldnames
     * @param bool $sortDescending sort in descending order
     * @param string $searchPhrase search phrase to use
     * @param null|function $filter_funct additional filter callable
     * @param int $sort_flags sorting behavior
     * @return array
     */
    public function fetch(
        $fields,
        $itemsPerPage,
        $currentPage,
        $sortBy = array(),
        $sortDescending = false,
        $searchPhrase = '',
        $filter_funct = null,
        $sort_flags = SORT_NATURAL
    ) {
        $result = array('rows' => array());

        $recordIndex = 0;
        foreach ($this->DataField->sortedBy($sortBy, $sortDescending, $sort_flags) as $record) {
            if (array_key_exists("uuid", $record->getAttributes())) {
                if (is_callable($filter_funct) && !$filter_funct($record)) {
                    // not applicable according to $filter_funct()
                    continue;
                }

                // parse rows, because we may need to convert some (list) items we need to know the actual content
                // before searching.
                $row =  array();
                $row['uuid'] = $record->getAttributes()['uuid'];
                foreach ($fields as $fieldname) {
                    if ($record->$fieldname != null) {
                        $row[$fieldname] = $record->$fieldname->getNodeData();
                        if (is_array($row[$fieldname])) {
                            $listItems = $row[$fieldname];
                            $row[$fieldname] = '';
                            foreach ($listItems as $fieldValue) {
                                if ($fieldValue['selected'] == 1) {
                                    if ($row[$fieldname] != "") {
                                        $row[$fieldname] .= ',';
                                    }
                                    $row[$fieldname] .= $fieldValue['value'];
                                }
                            }
                            if ($row[$fieldname] === null) {
                                $row[$fieldname] = "";
                            }
                        }
                    }
                }

                // if a search phrase is provided, use it to search in all requested fields
                if ($searchPhrase != '') {
                    $searchFound = false;
                    foreach ($fields as $fieldname) {
                        if (
                            isset($row[$fieldname]) &&
                            strpos(strtolower($row[$fieldname]), strtolower($searchPhrase)) !== false
                        ) {
                            $searchFound = true;
                            break;
                        }
                    }
                } else {
                    $searchFound = true;
                }

                // if result is relevant, count total and add (max number of) items to result.
                // $itemsPerPage = -1 is used as wildcard for "all results"
                if ($searchFound) {
                    if (
                        (count($result['rows']) < $itemsPerPage &&
                        $recordIndex >= ($itemsPerPage * ($currentPage - 1)) || $itemsPerPage == -1)
                    ) {
                        $result['rows'][] = $row;
                    }
                    $recordIndex++;
                }
            }
        }

        $result['rowCount'] = count($result['rows']);
        $result['total'] = $recordIndex;
        $result['current'] = (int)$currentPage;

        return $result;
    }
}
