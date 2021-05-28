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

class ArchiveOpenVPN extends PlainOpenVPN
{
    /**
     * @var string file extension
     */
    protected $fileExtension = "zip";

    /**
     * @return string plugin name
     */
    public function getName()
    {
        return gettext("Archive");
    }

    /**
     * @return array custom options
     */
    public function supportedOptions()
    {
        return array("plain_config", "p12_password", "random_local_port", "auth_nocache", "cryptoapi");
    }

    /**
     * @return string file type
     */
    public function getFileType()
    {
        return "application/zip";
    }

    /**
     * generate a zip archive for OpenVPN
     * @return string content
     */
    public function getContent()
    {
        $conf = $this->openvpnConfParts();
        $base_filename = $this->getBaseFilename();
        $tempdir = tempnam(sys_get_temp_dir(), '_ovpn');
        $content_dir = $tempdir . "/" . $base_filename;
        if (file_exists($tempdir)) {
            unlink($tempdir);
        }
        mkdir($content_dir, 0700, true);

        if (empty($this->config['cryptoapi'])) {
            if (!empty($this->config['client_crt'])) {
                // export keypair
                $p12 = $this->export_pkcs12(
                    $this->config['client_crt'],
                    $this->config['client_prv'],
                    !empty($this->config['p12_password']) ? $this->config['p12_password'] : null,
                    !empty($this->config['server_ca_chain']) ? $this->config['server_ca_chain'] : null
                );

                file_put_contents("{$content_dir}/{$base_filename}.p12", $p12);
                $conf[] = "pkcs12 {$base_filename}.p12";
            }
        } else {
            // use internal Windows store, only flush ca (when available)
            if (!empty($this->config['server_ca_chain'])) {
                $cafilename = "{$base_filename}.crt";
                file_put_contents("{$content_dir}/$cafilename", implode("\n", $this->config['server_ca_chain']));
                $conf[] = "ca {$cafilename}";
            }
        }
        if (!empty($this->config['tls'])) {
            $conf[] = "tls-auth {$base_filename}-tls.key 1";
            file_put_contents("{$content_dir}/{$base_filename}-tls.key", trim(base64_decode($this->config['tls'])));
        }
        file_put_contents("{$content_dir}/{$base_filename}.ovpn", implode("\n", $conf));

        $command = "cd " . escapeshellarg("{$tempdir}")
            . " && /usr/local/bin/zip -r "
            . escapeshellarg("{$content_dir}.zip")
            . " " . escapeshellarg($base_filename);
        exec($command);
        $result = file_get_contents($content_dir . ".zip");

        // cleanup
        unlink($content_dir . ".zip");
        foreach (glob($content_dir . "/*") as $filename) {
            unlink($filename);
        }
        rmdir($content_dir);
        rmdir($tempdir);

        return $result;
    }
}
