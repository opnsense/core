<?php

function core_fw_firewall($fw)
{
    global $config;
    $log_block = !isset($config['syslog']['nologdefaultblock']);
    $log_pass = !isset($config['syslog']['nologdefaultpass']);

    if (!isset($config['system']['ipv6allow'])) {
        // block All IPv6 except loopback traffic
        $fw->registerFilterRule(0,
          array('type'=>'pass','log'=>$log_pass, 'interface' => 'loopback', 'ipprotocol'=>'inet6')
        );
        $fw->registerFilterRule(0,
          array('type'=>'block','log'=>$log_block, 'ipprotocol'=>'inet6', 'label' => 'Block all IPv6')
        );
    }
}
