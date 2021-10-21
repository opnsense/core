<?php

/*
 * Copyright (c) 2019 Deciso B.V.
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

namespace OPNsense\Syslog\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * {@inheritdoc}
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Syslog\Syslog';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceTemplate = 'OPNsense/Syslog';
    protected static $internalServiceName = 'syslog';

    protected function reconfigureForceRestart()
    {
        return 0;
    }

    /**
     * fetch syslog-ng statistics
     * @return array of stat records
     */
    public function statsAction()
    {
        $this->sessionClose();
        // transform stats data to recordset
        $destinations = array();
        foreach ($this->getModel()->destinations->destination->iterateItems() as $destid => $dest) {
            $destinations["d_" . str_replace('-', '', (string)$destid)] = array(
                "uuid" => $destid,
                "description" => (string)$dest->description
            );
        }
        $stats = trim((new Backend())->configdRun('syslog stats'));
        $fieldnames = array();
        $records = array();
        foreach (explode("\n", $stats) as $line) {
            $parts = explode(";", $line);
            if (empty($fieldnames)) {
                foreach ($parts as $item) {
                    $fieldnames[] = $item;
                }
            } else {
                $record = array('Description' => '');
                for ($i = 0; $i < count($fieldnames); $i++) {
                    $record[$fieldnames[$i]] = $parts[$i];
                }
                if (!empty($record['SourceId'])) {
                    $id = explode('#', $record['SourceId'])[0];
                    if (!empty($destinations[$id])) {
                        $record['Description'] = $destinations[$id]['description'];
                    }
                }
                $records[md5($line)] = $record;
            }
        }

        // handle query if specified
        $itemsPerPage = intval($this->request->getPost('rowCount', 'int', 9999));
        $currentPage = intval($this->request->getPost('current', 'int', 1));
        $offset = ($currentPage - 1) * $itemsPerPage;
        $entry_keys = array_keys($records);
        if ($this->request->hasPost('searchPhrase') && $this->request->getPost('searchPhrase') !== '') {
            $searchPhrase = $this->request->getPost('searchPhrase');
            $entry_keys = array_filter($entry_keys, function ($key) use ($searchPhrase, $records) {
                foreach ($records[$key] as $itemval) {
                    if (strpos($itemval, $searchPhrase) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }
        $formatted = array_map(function ($value) use (&$records) {
            $item = ['#' => $value];
            foreach ($records[$value] as $ekey => $evalue) {
                $item[$ekey] = $evalue;
            }
            return $item;
        }, array_slice($entry_keys, $offset, $itemsPerPage));

        if ($this->request->hasPost('sort') && is_array($this->request->getPost('sort'))) {
            $keys = array_keys($this->request->getPost('sort'));
            $order = $this->request->getPost('sort')[$keys[0]];
            $keys = array_column($formatted, $keys[0]);
            array_multisort($keys, $order == 'asc' ? SORT_ASC : SORT_DESC, $formatted);
        }

        return [
           'total' => count($entry_keys),
           'rowCount' => $itemsPerPage,
           'current' => $currentPage,
           'rows' => $formatted,
        ];
    }
}
