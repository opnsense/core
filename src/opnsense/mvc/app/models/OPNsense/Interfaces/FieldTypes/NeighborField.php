<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Interfaces\FieldTypes;

use ReflectionClass;
use ReflectionException;
use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;

class NeighborField extends ArrayField
{
    protected static $internalStaticChildren = [];
    private static $internalSourcemap = [];

    /**
     * {@inheritdoc}
     */
    protected static function getStaticChildren()
    {
        $result = [];
        foreach (glob(__DIR__ . "/../Neighbor/*.php") as $filename) {
            $origin = explode('.', basename($filename))[0];
            $classname = 'OPNsense\\Interfaces\\Neighbor\\' . $origin;
            try {
                $class = new ReflectionClass($classname);
                $obj = $class->newInstance();
                $dynamic_data = $obj->collect();
                if (is_array($dynamic_data)) {
                    $seq = 1;
                    foreach ($dynamic_data as $record) {
                        if (is_array($record) && !empty($record['etheraddr']) && !empty($record['ipaddress'])) {
                            $itemKey = sprintf('%s-%s', $origin, $seq);
                            $result[$itemKey] = [
                                'etheraddr' => $record['etheraddr'],
                                'ipaddress' => $record['ipaddress'],
                                'descr' => $record['descr'] ?? ''
                            ];
                            self::$internalSourcemap[$itemKey] = $record['source'] ?? $origin;
                            $seq++;
                        }
                    }
                }
            } catch (\Error | \Exception | ReflectionException $e) {
                syslog(LOG_ERR, sprintf(
                    "Invalid neightbor object %s in %s (%s)",
                    $classname,
                    realpath($filename),
                    $e->getMessage()
                ));
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function actionPostLoadingEvent()
    {
        parent::actionPostLoadingEvent();
        foreach ($this->iterateItems() as $key => $node) {
            $type_node = new TextField();
            $type_node->setInternalIsVirtual();
            if (isset(self::$internalSourcemap[$key])) {
                $type_node->setValue(self::$internalSourcemap[$key]);
            } else {
                $type_node->setValue('manual');
            }
            $node->addChildNode('origin', $type_node);
        }
    }
}
