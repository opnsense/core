<?php

/*
 * Copyright (C) 2017-2018 EURO-LOG AG
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

namespace OPNsense\Monit\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Monit\Monit;

/**
 * Class StatusController
 * @package OPNsense\Monit
 */
class StatusController extends ApiControllerBase
{
    /**
     * get monit status page
     * see monit(1)
     * @return array
     */
    public function getAction($format = 'xml')
    {
        $result = array("result" => "failed");

        $socketPath = "/var/run/monit.sock";

        // map the requested html format from the status page to the Monit text format
        $format = $format == 'html' ? 'text' : $format;

        // check monit httpd socket defined in monitrc by 'set httpd ...'
        if (file_exists($socketPath) && filetype($socketPath) == "socket") {
            // set curl options
            $ch = curl_init("http://127.0.0.1/_status?format=" . $format);
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socketPath);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // get credentials if configured
            $mdlMonit = new Monit(false);
            if (
                (string)$mdlMonit->general->httpdUsername != null && trim((string)$mdlMonit->general->httpdUsername) !== "" &&
                (string)$mdlMonit->general->httpdPassword != null && trim((string)$mdlMonit->general->httpdPassword) !== ""
            ) {
                    curl_setopt($ch, CURLOPT_USERPWD, ((string)$mdlMonit->general->httpdUsername) . ":" . ((string)$mdlMonit->general->httpdPassword));
            }

            // send request
            if (!$response = curl_exec($ch)) {
                $result['status'] = curl_error($ch);
                return $result;
            }
            $HTTPCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($HTTPCode != 200) {
                $result['status'] = 'Monit returns with code ' . $HTTPCode;
            } else {
                $result['result'] = "ok";

                // format the response
                if ($format == 'xml') {
                    $result['status'] = simplexml_load_string($response);
                } elseif ($format === 'text') {
                    $result['status'] = '<div class="alert"><pre>' . $this->bashColorToCSS($response) . '</pre></div>';
                }
            }
        } else {
            $msg = "
Either the file " . $socketPath . " does not exists or it is not a unix socket.
Please check if the Monit service is running.

If you have started Monit recently, wait for StartDelay seconds and refresh this page.";
            if ($format == 'xml') {
                $result['status'] = $msg;
            } elseif ($format === 'text') {
                $result['status'] = '<div class="alert"><pre>' . $msg . '</pre></div>';
            }
        }
        return $result;
    }

    /**
     * convert bash color escape codes to CSS
     * @param $string
     * @return string
     */
    private function bashColorToCSS($string)
    {
        $colors = [
            '/\x1b\[0;30m(.*?)\x1b\[0m/s' => '<strong>$1</strong>',

            '/\x1b\[0;30m(.*?)\x1b\[0m/s' => '<span class="text-info">$1</span>',
            '/\x1b\[0;31m(.*?)\x1b\[0m/s' => '<span class="text-danger">$1</span>',
            '/\x1b\[0;32m(.*?)\x1b\[0m/s' => '<span class="text-success">$1</span>',
            '/\x1b\[0;33m(.*?)\x1b\[0m/s' => '<span class="text-warning">$1</span>',
            '/\x1b\[0;34m(.*?)\x1b\[0m/s' => '<span class="text-info">$1</span>',
            '/\x1b\[0;35m(.*?)\x1b\[0m/s' => '<span class="text-danger">$1</span>',
            '/\x1b\[0;36m(.*?)\x1b\[0m/s' => '<span class="text-primary">$1</span>',
            '/\x1b\[0;37m(.*?)\x1b\[0m/s' => '<span class="text-info">$1</span>',
            '/\x1b\[0;39m(.*?)\x1b\[0m/s' => '<span>$1</span>',

            '/\x1b\[1;30m(.*?)\x1b\[0m/s' => '<strong class="text-info">$1</strong>',
            '/\x1b\[1;31m(.*?)\x1b\[0m/s' => '<strong class="text-danger">$1</strong>',
            '/\x1b\[1;32m(.*?)\x1b\[0m/s' => '<strong class="text-success">$1</strong>',
            '/\x1b\[1;33m(.*?)\x1b\[0m/s' => '<strong class="text-warning">$1</strong>',
            '/\x1b\[1;34m(.*?)\x1b\[0m/s' => '<strong class="text-info">$1</strong>',
            '/\x1b\[1;35m(.*?)\x1b\[0m/s' => '<strong class="text-danger">$1</strong>',
            '/\x1b\[1;36m(.*?)\x1b\[0m/s' => '<strong class="text-primary">$1</strong>',
            '/\x1b\[1;37m(.*?)\x1b\[0m/s' => '<strong class="text-info">$1</strong>',

            '/\x1b\[0;90m(.*?)\x1b\[0m/s' => '<span class="text-info">$1</span>',
            '/\x1b\[0;91m(.*?)\x1b\[0m/s' => '<span class="text-danger">$1</span>',
            '/\x1b\[0;92m(.*?)\x1b\[0m/s' => '<span class="text-success">$1</span>',
            '/\x1b\[0;93m(.*?)\x1b\[0m/s' => '<span class="text-warning">$1</span>',
            '/\x1b\[0;94m(.*?)\x1b\[0m/s' => '<span class="text-info">$1</span>',
            '/\x1b\[0;95m(.*?)\x1b\[0m/s' => '<span class="text-danger">$1</span>',
            '/\x1b\[0;96m(.*?)\x1b\[0m/s' => '<span class="text-primary">$1</span>',
            '/\x1b\[0;97m(.*?)\x1b\[0m/s' => '<span class="text-info">$1</span>'
        ];
        return preg_replace(array_keys($colors), $colors, $string);
    }
}
