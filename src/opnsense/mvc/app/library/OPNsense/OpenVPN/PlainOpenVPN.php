<?php
/*
 * Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\OpenVPN;

class PlainOpenVPN extends BaseExporter implements IExportProvider
{
    public function getName()
    {
        return gettext("File Only");
    }

    public function supportedOptions()
    {
        return array("testxx1", "testxx3");
    }

    /**
     * @return string filename
     */
    public function getFilename()
    {
        $result = array();
        if (!empty($this->config['description'])) {
            $result[] = $this->config['description'];
        } else {
            $result[] = "openvpn";
        }
        if (!empty($this->config['client_cn'])) {
            $result[] = $this->config['client_cn'];
        }
        return implode("_", $result) . ".ovpn";
    }

    /**
     * @return string file type
     */
    public function getFileType()
    {
        return "text/plain";
    }

    /**
     * @return array
     */
    protected function openvpnConfParts()
    {
        $conf = array();
        if (isset($this->config['dev_mode'])) {
           $conf[] = "dev {$this->config['dev_mode']}";
        }
        $conf[] = "persist-tun";
        $conf[] = "persist-key";

        return $conf;
    }

    /**
     * @return string content
     */
    public function getContent()
    {

        return implode("\n", $this->openvpnConfParts());
    }
}
