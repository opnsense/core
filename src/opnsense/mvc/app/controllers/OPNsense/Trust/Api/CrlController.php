<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Trust\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Config;
use OPNsense\Trust\Store as CertStore;

/**
 * Class CrlController
 * @package OPNsense\Trust\Api
 */
class CrlController extends ApiControllerBase
{
    public function searchAction()
    {
        $this->sessionClose();
        $config = Config::getInstance()->object();
        $items = [];
        foreach ($config->ca as $node) {
            $items[(string)$node->refid] =  ['descr' => (string)$node->descr, 'refid' =>  (string)$node->refid];
        }
        foreach ($config->crl as $node) {
            if (isset($items[(string)$node->caref])) {
                $items[(string)$node->caref]['crl_descr'] = (string)$node->descr;
            }
        }
        return $this->searchRecordsetBase(array_values($items));
    }

    public function getAction($caref)
    {
        if ($this->request->isGet() && !empty($caref)) {
            $config = Config::getInstance()->object();
            $found = false;
            foreach ($config->ca as $node) {
                if ((string)$node->refid == $caref) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $result = ['caref' => $caref];
                foreach ($config->crl as $node) {
                    if ((string)$node->caref == $caref) {
                        $result['descr'] = (string)$node->descr;
                    }
                }
                $certs = [];
                foreach ($config->cert as $node) {
                    if ((string)$node->caref == $caref) {
                        $certs[(string)$node->refid] = (string)255;
                    }
                }
                foreach ($config->crl as $node) {
                    if ((string)$node->caref == $caref) {
                        foreach ($node->cert as $cert) {
                            if (!empty((string)$cert->refid)) {
                                $certs[(string)$cert->refid] = (string)$cert->reason;
                            }
                        }
                    }
                }

                return ['crl' => $result, 'certs' => $certs];
            }
        }
    }
}
