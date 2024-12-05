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

namespace OPNsense\RRD\Types;

class Traffic extends Base
{
    /**
     * {@inheritdoc}
     */
    protected int $ds_heartbeat =  120;
    protected int $ds_min = 0;
    protected int $ds_max = 2500000000;
    protected static string $stdfilename = '%s-traffic.rrd';

    /**
     * {@inheritdoc}
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename);
        $this->addDatasets(
            ['inpass','outpass','inblock','outblock','inpass6','outpass6','inblock6','outblock6'],
            'COUNTER'
        );
    }

    /**
     * Traffic is a subcollection of Interfaces
     */
    public static function wantsStats()
    {
        return 'Interfaces';
    }

    /**
     * @inheritdoc
     */
    public static function payloadSplitter(array $payload)
    {
        foreach ($payload as $intf => $data) {
            $tmp = [
                'inpass' => $data['in4_pass_bytes'],
                'outpass' => $data['out4_pass_bytes'],
                'inblock' => $data['in4_block_bytes'],
                'outblock' => $data['out4_block_bytes'],
                'inpass6' => $data['in6_pass_bytes'],
                'outpass6' => $data['out6_pass_bytes'],
                'inblock6' => $data['in6_block_bytes'],
                'outblock6' => $data['out6_block_bytes']
            ];
            yield static::$basedir . sprintf(static::$stdfilename, $intf) => $tmp;
        }
    }
}
