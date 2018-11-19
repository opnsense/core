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
        return array("plain_config");
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
        if (!empty($this->config['tunnel_networkv6'])) {
            $conf[] .= "tun-ipv6";
        }
        $conf[] = "persist-tun";
        $conf[] = "persist-key";
        if (strncasecmp($this->config['protocol'], "tcp", 3)) {
            $conf[] = "{$this->config['protocol']}-client";
        } else {
            $conf[] =  $this->config['protocol'];
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
            $conf[] = "remote {$hostname}";
        }
        if (!empty($this->config['random_local_port'])) {
            $conf[] = "lport 0";
        }

        if ($this->config['mode'] !== 'server_user' && !empty($this->config['server_cn'])
                && !empty($this->config['validate_server_cn'])) {
            $conf[] = "verify-x509-name \"{$this->config['server_cn']}\" name";
        } elseif (in_array($this->config['mode'], array('server_user', 'server_tls_user'))) {
            $conf[] = "auth-user-pass";
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
        if ($this->config['mode'] !== "server_user") {
            $conf[] = "<cert>";
            $conf = array_merge($conf, explode("\n", trim($this->config['client_crt'])));
            $conf[] = "</cert>";

            $conf[] = "<key>";
            $conf = array_merge($conf, explode("\n", trim($this->config['client_prv'])));
            $conf[] = "</key>";
        }
        if (!empty($this->config['tls'])) {
            $conf[] = "<tls-auth>";
            $conf = array_merge($conf, explode("\n", trim(base64_decode($this->config['tls']))));
            $conf[] = "</tls-auth>";
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
