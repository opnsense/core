<?php

/**
 *    Copyright (C) 2017 Smart-Soft
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

namespace OPNsense\Trust;

use \OPNsense\Base\IndexController;


/**
 * Class CertificatesController
 * @package OPNsense\Trust
 */
class CertificatesController extends IndexController
{
    /**
     * Certification index
     * @throws \Exception
     */
    public function indexAction()
    {
        $this->view->title = gettext('System') . ": " . gettext("Trust") . ": " . gettext("Certificates");
        // include dialog form definitions
        $this->view->pick('OPNsense/Trust/certificates');
        $this->view->importCert = $this->getForm("importCert");
        $this->view->internalCert = $this->getForm("internalCert");
        $this->view->externalCert = $this->getForm("externalCert");
        $this->view->csr = $this->getForm("csr");
    }


    /**
     * Page for creating user certificate
     * @param null $method
     * @param null $user_id
     * @return bool
     * @throws \Exception
     */
    public function userAction($method = null, $user_id = null)
    {
        if (!in_array($method, ["existing", "import", "internal", "external"]) || !is_int((int)$user_id))
            return false;
        $this->view->title = gettext('System') . ": " . gettext("Trust") . ": " . gettext("Certificates");
        // include dialog form definitions
        $this->view->pick('OPNsense/Trust/user');
        $this->view->setVar("user_id", $user_id);
        $this->view->setVar("method", $method);
        $this->view->userCert = $this->getForm($method . "Cert");
    }

    /**
     * List with methods for creating user certificate
     * @param null $user_id
     * @return bool
     */
    public function methodsAction($user_id = null)
    {
        if (!is_int((int)$user_id))
            return false;
        $this->view->title = gettext('System') . ": " . gettext("Trust") . ": " . gettext("Certificates");
        // include dialog form definitions
        $this->view->pick('OPNsense/Trust/methods');
        $this->view->setVar("user_id", $user_id);
    }
}
