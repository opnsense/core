<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

require_once("system.inc");
require_once('util.inc');
require_once("filter.inc");
require_once("interfaces.inc");

/**
 * @return array
 */
function xmlrpc_publishable_service()
{
    return array(
        'list_services_xmlrpc',
        'stop_service_xmlrpc',
        'start_service_xmlrpc',
        'restart_service_xmlrpc',
        'configd_reload_all_templates_xmlrpc',
    );
}

/**
 * list configured services
 * @return array
 */
function list_services_xmlrpc()
{
    $services = plugins_services();

    foreach ($services as &$service) {
        $service['status'] = service_status($service);
    }

    return $services;
}

/**
 * stop service
 * @param array $params
 * @return string
 */
function stop_service_xmlrpc($params)
{
    $filter = array();
    $name = $params["service"];
    if (!empty($params["id"])) {
        $filter['id'] = $params["id"];
    }

    return service_control_stop($name, $filter);
}

/**
 * start service
 * @param array $params
 * @return string
 */
function start_service_xmlrpc($params)
{
    $filter = array();
    $name = $params["service"];
    if (!empty($params["id"])) {
        $filter['id'] = $params["id"];
    }

    return service_control_start($name, $filter);
}

/**
 * restart service
 * @param array $params
 * @return string
 */
function restart_service_xmlrpc($params)
{
    $filter = array();
    $name = $params["service"];
    if (!empty($params["id"])) {
        $filter['id'] = $params["id"];
    }

    return service_control_restart($name, $filter);
}

/**
 * generate all templates
 */
function configd_reload_all_templates_xmlrpc($params)
{
    configd_run('template reload *');
    return array("status" => "done");
}
