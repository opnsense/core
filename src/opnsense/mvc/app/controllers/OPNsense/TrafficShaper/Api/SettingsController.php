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
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for the Traffic Shaper
 * @package OPNsense\Proxy
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve pipe settings
     * @param $uuid item unique id
     * @return array
     */
    public function getPipeAction($uuid)
    {
        if ($uuid != null) {
            $mdlShaper = new TrafficShaper();
            $node = $mdlShaper->getNodeByReference('pipes.pipe.'.$uuid);
            if ($node != null) {
                return $node->getNodes();
            }
        }
        return array();
    }

    /**
     * search traffic shaper pipes
     * @return array
     */
    public function searchPipesAction()
    {
        if ($this->request->isPost()) {
            // fetch query parameters
            $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
            $currentPage = $this->request->getPost('current', 'int', 1);
            $sortBy = array("number");
            $sortDescending = false;

            if ($this->request->hasPost('sort') && is_array($this->request->getPost("sort"))) {
                $sortBy = array_keys($this->request->getPost("sort"));
                if ($this->request->getPost("sort")[$sortBy[0]] == "desc") {
                    $sortDescending = true;
                }
            }

            $searchPhrase = $this->request->getPost('searchPhrase', 'string', '');

            // create model and fetch query resuls
            $fields = array("number", "bandwidth","bandwidthMetric","description");
            $mdlShaper = new TrafficShaper();
            $grid = new UIModelGrid($mdlShaper->pipes->pipe);
            return $grid->fetch($fields, $itemsPerPage, $currentPage, $sortBy, $sortDescending, $searchPhrase);
        } else {
            return array();
        }

    }
}
