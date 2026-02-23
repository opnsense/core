<?php

/*
 * Copyright (C) 2015-2026 Deciso B.V.
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

use OPNsense\Core\Config;

/**
 * Class CertificateField field type to select certificates from the internal cert manager
 * package to glue legacy certificates into the model.
 * @package OPNsense\Base\FieldTypes
 */
class CertificateField extends BaseListField
{
    /**
     * @var string certificate type cert/ca, reflects config section to use as source
     */
    private $certificateType = 'cert';

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Please select a valid certificate from the list.');
    }

    /**
     * set certificate type (cert/ca)
     * @param $value certificate type
     */
    public function setType($value)
    {
        if (trim(strtolower($value)) == 'ca') {
            $this->certificateType = 'ca';
        } elseif (trim(strtolower($value)) == 'crl') {
            $this->certificateType = 'crl';
        } else {
            $this->certificateType = 'cert';
        }
    }

    /**
     * generate validation data (list of certificates)
     */
    protected function actionPostLoadingEvent()
    {
        if ($this->hasStaticOptions($this->certificateType)) {
            $this->internalOptionList = $this->getStaticOptions($this->certificateType);
            return;
        }

        $configObj = Config::getInstance()->object();
        $data = [];

        foreach ($configObj->{$this->certificateType} as $cert) {
            if ($this->certificateType == 'ca' && (string)$cert->x509_extensions == 'ocsp') {
                // skip ocsp signing certs
                continue;
            }
            $data[(string)$cert->refid] = (string)$cert->descr;
        }

        natcasesort($data);

        $this->internalOptionList = $this->setStaticOptions($data, $this->certificateType);
    }
}
