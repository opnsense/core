<?php
/*
        Copyright (C) 2015 Deciso B.V.
        Copyright (C) 2009, 2010 Scott Ullrich
        Copyright (C) 2005 Colin Smith
        All rights reserved.

        Redistribution and use in source and binary forms, with or without
        modification, are permitted provided that the following conditions are met:

        1. Redistributions of source code must retain the above copyright notice,
           this list of conditions and the following disclaimer.

        2. Redistributions in binary form must reproduce the above copyright
           notice, this list of conditions and the following disclaimer in the
           documentation and/or other materials provided with the distribution.

        THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
        AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
        OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
        SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
        INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
        CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
        ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.
*/

/**
 * request functions which may be registered by the xmlrpc system
 * @return array
 */
function xmlrpc_publishable_legacy()
{
    $publish = array(
        'exec_shell_xmlrpc','exec_php_xmlrpc','filter_configure_xmlrpc','interfaces_carp_configure_xmlrpc',
        'backup_config_section_xmlrpc','restore_config_section_xmlrpc', 'merge_config_section_xmlrpc',
        'firmware_version_xmlrpc','reboot_xmlrpc','get_notices_xmlrpc'
    );


    return $publish;
}

/**
 * @param null $category
 * @return mixed
 */
function get_notices_xmlrpc($category = null)
{
    require_once("notices.inc");

    if ($category==null) {
        return get_notices();
    } else {
        return get_notices($category);
    }

}

/**
 * perform a system reboot
 * @return bool true
 */
function reboot_xmlrpc()
{
    mwexec_bg("/usr/local/etc/rc.reboot");

    return true;
}

/**
 * retrieve firmware version
 * @return mixed
 */
function firmware_version_xmlrpc()
{
    require_once("pfsense-utils.inc");

    return host_firmware_version();
}

/**
 * interfaces_vips_configure
 * @return mixed
 */
function interfaces_carp_configure_xmlrpc()
{
    require_once("interfaces.inc");

    interfaces_vips_configure();
    return true;
}

/**
 * filter reconfigure
 * @return mixed
 */
function filter_configure_xmlrpc()
{
    global $config;
    require_once("config.inc");
    require_once("filter.inc");
    require_once("system.inc");
    require_once("gwlb.inc");
    require_once("vslb.inc");
    require_once("openvpn.inc");
    require_once("services.inc");
    require_once("auth.inc");

    filter_configure();
    system_routing_configure();
    setup_gateways_monitor();
    relayd_configure();
    openvpn_resync_all();
    if (isset($config['dnsmasq']['enable'])) {
        services_dnsmasq_configure();
    } elseif (isset($config['unbound']['enable'])) {
        services_unbound_configure();
    } else {
        # Both calls above run services_dhcpd_configure(), then we just
        # need to call it when them are not called to avoid restart dhcpd
        # twice, as described on ticket #3797
        services_dhcpd_configure();
    }
    local_sync_accounts();

    return true;
}


/**
 * @param $confData array containing config data
 * @return bool
 */
function merge_config_section_xmlrpc($confData)
{
    global $config;
    require_once("config.inc");

    $config_new = array_merge_recursive($config, $confData);
    $config = $config_new;
    $mergedkeys = implode(",", array_keys($confData));
    write_config(sprintf(gettext("Merged in config (%s sections) from XMLRPC client."), $mergedkeys));
    return true;
}

/**
 * restore config section
 * @param $new_config
 * @return bool
 */
function restore_config_section_xmlrpc($new_config)
{
    global $config;
    require_once("config.inc");
    require_once("interfaces.inc");

    // TODO: initial cleanup operation performed, but a full rewrite is probably a better plan.
    $old_config = $config;


    // Some sections should just be copied and not merged or we end
    //   up unable to sync the deletion of the last item in a section
    $sync_full = array('ipsec', 'aliases', 'wol', 'load_balancer', 'openvpn', 'cert', 'ca', 'crl', 'schedules', 'filter', 'nat', 'dhcpd', 'dhcpv6');
    $sync_full_done = array();
    foreach ($sync_full as $syncfull) {
        if (isset($new_config[$syncfull])) {
            $config[$syncfull] = $new_config[$syncfull];
            unset($new_config[$syncfull]);
            $sync_full_done[] = $syncfull;
        }
    }

    $vipbackup = array();
    $oldvips = array();
    if (isset($new_config['virtualip'])) {
        if (is_array($config['virtualip']['vip'])) {
            foreach ($config['virtualip']['vip'] as $vipindex => $vip) {
                if ($vip['mode'] == "carp")
                    $oldvips["{$vip['interface']}_vip{$vip['vhid']}"] = "{$vip['password']}{$vip['advskew']}{$vip['subnet']}{$vip['subnet_bits']}{$vip['advbase']}";
                else if ($vip['mode'] == "ipalias" && (strstr($vip['interface'], "_vip") || strstr($vip['interface'], "lo0")))
                    $oldvips[$vip['subnet']] = "{$vip['interface']}{$vip['subnet']}{$vip['subnet_bits']}";
                else if (($vip['mode'] == "ipalias" || $vip['mode'] == 'proxyarp') && !(strstr($vip['interface'], "_vip") || strstr($vip['interface'], "lo0")))
                    $vipbackup[] = $vip;
            }
        }
    }

    // For vip section, first keep items sent from the master
    $config = array_merge_recursive_unique($config, $new_config);

    /* Then add ipalias and proxyarp types already defined on the backup */
    if (is_array($vipbackup) && !empty($vipbackup)) {
        if (!is_array($config['virtualip'])) {
            $config['virtualip'] = array();
        }
        if (!is_array($config['virtualip']['vip'])) {
            $config['virtualip']['vip'] = array();
        }
        foreach ($vipbackup as $vip) {
            array_unshift($config['virtualip']['vip'], $vip);
        }

    }

    /* Log what happened */
    $mergedkeys = implode(",", array_merge(array_keys($new_config), $sync_full_done));
    write_config(sprintf(gettext("Merged in config (%s sections) from XMLRPC client."), $mergedkeys));

    /*
     * The real work on handling the vips specially
     * This is a copy of intefaces_vips_configure with addition of not reloading existing/not changed carps
     */
    if (isset($new_config['virtualip']) && is_array($config['virtualip']) && is_array($config['virtualip']['vip'])) {
        $carp_setuped = false;
        $anyproxyarp = false;
        foreach ($config['virtualip']['vip'] as $vip) {
            if ($vip['mode'] == "carp" && isset($oldvips["{$vip['interface']}_vip{$vip['vhid']}"])) {
                if ($oldvips["{$vip['interface']}_vip{$vip['vhid']}"] == "{$vip['password']}{$vip['advskew']}{$vip['subnet']}{$vip['subnet_bits']}{$vip['advbase']}") {
                    if (does_vip_exist($vip)) {
                        unset($oldvips["{$vip['interface']}_vip{$vip['vhid']}"]);
                        continue; // Skip reconfiguring this vips since nothing has changed.
                    }
                }
                unset($oldvips["{$vip['interface']}_vip{$vip['vhid']}"]);
            } else if ($vip['mode'] == "ipalias" && strstr($vip['interface'], "_vip") && isset($oldvips[$vip['subnet']])) {
                if ($oldvips[$vip['subnet']] == "{$vip['interface']}{$vip['subnet']}{$vip['subnet_bits']}") {
                    if (does_vip_exist($vip)) {
                        unset($oldvips[$vip['subnet']]);
                        continue; // Skip reconfiguring this vips since nothing has changed.
                    }
                }
                unset($oldvips[$vip['subnet']]);
            }

            switch ($vip['mode']) {
                case "proxyarp":
                    $anyproxyarp = true;
                    break;
                case "ipalias":
                    interface_ipalias_configure($vip);
                    break;
                case "carp":
                    if (!$carp_setuped) {
                        $carp_setuped = true;
                    }
                    interface_carp_configure($vip);
                    break;
            }
        }
        if ($carp_setuped) {
            interfaces_carp_setup();
        }

        if ($anyproxyarp) {
            interface_proxyarp_configure();
        }

    }

    if (isset($old_config['ipsec']['enable']) !== isset($config['ipsec']['enable'])) {
        vpn_ipsec_configure();
    }


    unset($old_config);

    return true;
}

/**
 * @param $sectionKeys
 * @return array config data
 */
function backup_config_section_xmlrpc($sectionKeys)
{
    global $config;
    require_once("config.inc");

    return array_intersect_key($config, array_flip($sectionKeys));
}
