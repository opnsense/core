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

namespace OPNsense\Dnsmasq;
use OPNsense\Core\Backend;
use OPNsense\Dnsmasq\Plugin;


/**
 * Class PluginIndexController extends Core's OPNsense\Base\IndexController.
 *
 * All controllers for this plugin, should extend from this one.
 *
 *
 *
 *
 *
 * @package OPNsense\Dnsmasq
 */
class PluginIndexController extends \OPNsense\Base\IndexController
{

    /**
     * Function to call the indexAction() for any page, and create a UI endpoint for it.
     *
     * UI endpoint (example):
     * `/ui/dnscryptproxy/settings`

     * This function is to be inherited by all of the controllers, and it will then dynamically
     * derive the names of the forms out of Phalcon instead of needing to statically define an indexAction()
     * for each page individually.
     *
     * indexAction() is analogous to index.html, it's the default if no API action is provided.
     */
    public function indexAction()
    {
        // Pull the name of this api from the Phalcon view to use in further calls.
        //$this_api_name = $this->view->getNamespaceName();
        // This also may be acceptible but probably less reliable.
        $this_api_name = $this->router->getMatches()[1];            // "about"

        $plugin = new Plugin;
        $form_xml = $plugin->getFormXml($this_api_name);

        $this->view->setVars(
            [
                // Derive the API path from the UI path of the view, swapping out the leading "/ui/" for "/api/".
                // This is crude, but it will work until I discover a more reliable way to do it in the view.
                // XXX Unused?
                //'plugin_version' => $this->invokeConfigdRun('plugin_version'),  // "2.0.45.1"
                //'dnscrypt_proxy_version' => $this->invokeConfigdRun('version'), // "2.0.45"
                'plugin_api_path' => preg_replace("/^\/ui\//", "/api/", $this->router->getMatches()[0]),
                'this_xml' => $form_xml,
                // example: controllers/OPNsense/Dnsmasq/forms/settings.xml
                //'data_get_map' => $form_xml->xpath('model')
            ]
        );
        // Since the directory structure of OPNsense's plugins isn't conducive to automatically loading the template,
        // pick the specific template we want to load. Relative to /usr/local/opnsense/mvc/app/views, no file extension

        $this->view->pick('OPNsense/Dnsmasq/' . $this_api_name);
        // reference: views/OPNsense/Dnsmasq/settings.volt

    }

    /**
     * This is a special function which is executed after routing by XXX
     * @param $formname
     * @return array
     * @throws \Exception
     */
    public function afterExecuteRoute($dispatcher)
    {
        // Create plugin object to get some settings for in the view.
        $plugin = new Plugin;

        // Set in the view our plugin settings.
        $this->view->setVars($plugin->getSettings());

        // We derive the plugin_api_name from the namespace of this PHP class.
        // This assumes that the namespace will be something like: OPNsense\Dnsmasq
        $plugin_api_name = preg_replace('/^.*\\\/','',strtolower($this->router->getNamespaceName()));

        // Set the plugin_name in the view.
        $this->view->setVar('plugin_api_name', $plugin_api_name);
    }




}
