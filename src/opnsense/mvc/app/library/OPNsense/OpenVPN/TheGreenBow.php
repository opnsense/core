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
        return $this->getBaseFilename() . ".". $this->fileExtension;
    }

    /**
     * @return string file type
     */
    public function getFileType()
    {
        return "text/plain";
    }

    /**
     * replace placeholders
     * @param $template
     * @return mixed
     */
    private function replacePlaceHolders($template)
    {
        $result = $template;
        $tags = $this->config;
        $tags['Auth'] = "";
        $tags['AuthSize'] = "";
        $tags['creation_date'] =  date('Y-m-d \a\t H:i:s');
        preg_match_all('!\d+!', $this->config['crypto'], $matches);
        if (!empty($matches)) {
            $tags['CipherKeySize'] = preg_match_all('!\d+!', $this->config['crypto'], $matches);
        } else {
            $tags['CipherKeySize'] = "auto";
        }
        if (isset($tags['server_ca_chain'])) {
            $tags['server_ca_chain'] = implode("\n", $tags['server_ca_chain']);
        }

        if (!empty($this->config['digest'])) {
            if (strpos($this->config['digest'], "SHA1") !== false) {
                $tags['Auth'] = "MD5";
                $tags['AuthSize'] = "128";
            } elseif ($this->config['digest'] == "SHA256") {
                $tags['Auth'] = "SHA2";
                $tags['AuthSize'] = "256";
            } elseif ($this->config['digest'] == "SHA384") {
                $tags['Auth'] = "SHA2";
                $tags['AuthSize'] = "384";
            } elseif ($this->config['digest'] == "SHA512") {
                $tags['Auth'] = "SHA2";
                $tags['AuthSize'] = "SHA512";
            }
        }

        if (strncasecmp($this->config['protocol'], "tcp", 3) === 0) {
            $tags['proto'] = 'TCP';
        } else {
            $tags['proto'] = 'UDP';
        }

        if (in_array($this->config['mode'], array('server_user', 'server_tls_user'))) {
            $tags['UserPassword'] = 'yes';
        } else {
            $tags['UserPassword'] = 'no';
        }

        $tags['Compression'] = !empty($this->config['compression']) ? 'yes' : 'no';

        foreach ($tags as $name => $value) {
            if (!is_array($value)) {
                $result = str_replace("[[{$name}]]", $value, $result);
            }
        }

        return $result;
    }


    /**
     * @return string content
     */
    public function getContent()
    {
        $template = file_get_contents(substr(__FILE__, 0, -3).'tgb');
        return $this->replacePlaceHolders($template);
    }
}
