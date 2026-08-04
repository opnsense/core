<?php

use OPNsense\Core\Shell;

// Array
// (
//    [dhcp4] => Array
//        (
//            [dce6d637-2550-45ed-a549-b26097bb804a] => 1
//            [2fd2d3bd-6747-481e-bfb8-77ff96150415] => 2
//        )
//    [dhcp6] => Array
//        (
//        )
// )
$subnet_map = [];

foreach (['dhcp4' => 'Dhcp4', 'dhcp6' => 'Dhcp6'] as $service => $section) {
    $response = json_decode(Shell::shell_safe('/usr/local/opnsense/scripts/kea/kea_get_config.py %s', [$service]), true);
    foreach ($response['arguments'][$section]['subnet' . substr($service, -1)] as $subnet) {
        $subnet_map[$service][$subnet['user-context']['uuid']] = $subnet['id'];
    }
}

// XXX: Migration needs implementation
