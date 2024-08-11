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

namespace OPNsense\RRD;


use ReflectionClass;

class TypeNotFound extends \Exception { }

class Factory
{
    /**
     * @param string $type type of rrd graph to get
     * @param string $target filename to store data in
     * @param int $ds_heartbeat default heartbeat to use for new datasets
     * @param int $ds_min default min value to use for new datasets
     * @param int $ds_max default max value to use for new datasets
     */
    public function get(string $type, string $target, ?int $ds_heartbeat=null, ?int $ds_min=null, ?int $ds_max=null)
    {
        try {
            $cls = new ReflectionClass('\\OPNsense\\RRD\\Types\\'. $type);
            if (!$cls->isInstantiable() || !$cls->isSubclassOf('OPNsense\\RRD\\Types\\Base')) {
                throw new TypeNotFound(sprintf("%s not found", $type));
            }
        } catch (ReflectionException) {
            throw new TypeNotFound(sprintf("%s not found", $type));
        }
        return $cls->newInstance($target, $ds_heartbeat, $ds_min, $ds_max);
    }

}