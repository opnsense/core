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

class TheGreenBow extends BaseExporter implements IExportProvider
{
    /**
     * @var string file extension
     */
    protected $fileExtension = "tgb";

    /**
     * @return string plugin name
     */
    public function getName()
    {
        return gettext("TheGreenBow");
    }

    /**
     * @return array supported options
     */
    public function supportedOptions()
    {
        return array();
    }

    /**
     * @return string base filename without extension
     */
    protected function getBaseFilename()
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
        return implode("_", $result);
    }

    /**
     * @return string filename
     */
    public function getFilename()
    {
        return $this->getBaseFilename() . "." . $this->fileExtension;
    }

    /**
     * @return string file type
     */
    public function getFileType()
    {
        return "text/plain";
    }

    /**
     * @return string content
     */
    public function getContent()
    {
        $output = new \SimpleXMLElement(
            file_get_contents(substr(__FILE__, 0, -3) . 'tgb')
        );

        if (!empty($this->config['description'])) {
            $output->cfg_ssl->cfg_sslconnection['name'] = $this->config['description'];
        } else {
            $output->cfg_ssl->cfg_sslconnection['name'] = 'openvpn';
        }
        $output->cfg_ssl->cfg_sslconnection['server'] = $this->config['hostname'];
        $output->cfg_ssl->cfg_sslconnection['port'] = $this->config['local_port'];
        if (strncasecmp($this->config['protocol'], "tcp", 3) === 0) {
            $output->cfg_ssl->cfg_sslconnection['proto'] = 'TCP';
        } else {
            $output->cfg_ssl->cfg_sslconnection['proto'] = 'UDP';
        }

        if (in_array($this->config['mode'], array('server_user', 'server_tls_user'))) {
            $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions['UserPassword'] = 'yes';
            $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions['PopupUserPassword'] = 'yes';
        } else {
            $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions['UserPassword'] = 'no';
            $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions['PopupUserPassword'] = 'no';
        }

        $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->Cipher = $this->config['crypto'];
        preg_match_all('!\d+!', $this->config['crypto'], $matches);
        if (!empty($matches)) {
            $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->CipherKeySize = $matches[0][0];
        } else {
            $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->CipherKeySize = "auto";
        }

        if (!empty($this->config['digest'])) {
            if (strpos($this->config['digest'], "SHA1") !== false) {
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->Auth = "SHA1";
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->AuthSize = "160";
            } elseif ($this->config['digest'] == "SHA256") {
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->Auth = "SHA2";
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->AuthSize = "256";
            } elseif ($this->config['digest'] == "SHA384") {
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->Auth = "SHA2";
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->AuthSize = "384";
            } elseif ($this->config['digest'] == "SHA512") {
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->Auth = "SHA2";
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->AuthSize = "SHA512";
            } else {
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->Auth = "MD5";
                $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->AuthSize = "128";
            }
        }
        if (!empty($this->config['compression'])) {
            $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->Compression = 'yes';
        } else {
            $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->Compression = 'no';
        }

        if (
            $this->config['mode'] !== 'server_user' && !empty($this->config['server_subject_name'])
            && !empty($this->config['validate_server_cn'])
        ) {
            $output->cfg_ssl->cfg_sslconnection->cfg_tunnelestablish->GatewayName = $this->config['server_subject_name'];
        }

        $output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->RenegSeconds = $this->config['reneg-sec'];
        if (!empty($this->config['tls'])) {
            $tls = array("\n-----BEGIN Static key-----");
            foreach (explode("\n", trim(base64_decode($this->config['tls']))) as $line) {
                if (!empty($line) && !in_array($line[0], ['-', '#'])) {
                    $tls[] = $line;
                }
            }
            $tls[] = "-----END Static key-----\n";

            $output->cfg_ssl->cfg_sslconnection->cfg_TlsAuth->key = (string)implode("\n", $tls);
        } else {
            unset($output->cfg_ssl->cfg_sslconnection->cfg_TlsAuth);
            unset($output->cfg_ssl->cfg_sslconnection->cfg_tunneloptions->KeyDirection);
        }

        // client certificate
        if (!empty($this->config['client_crt'])) {
            $output->cfg_ssl->cfg_sslconnection->authentication->certificate[0]->public_key =
                "\n" . $this->config['client_crt'];
            $output->cfg_ssl->cfg_sslconnection->authentication->certificate[0]->private_key =
                "\n" . $this->config['client_prv'];
            // server CA-chain
            $output->cfg_ssl->cfg_sslconnection->authentication->certificate[1]->public_key = "\n" . implode(
                "\n",
                $this->config['server_ca_chain']
            );
        }

        // export to DOM to reformat+pretty-print output
        $dom = new \DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($output->asXML());

        // legacy header
        $heading = "# Do not edit this file. It is overwritten by VpnConf.\n";
        $heading .= "# SIGNATURE MD5 = xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n";
        $heading .= "# Creation Date : " . date('Y-m-d \a\t H:i:s') . "\n";
        $heading .= "# Written by OPNsense\n\n\n";
        $heading .= "[TGBIKENG]\n";

        return $heading . str_replace('&#13;', "", $dom->saveXML());
    }
}
