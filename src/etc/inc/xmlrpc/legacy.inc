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

