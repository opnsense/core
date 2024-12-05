<?php

/*
 * Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
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

function rrd_export()
{
    $rrddbpath = '/var/db/rrd';

    $result = "\t<rrddata>\n";
    $rrd_files = glob("{$rrddbpath}/*.rrd");
    foreach ($rrd_files as $rrd_file) {
        $basename = basename($rrd_file);
        $xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
        exec("/usr/local/bin/rrdtool dump '{$rrd_file}' '{$xml_file}'");
        $xml_data = @file_get_contents($xml_file);
        @unlink($xml_file);
        if ($xml_data !== false) {
            $result .= "\t\t<rrddatafile>\n";
            $result .= "\t\t\t<filename>{$basename}</filename>\n";
            $result .= "\t\t\t<xmldata>" . base64_encode(gzdeflate($xml_data)) . "</xmldata>\n";
            $result .= "\t\t</rrddatafile>\n";
        }
    }
    $result .= "\t</rrddata>\n";
    return $result;
}

function rrd_import()
{
    global $config;

    foreach ($config['rrddata']['rrddatafile'] as $rrd) {
        if (!empty($rrd['xmldata'])) {
            $rrd_file = "/var/db/rrd/{$rrd['filename']}";
            $xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
            if (file_put_contents($xml_file, gzinflate(base64_decode($rrd['xmldata']))) === false) {
                log_msg("Cannot write $xml_file", LOG_ERR);
                continue;
            }
            $output = array();
            $status = null;
            exec("/usr/local/bin/rrdtool restore -f '{$xml_file}' '{$rrd_file}'", $output, $status);
            if ($status) {
                log_msg("rrdtool restore -f '{$xml_file}' '{$rrd_file}' failed returning {$status}.", LOG_ERR);
                continue;
            }
            unlink($xml_file);
        } elseif (!empty($rrd['data'])) {
            /* rrd backup format */
            $rrd_file = "/var/db/rrd/{$rrd['filename']}";
            $rrd_fd = fopen($rrd_file, "w");
            if (!$rrd_fd) {
                log_msg("Cannot write $rrd_file", LOG_ERR);
                continue;
            }
            $data = base64_decode($rrd['data']);
            /* Try to decompress the data. */
            $dcomp = @gzinflate($data);
            if ($dcomp) {
                /* If the decompression worked, write the decompressed data */
                if (fwrite($rrd_fd, $dcomp) === false) {
                    log_msg("fwrite $rrd_file failed", LOG_ERR);
                    continue;
                }
            } elseif (fwrite($rrd_fd, $data) === false) {
                  /* If the decompression failed, it wasn't compressed, so write raw data */
                  log_msg("fwrite $rrd_file failed", LOG_ERR);
                  continue;
            }
            if (fclose($rrd_fd) === false) {
                log_msg("fclose $rrd_file failed", LOG_ERR);
                continue;
            }
        }
    }
}
