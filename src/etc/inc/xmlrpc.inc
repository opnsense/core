<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

require_once('IXR/IXR_Library.php');

class XMLRPCServer
{
    private $prefix = 'opnsense';

    private $xmlrpc_callbacks = array();

    /**
     * load publishable function from inc/xmlrpc/*.inc
     * every usable module should include a function to return its own registrable functions, using
     * the following pattern:
     *      xmlrpc_publishable_{filename without .inc}
     *
     * when functions wrap around a local variant with the same name, suffix with _xmlrpc (which will be stripped off)
     */
    private function loadPlugins()
    {
        // load all xmlrpc published functions
        foreach (glob(__DIR__ . "/xmlrpc/*.inc") as $filename) {
            require_once($filename);
            $publ_func = "xmlrpc_publishable_" . str_replace(".inc", "", basename($filename));
            if (function_exists($publ_func)) {
                foreach ($publ_func() as $function) {
                    $publish_name = $this->prefix . "." . str_replace("_xmlrpc", "", $function);
                    $this->xmlrpc_callbacks[$publish_name] = $function;
                }
            }
        }
    }

    /**
     * handle request
     * @return IXR_Server
     */
    public function start()
    {
        $this->loadPlugins();
        $server = new IXR_Server($this->xmlrpc_callbacks);
        return $server;
    }
}
