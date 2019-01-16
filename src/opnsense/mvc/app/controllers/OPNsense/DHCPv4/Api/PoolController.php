<?php
/**
 *    Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\DHCPv4\Api;

use \OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

/**
 * @package OPNsense\DHCPv4
 */
class PoolController extends ApiControllerBase
{
    const FIELDS = [
        'name',
        'mystate',
        'mydate',
        'peerstate',
        'peerdate',
    ];

    public function searchItemAction()
    {
        $itemsPerPage = (int)$this->request->getPost('rowCount', 'int', 9999);
        $currentPage = (int)$this->request->getPost('current', 'int', 1);

        $backend = new Backend();
        $pools = json_decode($backend->configdRun('dhcpd process pools'), true);
        $sortKey = 'ip';
        $reverse = false;
        if ($this->request->hasPost('sort') &&
            is_array($this->request->getPost('sort'))) {
            $sortArray = $this->request->getPost('sort');
            $reverse = (reset($sortArray) == 'desc');
            $sortKey = key($sortArray);
            if (!in_array($sortKey, self::FIELDS)) {
                $sortKey = 'name';
            }
        }

        usort($pools,
            function ($a, $b) use ($sortKey, $reverse) {
                $cmp = strnatcasecmp($a[$sortKey], $b[$sortKey]);
                if ($cmp === 0) {
                    $cmp = strnatcasecmp($a['name'], $b['name']);
                }
                if (!$reverse) {
                    return $cmp;
                } else {
                    return $cmp * -1;
                }
            }
        );

        $offset = ($currentPage - 1) * $itemsPerPage;
        $selectedPools = array_slice($pools, $offset, $itemsPerPage);
        return [
            'rows' => $selectedPools,
            'rowCount' => $itemsPerPage,
            'total' => count($pools),
            'current' => $currentPage,
            'sortKey' => $sortKey,
            'reverse' => $reverse,
        ];
    }
}
