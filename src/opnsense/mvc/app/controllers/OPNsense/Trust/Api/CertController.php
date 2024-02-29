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

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Config;
use OPNsense\Trust\Store as CertStore;

/**
 * Class CertController
 * @package OPNsense\Trust\Api
 */
class CertController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'cert';
    protected static $internalModelClass = 'OPNsense\Trust\Cert';


    protected function setBaseHook($node)
    {
        if (empty((string)$node->refid)) {
            $node->refid = uniqid();
        }
        throw new UserException((string)$node->refid, (string)$node->action);
    }

    public function searchAction()
    {
        $carefs = $this->request->get('carefs');
        $filter_funct = function ($record) use ($carefs) {
            return empty($carefs) || array_intersect(explode(',', $record->caref), $carefs);
        };
        return $this->searchBase('cert', ['descr', 'caref', 'name', 'valid_from', 'valid_to'], null, $filter_funct);
    }

    public function getAction($uuid = null)
    {
        return $this->getBase('cert', 'cert', $uuid);
    }
    public function addAction()
    {
        return $this->addBase('cert', 'cert');
    }
    public function setAction($uuid = null)
    {
        return $this->setBase('cert', 'cert', $uuid);
    }
    public function delAction($uuid)
    {
        return $this->delBase('cert', $uuid);
    }
    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('cert', $uuid, $enabled);
    }

    public function caInfoAction($caref)
    {
        if ($this->request->isGet()) {
            $ca = CertStore::getCACertificate($caref);
            if ($ca) {
                $payload = CertStore::parseX509($ca['cert']);
                if ($payload) {
                    return $payload;
                }
            }
        }
        return [];
    }

    public function caListAction()
    {
        $result = [];
        if ($this->request->isGet()) {
            $result['rows'] = [];
            if (isset(Config::getInstance()->object()->ca)) {
                foreach (Config::getInstance()->object()->ca as $cert) {
                    if (isset($cert->refid)) {
                        $result['rows'][] = [
                            'caref' => (string)$cert->refid,
                            'descr' => (string)$cert->descr
                        ];
                    }
                }
            }
            $result['count'] = count($result['rows']);
        }
        return $result;
    }
}
