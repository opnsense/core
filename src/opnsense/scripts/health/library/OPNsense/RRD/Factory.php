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

class TypeNotFound extends \Exception
{
}

/**
 * RRD Factory class, offers access to RRD types and statistics.
 */
class Factory
{
    /**
     * gathered statistics
     */
    private $stats = [];

    /**
     * @param string $type type of rrd graph to get
     * @param string $target filename to store data in
     * @return \OPNsense\RRD\Types\Base
     * @throws TypeNotFound if the class is not found or has an improper type
     */
    public function get(string $type, string $target)
    {
        try {
            $cls = new ReflectionClass('\\OPNsense\\RRD\\Types\\' . $type);
            if (!$cls->isInstantiable() || !$cls->isSubclassOf('OPNsense\\RRD\\Types\\Base')) {
                throw new TypeNotFound(sprintf("%s not found", $type));
            }
        } catch (ReflectionException) {
            throw new TypeNotFound(sprintf("%s not found", $type));
        }
        return $cls->newInstance($target);
    }

    /**
     * collect statistics to feed to rrd, generates an array containing classnames including runtimes and payload
     * for example: ['Mbuf'] => ['data' => [], 'runtime' => 0.1555]
     * @return $this
     */
    public function collect()
    {
        $this->stats = [];
        foreach (glob(sprintf("%s/Stats/*.php", __DIR__)) as $filename) {
            $classname = substr(basename($filename), 0, -4);
            $cls = new ReflectionClass('\\OPNsense\\RRD\\Stats\\' . $classname);
            if ($cls->isInstantiable() &&  $cls->isSubclassOf('OPNsense\\RRD\\Stats\\Base')) {
                try {
                    $start_time = microtime(true);
                    $obj = $cls->newInstance();
                    $tmp = $obj->run();
                    $this->stats[$classname] = [
                        'data' => $tmp,
                        'runtime' => microtime(true) - $start_time
                    ];
                } catch (\Error | \Exception $e) {
                    echo $e;
                    syslog(LOG_ERR, sprintf("Error collecting %s [%s]", $classname, $e));
                }
            }
        }
        return $this;
    }

    /**
     * get gathered statistics for a specific collector
     * @param string $name name of the collector, e.g. Mbuf
     * @return array
     */
    public function getData(string $name)
    {
        return isset($this->stats[$name]) ? $this->stats[$name]['data'] : [];
    }

    /**
     * @return array collected statistics
     */
    public function getRawStats()
    {
        return $this->stats;
    }

    /**
     * update all registered RRD graphs
     * @return $this
     */
    public function updateAll($debug = false)
    {
        foreach (glob(sprintf("%s/Types/*.php", __DIR__)) as $filename) {
            $classname = substr(basename($filename), 0, -4);
            $fclassname = '\\OPNsense\\RRD\\Types\\' . $classname;
            try {
                $cls = new ReflectionClass($fclassname);
                if ($cls->isInstantiable() && $cls->isSubclassOf('OPNsense\\RRD\\Types\\Base')) {
                    $wants = call_user_func([$fclassname, 'wantsStats']);
                    $the_data = $this->getData($wants);
                    if (empty($the_data)) {
                        continue;
                    }
                    /**
                     *  Because some data sets should be split into multiple targets, we need a facility to map
                     *  (a part) of the dataset to the target.
                     *
                     *  In most cases an RRD type results in a single target, in which case the 'type' knows where
                     *  it should render the content to by default.
                     *
                     *  If the type+stats combination results in multiple targets we need to leave this mapping
                     *  somewhere, in which case the 'Type' is the most logical place
                     *  as being responsible for the actual output.
                     */
                    foreach (call_user_func([$fclassname, 'payloadSplitter'], $the_data) as $tfilename => $data) {
                        $obj = $cls->newInstance($tfilename);
                        $obj->create(); /* only creates when no target exists yet */
                        $obj->update($data, $debug);
                    }
                }
            } catch (\Error | \Exception $e) {
                echo $e;
                syslog(LOG_ERR, sprintf("Error instantiating %s [%s]", $classname, $e));
            }
        }
        return $this;
    }
}
