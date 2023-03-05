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

/**
* Class ExportFactory
* @package OPNsense\OpenVPN
*/
class ExportFactory
{
    /**
     * list installed backup providers
     * @return array
     */
    public function listProviders()
    {
        $providers = array();
        foreach (glob(__DIR__ . "/*.php") as $filename) {
            $pathParts = explode('/', $filename);
            $vendor = $pathParts[count($pathParts) - 3];
            $module = $pathParts[count($pathParts) - 2];
            $classname = explode('.php', $pathParts[count($pathParts) - 1])[0];
            try {
                $reflClass = new \ReflectionClass("{$vendor}\\{$module}\\{$classname}");
                if (
                    $reflClass->implementsInterface('OPNsense\\OpenVPN\\IExportProvider')
                        && !$reflClass->isInterface()
                ) {
                    $providers[$classname] = array(
                        "class" => "{$vendor}\\{$module}\\{$classname}",
                        "handle" => $reflClass->newInstance()
                    );
                }
            } catch (\ReflectionException $e) {
                /* skip when unable to parse */
            }
        }
        return $providers;
    }

    /**
     * return a specific provider by class name (without namespace)
     * @param string $className without namespace
     * @return mixed|null
     */
    public function getProvider($className)
    {
        $providers = $this->listProviders();
        if (!empty($providers[$className])) {
            return $providers[$className]['handle'];
        } else {
            return null;
        }
    }
}
