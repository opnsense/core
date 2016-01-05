<?php
/**
 *    Copyright (C) 2016 Deciso B.V.
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


namespace OPNsense\Diagnostics\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Core\Backend;

/**
 * Class InterfaceController
 * @package OPNsense\SystemHealth
 */
class InterfaceController extends ApiControllerBase
{
    /**
     * retrieve system arp table contents
     * @return array
     */
    public function getArpAction()
    {
        $backend = new Backend();
        $response = $backend->configdpRun("interface list arp json");
        $arptable = json_decode($response, true);

        // collect interface names
        $intfmap = array();
        $config = Config::getInstance()->object() ;
        if ($config->interfaces != null) {
            foreach ($config->interfaces->children() as $key => $node) {
                $intfmap[(string)$node->if] = !empty((string)$node->descr) ? (string)$node->descr : $key ;
            }
        }
        // merge arp output with interface names
        if (is_array($arptable)) {
            foreach ($arptable as &$arpentry) {
                if (array_key_exists($arpentry['intf'], $intfmap)) {
                    $arpentry['intf_description'] = $intfmap[$arpentry['intf']];
                } else {
                    $arpentry['intf_description'] = "";
                }
            }
        }

        return $arptable;
    }
}
