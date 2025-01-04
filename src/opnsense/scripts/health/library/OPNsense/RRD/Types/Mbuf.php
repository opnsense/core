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

class Mbuf extends Base
{
    /**
     * {@inheritdoc}
     */
    protected int $ds_heartbeat =  120;
    protected int $ds_min = 0;
    protected int $ds_max = 10000000;
    protected static string $stdfilename = 'system-mbuf.rrd';

    /**
     * {@inheritdoc}
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename);
        $this->addDatasets(['current', 'cache', 'total', 'max'], 'GAUGE');
        $this->setRRA([
            ['MIN', 0.5, 1, 1200],
            ['MIN', 0.5, 5, 720],
            ['MIN', 0.5, 60, 1860],
            ['MIN', 0.5, 1440, 2284],
            ['AVERAGE', 0.5, 1, 1200],
            ['AVERAGE', 0.5, 5, 720],
            ['AVERAGE', 0.5, 60, 1860],
            ['AVERAGE', 0.5, 1440, 2284],
            ['MAX', 0.5, 1, 1200],
            ['MAX', 0.5, 5, 720],
            ['MAX', 0.5, 60, 1860],
            ['MAX', 0.5, 1440, 2284],
        ]);
    }
}
