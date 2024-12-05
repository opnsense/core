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

namespace OPNsense\RRD\Stats;

class GatewayQuality extends Base
{
    public function run()
    {
        $result = [];
        foreach (glob('/var/run/dpinger_*.sock') as $filename) {
            $fp = @stream_socket_client("unix://{$filename}", $errno, $errstr, 1);
            if (!$fp) {
                continue;
            }

            $dinfo = '';
            while (!feof($fp)) {
                $dinfo .= fgets($fp, 1024);
            }
            fclose($fp);
            $record = explode(' ', trim($dinfo));
            $result[$record[0]] = [
                'gwname' => $record[0],
                'delay' => sprintf('%.07f', $record[1] / 1000.0 / 1000.0),
                'stddev' => sprintf('%.07f', $record[2] / 1000.0 / 1000.0),
                'loss' => $record[3]
            ];
        }
        return $result;
    }
}
