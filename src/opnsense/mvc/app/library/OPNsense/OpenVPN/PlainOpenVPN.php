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
    /**
     * @var string file extension
     */
    protected $fileExtension = "ovpn";

    /**
     * @return string plugin name
     */
    public function getName()
    {
        return gettext("File Only");
    }

    /**
     * @return array supported options
     */
    public function supportedOptions()
    {
        return array("plain_config", "random_local_port", "auth_nocache", "cryptoapi");
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
        return preg_replace("/[^a-zA-Z0-9]/", "_", implode("_", $result));
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
     * @return array
     */
    protected function openvpnConfParts()
    {
        $conf = [];

        $conf[] = "dev " . (!empty($this->config['dev_mode']) ? $this->config['dev_mode'] : 'tun');

        $conf[] = "persist-tun";
        $conf[] = "persist-key";
        if (strncasecmp($this->config['protocol'], "tcp", 3) === 0) {
            $conf[] = "proto " . strtolower("{$this->config['protocol']}-client");
        }

        $conf[] = "cipher {$this->config['crypto']}";
        if (!empty($this->config['digest'])) {
            $conf[] = "auth {$this->config['digest']}";
        }
        $conf[] = "client";
        $conf[] = "resolv-retry infinite";
        if (isset($this->config['reneg-sec']) && $this->config['reneg-sec'] != "") {
            $conf[] = "reneg-sec {$this->config['reneg-sec']}";
        }
        foreach (explode(",", $this->config['hostname']) as $hostname) {
            $conf[] = "remote {$hostname} {$this->config['local_port']} " . strtolower($this->config['protocol']);
        }
        if (!empty($this->config['random_local_port'])) {
            $conf[] = "lport 0";
        }

        if (!empty($this->config['server_subject']) && !empty($this->config['validate_server_cn'])) {
            $tmp_subject = "";
            foreach ($this->config['server_subject'] as $key => $value) {
                if (!empty($tmp_subject)) {
                    $tmp_subject .= ", ";
                }
                $tmp_subject .= "{$key}={$value}";
            }
            $conf[] = "verify-x509-name \"{$tmp_subject}\" subject";
            if (!empty($this->config['server_cert_is_srv'])) {
                $conf[] = "remote-cert-tls server";
            }
        }
        if (!empty($this->config['cryptoapi'])) {
            $conf[] = "cryptoapicert \"SUBJ:{$this->config['client_cn']}\"";
        }
        if (in_array($this->config['mode'], array('server_user', 'server_tls_user'))) {
            $conf[] = "auth-user-pass";
            if (!empty($this->config['auth_nocache'])) {
                $conf[] = "auth-nocache";
            }
        }

        if (!empty($this->config['compression'])) {
            switch ($this->config['compression']) {
                case 'no':
                case 'adaptive':
                case 'yes':
                    $conf[] = "comp-lzo " . $this->config['compression'];
                    break;
                case 'pfc':
                    $conf[] = "compress";
                    break;
                default:
                    $conf[] = "compress " . $this->config['compression'];
                    break;
            }
        }

        if (!empty($this->config['plain_config'])) {
            foreach (preg_split('/\r\n|\r|\n/', $this->config['plain_config']) as $line) {
                if (!empty($line)) {
                    $conf[] = $line;
                }
            }
        }
        return $conf;
    }


    /**
     * @return array inline OpenVPN files
     */
    protected function openvpnInlineFiles()
    {
        $conf = array();
        if (!empty($this->config['server_ca_chain'])) {
            $conf[] = "<ca>";
            foreach ($this->config['server_ca_chain'] as $ca) {
                $conf = array_merge($conf, explode("\n", trim($ca)));
            }
            $conf[] = "</ca>";
        }

        if (!empty($this->config['client_crt']) && empty($this->config['cryptoapi'])) {
            $conf[] = "<cert>";
            $conf = array_merge($conf, explode("\n", trim($this->config['client_crt'])));
            $conf[] = "</cert>";

            $conf[] = "<key>";
            $conf = array_merge($conf, explode("\n", trim($this->config['client_prv'])));
            $conf[] = "</key>";
        }
        if (!empty($this->config['tls'])) {
            if ($this->config['tlsmode'] === 'crypt') {
                $conf[] = "<tls-crypt>";
                $conf = array_merge($conf, explode("\n", trim(base64_decode($this->config['tls']))));
                $conf[] = "</tls-crypt>";
            } else {
                $conf[] = "<tls-auth>";
                $conf = array_merge($conf, explode("\n", trim(base64_decode($this->config['tls']))));
                $conf[] = "</tls-auth>";
                $conf[] = "key-direction 1";
            }
        }

        return $conf;
    }

    /**
     * @return string content
     */
    public function getContent()
    {
        return implode("\n", array_merge($this->openvpnConfParts(), $this->openvpnInlineFiles()));
    }
}
