<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
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
namespace OPNsense\TrafficShaper\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\TrafficShaper\TrafficShaper;

/**
 * Class SettingsController
 * @package OPNsense\Proxy
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve  settings
     * @return array
     */
    public function getAction()
    {
        $result = array('rows'=>array());

        for ($i=1; $i<100; $i++) {
            $result['rows'][] = array('id'=>$i,'sender'=>$i.'xyz','receiver'=>'xxx'.$i);
        }

        $result['rowCount'] = count($result['rows']);
        $result['current'] = 1;

        return $result;
    }

    /**
     * retrieve  settings
     * @return array
     */
    public function searchPipesAction()
    {
        if ($this->request->isPost()) {
            $mdlShaper = new TrafficShaper();

            // parse search parameters
            if ($this->request->hasPost('rowCount')) {
                $itemsPerPage = $this->request->getPost('rowCount');
            } else {
                $itemsPerPage = 9999;
            }
            if ($this->request->hasPost('current')) {
                $currentPage = $this->request->getPost('current');
            } else {
                $currentPage = 1;
            }

            if ($this->request->hasPost('sort')) {
                $sortBy = array_keys($this->request->getPost("sort"));
                if ($this->request->getPost("sort")[$sortBy[0]] == "desc") {
                    $sortDescending = true;
                } else {
                    $sortDescending = false;
                }
            } else {
                $sortBy = array("number");
                $sortDescending = false;
            }



            //searchPhrase
            //sort

            //$mdlShaper

            $result = array('rows'=>array());

            $fields = array("number", "bandwidth","bandwidthMetric");
            $recordIndex = 0;
            foreach ($mdlShaper->pipes->pipe->sortedBy($sortBy, $sortDescending) as $pipe) {
                if (count($result['rows']) < $itemsPerPage &&
                    $recordIndex >= ($itemsPerPage*($currentPage-1))
                ) {
                    $row =  array();
                    $row['uuid'] = $pipe->getAttributes()['uuid'];
                    foreach ($fields as $fieldname) {
                        $row[$fieldname] = $pipe->$fieldname->getNodeData();
                        if (is_array($row[$fieldname])) {
                            foreach ($row[$fieldname] as $fieldKey => $fieldValue) {
                                if ($fieldValue['selected'] == 1) {
                                    $row[$fieldname] = $fieldValue['value'];
                                }
                            }
                        }
                    }
                    $result['rows'][] = $row;
                }
                $recordIndex++;
            }


            $result['rowCount'] = count($result['rows']);
            $result['total'] = $recordIndex;
            $result['current'] = (int)$currentPage;

            return $result;
        }

    }

}
