#!/usr/local/bin/php
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

require_once 'config.inc';
require_once 'util.inc';
require_once 'XMLRPC_Client.inc';

$action = $argv[1] ?? '';
$service = $argv[2] ?? '';
$service_id = $argv[3] ?? '';

try {
    switch ($action) {
        case 'stop':
            $result = xmlrpc_execute('opnsense.stop_service', ['service' => $service, 'id' => $service_id]);
            echo json_encode(['response' => $result, 'status' => 'ok']);
            break;
        case 'start':
            $result = xmlrpc_execute('opnsense.start_service', ['service' => $service, 'id' => $service_id]);
            echo json_encode(['response' => $result, 'status' => 'ok']);
            break;
        case 'restart':
            $result = xmlrpc_execute('opnsense.restart_service', ['service' => $service, 'id' => $service_id]);
            echo json_encode(['response' => $result, 'status' => 'ok']);
            break;
        case 'reload_templates':
            xmlrpc_execute('opnsense.configd_reload_all_templates');
            echo json_encode(['status' => 'done']);
            break;
        case 'exec_sync':
            configd_run('filter sync');
            echo json_encode(['status' => 'done']);
            break;
        case 'version':
            $payload = xmlrpc_execute('opnsense.firmware_version');
            if (isset($payload['firmware'])) {
                $payload['firmware']['_my_version'] = shell_safe('opnsense-version -v core');
            }
            echo json_encode(['response' => $payload]);
            break;
        case 'services':
            echo json_encode(['response' => xmlrpc_execute('opnsense.list_services')]);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'usage ha_xmlrpc_exec.php action [service_id]']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
