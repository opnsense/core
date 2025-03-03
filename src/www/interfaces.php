<?php

/*
 * Copyright (C) 2014-2022 Deciso B.V.
 * Copyright (C) 2010 Erik Fonnesbeck
 * Copyright (C) 2008-2010 Ermal Luçi
 * Copyright (C) 2004-2008 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2006 Daniel S. Haischt
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
 *   documentation and/or other materials provided with the distribution.
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

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("interfaces.inc");

/***************************************************************************************************************
 * imported from xmlparse_attr.inc
 ***************************************************************************************************************/

function startElement_attr($parser, $name, $attrs) {
    global $parsedcfg, $depth, $curpath, $havedata, $listtags, $parsedattrs;

    array_push($curpath, strtolower($name));

    $ptr =& $parsedcfg;
    if (!empty($attrs)) {
        $attrptr =& $parsedattrs;
        $writeattrs = true;
    }
    foreach ($curpath as $path) {
        $ptr =& $ptr[$path];
        if (isset($writeattrs)) {
            $attrptr =& $attrptr[$path];
        }
    }

    /* is it an element that belongs to a list? */
    if (in_array(strtolower($name), $listtags)) {
        /* is there an array already? */
        if (!is_array($ptr)) {
            /* make an array */
            $ptr = array();
        }

        array_push($curpath, count($ptr));

        if (isset($writeattrs)) {
            if (!is_array($attrptr)) {
                $attrptr = array();
            }
            $attrptr[count($ptr)] = $attrs;
        }
    } elseif (isset($ptr)) {
        /* multiple entries not allowed for this element, bail out */
        die(sprintf(gettext('XML error: %s at line %d cannot occur more than once') . "\n",
            $name,
            xml_get_current_line_number($parser)));
    } elseif (isset($writeattrs)) {
        $attrptr = $attrs;
    }

    $depth++;
    $havedata = $depth;
}

function endElement_attr($parser, $name) {
    global $depth, $curpath, $parsedcfg, $havedata, $listtags;

    if ($havedata == $depth) {
        $ptr =& $parsedcfg;
        foreach ($curpath as $path) {
            $ptr =& $ptr[$path];
        }
        $ptr = "";
    }

    array_pop($curpath);

    if (in_array(strtolower($name), $listtags)) {
        array_pop($curpath);
    }

    $depth--;
}

function cData_attr($parser, $data) {
    global $curpath, $parsedcfg, $havedata;

    $data = trim($data, "\t\n\r");

    if ($data != "") {
        $ptr =& $parsedcfg;
        foreach ($curpath as $path) {
            $ptr =& $ptr[$path];
        }

        if (is_string($ptr)) {
            $ptr .= html_entity_decode($data);
        } else {
            if (trim($data, " ") != "") {
                $ptr = html_entity_decode($data);
                $havedata++;
            }
        }
    }
}

function parse_xml_regdomain(&$rdattributes, $rdfile = '', $rootobj = 'regulatory-data')
{
    global $listtags;

    if (empty($rdfile)) {
        $rdfile = '/etc/regdomain.xml';
    }

    $listtags = explode(" ", "band country flags freqband netband rd");
    $parsed_xml = array();

    if (file_exists('/tmp/regdomain.cache')) {
        $parsed_xml = unserialize(file_get_contents('/tmp/regdomain.cache'));
        if (!empty($parsed_xml)) {
            $rdmain = $parsed_xml['main'];
            $rdattributes = $parsed_xml['attributes'];
        }
    }
    if (empty($parsed_xml) && file_exists('/etc/regdomain.xml')) {
        $rdmain = parse_xml_config_raw_attr($rdfile, $rootobj, $rdattributes);

        // unset parts that aren't used before making cache
        foreach ($rdmain['regulatory-domains']['rd'] as $rdkey => $rdentry) {
            if (isset($rdmain['regulatory-domains']['rd'][$rdkey]['netband'])) {
                unset($rdmain['regulatory-domains']['rd'][$rdkey]['netband']);
            }
            if (isset($rdattributes['regulatory-domains']['rd'][$rdkey]['netband'])) {
                unset($rdattributes['regulatory-domains']['rd'][$rdkey]['netband']);
            }
        }
        if (isset($rdmain['shared-frequency-bands'])) {
            unset($rdmain['shared-frequency-bands']);
        }
        if (isset($rdattributes['shared-frequency-bands'])) {
            unset($rdattributes['shared-frequency-bands']);
        }

        $parsed_xml = array('main' => $rdmain, 'attributes' => $rdattributes);
        $rdcache = fopen('/tmp/regdomain.cache', 'w');
        fwrite($rdcache, serialize($parsed_xml));
        fclose($rdcache);
    }

    return $rdmain;
}

function parse_xml_config_raw_attr($cffile, $rootobj, &$parsed_attributes, $isstring = "false") {
    global $depth, $curpath, $parsedcfg, $havedata, $parsedattrs;
    $parsedcfg = array();
    $curpath = array();
    $depth = 0;
    $havedata = 0;

    if (isset($parsed_attributes)) {
        $parsedattrs = array();
    }

    $xml_parser = xml_parser_create();

    xml_set_element_handler($xml_parser, "startElement_attr", "endElement_attr");
    xml_set_character_data_handler($xml_parser, "cData_attr");
    xml_parser_set_option($xml_parser,XML_OPTION_SKIP_WHITE, 1);

    if (!($fp = fopen($cffile, "r"))) {
        log_msg('Error: could not open XML input', LOG_ERR);
        if (isset($parsed_attributes)) {
            $parsed_attributes = array();
            unset($parsedattrs);
        }
        return -1;
    }

    while ($data = fread($fp, 4096)) {
        if (!xml_parse($xml_parser, $data, feof($fp))) {
            log_msg(sprintf('XML error: %s at line %d' . "\n",
                  xml_error_string(xml_get_error_code($xml_parser)),
                  xml_get_current_line_number($xml_parser)), LOG_ERR);
            if (isset($parsed_attributes)) {
                $parsed_attributes = array();
                unset($parsedattrs);
            }
            return -1;
        }
    }
    xml_parser_free($xml_parser);

    if (!$parsedcfg[$rootobj]) {
        log_msg(sprintf('XML error: no %s object found!', $rootobj), LOG_ERR);
        if (isset($parsed_attributes)) {
            $parsed_attributes = array();
            unset($parsedattrs);
        }
        return -1;
    }

    if (isset($parsed_attributes)) {
        if ($parsedattrs[$rootobj]) {
            $parsed_attributes = $parsedattrs[$rootobj];
        }
        unset($parsedattrs);
    }

    return $parsedcfg[$rootobj];
}

/***************************************************************************************************************
 * End of import
 ***************************************************************************************************************/

function test_wireless_capability($if, $cap)
{
    $caps = ['hostap' => 'HOSTAP', 'adhoc' => 'IBSS'];

    if (!isset($caps[$cap])) {
        return false;
    }

    exec(sprintf('/sbin/ifconfig %s list caps', escapeshellarg($if)), $lines);

    foreach ($lines as $line) {
        if (preg_match("/^drivercaps=.*<.*{$caps[$cap]}.*>$/", $line)) {
            return true;
        }
    }

    return false;
}

/* return wireless modes and channels */
function get_wireless_modes($interface)
{
    $wireless_modes = [];

    $cloned_interface = get_real_interface($interface);
    if ($cloned_interface) {
        $chan_list = shell_safe('/sbin/ifconfig -v %s list chan', $cloned_interface);
        $matches = [];

        preg_match_all('/Channel\s+([^\s]+)\s+:\s+[^\s]+\s+[^\s]+\s+([^\s]+(?:\sht(?:\/[^\s]+)?)?)/', $chan_list, $matches);

        $interface_channels = [];

        foreach (array_keys($matches[0]) as $i) {
            $interface_channels[] = [$matches[1][$i], $matches[2][$i]];
        }

        array_multisort($interface_channels);

        foreach ($interface_channels as $wireless_info) {
            /* XXX discard possible channel width for now */
            $wireless_mode = explode('/', $wireless_info[1])[0];
            $wireless_channel = (string)$wireless_info[0];
            switch ($wireless_mode) {
                case '11g ht':
                    $wireless_mode = '11ng';
                    break;
                case '11a ht':
                    $wireless_mode = '11na';
                    break;
                default:
                    break;
            }
            $wireless_modes[$wireless_mode][] = $wireless_channel;
        }

        ksort($wireless_modes);
    }

    return $wireless_modes;
}

/* return channel numbers, frequency, max txpower, and max regulation txpower */
function get_wireless_channel_info($interface)
{
    $wireless_channels = [];

    $cloned_interface = get_real_interface($interface);
    if ($cloned_interface) {
        $chan_list = shell_safe('/sbin/ifconfig %s list txpower', $cloned_interface);
        $matches = [];

        preg_match_all('/Channel\s+([^\s]+)\s+:\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+[^\s]+\s+([^\s]+)/', $chan_list, $matches);

        foreach (array_keys($matches[0]) as $i) {
            $wireless_channels[$matches[1][$i]] = "{$matches[2][$i]} {$matches[3][$i]}@{$matches[4][$i]}/{$matches[5][$i]}";
        }

        ksort($wireless_channels);
    }

    return $wireless_channels;
}

$ifdescrs = legacy_config_get_interfaces(['virtual' => false]);
$hwifs = array_keys(get_interface_list());

$a_interfaces = &config_read_array('interfaces');
$a_ppps = &config_read_array('ppps', 'ppp');

$a_cert = isset($config['cert']) ? $config['cert'] : array();
$a_ca = isset($config['ca']) ? $config['ca'] : array();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['if']) && !empty($a_interfaces[$_GET['if']])) {
        $if = $_GET['if'];
    } else {
        // no interface provided, redirect to interface assignments
        header(url_safe('Location: /interfaces_assign.php'));
        exit;
    }

    /* locate PPP details (if any) */
    $pppid = count($a_ppps);
    foreach ($a_ppps as $key => $ppp) {
        if ($a_interfaces[$if]['if'] == $ppp['if']) {
            $pppid = $key;
            break;
        }
    }

    $pconfig = [];
    $std_copy_fieldnames = [
        'adv_dhcp6_authentication_statement_algorithm',
        'adv_dhcp6_authentication_statement_authname',
        'adv_dhcp6_authentication_statement_protocol',
        'adv_dhcp6_authentication_statement_rdm',
        'adv_dhcp6_config_advanced',
        'adv_dhcp6_config_file_override',
        'adv_dhcp6_config_file_override_path',
        'adv_dhcp6_id_assoc_statement_address',
        'adv_dhcp6_id_assoc_statement_address_enable',
        'adv_dhcp6_id_assoc_statement_address_id',
        'adv_dhcp6_id_assoc_statement_address_pltime',
        'adv_dhcp6_id_assoc_statement_address_vltime',
        'adv_dhcp6_id_assoc_statement_prefix',
        'adv_dhcp6_id_assoc_statement_prefix_enable',
        'adv_dhcp6_id_assoc_statement_prefix_id',
        'adv_dhcp6_id_assoc_statement_prefix_pltime',
        'adv_dhcp6_id_assoc_statement_prefix_vltime',
        'adv_dhcp6_interface_statement_information_only_enable',
        'adv_dhcp6_interface_statement_request_options',
        'adv_dhcp6_interface_statement_script',
        'adv_dhcp6_interface_statement_send_options',
        'adv_dhcp6_key_info_statement_expire',
        'adv_dhcp6_key_info_statement_keyid',
        'adv_dhcp6_key_info_statement_keyname',
        'adv_dhcp6_key_info_statement_realm',
        'adv_dhcp6_key_info_statement_secret',
        'adv_dhcp6_prefix_interface_statement_sla_len',
        'adv_dhcp_config_advanced',
        'adv_dhcp_config_file_override',
        'adv_dhcp_config_file_override_path',
        'adv_dhcp_option_modifiers',
        'adv_dhcp_pt_backoff_cutoff',
        'adv_dhcp_pt_initial_interval',
        'adv_dhcp_pt_reboot',
        'adv_dhcp_pt_retry',
        'adv_dhcp_pt_select_timeout',
        'adv_dhcp_pt_timeout',
        'adv_dhcp_pt_values',
        'adv_dhcp_request_options',
        'adv_dhcp_required_options',
        'adv_dhcp_send_options',
        'alias-address',
        'alias-subnet',
        'descr',
        'dhcp6-ia-pd-len',
        'dhcp6-prefix-id',
        'dhcp6_ifid',
        'dhcp6vlanprio',
        'dhcphostname',
        'dhcprejectfrom',
        'dhcpvlanprio',
        'disablechecksumoffloading',
        'disablelargereceiveoffloading',
        'disablesegmentationoffloading',
        'disablevlanhwfilter',
        'gateway',
        'gateway-6rd',
        'gatewayv6',
        'hw_settings_overwrite',
        'if',
        'ipaddr',
        'ipaddrv6',
        'media',
        'mediaopt',
        'mss',
        'mtu',
        'prefix-6rd',
        'prefix-6rd-v4addr',
        'prefix-6rd-v4plen',
        'spoofmac',
        'subnet',
        'subnetv6',
        'track6-interface',
        'track6-prefix-id',
        'track6_ifid',
    ];
    foreach ($std_copy_fieldnames as $fieldname) {
        $pconfig[$fieldname] = isset($a_interfaces[$if][$fieldname]) ? $a_interfaces[$if][$fieldname] : null;
    }
    $pconfig['enable'] = isset($a_interfaces[$if]['enable']);
    $pconfig['lock'] = isset($a_interfaces[$if]['lock']);
    $pconfig['blockpriv'] = !empty($a_interfaces[$if]['blockpriv']);
    $pconfig['blockbogons'] = !empty($a_interfaces[$if]['blockbogons']);
    $pconfig['gateway_interface'] = isset($a_interfaces[$if]['gateway_interface']);
    $pconfig['promisc'] = isset($a_interfaces[$if]['promisc']);
    $pconfig['dhcpoverridemtu'] = empty($a_interfaces[$if]['dhcphonourmtu']) ? true : null;
    $pconfig['dhcp6-ia-pd-send-hint'] = isset($a_interfaces[$if]['dhcp6-ia-pd-send-hint']);
    $pconfig['dhcp6prefixonly'] = isset($a_interfaces[$if]['dhcp6prefixonly']);
    $pconfig['track6-prefix-id--hex'] = sprintf("%x", empty($pconfig['track6-prefix-id']) ? 0 : $pconfig['track6-prefix-id']);
    $pconfig['track6_ifid--hex'] = isset($pconfig['track6_ifid']) && $pconfig['track6_ifid'] != '' ? sprintf("%x", $pconfig['track6_ifid']) : '';
    $pconfig['dhcp6-prefix-id--hex'] = isset($pconfig['dhcp6-prefix-id']) && $pconfig['dhcp6-prefix-id'] != '' ? sprintf("%x", $pconfig['dhcp6-prefix-id']) : '';
    $pconfig['dhcp6_ifid--hex'] = isset($pconfig['dhcp6_ifid']) && $pconfig['dhcp6_ifid'] != '' ? sprintf("%x", $pconfig['dhcp6_ifid']) : '';
    $pconfig['dhcpd6track6allowoverride'] = isset($a_interfaces[$if]['dhcpd6track6allowoverride']);

    $pconfig['ports'] = isset($a_ppps[$pppid]['ports']) ? $a_ppps[$pppid]['ports'] : null;

    // ipv4 type (from ipaddr)
    if (is_ipaddrv4($pconfig['ipaddr'])) {
        $pconfig['type'] = "staticv4";
    } else {
        if (empty($pconfig['ipaddr'])) {
            $pconfig['type'] = "none";
        } else {
            $pconfig['type'] = $pconfig['ipaddr'];
        }
        $pconfig['ipaddr'] = null;
    }

    // ipv6 type (from ipaddrv6)
    if (is_ipaddrv6($pconfig['ipaddrv6'])) {
        $pconfig['type6'] = "staticv6";
    } else {
        if (empty($pconfig['ipaddrv6'])) {
            $pconfig['type6'] = "none";
        } else {
            $pconfig['type6'] = $pconfig['ipaddrv6'];
        }
        $pconfig['ipaddrv6'] = null;
    }

    if (isset($a_interfaces[$if]['wireless'])) {
        config_read_array('interfaces', $if, 'wireless');
        /* Sync first to be sure it displays the actual settings that will be used */
        interface_sync_wireless_clones($a_interfaces[$if], false);
        /* Get wireless modes */
        _interfaces_wlan_clone(get_real_interface($if), $a_interfaces[$if]);
        $wlanbaseif = interface_get_wireless_base($a_interfaces[$if]['if']);
        $std_wl_copy_fieldnames = array(
          'standard', 'mode','protmode', 'ssid', 'channel', 'txpower', 'diversity', 'txantenna', 'rxantenna',
          'regdomain', 'regcountry', 'reglocation', 'authmode', 'auth_server_addr', 'auth_server_port', 'auth_server_shared_secret',
          'auth_server_addr2', 'auth_server_port2', 'auth_server_shared_secret2', 'mac_acl'
        );
        foreach ($std_wl_copy_fieldnames as $fieldname) {
            $pconfig[$fieldname] = isset($a_interfaces[$if]['wireless'][$fieldname]) ? $a_interfaces[$if]['wireless'][$fieldname] : null;
        }
        $pconfig['persistcommonwireless'] = isset($config['wireless']['interfaces'][$wlanbaseif]);
        $pconfig['wme_enable'] = isset($a_interfaces[$if]['wireless']['wme']['enable']);
        $pconfig['apbridge_enable'] = isset($a_interfaces[$if]['wireless']['apbridge']['enable']);
        $pconfig['hidessid_enable'] = isset($a_interfaces[$if]['wireless']['hidessid']['enable']);
        $pconfig['wep_enable'] = isset($a_interfaces[$if]['wireless']['wep']['enable']);

        if (isset($a_interfaces[$if]['wireless']['puren']['enable'])) {
            $pconfig['puremode'] = '11n';
        } elseif (isset($a_interfaces[$if]['wireless']['pureg']['enable'])) {
            $pconfig['puremode'] = '11g';
        } else {
            $pconfig['puremode'] = 'any';
        }

        if (isset($a_interfaces[$if]['wireless']['wpa']) && is_array($a_interfaces[$if]['wireless']['wpa'])) {
            $std_wl_wpa_copy_fieldnames = array(
              'debug_mode', 'macaddr_acl', 'auth_algs', 'wpa_mode', 'wpa_eap_method', 'wpa_eap_p2_auth', 'wpa_key_mgmt', 'wpa_pairwise',
              'wpa_eap_cacertref', 'wpa_eap_cltcertref', 'wpa_group_rekey', 'wpa_gmk_rekey', 'identity', 'passphrase', 'ext_wpa_sw'
            );
            foreach ($std_wl_wpa_copy_fieldnames as $fieldname) {
                $pconfig[$fieldname] = isset($a_interfaces[$if]['wireless']['wpa'][$fieldname]) ? $a_interfaces[$if]['wireless']['wpa'][$fieldname] : null;
            }
            $pconfig['ieee8021x'] = isset($a_interfaces[$if]['wireless']['wpa']['ieee8021x']['enable']);
            $pconfig['rsn_preauth'] = isset($a_interfaces[$if]['wireless']['wpa']['rsn_preauth']);
            $pconfig['mac_acl_enable'] = isset($a_interfaces[$if]['wireless']['wpa']['mac_acl_enable']);
            $pconfig['wpa_strict_rekey'] = isset($a_interfaces[$if]['wireless']['wpa']['wpa_strict_rekey']);
            $pconfig['wpa_enable'] = isset($a_interfaces[$if]['wireless']['wpa']['enable']);
        }
        if (!empty($a_interfaces[$if]['wireless']['wep']['key'])) {
            $i = 1;
            foreach ($a_interfaces[$if]['wireless']['wep']['key'] as $wepkey) {
                $pconfig['key' . $i] = $wepkey['value'];
                if (isset($wepkey['txkey'])) {
                    $pconfig['txkey'] = $i;
                }
                $i++;
            }
            if (!isset($wepkey['txkey'])) {
                $pconfig['txkey'] = 1;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;

    $input_errors = array();
    if (!empty($_POST['if']) && !empty($a_interfaces[$_POST['if']])) {
        $if = $_POST['if'];
        // read physical interface name from config.xml
        $pconfig['if'] = $a_interfaces[$if]['if'];
    }
    $ifgroup = !empty($_GET['group']) ? $_GET['group'] : '';

    /* locate PPP details (if any) */
    $pppid = count($a_ppps);
    foreach ($a_ppps as $key => $ppp) {
        if ($a_interfaces[$if]['if'] == $ppp['if']) {
            $pppid = $key;
            break;
        }
    }

    $pconfig['ports'] = isset($a_ppps[$pppid]['ports']) ? $a_ppps[$pppid]['ports'] : null;

    if (!empty($pconfig['apply'])) {
        if (!is_subsystem_dirty('interfaces')) {
            $intput_errors[] = gettext("You have already applied your settings!");
        } else {
            if (file_exists('/tmp/.interfaces.apply')) {
                $toapplylist = unserialize(file_get_contents('/tmp/.interfaces.apply'));
                foreach ($toapplylist as $ifapply => $ifcfgo) {
                    interface_reset($ifapply, $ifcfgo, isset($ifcfgo['enable']));
                    interface_configure(false, $ifapply, true);
                }

                system_routing_configure(false, array_keys($toapplylist));
                filter_configure();
                configd_run('webgui restart 3', true);
            }

            clear_subsystem_dirty('interfaces');
            @unlink('/tmp/.interfaces.apply');
        }
        if (!empty($ifgroup)) {
            header(url_safe('Location: /interfaces.php?if=%s&group=%s', array($if, $ifgroup)));
        } else {
            header(url_safe('Location: /interfaces.php?if=%s', array($if)));
        }
        exit;
    } elseif (empty($pconfig['enable'])) {
        if (isset($a_interfaces[$if]['enable'])) {
            unset($a_interfaces[$if]['enable']);
        }
        if (!empty($pconfig['lock'])) {
            $a_interfaces[$if]['lock'] = true;
        } elseif (isset($a_interfaces[$if]['lock'])) {
            unset($a_interfaces[$if]['lock']);
        }
        if (isset($a_interfaces[$if]['wireless'])) {
            config_read_array('interfaces', $if, 'wireless');
            interface_sync_wireless_clones($a_interfaces[$if], false);
        }
        $a_interfaces[$if]['descr'] = preg_replace('/[^a-z_0-9]/i', '', $pconfig['descr']);

        write_config("Interface {$pconfig['descr']}({$if}) is now disabled.");
        mark_subsystem_dirty('interfaces');
        if (file_exists('/tmp/.interfaces.apply')) {
            $toapplylist = unserialize(file_get_contents('/tmp/.interfaces.apply'));
        } else {
            $toapplylist = array();
        }
        if (empty($toapplylist[$if])) {
            // only flush if the running config is not in our list yet
            $toapplylist[$if]['ifcfg'] = $a_interfaces[$if];
            $toapplylist[$if]['ifcfg']['realif'] = get_real_interface($if);
            $toapplylist[$if]['ifcfg']['realifv6'] = get_real_interface($if, "inet6");
            $toapplylist[$if]['ppps'] = $a_ppps;
            file_put_contents('/tmp/.interfaces.apply', serialize($toapplylist));
        }
        if (!empty($ifgroup)) {
            header(url_safe('Location: /interfaces.php?if=%s&group=%s', array($if, $ifgroup)));
        } else {
            header(url_safe('Location: /interfaces.php?if=%s', array($if)));
        }
        exit;
    } else {
        foreach ($ifdescrs as $ifent => $ifcfg) {
            if ($if != $ifent && $ifcfg['descr'] == $pconfig['descr']) {
                $input_errors[] = gettext("An interface with the specified description already exists.");
                break;
            }
        }

        if (isset($config['dhcpd']) && isset($config['dhcpd'][$if]['enable']) && !preg_match('/^staticv4/', $pconfig['type'])) {
            $input_errors[] = gettext("The DHCP Server is active on this interface and it can be used only with a static IP configuration. Please disable the DHCP Server service on this interface first, then change the interface configuration.");
        }
        if (isset($config['dhcpdv6']) && isset($config['dhcpdv6'][$if]['enable']) && !preg_match('/^staticv6/', $pconfig['type6']) && !isset($pconfig['dhcpd6track6allowoverride'])) {
            $input_errors[] = gettext("The DHCPv6 Server is active on this interface and it can be used only with a static IPv6 configuration. Please disable the DHCPv6 Server service on this interface first, then change the interface configuration.");
        }

        foreach (plugins_devices() as $device) {
            if (!preg_match('/' . $device['pattern'] . '/', $pconfig['if'])) {
                continue;
            }

            if (!$device['configurable']) {
                if ($pconfig['type'] != 'none' || $pconfig['type6'] != 'none') {
                    $input_errors[] = gettext('Cannot assign an IP configuration type to a tunnel interface.');
                }
            }

            if (isset($device['spoofmac']) && $device['spoofmac'] == false) {
                if (!empty($pconfig['spoofmac'])) {
                    $input_errors[] = gettext('Cannot assign a MAC address to this type of interface.');
                }
            }
        }

        switch ($pconfig['type']) {
            case "staticv4":
                $reqdfields = explode(" ", "ipaddr subnet gateway");
                $reqdfieldsn = array(gettext("IPv4 address"),gettext("Subnet bit count"),gettext("Gateway"));
                do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
                break;
            case "dhcp":
                if (!empty($pconfig['adv_dhcp_config_file_override'] && !file_exists($pconfig['adv_dhcp_config_file_override_path']))) {
                    $input_errors[] = sprintf(gettext('The DHCP override file "%s" does not exist.'), $pconfig['adv_dhcp_config_file_override_path']);
                }
                break;
        }

        switch ($pconfig['type6']) {
            case "staticv6":
                $reqdfields = explode(" ", "ipaddrv6 subnetv6 gatewayv6");
                $reqdfieldsn = array(gettext("IPv6 address"),gettext("Subnet bit count"),gettext("Gateway"));
                do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
                break;
            case 'dhcp6':
                if (!empty($pconfig['adv_dhcp6_config_file_override'] && !file_exists($pconfig['adv_dhcp6_config_file_override_path']))) {
                    $input_errors[] = sprintf(gettext('The DHCPv6 override file "%s" does not exist.'), $pconfig['adv_dhcp6_config_file_override_path']);
                }
                if (isset($pconfig['dhcp6-prefix-id--hex']) && $pconfig['dhcp6-prefix-id--hex'] != '') {
                    if (!ctype_xdigit($pconfig['dhcp6-prefix-id--hex'])) {
                        $input_errors[] = gettext("You must enter a valid hexadecimal number for the IPv6 prefix ID.");
                    } else {
                        $ipv6_delegation_length = calculate_ipv6_delegation_length($if);
                        if ($ipv6_delegation_length >= 0) {
                            $ipv6_num_prefix_ids = pow(2, $ipv6_delegation_length);
                            $dhcp6_prefix_id = intval($pconfig['dhcp6-prefix-id--hex'], 16);
                            if ($dhcp6_prefix_id < 0 || $dhcp6_prefix_id >= $ipv6_num_prefix_ids) {
                                $input_errors[] = gettext("You specified an IPv6 prefix ID that is out of range.");
                            }
                        }
                        foreach (link_interface_to_track6($pconfig['track6-interface']) as $trackif => $trackcfg) {
                            if ($trackcfg['track6-prefix-id'] == $dhcp6_prefix_id) {
                                $input_errors[] = gettext('You specified an IPv6 prefix ID that is already in use.');
                                break;
                            }
                        }
                    }
                }
                if (isset($pconfig['dhcp6_ifid--hex']) && $pconfig['dhcp6_ifid--hex'] != '') {
                    if (!ctype_xdigit($pconfig['dhcp6_ifid--hex'])) {
                        $input_errors[] = gettext('You must enter a valid hexadecimal number for the IPv6 interface ID.');
                    }
                }
                break;
            case '6rd':
                if ($pconfig['type'] == 'none') {
                    $input_errors[] = gettext('6RD requires an IPv4 configuration type to operate on.');
                }
                if (empty($pconfig['gateway-6rd']) || !is_ipaddrv4($pconfig['gateway-6rd'])) {
                    $input_errors[] = gettext('6RD border relay gateway must be a valid IPv4 address.');
                }
                if (empty($pconfig['prefix-6rd']) || !is_subnetv6($pconfig['prefix-6rd'])) {
                    $input_errors[] = gettext('6RD prefix must be a valid IPv6 subnet.');
                }
                if (!empty($pconfig['prefix-6rd-v4addr']) && !is_ipaddrv4($pconfig['prefix-6rd-v4addr'])) {
                    $input_errors[] = gettext('6RD IPv4 prefix address must be a valid IPv4 address.');
                }
                if (!is_numeric($pconfig['prefix-6rd-v4plen'])) {
                    $input_errors[] = gettext('6RD IPv4 prefix length must be a number.');
                }
                foreach (array_keys($ifdescrs) as $ifent) {
                    if ($if != $ifent && ($config['interfaces'][$ifent]['ipaddrv6'] == $pconfig['type6'])) {
                        if ($config['interfaces'][$ifent]['prefix-6rd'] == $pconfig['prefix-6rd']) {
                            $input_errors[] = gettext('You can only have one interface configured in 6rd with same prefix.');
                            break;
                        }
                    }
                }
                break;
            case '6to4':
                if ($pconfig['type'] == 'none') {
                    $input_errors[] = gettext('6to4 requires an IPv4 configuration type to operate on.');
                }
                foreach (array_keys($ifdescrs) as $ifent) {
                    if ($if != $ifent && ($config['interfaces'][$ifent]['ipaddrv6'] == $pconfig['type6'])) {
                        $input_errors[] = gettext('You can only have one interface configured as 6to4.');
                        break;
                    }
                }
                break;
            case 'track6':
                if (!empty($pconfig['track6-prefix-id--hex']) && !ctype_xdigit($pconfig['track6-prefix-id--hex'])) {
                    $input_errors[] = gettext("You must enter a valid hexadecimal number for the IPv6 prefix ID.");
                } elseif (!empty($pconfig['track6-interface'])) {
                    $ipv6_delegation_length = calculate_ipv6_delegation_length($pconfig['track6-interface']);
                    if ($ipv6_delegation_length >= 0) {
                        $ipv6_num_prefix_ids = pow(2, $ipv6_delegation_length);
                        $track6_prefix_id = intval($pconfig['track6-prefix-id--hex'], 16);
                        if ($track6_prefix_id < 0 || $track6_prefix_id >= $ipv6_num_prefix_ids) {
                            $input_errors[] = gettext("You specified an IPv6 prefix ID that is out of range.");
                        }
                        foreach (link_interface_to_track6($pconfig['track6-interface']) as $trackif => $trackcfg) {
                            if ($trackif != $if && $trackcfg['track6-prefix-id'] == $track6_prefix_id) {
                                $input_errors[] = gettext('You specified an IPv6 prefix ID that is already in use.');
                                break;
                            }
                        }
                        if (isset($config['interfaces'][$pconfig['track6-interface']]['dhcp6-prefix-id'])) {
                            if ($config['interfaces'][$pconfig['track6-interface']]['dhcp6-prefix-id'] == $track6_prefix_id) {
                                $input_errors[] = gettext('You specified an IPv6 prefix ID that is already in use.');
                            }
                        }
                    }
                }
                if (isset($pconfig['track6_ifid--hex']) && $pconfig['track6_ifid--hex'] != '') {
                    if (!ctype_xdigit($pconfig['track6_ifid--hex'])) {
                        $input_errors[] = gettext('You must enter a valid hexadecimal number for the IPv6 interface ID.');
                    }
                }
                break;
        }

        /* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
        $staticroutes = get_staticroutes(true);
        if (!empty($pconfig['ipaddr'])) {
            if (!is_ipaddrv4($pconfig['ipaddr'])) {
                $input_errors[] = gettext("A valid IPv4 address must be specified.");
            } else {
                if (is_ipaddr_configured($pconfig['ipaddr'], $if)) {
                    $input_errors[] = gettext("This IPv4 address is being used by another interface or VIP.");
                }
                /* Do not accept network or broadcast address, except if subnet is 31 or 32 */
                if ($pconfig['subnet'] < 31) {
                    if ($pconfig['ipaddr'] == gen_subnet($pconfig['ipaddr'], $pconfig['subnet'])) {
                        $input_errors[] = gettext("This IPv4 address is the network address and cannot be used");
                    } elseif ($pconfig['ipaddr'] == gen_subnet_max($pconfig['ipaddr'], $pconfig['subnet'])) {
                        $input_errors[] = gettext("This IPv4 address is the broadcast address and cannot be used");
                    }
                }

                foreach ($staticroutes as $route_subnet) {
                    list($network, $subnet) = explode("/", $route_subnet);
                    if ($pconfig['subnet'] == $subnet && $network == gen_subnet($pconfig['ipaddr'], $pconfig['subnet'])) {
                        $input_errors[] = gettext("This IPv4 address conflicts with a Static Route.");
                        break;
                    }
                    unset($network, $subnet);
                }
            }
        }
        if (!empty($pconfig['ipaddrv6'])) {
            if (!is_ipaddrv6($pconfig['ipaddrv6'])) {
                $input_errors[] = gettext("A valid IPv6 address must be specified.");
            } else {
                if (is_ipaddr_configured($pconfig['ipaddrv6'], $if)) {
                    $input_errors[] = gettext("This IPv6 address is being used by another interface or VIP.");
                }

                foreach ($staticroutes as $route_subnet) {
                    list($network, $subnet) = explode("/", $route_subnet);
                    if ($pconfig['subnetv6'] == $subnet && $network == gen_subnetv6($pconfig['ipaddrv6'], $pconfig['subnetv6'])) {
                        $input_errors[] = gettext("This IPv6 address conflicts with a Static Route.");
                        break;
                    }
                    unset($network, $subnet);
                }
            }
        }
        if (!empty($pconfig['subnet']) && !is_numeric($pconfig['subnet'])) {
            $input_errors[] = gettext("A valid subnet bit count must be specified.");
        }
        if (!empty($pconfig['subnetv6']) && !is_numeric($pconfig['subnetv6'])) {
            $input_errors[] = gettext("A valid subnet bit count must be specified.");
        }
        if (!empty($pconfig['alias-address']) && !is_ipaddrv4($pconfig['alias-address'])) {
            $input_errors[] = gettext("A valid alias IP address must be specified.");
        }
        if (!empty($pconfig['alias-subnet']) && !is_numeric($pconfig['alias-subnet'])) {
            $input_errors[] = gettext("A valid alias subnet bit count must be specified.");
        }

        if (!empty($pconfig['dhcprejectfrom'])) {
            foreach (explode(',', $pconfig['dhcprejectfrom']) as $addr) {
                if (!is_ipaddrv4($addr)) {
                    $input_errors[] = gettext('A valid IP address list must be specified to reject DHCP leases from.');
                    break;
                }
            }
        }

        if ($pconfig['gateway'] != "none" || $pconfig['gatewayv6'] != "none") {
            $match = false;
            foreach ((new \OPNsense\Routing\Gateways())->gatewayIterator() as $gateway) {
                if (in_array($pconfig['gateway'], $gateway) || in_array($pconfig['gatewayv6'], $gateway)) {
                    $match = true;
                }
            }
            if (!$match) {
                $input_errors[] = gettext("A valid gateway must be specified.");
            }
        }

        if (!empty($pconfig['spoofmac']) && !is_macaddr($pconfig['spoofmac'])) {
            $input_errors[] = gettext("A valid MAC address must be specified.");
        }

        if (!empty($pconfig['mtu'])) {
            $mtu_low = 576;
            $mtu_high = 65535;
            if ($pconfig['mtu'] < $mtu_low || $pconfig['mtu'] > $mtu_high) {
                $input_errors[] = sprintf(gettext('The MTU must be greater than %s bytes and less than %s.'), $mtu_low, $mtu_high);
            }

            if (strstr($a_interfaces[$if]['if'], 'vlan') || strstr($a_interfaces[$if]['if'], 'qinq')) {
                list ($parentif) = interface_parent_devices($a_interfaces[$if]['if']);
                $intf_details = legacy_interface_details($parentif);
                if ($intf_details['mtu'] < $pconfig['mtu']) {
                    $input_errors[] = gettext("MTU of a VLAN should not be bigger than parent interface.");
                }
            } else {
                foreach ($config['interfaces'] as $idx => $ifdata) {
                    if ($idx == $if || !strstr($ifdata['if'], 'vlan') || !strstr($ifdata['if'], 'qinq')) {
                        continue;
                    }

                    list ($parentif) = interface_parent_devices($ifdata['if']);
                    if ($parentif != $a_interfaces[$if]['if']) {
                        continue;
                    }

                    if (isset($ifdata['mtu']) && $ifdata['mtu'] > $pconfig['mtu']) {
                        $input_errors[] = sprintf(gettext("Interface %s (VLAN) has MTU set to a bigger value"), $ifdata['descr']);
                    }
                }
            }
        }
        if (!empty($pconfig['mss']) && $pconfig['mss'] < 576) {
            $input_errors[] = gettext("The MSS must be greater than 576 bytes.");
        }
        /*
          Wireless interface
        */
        if (isset($a_interfaces[$if]['wireless'])) {
            config_read_array('interfaces', $if, 'wireless');
            $reqdfields = array("mode");
            $reqdfieldsn = array(gettext("Mode"));
            if ($pconfig['mode'] == 'hostap') {
                $reqdfields[] = "ssid";
                $reqdfieldsn[] = gettext("SSID");
            }
            do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

            /* XXX validations should not even perform temporary actions, needs serious fixing at some point */
            if (empty($a_interfaces[$if]['wireless']['mode'])) {
                $a_interfaces[$if]['wireless']['mode'] = 'bss';
            }
            if ($a_interfaces[$if]['wireless']['mode'] != $pconfig['mode']) {
                $wlanbaseif = interface_get_wireless_base($a_interfaces[$if]['if']);
                $clone_count = does_interface_exist("{$wlanbaseif}_wlan0") ? 1 : 0;
                if (!empty($config['wireless']['clone'])) {
                    foreach ($config['wireless']['clone'] as $clone) {
                        if ($clone['if'] == $wlanbaseif) {
                            $clone_count++;
                        }
                    }
                }
                if ($clone_count > 1) {
                    $wlanif = get_real_interface($if);
                    $a_interfaces[$if]['wireless']['mode'] = $pconfig['mode'];
                    if (empty(_interfaces_wlan_clone("{$wlanif}_", $a_interfaces[$if]))) {
                        $input_errors[] = sprintf(gettext("Unable to change mode to %s. You may already have the maximum number of wireless clones supported in this mode."), $wlan_modes[$a_interfaces[$if]['wireless']['mode']]);
                    } else {
                        legacy_interface_destroy("{$wlanif}_");
                    }
                }
            }

            /* loop through keys and enforce size */
            for ($i = 1; $i <= 4; $i++) {
                if ($pconfig['key' . $i]) {
                    if (strlen($pconfig['key' . $i]) == 5) {
                        /* 64 bit */
                        continue;
                    } elseif (strlen($pconfig['key' . $i]) == 10) {
                        /* hex key */
                        if (stristr($pconfig['key' . $i], "0x") == false) {
                            $pconfig['key' . $i] = "0x" . $pconfig['key' . $i];
                        }
                        continue;
                    } elseif (strlen($pconfig['key' . $i]) == 12) {
                        /* hex key */
                        if (stristr($pconfig['key' . $i], "0x") == false) {
                            $pconfig['key' . $i] = "0x" . $pconfig['key' . $i];
                        }
                        continue;
                    } elseif (strlen($pconfig['key' . $i]) == 13) {
                        /* 128 bit */
                        continue;
                    } elseif (strlen($pconfig['key' . $i]) == 26) {
                        /* hex key */
                        if (stristr($pconfig['key' . $i], "0x") == false)
                          $_POST['key' . $i] = "0x" . $pconfig['key' . $i];
                        continue;
                    } elseif (strlen($pconfig['key' . $i]) == 28) {
                        continue;
                    } else {
                        $input_errors[] = gettext("Invalid WEP key size. Sizes should be 40 (64) bit keys or 104 (128) bit.");
                    }
                }
            }

            if (!empty($pconfig['passphrase'])) {
                $passlen = strlen($pconfig['passphrase']);
                if ($passlen < 8 || $passlen > 63) {
                    $input_errors[] = gettext("The length of the passphrase should be between 8 and 63 characters.");
                }
            }
        }
        // save form data
        if (count($input_errors) == 0) {
            $old_config = $a_interfaces[$if];
            // retrieve our interface names before anything changes
            $old_config['realif'] = get_real_interface($if);
            $old_config['realifv6'] = get_real_interface($if, "inet6");
            $new_config = array();

            // copy physical interface data (wireless is a strange case, partly managed via interface_sync_wireless_clones)
            $new_config['if'] = $old_config['if'];
            if (isset($old_config['wireless'])) {
                $new_config['wireless'] = $old_config['wireless'];
            }
            //
            $new_config['descr'] = preg_replace('/[^a-z_0-9]/i', '', $pconfig['descr']);
            $new_config['enable'] = !empty($pconfig['enable']);
            $new_config['lock'] = !empty($pconfig['lock']);
            $new_config['spoofmac'] = $pconfig['spoofmac'];

            $new_config['blockpriv'] = !empty($pconfig['blockpriv']);
            $new_config['blockbogons'] = !empty($pconfig['blockbogons']);
            $new_config['gateway_interface'] = !empty($pconfig['gateway_interface']);
            $new_config['promisc'] = !empty($pconfig['promisc']);
            if (!empty($pconfig['mtu'])) {
                $new_config['mtu'] = $pconfig['mtu'];
            }
            if (!empty($pconfig['mss'])) {
                $new_config['mss'] = $pconfig['mss'];
            }
            if (!empty($pconfig['mediaopt'])) {
                $mediaopts = explode(' ', $pconfig['mediaopt']);
                if (isset($mediaopts[0])) {
                    $new_config['media'] = $mediaopts[0];
                }
                if (isset($mediaopts[0])) {
                    $new_config ['mediaopt'] = $mediaopts[1];
                }
            }

            // switch ipv4 config by type
            switch ($pconfig['type']) {
                case "staticv4":
                    $new_config['ipaddr'] = $pconfig['ipaddr'];
                    $new_config['subnet'] = $pconfig['subnet'];
                    if ($pconfig['gateway'] != "none") {
                        $new_config['gateway'] = $pconfig['gateway'];
                    }
                    break;
                case "dhcp":
                    $new_config['ipaddr'] = "dhcp";
                    $new_config['dhcphostname'] = $pconfig['dhcphostname'];
                    $new_config['alias-address'] = $pconfig['alias-address'];
                    $new_config['alias-subnet'] = $pconfig['alias-subnet'];
                    $new_config['dhcprejectfrom'] = $pconfig['dhcprejectfrom'];
                    $new_config['adv_dhcp_pt_timeout'] = $pconfig['adv_dhcp_pt_timeout'];
                    $new_config['adv_dhcp_pt_retry'] = $pconfig['adv_dhcp_pt_retry'];
                    $new_config['adv_dhcp_pt_select_timeout'] = $pconfig['adv_dhcp_pt_select_timeout'];
                    $new_config['adv_dhcp_pt_reboot'] = $pconfig['adv_dhcp_pt_reboot'];
                    $new_config['adv_dhcp_pt_backoff_cutoff'] = $pconfig['adv_dhcp_pt_backoff_cutoff'];
                    $new_config['adv_dhcp_pt_initial_interval'] = $pconfig['adv_dhcp_pt_initial_interval'];
                    $new_config['adv_dhcp_pt_values'] = $pconfig['adv_dhcp_pt_values'];
                    $new_config['adv_dhcp_send_options'] = $pconfig['adv_dhcp_send_options'];
                    $new_config['adv_dhcp_request_options'] = $pconfig['adv_dhcp_request_options'];
                    $new_config['adv_dhcp_required_options'] = $pconfig['adv_dhcp_required_options'];
                    $new_config['adv_dhcp_option_modifiers'] = $pconfig['adv_dhcp_option_modifiers'];
                    $new_config['adv_dhcp_config_advanced'] = $pconfig['adv_dhcp_config_advanced'];
                    $new_config['adv_dhcp_config_file_override'] = $pconfig['adv_dhcp_config_file_override'];
                    $new_config['adv_dhcp_config_file_override_path'] = $pconfig['adv_dhcp_config_file_override_path'];
                    /* flipped in GUI on purpose */
                    if (empty($pconfig['dhcpoverridemtu'])) {
                        $new_config['dhcphonourmtu'] = true;
                    }
                    if (isset($pconfig['dhcpvlanprio']) && $pconfig['dhcpvlanprio'] !== '') {
                        $new_config['dhcpvlanprio'] = $pconfig['dhcpvlanprio'];
                    }
                    break;
                case 'l2tp':
                case 'ppp':
                case 'pppoe':
                case 'pptp':
                    $new_config['ipaddr'] = $pconfig['type'];
                    break;
            }

            // switch ipv6 config by type
            switch ($pconfig['type6']) {
                case 'staticv6':
                    $new_config['ipaddrv6'] = $pconfig['ipaddrv6'];
                    $new_config['subnetv6'] = $pconfig['subnetv6'];
                    if ($pconfig['gatewayv6'] != 'none') {
                        $new_config['gatewayv6'] = $pconfig['gatewayv6'];
                    }
                    break;
                case 'slaac':
                    $new_config['ipaddrv6'] = 'slaac';
                    break;
                case 'dhcp6':
                    $new_config['ipaddrv6'] = 'dhcp6';
                    $new_config['dhcp6-ia-pd-len'] = $pconfig['dhcp6-ia-pd-len'];
                    if (!empty($pconfig['dhcp6-ia-pd-send-hint'])) {
                        $new_config['dhcp6-ia-pd-send-hint'] = true;
                    }
                    if (!empty($pconfig['dhcp6prefixonly'])) {
                        $new_config['dhcp6prefixonly'] = true;
                    }
                    if (isset($pconfig['dhcp6vlanprio']) && $pconfig['dhcp6vlanprio'] !== '') {
                        $new_config['dhcp6vlanprio'] = $pconfig['dhcp6vlanprio'];
                    }
                    if (isset($pconfig['dhcp6-prefix-id--hex']) && ctype_xdigit($pconfig['dhcp6-prefix-id--hex'])) {
                        $new_config['dhcp6-prefix-id'] = intval($pconfig['dhcp6-prefix-id--hex'], 16);
                    }
                    if (isset($pconfig['dhcp6_ifid--hex']) && ctype_xdigit($pconfig['dhcp6_ifid--hex'])) {
                        $new_config['dhcp6_ifid'] = intval($pconfig['dhcp6_ifid--hex'], 16);
                    }
                    $new_config['adv_dhcp6_interface_statement_send_options'] = $pconfig['adv_dhcp6_interface_statement_send_options'];
                    $new_config['adv_dhcp6_interface_statement_request_options'] = $pconfig['adv_dhcp6_interface_statement_request_options'];
                    $new_config['adv_dhcp6_interface_statement_information_only_enable'] = $pconfig['adv_dhcp6_interface_statement_information_only_enable'];
                    $new_config['adv_dhcp6_interface_statement_script'] = $pconfig['adv_dhcp6_interface_statement_script'];
                    $new_config['adv_dhcp6_id_assoc_statement_address_enable'] = $pconfig['adv_dhcp6_id_assoc_statement_address_enable'];
                    $new_config['adv_dhcp6_id_assoc_statement_address'] = $pconfig['adv_dhcp6_id_assoc_statement_address'];
                    $new_config['adv_dhcp6_id_assoc_statement_address_id'] = $pconfig['adv_dhcp6_id_assoc_statement_address_id'];
                    $new_config['adv_dhcp6_id_assoc_statement_address_pltime'] = $pconfig['adv_dhcp6_id_assoc_statement_address_pltime'];
                    $new_config['adv_dhcp6_id_assoc_statement_address_vltime'] = $pconfig['adv_dhcp6_id_assoc_statement_address_vltime'];
                    $new_config['adv_dhcp6_id_assoc_statement_prefix_enable'] = $pconfig['adv_dhcp6_id_assoc_statement_prefix_enable'];
                    $new_config['adv_dhcp6_id_assoc_statement_prefix'] = $pconfig['adv_dhcp6_id_assoc_statement_prefix'];
                    $new_config['adv_dhcp6_id_assoc_statement_prefix_id'] = $pconfig['adv_dhcp6_id_assoc_statement_prefix_id'];
                    $new_config['adv_dhcp6_id_assoc_statement_prefix_pltime'] = $pconfig['adv_dhcp6_id_assoc_statement_prefix_pltime'];
                    $new_config['adv_dhcp6_id_assoc_statement_prefix_vltime'] = $pconfig['adv_dhcp6_id_assoc_statement_prefix_vltime'];
                    $new_config['adv_dhcp6_prefix_interface_statement_sla_len'] = $pconfig['adv_dhcp6_prefix_interface_statement_sla_len'];
                    $new_config['adv_dhcp6_authentication_statement_authname'] = $pconfig['adv_dhcp6_authentication_statement_authname'];
                    $new_config['adv_dhcp6_authentication_statement_protocol'] = $pconfig['adv_dhcp6_authentication_statement_protocol'];
                    $new_config['adv_dhcp6_authentication_statement_algorithm'] = $pconfig['adv_dhcp6_authentication_statement_algorithm'];
                    $new_config['adv_dhcp6_authentication_statement_rdm'] = $pconfig['adv_dhcp6_authentication_statement_rdm'];
                    $new_config['adv_dhcp6_key_info_statement_keyname'] = $pconfig['adv_dhcp6_key_info_statement_keyname'];
                    $new_config['adv_dhcp6_key_info_statement_realm'] = $pconfig['adv_dhcp6_key_info_statement_realm'];
                    $new_config['adv_dhcp6_key_info_statement_keyid'] = $pconfig['adv_dhcp6_key_info_statement_keyid'];
                    $new_config['adv_dhcp6_key_info_statement_secret'] = $pconfig['adv_dhcp6_key_info_statement_secret'];
                    $new_config['adv_dhcp6_key_info_statement_expire'] = $pconfig['adv_dhcp6_key_info_statement_expire'];
                    $new_config['adv_dhcp6_config_advanced'] = $pconfig['adv_dhcp6_config_advanced'];
                    $new_config['adv_dhcp6_config_file_override'] = $pconfig['adv_dhcp6_config_file_override'];
                    $new_config['adv_dhcp6_config_file_override_path'] = $pconfig['adv_dhcp6_config_file_override_path'];
                    break;
                case '6rd':
                    $new_config['ipaddrv6'] = '6rd';
                    $new_config['prefix-6rd'] = $pconfig['prefix-6rd'];
                    $new_config['prefix-6rd-v4addr'] = $pconfig['prefix-6rd-v4addr'];
                    $new_config['prefix-6rd-v4plen'] = $pconfig['prefix-6rd-v4plen'];
                    $new_config['gateway-6rd'] = $pconfig['gateway-6rd'];
                    break;
                case '6to4':
                    $new_config['ipaddrv6'] = '6to4';
                    break;
                case 'pppoev6':
                    $new_config['ipaddrv6'] = 'pppoev6';
                    break;
                case 'track6':
                    $new_config['ipaddrv6'] = 'track6';
                    $new_config['track6-interface'] = $pconfig['track6-interface'];
                    $new_config['track6-prefix-id'] = 0;
                    if (ctype_xdigit($pconfig['track6-prefix-id--hex'])) {
                        $new_config['track6-prefix-id'] = intval($pconfig['track6-prefix-id--hex'], 16);
                    }
                    if (isset($pconfig['track6_ifid--hex']) && ctype_xdigit($pconfig['track6_ifid--hex'])) {
                        $new_config['track6_ifid'] = intval($pconfig['track6_ifid--hex'], 16);
                    }
                    $new_config['dhcpd6track6allowoverride'] = !empty($pconfig['dhcpd6track6allowoverride']);
                    break;
            }

            // wireless
            if (isset($new_config['wireless'])) {
                $new_config['wireless']['wpa'] = array();
                $new_config['wireless']['wme'] = array();
                $new_config['wireless']['wep'] = array();
                $new_config['wireless']['hidessid'] = array();
                $new_config['wireless']['pureg'] = array();
                $new_config['wireless']['puren'] = array();
                $new_config['wireless']['ieee8021x'] = array();
                $new_config['wireless']['standard'] = $pconfig['standard'];
                $new_config['wireless']['mode'] = $pconfig['mode'];
                $new_config['wireless']['protmode'] = $pconfig['protmode'];
                $new_config['wireless']['ssid'] = $pconfig['ssid'];
                $new_config['wireless']['hidessid']['enable'] = !empty($pconfig['hidessid_enable']);
                $new_config['wireless']['channel'] = $pconfig['channel'];
                $new_config['wireless']['authmode'] = $pconfig['authmode'];
                $new_config['wireless']['txpower'] = $pconfig['txpower'];
                $new_config['wireless']['regdomain'] = $pconfig['regdomain'];
                $new_config['wireless']['regcountry'] = $pconfig['regcountry'];
                $new_config['wireless']['reglocation'] = $pconfig['reglocation'];
                if (!empty($pconfig['regcountry']) && !empty($pconfig['reglocation'])) {
                    $wl_regdomain_xml_attr = array();
                    $wl_regdomain_xml = parse_xml_regdomain($wl_regdomain_xml_attr);
                    $wl_countries_attr = &$wl_regdomain_xml_attr['country-codes']['country'];

                    foreach($wl_countries_attr as $wl_country) {
                        if ($pconfig['regcountry'] == $wl_country['ID']) {
                            $new_config['wireless']['regdomain'] = $wl_country['rd'][0]['REF'];
                            break;
                        }
                    }
                }
                if (isset($pconfig['diversity']) && is_numeric($pconfig['diversity'])) {
                    $new_config['wireless']['diversity'] = $pconfig['diversity'];
                } elseif (isset($new_config['wireless']['diversity'])) {
                    unset($new_config['wireless']['diversity']);
                }
                if (isset($pconfig['txantenna']) && is_numeric($pconfig['txantenna'])) {
                    $new_config['wireless']['txantenna'] = $pconfig['txantenna'];
                } elseif (isset($new_config['wireless']['txantenna'])) {
                    unset($new_config['wireless']['txantenna']);
                }
                if (isset($pconfig['rxantenna']) && is_numeric($pconfig['rxantenna'])) {
                    $new_config['wireless']['rxantenna'] = $_POST['rxantenna'];
                } elseif (isset($new_config['wireless']['rxantenna'])) {
                    unset($new_config['wireless']['rxantenna']);
                }
                $new_config['wireless']['wpa']['macaddr_acl'] = $pconfig['macaddr_acl'];
                $new_config['wireless']['wpa']['auth_algs'] = $pconfig['auth_algs'];
                $new_config['wireless']['wpa']['wpa_mode'] = $pconfig['wpa_mode'];
                $new_config['wireless']['wpa']['wpa_key_mgmt'] = $pconfig['wpa_key_mgmt'];
                $new_config['wireless']['wpa']['wpa_eap_method'] = $pconfig['wpa_eap_method'];
                $new_config['wireless']['wpa']['wpa_eap_p2_auth'] = $pconfig['wpa_eap_p2_auth'];
                $new_config['wireless']['wpa']['wpa_eap_cacertref'] = $pconfig['wpa_eap_cacertref'];
                $new_config['wireless']['wpa']['wpa_eap_cltcertref'] = $pconfig['wpa_eap_cltcertref'];
                $new_config['wireless']['wpa']['wpa_pairwise'] = $pconfig['wpa_pairwise'];
                $new_config['wireless']['wpa']['wpa_group_rekey'] = $pconfig['wpa_group_rekey'];
                $new_config['wireless']['wpa']['wpa_gmk_rekey'] = $pconfig['wpa_gmk_rekey'];
                $new_config['wireless']['wpa']['identity'] = $pconfig['identity'];
                $new_config['wireless']['wpa']['passphrase'] = $pconfig['passphrase'];
                $new_config['wireless']['wpa']['ext_wpa_sw'] = $pconfig['ext_wpa_sw'];
                $new_config['wireless']['wpa']['mac_acl_enable'] = !empty($pconfig['mac_acl_enable']);
                $new_config['wireless']['wpa']['rsn_preauth'] = !empty($pconfig['rsn_preauth']);
                $new_config['wireless']['wpa']['ieee8021x']['enable'] = !empty($pconfig['ieee8021x']);
                $new_config['wireless']['wpa']['wpa_strict_rekey'] = !empty($pconfig['wpa_strict_rekey']);
                $new_config['wireless']['wpa']['debug_mode'] = !empty($pconfig['debug_mode']);
                $new_config['wireless']['wpa']['enable'] = $_POST['wpa_enable'] = !empty($pconfig['wpa_enable']);

                $new_config['wireless']['auth_server_addr'] = $pconfig['auth_server_addr'];
                $new_config['wireless']['auth_server_port'] = $pconfig['auth_server_port'];
                $new_config['wireless']['auth_server_shared_secret'] = $pconfig['auth_server_shared_secret'];
                $new_config['wireless']['auth_server_addr2'] = $pconfig['auth_server_addr2'];
                $new_config['wireless']['auth_server_port2'] = $pconfig['auth_server_port2'];
                $new_config['wireless']['auth_server_shared_secret2'] = $pconfig['auth_server_shared_secret2'];

                $new_config['wireless']['wep']['enable'] = !empty($pconfig['wep_enable']);
                $new_config['wireless']['wme']['enable'] = !empty($pconfig['wme_enable']);

                $new_config['wireless']['pureg']['enable'] = !empty($pconfig['puremode']) && $pconfig['puremode'] == "11g";
                $new_config['wireless']['puren']['enable'] = !empty($pconfig['puremode']) && $pconfig['puremode'] == "11n";
                $new_config['wireless']['apbridge'] = array();
                $new_config['wireless']['apbridge']['enable'] = !empty($pconfig['apbridge_enable']);
                $new_config['wireless']['turbo'] = array();
                $new_config['wireless']['turbo']['enable'] = $pconfig['standard'] == "11g Turbo" || $pconfig['standard'] == "11a Turbo";

                $new_config['wireless']['wep']['key'] = array();
                for ($i = 1; $i <= 4; $i++) {
                    if (!empty($pconfig['key' . $i])) {
                        $newkey = array();
                        $newkey['value'] = $pconfig['key' . $i];
                        if ($pconfig['txkey'] == $i) {
                            $newkey['txkey'] = true;
                        }
                        $new_config['wireless']['wep']['key'][] = $newkey;
                    }
                }

                // todo: it's probably better to choose one place to store wireless data
                //       this construction implements a lot of weirdness (more info interface_sync_wireless_clones)
                $wlanbaseif = interface_get_wireless_base($a_interfaces[$if]['if']);
                if (!empty($pconfig['persistcommonwireless'])) {
                    config_read_array('wireless', 'interfaces', $wlanbaseif);
                } elseif (isset($config['wireless']['interfaces'][$wlanbaseif])) {
                    unset($config['wireless']['interfaces'][$wlanbaseif]);
                }

                // quite obscure this... copies parts of the config
                interface_sync_wireless_clones($new_config, true);
            }
            // hardware (offloading) Settings
            if (!empty($pconfig['hw_settings_overwrite'])) {
                $new_config['hw_settings_overwrite'] = true;
                if (!empty($pconfig['disablechecksumoffloading'])) {
                    $new_config['disablechecksumoffloading'] = true;
                }
                if (!empty($pconfig['disablesegmentationoffloading'])) {
                    $new_config['disablesegmentationoffloading'] = true;
                }
                if (!empty($pconfig['disablelargereceiveoffloading'])) {
                    $new_config['disablelargereceiveoffloading'] = true;
                }
                if (!empty($pconfig['disablevlanhwfilter'])) {
                    $new_config['disablevlanhwfilter'] = $pconfig['disablevlanhwfilter'];
                }
            }

            // save interface details
            $a_interfaces[$if] = $new_config;

            // save to config
            write_config();

            // log changes for apply action
            // (it would be better to diff the physical situation with the new config for changes)
            if (file_exists('/tmp/.interfaces.apply')) {
                $toapplylist = unserialize(file_get_contents('/tmp/.interfaces.apply'));
            } else {
                $toapplylist = array();
            }

            if (empty($toapplylist[$if])) {
                // only flush if the running config is not in our list yet
                $toapplylist[$if]['ifcfg'] = $old_config;
                $toapplylist[$if]['ppps'] = $a_ppps;
                file_put_contents('/tmp/.interfaces.apply', serialize($toapplylist));
            }

            mark_subsystem_dirty('interfaces');

            if (!empty($ifgroup)) {
                header(url_safe('Location: /interfaces.php?if=%s&group=%s', array($if, $ifgroup)));
            } else {
                header(url_safe('Location: /interfaces.php?if=%s', array($if)));
            }
            exit;
        }
    }
}

legacy_html_escape_form_data($pconfig);

// some wireless settings require additional details to build the listbox
if (isset($a_interfaces[$if]['wireless'])) {
    config_read_array('interfaces', $if, 'wireless');
    $wl_modes = get_wireless_modes($if);
    $wlanbaseif = interface_get_wireless_base($a_interfaces[$if]['if']);
    preg_match("/^(.*?)([0-9]*)$/", $wlanbaseif, $wlanbaseif_split);
    $wl_sysctl_prefix = 'dev.' . $wlanbaseif_split[1] . '.' . $wlanbaseif_split[2];
    $wl_sysctl = get_sysctl(array("{$wl_sysctl_prefix}.diversity", "{$wl_sysctl_prefix}.txantenna", "{$wl_sysctl_prefix}.rxantenna"));
    $wl_regdomain_xml_attr = array();
    $wl_regdomain_xml = parse_xml_regdomain($wl_regdomain_xml_attr);
    $wl_regdomains = &$wl_regdomain_xml['regulatory-domains']['rd'];
    $wl_regdomains_attr = &$wl_regdomain_xml_attr['regulatory-domains']['rd'];
    $wl_countries = &$wl_regdomain_xml['country-codes']['country'];
    $wl_countries_attr = &$wl_regdomain_xml_attr['country-codes']['country'];
}

// Find all possible media options for the interface
$mediaopts_list = legacy_interface_details($pconfig['if'])['supported_media'] ?? [];

$types4 = $types6 = ['none' => gettext('None')];

/* always eligible (leading) */
$types6['staticv6'] = gettext('Static IPv6');
$types6['dhcp6'] = gettext('DHCPv6');
$types6['slaac'] = gettext('SLAAC');

if (!interface_ppps_capable($a_interfaces[$if], $a_ppps)) {
    /* do not offer these raw types as a transition back from PPP */
    $types4['staticv4'] = gettext('Static IPv4');
    $types4['dhcp'] = gettext('DHCP');
} else {
    switch ($a_ppps[$pppid]['type']) {
        case 'ppp':
            $types4['ppp'] = gettext('PPP');
            break;
        case 'pppoe':
            $types4['pppoe'] = gettext('PPPoE');
            $types6['pppoev6'] = gettext('PPPoEv6');
            break;
        case 'pptp':
            $types4['pptp'] = gettext('PPTP');
            break;
        case 'l2tp':
            $types4['l2tp'] = gettext('L2TP');
            break;
        default:
            break;
    }
}

/* always eligible (trailing) */
$types6['6rd'] = gettext('6rd Tunnel');
$types6['6to4'] = gettext('6to4 Tunnel');
$types6['track6'] = gettext('Track Interface');

include("head.inc");
?>

<body>
<script>
  $( document ).ready(function() {
      function toggle_allcfg() {
          if ($("#enable").prop('checked')) {
              $("#allcfg").show();
          } else {
              $("#allcfg").hide();
          }
          toggle_wirelesscfg();
      }
      function toggle_wirelesscfg() {
          switch ($("#mode").prop('value')) {
              case 'hostap':
                  $(".cfg-wireless-bss").hide();
                  $(".cfg-wireless-adhoc").hide();
                  $(".cfg-wireless-ap").show();
                  break;
              case 'bss':
                  $(".cfg-wireless-ap").hide();
                  $(".cfg-wireless-adhoc").hide();
                  $(".cfg-wireless-bss").show();
                  break;
              case 'adhoc':
                  $(".cfg-wireless-ap").hide();
                  $(".cfg-wireless-bss").hide();
                  $(".cfg-wireless-adhoc").show();
                  break;
          }

          if ($("#wep_enable").prop('checked')) {
              $(".cfg-wireless-wep").show();
          }
          else {
              $(".cfg-wireless-wep").hide();
          }

          if ($("#wpa_enable").prop('checked')) {
              $(".cfg-wireless-wpa").show();
              if ($("#mode").prop('value') == "hostap") {
                $(".cfg-wireless-ap-wpa").show();
              }
              else {
                $(".cfg-wireless-ap-wpa").hide();
              }
          }
          else {
              $(".cfg-wireless-wpa").hide();
              $(".cfg-wireless-ap-wpa").hide();
          }

          if ($("#wpa_enable").prop('checked') &&
            $("#wpa_key_mgmt").prop('value') == "WPA-EAP" &&
            $("#mode").prop('value') == "bss") {
              $(".cfg-wireless-eap").show();
          }
          else {
              $(".cfg-wireless-eap").hide();
          }

          if ($("#mode").prop('value') == "hostap" &&
            $("#wpa_enable").prop('checked') &&
            $("#ieee8021x").prop('checked')) {
              $(".cfg-wireless-ieee8021x").show();
          }
          else {
              $(".cfg-wireless-ieee8021x").hide();
          }
      }
      // when disabled, hide settings.
      $("#enable").click(toggle_allcfg);
      $("#mode").change(toggle_wirelesscfg);
      $("#wep_enable").click(toggle_wirelesscfg);
      $("#wpa_enable").click(toggle_wirelesscfg);
      $("#wpa_key_mgmt").change(toggle_wirelesscfg);
      $("#ieee8021x").click(toggle_wirelesscfg);
      toggle_allcfg();

      $("#type").change(function () {
          $('#staticv4, #dhcp, #ppp').hide();
          if ($(this).val() == 'l2tp' || $(this).val() == 'pptp' || $(this).val() == 'pppoe') {
              $("#ppp").show();
          } else {
              $("#" +$(this).val()).show();
          }
          switch ($(this).val()) {
            case "pppoe":
            case "pptp":
              $("#mtu_calc").show();
              break;
            default:
              $("#mtu_calc").hide();
          }
      });
      $("#type").change();

      $("#type6").change(function(){
          $('#staticv6, #dhcp6, #6rd, #track6').hide();
          $("#" +$(this).val()).show();
      });
      $("#type6").change();

      // show inline form "new gateway"  (v4/v6)
      $("#btn_show_add_gateway").click(function(){
          $("#addgateway").toggleClass("hidden visible");
      });
      $("#btn_show_add_gatewayv6").click(function(){
          $("#addgatewayv6").toggleClass("hidden visible");
      });

      // handle dhcp advanced/basic or custom file select
      $("#dhcp_mode :input").change(function(){
          $(".dhcp_basic").addClass("hidden");
          $(".dhcp_advanced").addClass("hidden");
          $(".dhcp_file_override").addClass("hidden");
          var selected_opt = $(this).val();
          $("#dhcp_mode :input:checked").prop("checked", false);
          $(this).prop("checked", true);
          switch (selected_opt) {
            case "basic":
              $(".dhcp_basic").removeClass("hidden");
              break;
            case "advanced":
              $(".dhcp_advanced").removeClass("hidden");
              break;
            case "file":
              $(".dhcp_file_override").removeClass("hidden");
              break;
          }
      });
      $("#dhcp_mode :input:checked").change(); // trigger initial

      $("#dhcpv6_mode :input").change(function(){
          $(".dhcpv6_basic").addClass("hidden");
          $(".dhcpv6_advanced").addClass("hidden");
          $(".dhcpv6_file_override").addClass("hidden");
          var selected_opt = $(this).val();
          $("#dhcpv6_mode :input:checked").prop("checked", false);
          $(this).prop("checked", true);
          switch (selected_opt) {
            case "basic":
              $(".dhcpv6_basic").removeClass("hidden");
              break;
            case "advanced":
              $(".dhcpv6_advanced").removeClass("hidden");
              break;
            case "file":
              $(".dhcpv6_file_override").removeClass("hidden");
              break;
          }
      });
      $("#dhcpv6_mode :input:checked").change(); // trigger initial


      // handle dhcp Protocol Timing preselects
      $("#customdhcp :input").change(function() {
          var custom_map = {'DHCP' : {}, 'OPNsense' : {}, 'SavedCfg' : {} , 'Clear': {} };
          custom_map['DHCP'] = ["60", "300", "0", "10", "120", "10"];
          custom_map['OPNsense'] = ["60", "15", "0", "", "", "1"];
          custom_map['SavedCfg'] = ["<?=$pconfig['adv_dhcp_pt_timeout'];?>", "<?=$pconfig['adv_dhcp_pt_retry'];?>", "<?=$pconfig['adv_dhcp_pt_select_timeout'];?>", "<?=$pconfig['adv_dhcp_pt_reboot'];?>", "<?=$pconfig['adv_dhcp_pt_backoff_cutoff'];?>", "<?=$pconfig['adv_dhcp_pt_initial_interval'];?>"];
          custom_map['Clear'] = ["", "", "", "", "", ""];
          $("#adv_dhcp_pt_timeout").val(custom_map[$(this).val()][0]);
          $("#adv_dhcp_pt_retry").val(custom_map[$(this).val()][1]);
          $("#adv_dhcp_pt_select_timeout").val(custom_map[$(this).val()][2]);
          $("#adv_dhcp_pt_reboot.value").val(custom_map[$(this).val()][3]);
          $("#adv_dhcp_pt_backoff_cutoff").val(custom_map[$(this).val()][4]);
          $("#adv_dhcp_pt_initial_interval").val(custom_map[$(this).val()][5]);
          $("#dv_dhcp_pt_values").val(custom_map[$(this).val()][6]);
      });


      // Identity Association Statement -> Non-Temporary Address Allocation change
      $("#adv_dhcp6_id_assoc_statement_address_enable").change(function(){
        if ($("#adv_dhcp6_id_assoc_statement_address_enable").prop('checked')) {
            $("#show_adv_dhcp6_id_assoc_statement_address").removeClass("hidden");
        } else {
            $("#show_adv_dhcp6_id_assoc_statement_address").addClass("hidden");
        }
      });
      if ($("#adv_dhcp6_id_assoc_statement_address_enable").prop('checked')) {
          $("#show_adv_dhcp6_id_assoc_statement_address").removeClass("hidden");
      }

      // Identity Association Statement -> Prefix Delegation
      $("#adv_dhcp6_id_assoc_statement_prefix_enable").change(function(){
        if ($("#adv_dhcp6_id_assoc_statement_prefix_enable").prop('checked')) {
            $("#show_adv_dhcp6_id_assoc_statement_prefix").removeClass("hidden");
        } else {
            $("#show_adv_dhcp6_id_assoc_statement_prefix").addClass("hidden");
        }
      });
      if ($("#adv_dhcp6_id_assoc_statement_prefix_enable").prop('checked')) {
          $("#show_adv_dhcp6_id_assoc_statement_prefix").removeClass("hidden");
      }

      $("#mtu").change(function(){
        // ppp uses an mtu
        if (!isNaN($("#mtu").val()) && $("#mtu").val() > 8) {
            // display mtu used for the ppp(oe) connection
            $("#mtu_calc > small > label").html($("#mtu").val() - 8 );
        } else {
            // default ppp mtu is 1500 - 8 (header)
            $("#mtu_calc > small > label").html("1492");
        }
      });
      $("#mtu").change();

      // toggle hardware settings visibility
      $("#hw_settings_overwrite").change(function(){
          if ($("#hw_settings_overwrite").is(':checked')) {
              $(".hw_settings_overwrite").show();
          } else {
              $(".hw_settings_overwrite").hide();
          }
      }).change();

      window_highlight_table_option();
  });
</script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
      if (isset($input_errors) && count($input_errors) > 0) {
          print_input_errors($input_errors);
      }
      if (is_subsystem_dirty('interfaces')) {
          print_info_box_apply(sprintf(gettext("The %s configuration has been changed."),$pconfig['descr'])."<br/>".gettext("You must apply the changes in order for them to take effect.")."<br/>".gettext("Don't forget to adjust the DHCP Server range if needed after applying."));
      }
      if (isset($savemsg)) {
        print_info_box($savemsg);
      }
?>
        <section class="col-xs-12">
          <form method="post" name="iform" id="iform">
              <div class="tab-content content-box col-xs-12 __mb">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <thead>
                      <tr>
                        <td style="width:22%"><strong><?=gettext("Basic configuration"); ?></strong></td>
                        <td style="width:78%; text-align:right">
                          <small><?=gettext("full help"); ?> </small>
                          <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                          &nbsp;
                        </td>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Enable') ?></td>
                        <td>
                          <input id="enable" name="enable" type="checkbox" value="yes" <?=!empty($pconfig['enable']) ? 'checked="checked"' : '' ?>/>
                          <?= gettext('Enable Interface') ?>
                        </td>
                      </tr>
                      <tr>
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Lock') ?></td>
                        <td>
                          <input id="lock" name="lock" type="checkbox" value="yes" <?=!empty($pconfig['lock']) ? 'checked="checked"' : '' ?>/>
                          <?= gettext('Prevent interface removal') ?>
                        </td>
                      </tr>
                      <tr>
                        <td style="width:22%"><a id="help_for_ifid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Identifier"); ?></td>
                        <td style="width:78%">
                          <?= $if ?>
                          <div class="hidden" data-for="help_for_ifid">
                            <?= gettext("The internal configuration identifier of this interface."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td style="width:22%"><a id="help_for_ifname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Device"); ?></td>
                        <td style="width:78%">
                          <?= $pconfig['if'] ?>
                          <div class="hidden" data-for="help_for_ifname">
                            <?= gettext("The assigned network device name of this interface."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                        <td>
                          <input name="descr" type="text" id="descr" value="<?=$pconfig['descr'];?>" />
                          <div class="hidden" data-for="help_for_descr">
                            <?= gettext("Enter a description (name) for the interface here."); ?>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div id="allcfg" style="display:none">
                <div class="tab-content content-box col-xs-12 __mb">
                  <div class="table-responsive">
                    <!-- Section : All -->
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("Generic configuration"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><a id="help_for_blockpriv" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Block private networks"); ?></td>
                          <td style="width:78%">
                            <input name="blockpriv" type="checkbox" id="blockpriv" value="yes" <?=!empty($pconfig['blockpriv']) ? "checked=\"checked\"" : ""; ?> />
                            <div class="hidden" data-for="help_for_blockpriv">
                              <?=gettext("When set, this option blocks traffic from IP addresses that are reserved " .
                                "for private networks as per RFC 1918 (10/8, 172.16/12, 192.168/16) as well as loopback " .
                                "addresses (127/8) and Carrier-grade NAT addresses (100.64/10). This option should only " .
                                "be set for WAN interfaces that use the public IP address space.") ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_blockbogons" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Block bogon networks"); ?></td>
                          <td>
                            <input name="blockbogons" type="checkbox" id="blockbogons" value="yes" <?=!empty($pconfig['blockbogons']) ? "checked=\"checked\"" : ""; ?> />
                            <div class="hidden" data-for="help_for_blockbogons">
                              <?=gettext("When set, this option blocks traffic from IP addresses that are reserved " .
                              "(but not RFC 1918) or not yet assigned by IANA."); ?>
                              <?=gettext("Bogons are prefixes that should never appear in the Internet routing table, " .
                              "and obviously should not appear as the source address in any packets you receive."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv4 Configuration Type"); ?></td>
                          <td>
                            <select name="type" class="selectpicker" data-style="btn-default" id="type">
<?php foreach ($types4 as $key => $opt): ?>
                              <option value="<?= html_safe($key) ?>" <?=$key == $pconfig['type'] ? 'selected="selected"' : '' ?> ><?= $opt ?></option>
<?php endforeach ?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv6 Configuration Type"); ?></td>
                          <td>
                            <select name="type6" class="selectpicker" data-style="btn-default" id="type6">
<?php foreach ($types6 as $key => $opt): ?>
                              <option value="<?= html_safe($key) ?>" <?=$key == $pconfig['type6'] ? 'selected="selected"' : '' ?> ><?= $opt ?></option>
<?php endforeach ?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_spoofmac" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MAC address"); ?></td>
                          <td>
                            <input name="spoofmac" type="text" id="spoofmac" value="<?=htmlspecialchars($pconfig['spoofmac']);?>" />
                            <div class="hidden" data-for="help_for_spoofmac">
                              <?= gettext('This field can be used to spoof the MAC address of the interface. Enter a ' .
                                  'MAC address in the following format: xx:xx:xx:xx:xx:xx or leave blank if unsure. ' .
                                  'This may only be required e.g. with certain cable connections on a WAN interface.') ?><br />
                              <?= gettext('When used on a single VLAN interface the setting "Promiscuous mode" is required for this to work. ' .
                                  'Alternatively, the parent interface MAC can be spoofed applying the MAC address to all attached VLAN children automatically.') ?><br />
<?php
                              $ip = getenv('REMOTE_ADDR');
                              $mac = `/usr/sbin/arp -an | grep {$ip} | cut -d" " -f4`;
                              $mac = str_replace("\n","",$mac);
                              if (!empty($mac)):
?>
                              <a onclick="document.getElementById('spoofmac').value='<?= html_safe($mac) ?>';" href="#"><?=gettext("Insert my currently connected MAC address (use with care)"); ?></a><br />
<?php
                              endif; ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_promisc" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Promiscuous mode') ?></td>
                          <td>
                            <input id="promisc" name="promisc" type="checkbox" value="yes" <?=!empty($pconfig['promisc']) ? 'checked="checked"' : '' ?>/>
                            <div class="hidden" data-for="help_for_promisc">
                              <?=gettext(
                                  "Put interface into permanently promiscuous mode. ".
                                  "Only to be used for specific usecases requiring the interface to receive all packets being received. ".
                                  "When unsure, leave this disabled."
                              ); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_mtu" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MTU"); ?></td>
                          <td>
                            <input name="mtu" id="mtu" type="text" value="<?=$pconfig['mtu'];?>" />
                            <div id="mtu_calc" style="display:none">
                              <small><?= gettext('Calculated PPP MTU') ?>: <label></label></small>
                            </div>
                            <div class="hidden" data-for="help_for_mtu">
                              <?= gettext("If you leave this field blank, the adapter's default MTU will " .
                                "be used. This is typically 1500 bytes but can vary in some circumstances.");?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_mss" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MSS"); ?></td>
                          <td>
                            <input name="mss" type="text" id="mss" value="<?=$pconfig['mss'];?>" />
                            <div class="hidden" data-for="help_for_mss">
                              <?=gettext("If you enter a value in this field, then MSS clamping for " .
                              "TCP connections to the value entered above minus 40 (IPv4) or 60 (IPv6) " .
                              "will be in effect (TCP/IP header size)."); ?>
                            </div>
                          </td>
                        </tr>
<?php
                        if (count($mediaopts_list) > 1):?>
                        <tr>
                            <td><a id="help_for_mediaopt" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Speed and duplex");?>  </td>
                            <td>
                                <select name="mediaopt" class="selectpicker" data-style="btn-default" id="mediaopt">
                                  <option value=""><?=gettext('Default (no preference, typically autoselect)');?></option>
<?php
                                  foreach($mediaopts_list as $mediaopt):?>
                                    <option value="<?=$mediaopt;?>" <?=$mediaopt == trim($pconfig['media'] . " ". $pconfig['mediaopt']) ? "selected=\"selected\"" : "";?> >
                                      <?=$mediaopt;?>
                                    </option>
<?php
                                  endforeach;?>
                                </select>
                                <div class="hidden" data-for="help_for_mediaopt">
                                  <?=gettext("Here you can explicitly set speed and duplex mode for this interface. WARNING: You MUST leave this set to autoselect (automatically negotiate speed) unless the port this interface connects to has its speed and duplex forced.");?>
                                </div>
                            </td>
                        </tr>
<?php
                        endif;?>
                        <tr>
                          <td><a id="help_for_gateway_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Dynamic gateway policy') ?></td>
                          <td>
                            <input id="gateway_interface" name="gateway_interface" type="checkbox" value="yes" <?=!empty($pconfig['gateway_interface']) ? 'checked="checked"' : '' ?>/>
                            <?= gettext('This interface does not require an intermediate system to act as a gateway') ?>
                            <div class="hidden" data-for="help_for_gateway_interface">
                              <?=gettext("If the destination is directly reachable via an interface requiring no " .
                              "intermediary system to act as a gateway, you can select this option which allows dynamic gateways " .
                              "to be created without direct target addresses. Some tunnel types support this."); ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
<?php if (in_array($pconfig['if'], $hwifs)): ?>
                <!-- Hardware settings -->
                <div class="tab-content content-box col-xs-12 __mb">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("Hardware settings"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><a id="help_for_hw_settings_overwrite" href="#" class="showhelp"> <i class="fa fa-info-circle"></i></a> <?=gettext("Overwrite global settings"); ?></td>
                          <td style="width:78%">
                            <input id="hw_settings_overwrite" name="hw_settings_overwrite" type="checkbox" value="yes" <?=!empty($pconfig['hw_settings_overwrite']) ? 'checked="checked"' : '' ?>/>
                            <div class="hidden" data-for="help_for_hw_settings_overwrite">
                              <?=gettext("Overwrite custom interface hardware settings with settings specified below"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="hw_settings_overwrite" style="display:none">
                          <td><a id="help_for_disablechecksumoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware CRC"); ?></td>
                          <td>
                            <input name="disablechecksumoffloading" type="checkbox" id="disablechecksumoffloading" value="yes" <?= !empty($pconfig['disablechecksumoffloading']) ? "checked=\"checked\"" :"";?> />
                            <?=gettext("Disable hardware checksum offload"); ?>
                            <div class="hidden" data-for="help_for_disablechecksumoffloading">
                              <?=gettext("Checking this option will disable hardware checksum offloading. Checksum offloading is broken in some hardware, particularly some Realtek cards. Rarely, drivers may have problems with checksum offloading and some specific NICs."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="hw_settings_overwrite" style="display:none">
                          <td><a id="help_for_disablesegmentationoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware TSO"); ?></td>
                          <td>
                            <input name="disablesegmentationoffloading" type="checkbox" id="disablesegmentationoffloading" value="yes" <?= !empty($pconfig['disablesegmentationoffloading']) ? "checked=\"checked\"" :"";?>/>
                            <?=gettext("Disable hardware TCP segmentation offload"); ?>
                            <div class="hidden" data-for="help_for_disablesegmentationoffloading">
                              <?=gettext("Checking this option will disable hardware TCP segmentation offloading (TSO, TSO4, TSO6). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="hw_settings_overwrite" style="display:none">
                          <td><a id="help_for_disablelargereceiveoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware LRO"); ?></td>
                          <td>
                            <input name="disablelargereceiveoffloading" type="checkbox" id="disablelargereceiveoffloading" value="yes" <?= !empty($pconfig['disablelargereceiveoffloading']) ? "checked=\"checked\"" :"";?>/>
                            <?=gettext("Disable hardware large receive offload"); ?>
                            <div class="hidden" data-for="help_for_disablelargereceiveoffloading">
                              <?=gettext("Checking this option will disable hardware large receive offloading (LRO). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="hw_settings_overwrite" style="display:none">
                          <td><a id="help_for_disablevlanhwfilter" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("VLAN Hardware Filtering"); ?></td>
                          <td>
                            <select name="disablevlanhwfilter" class="selectpicker">
                                <option value="0" <?=$pconfig['disablevlanhwfilter'] == "0" ? "selected=\"selected\"" : "";?> >
                                  <?=gettext("Enable VLAN Hardware Filtering");?>
                                </option>
                                <option value="1" <?=$pconfig['disablevlanhwfilter'] == "1" ? "selected=\"selected\"" : "";?> >
                                  <?=gettext("Disable VLAN Hardware Filtering"); ?>
                                </option>
                                <option value="2" <?=$pconfig['disablevlanhwfilter'] == "2" ? "selected=\"selected\"" : "";?> >
                                  <?=gettext("Leave default");?>
                                </option>
                            </select>
                            <div class="hidden" data-for="help_for_disablevlanhwfilter">
                              <?= gettext('Set usage of VLAN hardware filtering. This hardware acceleration may be broken in a particular device driver, or may impact performance.') ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
<?php endif ?>
                <!-- static IPv4 -->
                <div class="tab-content content-box col-xs-12 __mb" id="staticv4" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("Static IPv4 configuration"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv4 address"); ?></td>
                          <td style="width:78%">
                            <table>
                              <tr>
                                <td style="width:348px">
                                  <input name="ipaddr" type="text" id="ipaddr" value="<?=$pconfig['ipaddr'];?>" />
                                </td>
                                <td>
                                  <select id="subnet" name="subnet" class="selectpicker" data-style="btn-default" data-width="auto" data-size="10" data-id="subnet">

<?php
                                    for ($i = 32; $i > 0; $i--):?>
                                    <option value="<?=$i;?>" <?=$i == $pconfig['subnet'] ? "selected=\"selected\"" : "";?>><?=$i;?></option>
<?php
                                    endfor;?>
                                  </select>
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_gateway" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('IPv4 gateway rules') ?></td>
                          <td>
                            <select name="gateway" class="selectpicker" data-style="btn-default" data-size="10" id="gateway">
                              <option value="none"><?= gettext('Disabled') ?></option>
<?php
                              foreach ((new \OPNsense\Routing\Gateways())->gatewayIterator() as $gateway):
                                if ($gateway['interface'] == $if && is_ipaddrv4($gateway['gateway'])):
?>
                                <option value="<?=$gateway['name'];?>" <?= $gateway['name'] == $pconfig['gateway'] ? "selected=\"selected\"" : ""; ?>>
                                  <?=htmlspecialchars($gateway['name']. " - " . $gateway['gateway']);?>
                                </option>
<?php
                                endif;
                              endforeach;
?>
                            </select>
                            <div class="hidden" data-for="help_for_gateway">
                              <?= gettext('Select a gateway from the list to reply the incoming packets to the proper next hop on their way back and apply source NAT when configured. ' .
                                          'This is typically disabled for LAN type interfaces.') ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- Section : dhcp v4 -->
                <div class="tab-content content-box col-xs-12 __mb" id="dhcp" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("DHCP client configuration");?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><a id="help_for_dhcp_mode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Configuration Mode"); ?></td>
                          <td style="width:78%">
                            <div id="dhcp_mode" class="btn-group" data-toggle="buttons">
                              <label class="btn btn-default <?=empty($pconfig['adv_dhcp_config_advanced']) && empty($pconfig['adv_dhcp_config_file_override']) ? "active" : "";?>">
                                <input type="radio" value="basic" <?=empty($pconfig['adv_dhcp_config_advanced']) && empty($pconfig['adv_dhcp_config_file_override']) ? "checked=\"\"" : "";?>/>
                                <?=gettext("Basic");?>
                              </label>
                              <label class="btn btn-default <?=!empty($pconfig['adv_dhcp_config_advanced']) ? "active" : "";?>">
                                <input name="adv_dhcp_config_advanced" type="radio" value="advanced" <?=!empty($pconfig['adv_dhcp_config_advanced']) ? "checked=\"\"" : "";?>/>
                                <?=gettext("Advanced");?>
                              </label>
                              <label class="btn btn-default <?=!empty($pconfig['adv_dhcp_config_file_override']) ? "active" : "";?>">
                                <input name="adv_dhcp_config_file_override" type="radio" value="file" <?=!empty($pconfig['adv_dhcp_config_file_override']) ? "checked=\"\"" : "";?> />
                                <?=gettext("Config File Override");?>
                              </label>
                            </div>
                            <div class="hidden" data-for="help_for_dhcp_mode">
                              <?= gettext('The basic mode auto-configures DHCP using default values and optional user input.') ?><br/>
                              <?= gettext('The advanced mode does not provide any default values, you will need to fill out any values you would like to use.') ?><br>
                              <?= gettext('The configuration file override mode may point to a fully customised file on the system instead.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_basic">
                          <td><a id="help_for_alias_address" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Alias IPv4 address"); ?></td>
                          <td>
                            <table>
                              <tr>
                                <td style="width:348px;">
                                  <input name="alias-address" type="text" id="alias-address" value="<?=$pconfig['alias-address'];?>" />
                                </td>
                                <td>
                                  <select name="alias-subnet" class="selectpicker" data-style="btn-default" id="alias-subnet" data-width="auto"  data-size="10">
<?php
                                    for ($i = 32; $i > 0; $i--):?>
                                        <option value="<?=$i;?>" <?=$i == $pconfig['alias-subnet'] ?  "selected=\"selected\"" : "";?> >
                                            <?=$i;?>
                                        </option>
<?php
                                    endfor;?>
                                  </select>
                                </td>
                              </tr>
                            </table>
                            <div class="hidden" data-for="help_for_alias_address">
                              <?=gettext("The value in this field is used as a fixed alias IPv4 address by the " .
                              "DHCP client."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_basic dhcp_advanced">
                          <td><a id="help_for_dhcprejectfrom" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Reject Leases From"); ?></td>
                          <td>
                            <input name="dhcprejectfrom" type="text" id="dhcprejectfrom" value="<?=htmlspecialchars($pconfig['dhcprejectfrom']);?>" />
                            <div class="hidden" data-for="help_for_dhcprejectfrom">
                              <?=gettext("If there are certain upstream DHCP servers that should be ignored, place the comma separated list of IP addresses of the DHCP servers to be ignored here."); ?>
                              <?=gettext("This is useful for rejecting leases from cable modems that offer private IPs when they lose upstream sync."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_basic dhcp_advanced">
                          <td><a id="help_for_dhcphostname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hostname"); ?></td>
                          <td>
                            <input name="dhcphostname" type="text" id="dhcphostname" value="<?=$pconfig['dhcphostname'];?>" />
                            <div class="hidden" data-for="help_for_dhcphostname">
                              <?=gettext("The value in this field is sent as the DHCP client identifier " .
                              "and hostname when requesting a DHCP lease. Some ISPs may require " .
                              "this (for client identification)."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_basic dhcp_advanced">
                          <td><a id="help_for_dhcpoverridemtu" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Override MTU') ?></td>
                          <td>
                            <input name="dhcpoverridemtu" type="checkbox" id="dhcpoverridemtu" value="yes" <?= !empty($pconfig['dhcpoverridemtu']) ? 'checked="checked"' : '' ?>/>
                            <div class="hidden" data-for="help_for_dhcpoverridemtu">
                              <?= gettext('An ISP may incorrectly set an MTU value which can cause intermittent network disruption. By default this ' .
                                'value will be ignored. Unsetting this option will allow to apply the MTU supplied by the ISP instead.'); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_basic dhcp_advanced">
                          <td><a id="help_for_dhcpvlanprio" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Use VLAN priority') ?></td>
                          <td>
                            <select name="dhcpvlanprio">
                              <option value="" <?= "{$pconfig['dhcpvlanprio']}" === '' ? 'selected="selected"' : '' ?>><?= gettext('Disabled') ?></option>
<?php
                              foreach (interfaces_vlan_priorities() as $pcp => $priority): ?>
                              <option value="<?= html_safe($pcp) ?>" <?= "{$pconfig['dhcpvlanprio']}" === "$pcp" ? 'selected="selected"' : '' ?>><?= htmlspecialchars($priority) ?></option>
<?php
                              endforeach ?>
                            </select>
                            <div class="hidden" data-for="help_for_dhcpvlanprio">
                              <?= gettext('Certain ISPs may require that DHCPv4 requests are sent with a specific VLAN priority.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_advanced">
                          <td><a id="help_for_dhcpprotocol_timing" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol Timing"); ?></td>
                          <td>
                            <?=gettext("Timeout");?>: <input name="adv_dhcp_pt_timeout" type="text" id="adv_dhcp_pt_timeout" value="<?=$pconfig['adv_dhcp_pt_timeout'];?>"/>
                            <?=gettext("Retry");?>:   <input name="adv_dhcp_pt_retry"   type="text" id="adv_dhcp_pt_retry"   value="<?=$pconfig['adv_dhcp_pt_retry'];?>"/>
                            <?=gettext("Select Timeout");?>: <input name="adv_dhcp_pt_select_timeout" type="text" id="adv_dhcp_pt_select_timeout" value="<?=$pconfig['adv_dhcp_pt_select_timeout'];?>" />
                            <?=gettext("Reboot");?>: <input name="adv_dhcp_pt_reboot" type="text" id="adv_dhcp_pt_reboot" value="<?=$pconfig['adv_dhcp_pt_reboot'];?>" />
                            <?=gettext("Backoff Cutoff");?>:   <input name="adv_dhcp_pt_backoff_cutoff"   type="text" id="adv_dhcp_pt_backoff_cutoff"   value="<?=$pconfig['adv_dhcp_pt_backoff_cutoff'];?>"   />
                            <?=gettext("Initial Interval");?>: <input name="adv_dhcp_pt_initial_interval" type="text" id="adv_dhcp_pt_initial_interval" value="<?=$pconfig['adv_dhcp_pt_initial_interval'];?>" />
                            <hr/>
                            <?=gettext("Presets:");?><br/>
                            <div id="customdhcp" class="btn-group" data-toggle="buttons">
                              <label class="btn btn-default">
                                <input name="adv_dhcp_pt_values" type="radio" value="DHCP"/><?=gettext("FreeBSD Default");?>
                              </label>
                              <label class="btn btn-default">
                                <input name="adv_dhcp_pt_values" type="radio" value="Clear"/><?=gettext("Clear");?>
                              </label>
                              <label class="btn btn-default">
                                <input name="adv_dhcp_pt_values" type="radio" value="OPNsense"/><?=gettext("OPNsense Default");?>
                              </label>
                              <label class="btn btn-default">
                                <input name="adv_dhcp_pt_values" type="radio" value="SavedCfg" checked="checked"/><?=gettext("Saved Cfg");?>
                              </label>
                            </div>
                            <div class="hidden" data-for="help_for_dhcpprotocol_timing">
                              <?=sprintf(gettext("The values in these fields are DHCP %sprotocol timings%s used when requesting a lease."),'<a target="_blank" href="https://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5#PROTOCOL_TIMING">','</a>') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_advanced">
                          <td><a id="help_for_dhcp_lease_requirements_and_requests" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Lease Requirements");?> </td>
                          <td>
                            <div class="hidden" data-for="help_for_dhcp_lease_requirements_and_requests">
                              <?=sprintf(gettext("More detailed information about lease requirements and requests can be found in the %sFreeBSD Manual%s."),'<a target="FreeBSD_DHCP" href="https://www.freebsd.org/cgi/man.cgi?query=dhclient.conf&amp;sektion=5#LEASE_REQUIREMENTS_AND_REQUESTS">','</a>')?><br/>
                              <hr/>
                            </div>
                            <?=gettext("Send Options"); ?><br />
                            <input name="adv_dhcp_send_options" type="text" id="adv_dhcp_send_options" value="<?=$pconfig['adv_dhcp_send_options'];?>" />
                            <div class="hidden" data-for="help_for_dhcp_lease_requirements_and_requests">
                              <?=gettext("The values in this field are DHCP options to be sent when requesting a DHCP lease. [option declaration [, ...]] <br />" .
                              "Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} <br />" .
                              "Where C is U(pper) or L(ower) Case, and D is \" :-.\" Delimiter (space, colon, hyphen, or period) (omitted for none).") ?>
                            </div>
                            <hr/>
                            <?=gettext("Request Options");?>
                            <input name="adv_dhcp_request_options" type="text" id="adv_dhcp_request_options" value="<?=$pconfig['adv_dhcp_request_options'];?>" />
                            <div class="hidden" data-for="help_for_dhcp_lease_requirements_and_requests">
                              <?=gettext("The values in this field are DHCP option 55 to be sent when requesting a DHCP lease. [option [, ...]]") ?>
                            </div>
                            <hr/>
                            <?=gettext("Require Options");?>
                            <input name="adv_dhcp_required_options" type="text" id="adv_dhcp_required_options" value="<?=htmlspecialchars($pconfig['adv_dhcp_required_options']);?>" />
                            <div class="hidden" data-for="help_for_dhcp_lease_requirements_and_requests">
                              <?=gettext("The values in this field are DHCP options required by the client when requesting a DHCP lease. [option [, ...]]"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_advanced">
                          <td><a id="help_for_dhcp_option_modifiers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Option Modifiers");?></td>
                          <td>
                            <input name="adv_dhcp_option_modifiers" type="text" id="adv_dhcp_option_modifiers" value="<?=$pconfig['adv_dhcp_option_modifiers'];?>" />
                            <div class="hidden" data-for="help_for_dhcp_option_modifiers">
                              <?=gettext("The values in this field are DHCP option modifiers applied to obtained DHCP lease. [modifier option declaration [, ...]] <br />" .
                              "modifiers: (default, supersede, prepend, append)"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcp_file_override">
                          <td><a id="help_for_dhcp_config_file_override_path" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Configuration File Override");?>
                          <td>
                            <input name="adv_dhcp_config_file_override_path"   type="text" id="adv_dhcp_config_file_override_path"  value="<?=$pconfig['adv_dhcp_config_file_override_path'];?>" />
                            <div class="hidden" data-for="help_for_dhcp_config_file_override_path">
                              <?= gettext('The value in this field is the full absolute path to a DHCP client configuration file.') ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- Section : PPP -->
                <div class="tab-content content-box col-xs-12 __mb" id="ppp" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?= gettext('Point-to-Point configuration') ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext('Modem Port') ?></td>
                          <td style="width:78%">
                            <?= $pconfig['ports'] ?>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Advanced"); ?></td>
                            <td>
                              <?= sprintf(gettext('%sClick here%s for PPP-specific configuration options.  Save first if you made changes.'), url_safe('<a href="/interfaces_ppps_edit.php?id=%d">', $pppid), '</a>') ?>
                            </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- Section : static IPv6 -->
                <div class="tab-content content-box col-xs-12 __mb" id="staticv6" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("Static IPv6 configuration"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv6 address"); ?></td>
                          <td style="width:78%">
                            <table>
                              <tr>
                                <td style="width:257px">
                                  <input name="ipaddrv6" type="text" id="ipaddrv6" size="28" value="<?=htmlspecialchars($pconfig['ipaddrv6']);?>" />
                                </td>
                                <td>
                                  <select id="subnetv6" name="subnetv6" class="selectpicker" data-style="btn-default" data-width="auto" data-size="10" data-id="subnetv6">
<?php
                                    for ($i = 128; $i > 0; $i--): ?>
                                      <option value="<?=$i;?>" <?=$i == $pconfig['subnetv6'] ? "selected=\"selected\"" : "";?>><?=$i;?></option>
<?php
                                    endfor;?>
                                  </select>
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_gatewayv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('IPv6 gateway rules') ?></td>
                          <td>
                            <select name="gatewayv6" class="selectpicker" data-size="10" data-style="btn-default" id="gatewayv6">
                              <option value="none"><?= gettext('Disabled') ?></option>
<?php
                              foreach ((new \OPNsense\Routing\Gateways())->gatewayIterator() as $gateway):
                                if ($gateway['interface'] == $if && is_ipaddrv6($gateway['gateway'])):
?>
                                <option value="<?=$gateway['name'];?>" <?= $gateway['name'] == $pconfig['gatewayv6'] ? "selected=\"selected\"" : ""; ?>>
                                <?=htmlspecialchars($gateway['name']. " - " . $gateway['gateway']);?>
                                </option>
<?php
                                endif;
                              endforeach;
?>
                            </select>
                            <div class="hidden" data-for="help_for_gatewayv6">
                              <?= gettext('Select a gateway from the list to reply the incoming packets to the proper next hop on their way back. ' .
                                          'This is typically disabled for LAN type interfaces.') ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- Section : dhcp v6 -->
                <div class="tab-content content-box col-xs-12 __mb" id="dhcp6" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("DHCPv6 client configuration");?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td><a id="help_for_dhcp6vlanprio" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Use VLAN priority') ?></td>
                          <td>
                            <select name="dhcp6vlanprio">
                              <option value="" <?= "{$pconfig['dhcp6vlanprio']}" === '' ? 'selected="selected"' : '' ?>><?= gettext('Disabled') ?></option>
<?php
                              foreach (interfaces_vlan_priorities() as $pcp => $priority): ?>
                              <option value="<?= html_safe($pcp) ?>" <?= "{$pconfig['dhcp6vlanprio']}" === "$pcp" ? 'selected="selected"' : '' ?>><?= htmlspecialchars($priority) ?></option>
<?php
                              endforeach ?>
                            </select>
                            <div class="hidden" data-for="help_for_dhcp6vlanprio">
                              <?= gettext('Certain ISPs may require that DHCPv6 requests are sent with a specific VLAN priority.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td style="width:22%"><a id="help_for_dhcpv6_mode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Configuration Mode"); ?></td>
                          <td style="width:78%">
                            <div id="dhcpv6_mode" class="btn-group" data-toggle="buttons">
                              <label class="btn btn-default <?=empty($pconfig['adv_dhcp6_config_advanced']) && empty($pconfig['adv_dhcp6_config_file_override']) ? "active" : "";?>">
                                <input type="radio" value="basic" <?=empty($pconfig['adv_dhcp6_config_advanced']) && empty($pconfig['adv_dhcp6_config_file_override']) ? "checked=\"\"" : "";?>/>
                                <?=gettext("Basic");?>
                              </label>
                              <label class="btn btn-default <?=!empty($pconfig['adv_dhcp6_config_advanced']) ? "active" : "";?>">
                                <input name="adv_dhcp6_config_advanced" type="radio" value="advanced" <?=!empty($pconfig['adv_dhcp6_config_advanced']) ? "checked=\"\"" : "";?>/>
                                <?=gettext("Advanced");?>
                              </label>
                              <label class="btn btn-default <?=!empty($pconfig['adv_dhcp6_config_file_override']) ? "active" : "";?>">
                                <input name="adv_dhcp6_config_file_override" type="radio" value="file" <?=!empty($pconfig['adv_dhcp6_config_file_override']) ? "checked=\"\"" : "";?> />
                                <?=gettext("Config File Override");?>
                              </label>
                            </div>
                            <div class="hidden" data-for="help_for_dhcpv6_mode">
                              <?= gettext('The basic mode auto-configures DHCP using default values and optional user input.') ?><br/>
                              <?= gettext('The advanced mode does not provide any default values, you will need to fill out any values you would like to use.') ?><br>
                              <?= gettext('The configuration file override mode may point to a fully customised file on the system instead.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_basic">
                          <td><a id="help_for_dhcp6-ia-pd-len" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Prefix delegation size"); ?></td>
                          <td>
                            <select name="dhcp6-ia-pd-len" class="selectpicker" data-style="btn-default" id="dhcp6-ia-pd-len">
<?php
                            foreach([
                              0 => '64',
                              1 => '63',
                              2 => '62',
                              3 => '61',
                              4 => '60',
                              5 => '59',
                              6 => '58',
                              7 => '57',
                              8 => '56',
                              9 => '55',
                              10 => '54',
                              11 => '53',
                              12 => '52',
                              13 => '51',
                              14 => '50',
                              15 => '49',
                              16 => '48',
                              'none' => gettext('None'),
                            ] as $bits => $length): ?>
                              <option value="<?=$bits;?>" <?= "{$bits}" === "{$pconfig['dhcp6-ia-pd-len']}" ? 'selected="selected"' : '' ?>>
                                  <?=$length;?>
                              </option>
<?php
                            endforeach;?>
                            </select>
                            <div class="hidden" data-for="help_for_dhcp6-ia-pd-len">
                              <?=gettext("The value in this field is the delegated prefix length provided by the DHCPv6 server."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_basic">
                          <td><a id="help_for_dhcp6prefixonly" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Request prefix only') ?></td>
                          <td>
                            <input name="dhcp6prefixonly" type="checkbox" id="dhcp6prefixonly" value="yes" <?=!empty($pconfig['dhcp6prefixonly']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_dhcp6prefixonly">
                              <?= gettext('Only request an IPv6 prefix; do not request an IPv6 address.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_basic">
                          <td><a id="help_for_dhcp6-ia-pd-send-hint" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Send prefix hint') ?></td>
                          <td>
                            <input name="dhcp6-ia-pd-send-hint" type="checkbox" id="dhcp6-ia-pd-send-hint" value="yes" <?=!empty($pconfig['dhcp6-ia-pd-send-hint']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_dhcp6-ia-pd-send-hint">
                              <?=gettext("Send an IPv6 prefix hint to indicate the desired prefix size for delegation"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_basic dhcpv6_advanced">
                          <td><a id="help_for_dhcp6-prefix-id" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Optional prefix ID') ?></td>
                          <td>
                            <div class="input-group" style="max-width:348px">
                              <div class="input-group-addon">0x</div>
                              <input name="dhcp6-prefix-id--hex" type="text" class="form-control" id="dhcp6-prefix-id--hex" value="<?= html_safe($pconfig['dhcp6-prefix-id--hex']) ?>" />
                            </div>
                            <div class="hidden" data-for="help_for_dhcp6-prefix-id">
                              <?= gettext('The value in this field is the delegated hexadecimal IPv6 prefix ID. This determines the configurable /64 network ID based on the dynamic IPv6 connection.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_basic dhcpv6_advanced">
                          <td><a id="help_for_dhcp6_ifid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Optional interface ID') ?></td>
                          <td>
                            <div class="input-group" style="max-width:348px">
                              <div class="input-group-addon">0x</div>
                              <input name="dhcp6_ifid--hex" type="text" class="form-control" id="dhcp6_ifid--hex" value="<?= html_safe($pconfig['dhcp6_ifid--hex']) ?>" />
                            </div>
                            <div class="hidden" data-for="help_for_dhcp6_ifid">
                              <?= gettext('The value in this field is the numeric IPv6 interface ID used to construct the lower part of the resulting IPv6 prefix address. Setting a hex value will use that fixed value in its lower address part. Please note the maximum usable value is 0x7fffffffffffffff due to a PHP integer restriction.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_advanced">
                          <td><a id="help_for_dhcp6_intf_stmt" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface Statement");?></td>
                          <td>
                            <?=gettext("Send Options"); ?>
                            <input name="adv_dhcp6_interface_statement_send_options" type="text" id="adv_dhcp6_interface_statement_send_options" value="<?=$pconfig['adv_dhcp6_interface_statement_send_options'];?>" />
                            <div class="hidden" data-for="help_for_dhcp6_intf_stmt">
                              <?=gettext("The values in this field are DHCP send options to be sent when requesting a DHCP lease. [option declaration [, ...]] <br />" .
                              "Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} <br />" .
                              "Where C is U(pper) or L(ower) Case, and D is \" :-.\" Delimiter (space, colon, hyphen, or period) (omitted for none).") ?>
                            </div>
                            <br />
                            <?=gettext("Request Options"); ?>
                            <input name="adv_dhcp6_interface_statement_request_options" type="text" id="adv_dhcp6_interface_statement_request_options" value="<?=$pconfig['adv_dhcp6_interface_statement_request_options'];?>" />
                            <div class="hidden" data-for="help_for_dhcp6_intf_stmt">
                              <?=gettext('The values in this field are DHCP request options to be sent when requesting a DHCP lease. [option [, ...]]') ?>
                            </div>
                            <br />
                            <?=gettext("Script"); ?>
                            <input name="adv_dhcp6_interface_statement_script" type="text" id="adv_dhcp6_interface_statement_script" value="<?=htmlspecialchars($pconfig['adv_dhcp6_interface_statement_script']);?>" />
                            <div class="hidden" data-for="help_for_dhcp6_intf_stmt">
                              <?= gettext('The value in this field is the absolute path to a script invoked on certain conditions including when a reply message is received.') ?>
                            </div>
                            <br />
                            <input name="adv_dhcp6_interface_statement_information_only_enable" type="checkbox" id="adv_dhcp6_interface_statement_information_only_enable" <?=!empty($pconfig['adv_dhcp6_interface_statement_information_only_enable']) ? "checked=\"checked\"" : "";?> />
                            <?=gettext("Information Only"); ?>
                            <div class="hidden" data-for="help_for_dhcp6_intf_stmt">
                              <?=gettext("This statement specifies dhcp6c to only exchange informational configuration parameters with servers. ".
                              "A list of DNS server addresses is an example of such parameters. ".
                              "This statement is useful when the client does not need ".
                              "stateful configuration parameters such as IPv6 addresses or prefixes.");?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_advanced">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Identity Association");?></td>
                          <td>
                            <input name="adv_dhcp6_id_assoc_statement_address_enable" type="checkbox" id="adv_dhcp6_id_assoc_statement_address_enable" <?=!empty($pconfig['adv_dhcp6_id_assoc_statement_address_enable']) ? "checked=\"checked\"" : "";?>  />
                            <?=gettext("Non-Temporary Address Allocation"); ?>
                            <div class="hidden" id="show_adv_dhcp6_id_assoc_statement_address">
                              <?=gettext("id-assoc na"); ?>
                              <i><?=gettext("ID"); ?></i>
                              <input name="adv_dhcp6_id_assoc_statement_address_id" type="text" id="adv_dhcp6_id_assoc_statement_address_id" value="<?=$pconfig['adv_dhcp6_id_assoc_statement_address_id'];?>" />
                              <br />
                              <?=gettext("Address"); ?>
                              <i><?=gettext("IPv6-address"); ?></i>
                              <input name="adv_dhcp6_id_assoc_statement_address" type="text" id="adv_dhcp6_id_assoc_statement_address" value="<?=$pconfig['adv_dhcp6_id_assoc_statement_address'];?>" />
                              <i><?=gettext("Preferred Lifetime"); ?></i>
                              <input name="adv_dhcp6_id_assoc_statement_address_pltime" type="text" id="adv_dhcp6_id_assoc_statement_address_pltime" value="<?=$pconfig['adv_dhcp6_id_assoc_statement_address_pltime'];?>" />
                              <i><?=gettext("Valid Time"); ?></i>
                              <input name="adv_dhcp6_id_assoc_statement_address_vltime" type="text" id="adv_dhcp6_id_assoc_statement_address_vltime" value="<?=$pconfig['adv_dhcp6_id_assoc_statement_address_vltime'];?>" />
                            </div>
                            <hr/>
                            <input name="adv_dhcp6_id_assoc_statement_prefix_enable" type="checkbox" id="adv_dhcp6_id_assoc_statement_prefix_enable" <?=!empty($pconfig['adv_dhcp6_id_assoc_statement_prefix_enable']) ? "checked=\"checked\"" : "";?> />
                            <?=gettext("Prefix Delegation"); ?>
                            <div class="hidden" id="show_adv_dhcp6_id_assoc_statement_prefix">
                              <?=gettext("id-assoc pd"); ?>
                              <i><?=gettext("ID"); ?></i>
                              <input name="adv_dhcp6_id_assoc_statement_prefix_id" type="text" id="adv_dhcp6_id_assoc_statement_prefix_id" value="<?=$pconfig['adv_dhcp6_id_assoc_statement_prefix_id'];?>" />
                              <br />
                              <?=gettext("Prefix"); ?>
                              <i><?=gettext("IPv6-Prefix"); ?></i>
                              <input name="adv_dhcp6_id_assoc_statement_prefix" type="text" id="adv_dhcp6_id_assoc_statement_prefix" value="<?=$pconfig['adv_dhcp6_id_assoc_statement_prefix'];?>" />
                              <i><?=gettext("Preferred Lifetime"); ?></i>
                              <input name="adv_dhcp6_id_assoc_statement_prefix_pltime" type="text" id="adv_dhcp6_id_assoc_statement_prefix_pltime" value="<?=$pconfig['adv_dhcp6_id_assoc_statement_prefix_pltime'];?>" />
                              <i><?=gettext("Valid Time"); ?></i>
                              <input name="adv_dhcp6_id_assoc_statement_prefix_vltime" type="text" id="adv_dhcp6_id_assoc_statement_prefix_vltime" value="<?=$pconfig['adv_dhcp6_id_assoc_statement_prefix_vltime'];?>" />
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_advanced">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Prefix Interface");?></td>
                          <td>
                            <?= gettext('Prefix Interface') ?>
                            <i><?=gettext("Site-Level Aggregation Length"); ?></i>
                            <input name="adv_dhcp6_prefix_interface_statement_sla_len" type="text" id="adv_dhcp6_prefix_interface_statement_sla_len" value="<?=$pconfig['adv_dhcp6_prefix_interface_statement_sla_len'];?>" />
                          </td>
                        </tr>
                        <tr class="dhcpv6_advanced">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Authentication");?></td>
                          <td>
                            <i><?=gettext("authname"); ?></i>
                            <input name="adv_dhcp6_authentication_statement_authname" type="text" id="adv_dhcp6_authentication_statement_authname" value="<?=$pconfig['adv_dhcp6_authentication_statement_authname'];?>" />
                            <i><?=gettext("protocol"); ?></i>
                            <input name="adv_dhcp6_authentication_statement_protocol" type="text" id="adv_dhcp6_authentication_statement_protocol" value="<?=$pconfig['adv_dhcp6_authentication_statement_protocol'];?>" />
                            <i><?=gettext("Algorithm"); ?></i>
                            <input name="adv_dhcp6_authentication_statement_algorithm" type="text" id="adv_dhcp6_authentication_statement_algorithm" value="<?=$pconfig['adv_dhcp6_authentication_statement_algorithm'];?>" />
                            <i><?=gettext("rdm"); ?></i>
                            <input name="adv_dhcp6_authentication_statement_rdm" type="text" id="adv_dhcp6_authentication_statement_rdm" value="<?=$pconfig['adv_dhcp6_authentication_statement_rdm'];?>" />
                          </td>
                        </tr>
                        <tr class="dhcpv6_advanced">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Keyinfo");?></td>
                          <td>
                            <i><?=gettext("keyname"); ?></i>
                            <input name="adv_dhcp6_key_info_statement_keyname" type="text" id="adv_dhcp6_key_info_statement_keyname" value="<?=$pconfig['adv_dhcp6_key_info_statement_keyname'];?>" />
                            <i><?=gettext("realm"); ?></i>
                            <input name="adv_dhcp6_key_info_statement_realm" type="text" id="adv_dhcp6_key_info_statement_realm" value="<?=$pconfig['adv_dhcp6_key_info_statement_realm'];?>" />
                            <br />
                            <i><?=gettext("keyid"); ?></i>
                            <input name="adv_dhcp6_key_info_statement_keyid" type="text" id="adv_dhcp6_key_info_statement_keyid" value="<?=$pconfig['adv_dhcp6_key_info_statement_keyid'];?>" />
                            <i><?=gettext("secret"); ?></i>
                            <input name="adv_dhcp6_key_info_statement_secret" type="text" id="adv_dhcp6_key_info_statement_secret" value="<?=$pconfig['adv_dhcp6_key_info_statement_secret'];?>" />
                            <i><?=gettext("expire"); ?></i>
                            <input name="adv_dhcp6_key_info_statement_expire" type="text" id="adv_dhcp6_key_info_statement_expire" value="<?=$pconfig['adv_dhcp6_key_info_statement_expire'];?>" />
                          </td>
                        </tr>
                        <tr class="dhcpv6_file_override">
                          <td><a id="help_for_adv_dhcp6_config_file_override_path" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Configuration File Override");?></td>
                          <td>
                            <input name="adv_dhcp6_config_file_override_path" type="text" id="adv_dhcp6_config_file_override_path"  value="<?=$pconfig['adv_dhcp6_config_file_override_path'];?>" />
                            <div class="hidden" data-for="help_for_adv_dhcp6_config_file_override_path">
                              <?= gettext('The value in this field is the full absolute path to a DHCP client configuration file.') ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- Section : 6RD-->
                <div class="tab-content content-box col-xs-12 __mb" id="6rd" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("6RD Rapid Deployment"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><a id="help_for_prefix-6rd" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("6RD prefix"); ?></td>
                          <td style="width:78%">
                            <input name="prefix-6rd" type="text" id="prefix-6rd" value="<?=$pconfig['prefix-6rd'];?>" />
                            <div class="hidden" data-for="help_for_prefix-6rd">
                              <?=gettext("The value in this field is the 6RD IPv6 prefix assigned by your ISP. e.g. '2001:db8::/32'") ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_gateway-6rd" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("6RD Border Relay"); ?></td>
                          <td>
                            <input name="gateway-6rd" type="text" id="gateway-6rd" value="<?=$pconfig['gateway-6rd'];?>" />
                            <div class="hidden" data-for="help_for_gateway-6rd">
                              <?=gettext("The value in this field is 6RD IPv4 gateway address assigned by your ISP") ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_prefix-6rd-v4plen" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("6RD IPv4 Prefix length"); ?></td>
                          <td>
                            <select name="prefix-6rd-v4plen" class="selectpicker" data-size="10" data-style="btn-default" id="prefix-6rd-v4plen">
<?php
                              for ($i = 0; $i <= 32; $i++):?>
                                <option value="<?=$i;?>" <?= $i == $pconfig['prefix-6rd-v4plen'] ? "selected=\"selected\"" : "";?>>
                                  <?=$i;?> <?=gettext("bits");?>
                                </option>
<?php
                              endfor;?>
                            </select>
                            <div class="hidden" data-for="help_for_prefix-6rd-v4plen">
                              <?=gettext("The value in this field is the 6RD IPv4 prefix length. A value of 0 means we embed the entire IPv4 address in the 6RD prefix."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_prefix-6rd-v4addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('6RD IPv4 Prefix address') ?></td>
                          <td>
                            <input name="prefix-6rd-v4addr" type="text" id="prefix-6rd-v6addr" value="<?= html_safe($pconfig['prefix-6rd-v4addr']) ?>" placeholder="<?= html_safe(gettext('Auto-detect')) ?>"/>
                            <div class="hidden" data-for="help_for_prefix-6rd-v4addr">
                              <?= gettext('The value in this field is the 6RD IPv4 prefix address. Optionally overrides the automatic detection.') ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- Section : Track 6 -->
                <div class="tab-content content-box col-xs-12 __mb" id="track6" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("Track IPv6 Interface"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><a id="help_for_track6-interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Parent interface') ?></td>
                          <td style="width:78%">
                            <select name='track6-interface' class='selectpicker' data-style='btn-default' >
<?php
                            foreach ($ifdescrs as $iface => $ifcfg):
                              switch ($config['interfaces'][$iface]['ipaddrv6'] ?? 'none') {
                                case '6rd':
                                case '6to4':
                                case 'dhcp6':
                                case 'slaac':
                                    break;
                                default:
                                    continue 2;
                              }?>
                                <option value="<?=$iface;?>" <?=$iface == $pconfig['track6-interface'] ? " selected=\"selected\"" : "";?>>
                                  <?= htmlspecialchars($ifcfg['descr']) ?>
                                </option>
<?php
                            endforeach;?>
                            </select>
                            <div class="hidden" data-for="help_for_track6-interface">
                              <?=gettext("This selects the dynamic IPv6 WAN interface to track for configuration") ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_track6-prefix-id" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Assign prefix ID') ?></td>
                          <td>
<?php
                            if (empty($pconfig['track6-prefix-id'])) {
                                $pconfig['track6-prefix-id'] = 0;
                            }
                            $track6_prefix_id_hex = !empty($pconfig['track6-prefix-id--hex']) ? $pconfig['track6-prefix-id--hex'] : sprintf("%x", $pconfig['track6-prefix-id']); ?>
                            <div class="input-group" style="max-width:348px">
                              <div class="input-group-addon">0x</div>
                              <input name="track6-prefix-id--hex" type="text" class="form-control" id="track6-prefix-id--hex" value="<?= $track6_prefix_id_hex ?>" />
                            </div>
                            <div class="hidden" data-for="help_for_track6-prefix-id">
                              <?= gettext('The value in this field is the delegated hexadecimal IPv6 prefix ID. This determines the configurable /64 network ID based on the dynamic IPv6 connection.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_track6_ifid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Optional interface ID') ?></td>
                          <td>
                            <div class="input-group" style="max-width:348px">
                              <div class="input-group-addon">0x</div>
                              <input name="track6_ifid--hex" type="text" class="form-control" id="track6_ifid--hex" value="<?= html_safe($pconfig['track6_ifid--hex']) ?>" />
                            </div>
                            <div class="hidden" data-for="help_for_track6_ifid">
                              <?= gettext('The value in this field is the numeric IPv6 interface ID used to construct the lower part of the resulting IPv6 prefix address. Setting a hex value will use that fixed value in its lower address part. Please note the maximum usable value is 0x7fffffffffffffff due to a PHP integer restriction.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_dhcpd6_opt" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Manual configuration') ?></td>
                          <td>
                            <input name="dhcpd6track6allowoverride" type="checkbox" value="yes" <?= $pconfig['dhcpd6track6allowoverride'] ? 'checked="checked"' : '' ?>/>
                            <?= gettext('Allow manual adjustment of DHCPv6 and Router Advertisements') ?>
                            <div class="hidden" data-for="help_for_dhcpd6_opt">
                              <?= gettext('If this option is set, you will be able to manually set the DHCPv6 and Router Advertisements service for this interface. Use with care.') ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
<?php
                /* Wireless interface? */
                if (isset($a_interfaces[$if]['wireless'])):?>
                <!-- Section : Wireless -->
                <div class="tab-content content-box col-xs-12 __mb">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("Common wireless configuration - Settings apply to all wireless networks on"); ?> <?=$wlanbaseif;?> </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><a id="help_for_persistcommonwireless" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Persist common settings");?></td>
                          <td style="width:78%">
                            <input name="persistcommonwireless" type="checkbox" value="yes"  id="persistcommonwireless" <?=!empty($pconfig['persistcommonwireless']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_persistcommonwireless">
                              <?=gettext("Enabling this preserves the common wireless configuration through interface deletions and reassignments.");?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Standard"); ?></td>
                          <td>
                            <select name="standard" class="selectpicker" data-size="10" data-style="btn-default" id="standard">
<?php foreach (array_keys($wl_modes) as $wl_standard): ?>
                              <option value="<?=$wl_standard;?>" <?=$pconfig['standard'] == $wl_standard ? "selected=\"selected\"" : "";?>>
                                802.<?=$wl_standard;?>
                              </option>
<?php endforeach ?>
                            </select>
                          </td>
                        </tr>
<?php if (isset($wl_modes['11g'])): ?>
                        <tr>
                          <td><a id="help_for_protmode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> 802.11g OFDM <?=gettext("Protection Mode"); ?></td>
                          <td>
                            <select name="protmode" class="selectpicker" data-style="btn-default" id="protmode">
                              <option <?=$pconfig['protmode'] == 'off' ? "selected=\"selected\"" : "";?> value="off"><?=gettext("Protection mode off"); ?></option>
                              <option <?=$pconfig['protmode'] == 'cts' ? "selected=\"selected\"" : "";?> value="cts"><?=gettext("Protection mode CTS to self"); ?></option>
                              <option <?=$pconfig['protmode'] == 'rtscts' ? "selected=\"selected\"" : "";?> value="rtscts"><?=gettext("Protection mode RTS and CTS"); ?></option>
                            </select>
                            <div class="hidden" data-for="help_for_protmode">
                              <?=gettext("For IEEE 802.11g, use the specified technique for protecting OFDM frames in a mixed 11b/11g network."); ?>
                            </div>
                          </td>
                        </tr>
<?php else: ?>
                          <input name="protmode" type="hidden" id="protmode" value="off" />
<?php endif ?>
                        <tr>
                          <td><a id="help_for_txpower" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Transmit power"); ?></td>
                          <td>
                            <select name="txpower" class="selectpicker" data-size="10" data-style="btn-default" id="txpower">
                              <option value=""><?= gettext('default') ?></option>
<?php
                            for($x = 99; $x > 0; $x--):?>
                              <option value="<?=$x;?>" <?=$pconfig['txpower'] == $x ? 'selected="selected"' : '';?>><?=$x;?></option>
<?php
                              endfor;?>
                            </select>
                            <div class="hidden" data-for="help_for_txpower">
                              <?=gettext("Typically only a few discreet power settings are available and the driver will use the setting closest to the specified value. Not all adapters support changing the transmit power setting."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_channel" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Channel"); ?></td>
                          <td>
                            <select name="channel" class="selectpicker" data-size="10" data-style="btn-default" id="channel">
                              <option <?= $pconfig['channel'] == 0 ? "selected=\"selected\"" : ""; ?> value="0"><?=gettext("Auto"); ?></option>
<?php
                              $wl_chaninfo = get_wireless_channel_info($if);
                              $wl_chanlist = [];
                              foreach ($wl_modes as $wl_standard => $wl_channels) {
                                  foreach ($wl_channels as $wl_channel) {
                                      $wl_chanlist[$wl_channel][$wl_standard] = 1;
                                  }
                              }
                              ksort($wl_chanlist);
                              foreach($wl_chanlist as $wl_channel => $wl_standards): ?>
                              <option value="<?= html_safe($wl_channel) ?>" <?=$pconfig['channel'] == $wl_channel ? 'selected="selected"' : '' ?>>
                                  <?=$wl_channel ?> - <?= join(', ', array_keys($wl_standards)) ?>
                                  <?= isset($wl_chaninfo[$wl_channel]) ? "({$wl_chaninfo[$wl_channel]})" : '' ?>
                              </option>
<?php endforeach ?>
                            </select>
                            <div class="hidden" data-for="help_for_channel">
                              <?=gettext("Legend: wireless standards - channel # (frequency @ max TX power / TX power allowed in reg. domain)"); ?>
                              <br />
                              <?=gettext("Not all channels may be supported by your card. Auto may override the wireless standard selected above."); ?>
                            </div>
                          </td>
                        </tr>
<?php
                        if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"]) || isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"]) || isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])): ?>
                        <tr>
                          <td><a id="help_for_antenna_settings" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Antenna settings"); ?></td>
                          <td>
                            <table class="table table-condensed">
                              <tr>
<?php
                              if (isset($wl_sysctl["{$wl_sysctl_prefix}.diversity"])): ?>
                                <td>
                                  <?=gettext("Diversity"); ?><br />
                                  <select name="diversity" class="selectpicker" data-style="btn-default" id="diversity">
                                    <option <?=!isset($pconfig['diversity']) ? "selected=\"selected\"" : ""; ?> value=""><?=gettext("Default"); ?></option>
                                    <option <?=$pconfig['diversity'] === '0' ? "selected=\"selected\"" : ""; ?> value="0"><?=gettext("Off"); ?></option>
                                    <option <?=$pconfig['diversity'] === '1' ? "selected=\"selected\"" : ""; ?> value="1"><?=gettext("On"); ?></option>
                                  </select>
                                </td>
                                <td>&nbsp;&nbsp;</td>
<?php
                              endif;
                              if (isset($wl_sysctl["{$wl_sysctl_prefix}.txantenna"])): ?>
                                <td>
                                  <?=gettext("Transmit antenna"); ?><br />
                                  <select name="txantenna" class="selectpicker" data-style="btn-default" id="txantenna">
                                    <option <?=!isset($pconfig['txantenna']) ? "selected=\"selected\"" : ""; ?> value=""><?=gettext("Default"); ?></option>
                                    <option <?=$pconfig['txantenna'] === '0' ? "selected=\"selected\"" : ""; ?> value="0"><?=gettext("Auto"); ?></option>
                                    <option <?=$pconfig['txantenna'] === '1' ? "selected=\"selected\"" : ""; ?> value="1"><?=gettext("#1"); ?></option>
                                    <option <?=$pconfig['txantenna'] === '2' ? "selected=\"selected\"" : ""; ?> value="2"><?=gettext("#2"); ?></option>
                                  </select>
                                </td>
                                <td>&nbsp;&nbsp;</td>
<?php
                              endif;
                              if (isset($wl_sysctl["{$wl_sysctl_prefix}.rxantenna"])): ?>
                                <td>
                                  <?=gettext("Receive antenna"); ?><br />
                                  <select name="rxantenna" class="selectpicker" data-style="btn-default" id="rxantenna">
                                    <option <?=!isset($pconfig['rxantenna']) ? "selected=\"selected\"" : ""; ?> value=""><?=gettext("Default"); ?></option>
                                    <option <?=$pconfig['rxantenna'] === '0' ? "selected=\"selected\"" : ""; ?> value="0"><?=gettext("Auto"); ?></option>
                                    <option <?=$pconfig['rxantenna'] === '1' ? "selected=\"selected\"" : ""; ?> value="1"><?=gettext("#1"); ?></option>
                                    <option <?=$pconfig['rxantenna'] === '2' ? "selected=\"selected\"" : ""; ?> value="2"><?=gettext("#2"); ?></option>
                                  </select>
                                </td>
<?php
                              endif; ?>
                              </tr>
                            </table>
                            <div class="hidden" data-for="help_for_antenna_settings">
                              <?=gettext("Note: The antenna numbers do not always match up with the labels on the card."); ?>
                            </div>
                          </td>
                        </tr>
<?php
                        endif; ?>
                        <tr>
                          <td><a id="help_for_regdomain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Regulatory settings"); ?></td>
                          <td>
                            <?=gettext("Regulatory domain"); ?><br />
                            <select name="regdomain" class="selectpicker" data-style="btn-default" id="regdomain">
                              <option <?= empty($pconfig['regdomain']) ? "selected=\"selected\"" : ""; ?> value=""><?=gettext("Default"); ?></option>
<?php
                              foreach($wl_regdomains as $wl_regdomain_key => $wl_regdomain):?>
                              <option value="<?=$wl_regdomains_attr[$wl_regdomain_key]['ID'];?>" <?=$pconfig['regdomain'] == $wl_regdomains_attr[$wl_regdomain_key]['ID'] ? "selected=\"selected\" " : "";?> >
                                <?=$wl_regdomain['name'];?>
                              </option>
<?php
                              endforeach;?>
                            </select>
                            <br />
                            <div class="hidden" data-for="help_for_regdomain">
                              <?=gettext("Some cards have a default that is not recognized and require changing the regulatory domain to one in this list for the changes to other regulatory settings to work."); ?>
                            </div>
                            <br />
                            <?=gettext("Country (listed with country code and regulatory domain)"); ?><br />
                            <select name="regcountry" class="selectpicker" data-size="10" data-style="btn-default" id="regcountry">
                              <option <?=empty($pconfig['regcountry']) ? "selected=\"selected\"" : ""; ?> value=""><?=gettext("Default"); ?></option>
<?php
                            foreach($wl_countries as $wl_country_key => $wl_country):?>
                              <option value="<?=$wl_countries_attr[$wl_country_key]['ID'];?>" <?=$pconfig['regcountry'] == $wl_countries_attr[$wl_country_key]['ID'] ?  "selected=\"selected\" " : "";?> >
                                  <?=$wl_country['name'];?> (<?=$wl_countries_attr[$wl_country_key]['ID'];?> <?=strtoupper($wl_countries_attr[$wl_country_key]['rd'][0]['REF']);?>)
                              </option>
<?php
                            endforeach;?>
                            </select>
                            <br />
                            <div class="hidden" data-for="help_for_regdomain">
                              <?=gettext("Any country setting other than \"Default\" will override the regulatory domain setting"); ?>.
                            </div>
                            <br />
                            <?=gettext("Location"); ?><br />
                            <select name="reglocation" class="selectpicker" data-style="btn-default" id="reglocation">
                              <option <?=empty($pconfig['reglocation']) ? "selected=\"selected\"" : ""; ?> value=""><?=gettext("Default"); ?></option>
                              <option <?=$pconfig['reglocation'] == 'indoor' ? "selected=\"selected\"" : ""; ?> value="indoor"><?=gettext("Indoor"); ?></option>
                              <option <?=$pconfig['reglocation'] == 'outdoor' ? "selected=\"selected\"" : ""; ?> value="outdoor"><?=gettext("Outdoor"); ?></option>
                              <option <?=$pconfig['reglocation'] == 'anywhere' ? "selected=\"selected\"" : ""; ?> value="anywhere"><?=gettext("Anywhere"); ?></option>
                            </select>
                            <div class="hidden" data-for="help_for_regdomain">
                              <?=gettext("These settings may affect which channels are available and the maximum transmit power allowed on those channels. Using the correct settings to comply with local regulatory requirements is recommended."); ?>
                              <br />
                              <?=gettext("All wireless networks on this interface will be temporarily brought down when changing regulatory settings. Some of the regulatory domains or country codes may not be allowed by some cards. These settings may not be able to add additional channels that are not already supported."); ?>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="tab-content content-box col-xs-12 __mb">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("Network-specific wireless configuration");?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Mode"); ?></td>
                          <td>
                            <select name="mode" class="selectpicker" data-style="btn-default" id="mode">
                              <option <?=$pconfig['mode'] == 'bss' ? "selected=\"selected\"" : "";?> value="bss"><?=gettext("Infrastructure (BSS)"); ?></option>
<?php if (test_wireless_capability(get_real_interface($pconfig['if']), 'adhoc')): ?>
                              <option <?=$pconfig['mode'] == 'adhoc' ? "selected=\"selected\"" : "";?> value="adhoc"><?=gettext("Ad-hoc (IBSS)"); ?></option>
<?php endif ?>
<?php if (test_wireless_capability(get_real_interface($pconfig['if']), 'hostap')): ?>
                              <option <?=$pconfig['mode'] == 'hostap' ? "selected=\"selected\"" : "";?> value="hostap"><?=gettext("Access Point"); ?></option>
<?php endif ?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_ssid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("SSID"); ?></td>
                          <td>
                            <input name="ssid" type="text" id="ssid" value="<?=$pconfig['ssid'];?>" />
                            <div class="hidden" data-for="help_for_ssid">
                              <?=gettext("Note: Only required in Access Point mode. If left blank in Ad-hoc or Infrastructure mode, this interface will connect to any available SSID"); ?>
                            </div>
                          </td>
                        </tr>
<?php if (isset($wl_modes['11ng']) || isset($wl_modes['11na'])): ?>
                        <tr>
                          <td><a id="help_for_puremode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Minimum standard"); ?></td>
                          <td>
                            <select name="puremode" class="selectpicker" data-style="btn-default" id="puremode">
                              <option <?=$pconfig['puremode'] == 'any' ? "selected=\"selected\"" : "";?> value="any"><?=gettext("Any"); ?></option>
<?php if (isset($wl_modes['11g'])): ?>
                              <option <?=$pconfig['puremode'] == '11g' ? "selected=\"selected\"" : "";?> value="11g"><?=gettext("802.11g"); ?></option>
<?php endif ?>
                              <option <?=$pconfig['puremode'] == '11n' ? "selected=\"selected\"" : "";?> value="11n"><?=gettext("802.11n"); ?></option>
                            </select>
                            <div class="hidden" data-for="help_for_puremode">
                              <?=gettext("When operating as an access point, allow only stations capable of the selected wireless standard to associate (stations not capable are not permitted to associate)."); ?>
                            </div>
                          </td>
                        </tr>
<?php elseif (isset($wl_modes['11g'])): ?>
                        <tr>
                          <td><a id="help_for_puremode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.11g only"); ?></td>
                          <td>
                            <input name="puremode" type="checkbox" value="11g"  id="puremode" <?php if ($pconfig['puremode'] == '11g') echo "checked=\"checked\"";?> />
                            <div class="hidden" data-for="help_for_puremode">
                              <?=gettext("When operating as an access point in 802.11g mode, allow only 11g-capable stations to associate (11b-only stations are not permitted to associate)."); ?>
                            </div>
                          </td>
                        </tr>
<?php endif ?>
                        <tr class="cfg-wireless-ap">
                          <td><a id="help_for_apbridge_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Allow intra-BSS communication"); ?></td>
                          <td>
                            <input name="apbridge_enable" type="checkbox" value="yes"  id="apbridge_enable" <?=!empty($pconfig['apbridge_enable']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_apbridge_enable">
                              <?=gettext("When operating as an access point, enable this if you want to pass packets between wireless clients directly."); ?>
                              <br />
                              <?=gettext("Disabling the internal bridging is useful when traffic is to be processed with packet filtering."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_wme_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable WME"); ?></td>
                          <td>
                            <input name="wme_enable" type="checkbox" id="wme_enable" value="yes" <?=!empty($pconfig['wme_enable']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_wme_enable">
                              <?=gettext("Setting this option will force the card to use WME (wireless QoS)."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ap cfg-wireless-adhoc">
                          <td><a id="help_for_hidessid_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable Hide SSID"); ?></td>
                          <td>
                            <input name="hidessid_enable" type="checkbox" id="hidessid_enable" value="yes" <?=!empty($pconfig['hidessid_enable']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_hidessid_enable">
                              <?=gettext("Setting this option will force the card to NOT broadcast its SSID (this might create problems for some clients)."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_wep" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WEP"); ?></td>
                          <td>
                            <input name="wep_enable" type="checkbox" id="wep_enable" value="yes" <?= $pconfig['wep_enable'] ? "checked=\"checked\"" : ""; ?> />
                            <label for="wep_enable"><?=gettext("Enable WEP"); ?></label>
                            <table class="table table-condensed cfg-wireless-wep">
                              <tr>
                                <td></td>
                                <td></td>
                                <td><?=gettext("TX key"); ?></td>
                              </tr>
                              <tr>
                                <td><?=gettext("Key 1:"); ?></td>
                                <td>
                                  <input name="key1" type="text" id="key1" value="<?=$pconfig['key1'];?>" />
                                </td>
                                <td>
                                  <input name="txkey" type="radio" value="1" <?=$pconfig['txkey'] == 1 ? "checked=\"checked\"" : "";?> />
                                </td>
                              </tr>
                              <tr>
                                <td><?=gettext("Key 2:"); ?></td>
                                <td>
                                  <input name="key2" type="text" id="key2" value="<?=$pconfig['key2'];?>" />
                                </td>
                                <td>
                                  <input name="txkey" type="radio" value="2" <?= $pconfig['txkey'] == 2 ? "checked=\"checked\"" :"";?> />
                                </td>
                              </tr>
                              <tr>
                                <td><?=gettext("Key 3:"); ?></td>
                                <td>
                                  <input name="key3" type="text" id="key3" value="<?=$pconfig['key3'];?>" />
                                </td>
                                <td>
                                  <input name="txkey" type="radio" value="3" <?= $pconfig['txkey'] == 3 ? "checked=\"checked\"" : "";?> />
                                </td>
                              </tr>
                              <tr>
                                <td><?=gettext("Key 4:"); ?></td>
                                <td>
                                  <input name="key4" type="text" id="key4" value="<?=$pconfig['key4'];?>" />
                                </td>
                                <td>
                                  <input name="txkey" type="radio" value="4" <?= $pconfig['txkey'] == 4 ? "checked=\"checked\"" : "";?> />
                                </td>
                              </tr>
                            </table>
                            <div class="hidden" data-for="help_for_wep">
                              <?=gettext("40 (64) bit keys may be entered as 5 ASCII characters or 10 hex digits preceded by '0x'."); ?><br />
                              <?=gettext("104 (128) bit keys may be entered as 13 ASCII characters or 26 hex digits preceded by '0x'."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WPA"); ?></td>
                          <td>
                            <input name="wpa_enable" type="checkbox" id="wpa_enable" value="yes" <?php if ($pconfig['wpa_enable']) echo "checked=\"checked\""; ?> />
                            <label for="wpa_enable"><?=gettext("Enable WPA"); ?></label>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-eap">
                          <td><a id="help_for_wpa_identity" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WPA EAP Identity"); ?></td>
                          <td>
                            <input name="identity" type="text" id="identity" value="<?=$pconfig['identity'];?>" />
                            <div class="hidden" data-for="help_for_wpa_identity">
                              <?=gettext("Only relevant when Extended Authentication Protocol (EAP) is used."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-wpa">
                          <td><a id="help_for_wpa_passphrase" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WPA Pre-Shared Key/EAP Password"); ?></td>
                          <td>
                            <input name="passphrase" type="text" id="passphrase" value="<?=$pconfig['passphrase'];?>" />
                            <div class="hidden" data-for="help_for_wpa_passphrase">
                              <?=gettext("Passphrase must be from 8 to 63 characters."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-wpa">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WPA Mode"); ?></td>
                          <td>
                            <select name="wpa_mode" class="selectpicker" data-style="btn-default" id="wpa_mode">
                              <option <?=$pconfig['wpa_mode'] == '1' ? "selected=\"selected\"" : "";?> value="1"><?=gettext("WPA"); ?></option>
                              <option <?=$pconfig['wpa_mode'] == '2' ? "selected=\"selected\"" : "";?> value="2"><?=gettext("WPA2"); ?></option>
                              <option <?=$pconfig['wpa_mode'] == '3' ? "selected=\"selected\"" : "";?> value="3"><?=gettext("Both"); ?></option>
                            </select>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-wpa">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WPA Key Management Mode"); ?></td>
                          <td>
                            <select name="wpa_key_mgmt" class="selectpicker" data-style="btn-default" id="wpa_key_mgmt">
                              <option <?=$pconfig['wpa_key_mgmt'] == 'WPA-PSK' ? "selected=\"selected\"" : "";?> value="WPA-PSK"><?=gettext("Pre-Shared Key"); ?></option>
                              <option <?=$pconfig['wpa_key_mgmt'] == 'WPA-EAP' ? "selected=\"selected\"" : "";?> value="WPA-EAP"><?=gettext("Extensible Authentication Protocol (EAP)"); ?></option>
                              <option <?=$pconfig['wpa_key_mgmt'] == 'WPA-PSK WPA-EAP' ? "selected=\"selected\"" : "";?> value="WPA-PSK WPA-EAP"><?=gettext("Both"); ?></option>
                            </select>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-eap">
                          <td><a id="help_for_eap_method" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("EAP Method"); ?></td>
                          <td>
                            <select name="wpa_eap_method" class="selectpicker" data-style="btn-default" id="wpa_eap_method">
                              <option <?=$pconfig['wpa_eap_method'] == 'PEAP' ? "selected=\"selected\"" : "";?> value="PEAP"><?=gettext("Protected Extensible Authentication Protocol (PEAP)"); ?></option>
                              <option <?=$pconfig['wpa_eap_method'] == 'TLS' ? "selected=\"selected\"" : "";?> value="TLS"><?=gettext("Transport Layer Security (TLS)"); ?></option>
                              <option <?=$pconfig['wpa_eap_method'] == 'TTLS' ? "selected=\"selected\"" : "";?> value="TTLS"><?=gettext("Tunneled Transport Layer Security (TTLS)"); ?></option>
                            </select>
                            <div class="hidden" data-for="help_for_eap_method">
                              <?=gettext("Note: Only relevant for infrastructure mode (BSS) and if Extensible Authentication Protocol (EAP) is used for key management."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-eap">
                          <td><a id="help_for_p2_auth" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("EAP Phase 2 Authentication"); ?></td>
                          <td>
                            <select name="wpa_eap_p2_auth" class="selectpicker" data-style="btn-default" id="eap_p2_auth">
                              <option <?=$pconfig['wpa_eap_p2_auth'] == 'MD5' ? "selected=\"selected\"" : "";?> value="MD5"><?=gettext("MD5"); ?></option>
                              <option <?=$pconfig['wpa_eap_p2_auth'] == 'MSCHAPv2' ? "selected=\"selected\"" : "";?> value="MSCHAPv2"><?=gettext("MSCHAPv2"); ?></option>
                            </select>
                            <div class="hidden" data-for="help_for_p2_auth">
                              <?=gettext("Note: Only relevant for infrastructure mode (BSS) and if Extensible Authentication Protocol (EAP) is used for key management."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-eap">
                          <td><a id="help_for_cacertref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("EAP TLS CA Certificate"); ?></td>
                          <td>
                            <select name="wpa_eap_cacertref" class="selectpicker" data-style="btn-default">
                              <option value="" <?=empty($pconfig['wpa_eap_cacertref']) ? "selected=\"selected\"" : "";?>><?=gettext("Do not verify server"); ?></option>
          <?php foreach ($a_ca as $ca): ?>
                              <option value="<?=$ca['refid'];?>" <?=$pconfig['wpa_eap_cacertref'] == $ca['refid'] ? "selected=\"selected\"" : "";?>>
                                <?=$ca['descr'];?>
                              </option>
          <?php endforeach ?>
                            </select>
                            <div class='hidden' data-for="help_for_cacertref">
                              <?=gettext('Certificate authority used to verify the access point\'s TLS certificate. Only relevant for infrastructure mode (BSS) if Extensible Authentication Protocol (EAP) is used for key management.');?><br />
                              <?=sprintf(
                                gettext('The %scertificate authority manager%s can be used to ' .
                                'create or import certificat authorities if required.'),
                                '<a href="/system_camanager.php">', '</a>'
                              );?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-eap">
                          <td><a id="help_for_clientcertref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("EAP TLS Client Certificate"); ?></td>
                          <td>
                            <select name="wpa_eap_cltcertref" class="selectpicker" data-style="btn-default">
                              <option value="" <?=empty($pconfig['wpa_eap_cltcertref']) ? "selected=\"selected\"" : "";?>><?=gettext("none"); ?></option>
          <?php foreach ($a_cert as $cert): ?>
          <?php if (isset($cert['prv'])): ?>
                              <option value="<?=$cert['refid'];?>" <?=$pconfig['wpa_eap_cltcertref'] == $cert['refid'] ? "selected=\"selected\"" : "";?>>
                                <?=$cert['descr'];?>
                              </option>
          <?php endif ?>
          <?php endforeach ?>
                            </select>
                            <div class='hidden' data-for="help_for_clientcertref">
                              <?=gettext('Certificate used for authentication towards the access point. Only relevant for infrastructure mode (BSS) if EAP with TLS is used for key management.');?><br />
                              <?=sprintf(
                                gettext('The %scertificate manager%s can be used to ' .
                                'create or import certificates if required.'),
                                '<a href="/system_certmanager.php">', '</a>'
                              );?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ap">
                          <td><a id="help_for_auth_algs" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Access Point Authentication"); ?></td>
                          <td>
                            <select name="auth_algs" class="selectpicker" data-style="btn-default" id="auth_algs">
                              <option <?=$pconfig['auth_algs'] == '1' ? "selected=\"selected\"" : "";?> value="1"><?=gettext("Open System Authentication"); ?></option>
                              <option <?=$pconfig['auth_algs'] == '2' ? "selected=\"selected\"" : "";?> value="2"><?=gettext("Shared Key Authentication"); ?></option>
                              <option <?=$pconfig['auth_algs'] == '3' ? "selected=\"selected\"" : "";?> value="3"><?=gettext("Both"); ?></option>
                            </select>
                            <div class="hidden" data-for="help_for_auth_algs">
                              <?=gettext("Note: Shared Key Authentication requires WEP. Only relevant for access point mode."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-wpa">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WPA Pairwise"); ?></td>
                          <td>
                            <select name="wpa_pairwise" class="selectpicker" data-style="btn-default" id="wpa_pairwise">
                              <option <?=$pconfig['wpa_pairwise'] == 'CCMP TKIP' ? "selected=\"selected\"" : "";?> value="CCMP TKIP"><?=gettext("Both"); ?></option>
                              <option <?=$pconfig['wpa_pairwise'] == 'CCMP' ? "selected=\"selected\"" : "";?> value="CCMP"><?=gettext("AES (recommended)"); ?></option>
                              <option <?=$pconfig['wpa_pairwise'] == 'TKIP' ? "selected=\"selected\"" : "";?> value="TKIP"><?=gettext("TKIP"); ?></option>
                            </select>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-wpa">
                          <td><a id="help_for_wpa_group_rekey" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Key Rotation"); ?></td>
                          <td>
                            <input name="wpa_group_rekey" type="text" id="wpa_group_rekey" value="<?=!empty($pconfig['wpa_group_rekey']) ? $pconfig['wpa_group_rekey'] : "60";?>" />
                            <div class="hidden" data-for="help_for_wpa_group_rekey">
                              <?=gettext("Allowed values are 1-9999 but should not be longer than Master Key Regeneration time."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-wpa">
                          <td><a id="help_for_wpa_gmk_rekey" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Master Key Regeneration"); ?></td>
                          <td>
                            <input name="wpa_gmk_rekey" type="text" id="wpa_gmk_rekey" value="<?=!empty($pconfig['wpa_gmk_rekey']) ? $pconfig['wpa_gmk_rekey'] : "3600";?>" />
                            <div class="hidden" data-for="help_for_wpa_gmk_rekey">
                              <?=gettext("Allowed values are 1-9999 but should not be shorter than Key Rotation time."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-wpa">
                          <td><a id="help_for_wpa_strict_rekey" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Strict Key Regeneration"); ?></td>
                          <td>
                            <input name="wpa_strict_rekey" type="checkbox" value="yes"  id="wpa_strict_rekey" <?php if ($pconfig['wpa_strict_rekey']) echo "checked=\"checked\""; ?> />
                            <div class="hidden" data-for="help_for_wpa_strict_rekey">
                              <?=gettext("Setting this option will force the AP to rekey whenever a client disassociates."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ap-wpa">
                          <td><a id="help_for_ieee8021x" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable IEEE802.1X Authentication"); ?></td>
                          <td>
                            <input name="ieee8021x" type="checkbox" value="yes"  id="ieee8021x" <?=!empty($pconfig['ieee8021x']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_ieee8021x">
                              <?=gettext("Setting this option will enable 802.1x authentication."); ?><br/>
                              <span class="text-danger"><?=gettext("This option requires checking the \"Enable WPA box\"."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ieee8021x">
                          <td><a id="help_for_auth_server_addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server IP Address"); ?></td>
                          <td>
                            <input name="auth_server_addr" id="auth_server_addr" type="text" value="<?=$pconfig['auth_server_addr'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_addr">
                              <?=gettext("Enter the IP address of the 802.1X Authentication Server. This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ieee8021x">
                          <td><a id="help_for_auth_server_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server Port"); ?></td>
                          <td>
                            <input name="auth_server_port" id="auth_server_port" type="text" value="<?=$pconfig['auth_server_port'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_port">
                              <?=gettext("Leave blank for the default 1812 port."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ieee8021x">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("802.1X Server Shared Secret"); ?></td>
                          <td>
                            <input name="auth_server_shared_secret" id="auth_server_shared_secret" type="text" value="<?=$pconfig['auth_server_shared_secret'];?>" />
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ieee8021x">
                          <td><a id="help_for_auth_server_addr2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server IP Address (2)"); ?></td>
                          <td>
                            <input name="auth_server_addr2" id="auth_server_addr2" type="text" value="<?=$pconfig['auth_server_addr2'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_addr2">
                              <?=gettext("Secondary 802.1X Authentication Server IP Address"); ?><br>
                              <?=gettext("Enter the IP address of the 802.1X Authentication Server. This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ieee8021x">
                          <td><a id="help_for_auth_server_port2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server Port (2)"); ?></td>
                          <td>
                            <input name="auth_server_port2" id="auth_server_port2" type="text" value="<?=$pconfig['auth_server_port2'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_port2">
                              <?=gettext("Secondary 802.1X Authentication Server Port"); ?><br />
                              <?=gettext("Leave blank for the default 1812 port."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ieee8021x">
                          <td><a id="help_for_auth_server_shared_secret2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server Shared Secret (2)"); ?></td>
                          <td>
                            <input name="auth_server_shared_secret2" id="auth_server_shared_secret2" type="text" value="<?=$pconfig['auth_server_shared_secret2'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_shared_secret2">
                              <?=gettext("Secondary 802.1X Authentication Server Shared Secret"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="cfg-wireless-ieee8021x">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("802.1X Roaming Preauth"); ?></td>
                          <td>
                            <input name="rsn_preauth" id="rsn_preauth" type="checkbox" value="yes" <?=!empty($pconfig['rsn_preauth']) ? "checked=\"checked\"" : ""; ?> />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
<?php
                endif; ?>
              <!-- End "allcfg" div -->
              </div>
              <div class="tab-content content-box col-xs-12 __mb">
                <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <tr>
                        <td style="width:22%"></td>
                        <td style="width:78%">
                          <input id="save" name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                          <input id="cancel" type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/interfaces.php'" />
                          <input name="if" type="hidden" id="if" value="<?=$if;?>" />
                        </td>
                      </tr>
                    </table>
                  </div>
                </div>
              </form>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc");
