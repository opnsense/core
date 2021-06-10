<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
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
require_once("rrd.inc");
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
        log_error('Error: could not open XML input');
        if (isset($parsed_attributes)) {
            $parsed_attributes = array();
            unset($parsedattrs);
        }
        return -1;
    }

    while ($data = fread($fp, 4096)) {
        if (!xml_parse($xml_parser, $data, feof($fp))) {
            log_error(sprintf('XML error: %s at line %d' . "\n",
                  xml_error_string(xml_get_error_code($xml_parser)),
                  xml_get_current_line_number($xml_parser)));
            if (isset($parsed_attributes)) {
                $parsed_attributes = array();
                unset($parsedattrs);
            }
            return -1;
        }
    }
    xml_parser_free($xml_parser);

    if (!$parsedcfg[$rootobj]) {
        log_error(sprintf('XML error: no %s object found!', $rootobj));
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

function get_wireless_modes($interface) {
    /* return wireless modes and channels */
    $wireless_modes = array();

    $cloned_interface = get_real_interface($interface);

    if ($cloned_interface && is_interface_wireless($cloned_interface)) {
        $chan_list = "/sbin/ifconfig {$cloned_interface} list chan";
        $stack_list = "/usr/bin/awk -F\"Channel \" '{ gsub(/\\*/, \" \"); print \$2 \"\\\n\" \$3 }'";
        $format_list = "/usr/bin/awk '{print \$5 \" \" \$6 \",\" \$1}'";

        $interface_channels = [];
        exec("$chan_list | $stack_list | sort -u | $format_list 2>&1", $interface_channels);

        foreach ($interface_channels as $c => $interface_channel) {
            $channel_line = explode(",", $interface_channel);
            $wireless_mode = trim($channel_line[0]);
            $wireless_channel = trim($channel_line[1]);
            if (trim($wireless_mode) != "") {
                /* if we only have 11g also set 11b channels */
                if ($wireless_mode == "11g") {
                    if (!isset($wireless_modes["11b"])) {
                        $wireless_modes["11b"] = array();
                    }
                } elseif ($wireless_mode == "11g ht") {
                    if (!isset($wireless_modes["11b"])) {
                        $wireless_modes["11b"] = array();
                    } elseif (!isset($wireless_modes["11g"])) {
                        $wireless_modes["11g"] = array();
                    }
                    $wireless_mode = "11ng";
                } elseif ($wireless_mode == "11a ht") {
                    if (!isset($wireless_modes["11a"])) {
                        $wireless_modes["11a"] = array();
                    }
                    $wireless_mode = "11na";
                }
                $wireless_modes[$wireless_mode][$c] = $wireless_channel;
            }
        }
    }
    return($wireless_modes);
}

/* return channel numbers, frequency, max txpower, and max regulation txpower */
function get_wireless_channel_info($interface) {
    $wireless_channels = array();

    $cloned_interface = get_real_interface($interface);
    if ($cloned_interface && is_interface_wireless($cloned_interface)) {
        $chan_list = "/sbin/ifconfig {$cloned_interface} list txpower";
        $stack_list = "/usr/bin/awk -F\"Channel \" '{ gsub(/\\*/, \" \"); print \$2 \"\\\n\" \$3 }'";
        $format_list = "/usr/bin/awk '{print \$1 \",\" \$3 \" \" \$4 \",\" \$5 \",\" \$7}'";

        $interface_channels = [];
        exec("$chan_list | $stack_list | sort -u | $format_list 2>&1", $interface_channels);

        foreach ($interface_channels as $channel_line) {
            $channel_line = explode(",", $channel_line);
            if (!isset($wireless_channels[$channel_line[0]])) {
                $wireless_channels[$channel_line[0]] = $channel_line;
            }
        }
    }
    return($wireless_channels);
}

$ifdescrs = legacy_config_get_interfaces(array('virtual' => false));

$a_interfaces = &config_read_array('interfaces');
$a_ppps = &config_read_array('ppps', 'ppp');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['if']) && !empty($a_interfaces[$_GET['if']])) {
        $if = $_GET['if'];
    } else {
        // no interface provided, redirect to interface assignments
        header(url_safe('Location: /interfaces_assign.php'));
        exit;
    }

    $pconfig = array();
    $std_copy_fieldnames = array(
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
        'dhcp6vlanprio',
        'dhcphostname',
        'dhcprejectfrom',
        'gateway',
        'gateway-6rd',
        'gatewayv6',
        'if',
        'ipaddr',
        'ipaddrv6',
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
    );
    foreach ($std_copy_fieldnames as $fieldname) {
        $pconfig[$fieldname] = isset($a_interfaces[$if][$fieldname]) ? $a_interfaces[$if][$fieldname] : null;
    }
    $pconfig['enable'] = isset($a_interfaces[$if]['enable']);
    $pconfig['lock'] = isset($a_interfaces[$if]['lock']);
    $pconfig['blockpriv'] = isset($a_interfaces[$if]['blockpriv']);
    $pconfig['blockbogons'] = isset($a_interfaces[$if]['blockbogons']);
    $pconfig['gateway_interface'] =  isset($a_interfaces[$if]['gateway_interface']);
    $pconfig['dhcpoverridemtu'] = empty($a_interfaces[$if]['dhcphonourmtu']) ? true : null;
    $pconfig['dhcp6-ia-pd-send-hint'] = isset($a_interfaces[$if]['dhcp6-ia-pd-send-hint']);
    $pconfig['dhcp6prefixonly'] = isset($a_interfaces[$if]['dhcp6prefixonly']);
    $pconfig['dhcp6usev4iface'] = isset($a_interfaces[$if]['dhcp6usev4iface']);
    $pconfig['track6-prefix-id--hex'] = sprintf("%x", empty($pconfig['track6-prefix-id']) ? 0 : $pconfig['track6-prefix-id']);
    $pconfig['dhcpd6track6allowoverride'] = isset($a_interfaces[$if]['dhcpd6track6allowoverride']);

    /*
     * Due to the settings being split per interface type, we need
     * to copy the settings that use the same config directive.
     */
    $pconfig['staticv6usev4iface'] = $pconfig['dhcp6usev4iface'];
    $pconfig['slaacusev4iface'] = $pconfig['dhcp6usev4iface'];

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

    /* locate PPP details (if any) */
    $pppid = count($a_ppps);
    foreach ($a_ppps as $key => $ppp) {
        if ($a_interfaces[$if]['if'] == $ppp['if']) {
            $pppid = $key;
            break;
        }
    }

    $std_ppp_copy_fieldnames = array("ptpid", "ports", "username", "phone", "apn", "provider", "idletimeout", "localip", 'hostuniq');
    foreach ($std_ppp_copy_fieldnames as $fieldname) {
        $pconfig[$fieldname] = isset($a_ppps[$pppid][$fieldname]) ? $a_ppps[$pppid][$fieldname] : null;
    }

    $pconfig['password'] = base64_decode($a_ppps[$pppid]['password']); // ppp password field
    $pconfig['pppoe_dialondemand'] = isset($a_ppps[$pppid]['ondemand']);
    $pconfig['pptp_dialondemand'] = isset($a_ppps[$pppid]['ondemand']);
    $pconfig['pppoe_password'] = $pconfig['password']; // pppoe password field
    $pconfig['pppoe_username'] = $pconfig['username'];
    $pconfig['pppoe_hostuniq'] = $pconfig['hostuniq'];
    $pconfig['pppoe_idletimeout'] = $pconfig['idletimeout'];

    $pconfig['pptp_username'] = $pconfig['username'];
    $pconfig['pptp_password'] = $pconfig['password'];
    $pconfig['pptp_subnet'] = $a_ppps[$pppid]['subnet'];
    $pconfig['pptp_remote'] = $a_ppps[$pppid]['gateway'];
    $pconfig['pptp_idletimeout'] = $a_ppps[$pppid]['timeout'];

    if (isset($a_ppps[$pppid])) {
        $pconfig['pppid'] = $pppid;
    } else {
        $pconfig['ptpid'] = interfaces_ptpid_next();
        $pppid = count($a_ppps);
    }

    if (isset($a_interfaces[$if]['wireless'])) {
        /* Sync first to be sure it displays the actual settings that will be used */
        interface_sync_wireless_clones($a_interfaces[$if], false);
        /* Get wireless modes */
        $wlanif = get_real_interface($if);
        if (!does_interface_exist($wlanif)) {
            interface_wireless_clone($wlanif, $a_interfaces[$if]);
        }
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
              'debug_mode', 'macaddr_acl', 'auth_algs', 'wpa_mode', 'wpa_key_mgmt', 'wpa_pairwise',
              'wpa_group_rekey', 'wpa_gmk_rekey', 'passphrase', 'ext_wpa_sw'
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
        if (is_array($a_interfaces[$if]['wireless']['wep']) && is_array($a_interfaces[$if]['wireless']['wep']['key'])) {
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
        // read physcial interface name from config.xml
        $pconfig['if'] = $a_interfaces[$if]['if'];
    }
    $ifgroup = !empty($_GET['group']) ? $_GET['group'] : '';

    if (!empty($pconfig['apply'])) {
        if (!is_subsystem_dirty('interfaces')) {
            $intput_errors[] = gettext("You have already applied your settings!");
        } else {
            clear_subsystem_dirty('interfaces');

            if (file_exists('/tmp/.interfaces.apply')) {
                $toapplylist = unserialize(file_get_contents('/tmp/.interfaces.apply'));
                foreach ($toapplylist as $ifapply => $ifcfgo) {
                    interface_bring_down($ifapply, $ifcfgo);
                    interface_configure(false, $ifapply, true);
                }

                system_routing_configure();
                plugins_configure('monitor');
                filter_configure();
                foreach ($toapplylist as $ifapply => $ifcfgo) {
                    plugins_configure('newwanip', false, array($ifapply));
                }
                rrd_configure();
            }
        }
        @unlink('/tmp/.interfaces.apply');
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
        // locate sequence in ppp list
        $pppid = count($a_ppps);
        foreach ($a_ppps as $key => $ppp) {
            if ($a_interfaces[$if]['if'] == $ppp['if']) {
                $pppid = $key;
                break;
            }
        }

        $old_ppps = $a_ppps;

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

        if ($pconfig['type'] != 'none' || $pconfig['type6'] != 'none') {
            foreach (plugins_devices() as $device) {
                if (!isset($device['configurable']) || $device['configurable'] == true) {
                    continue;
                }
                if (preg_match('/' . $device['pattern'] . '/', $pconfig['if'])) {
                    $input_errors[] = gettext('Cannot assign an IP configuration type to a tunnel interface.');
                }
            }
        }

        switch ($pconfig['type']) {
            case "staticv4":
                $reqdfields = explode(" ", "ipaddr subnet gateway");
                $reqdfieldsn = array(gettext("IPv4 address"),gettext("Subnet bit count"),gettext("Gateway"));
                do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
                break;
            case "none":
                if (isset($config['virtualip']['vip'])) {
                    foreach ($config['virtualip']['vip'] as $vip) {
                        if (is_ipaddrv4($vip['subnet']) && $vip['interface'] == $if) {
                            $input_errors[] = gettext("This interface is referenced by IPv4 VIPs. Please delete those before setting the interface to 'none' configuration.");
                        }
                    }
                }
                break;
            case "dhcp":
                if (!empty($pconfig['adv_dhcp_config_file_override'] && !file_exists($pconfig['adv_dhcp_config_file_override_path']))) {
                    $input_errors[] = sprintf(gettext('The DHCP override file "%s" does not exist.'), $pconfig['adv_dhcp_config_file_override_path']);
                }
                break;
            case "ppp":
                $reqdfields = explode(" ", "ports phone");
                $reqdfieldsn = array(gettext("Modem Port"),gettext("Phone Number"));
                do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
                break;
            case "pppoe":
                if (!empty($pconfig['pppoe_dialondemand'])) {
                    $reqdfields = explode(" ", "pppoe_username pppoe_password pppoe_dialondemand pppoe_idletimeout");
                    $reqdfieldsn = array(gettext("PPPoE username"),gettext("PPPoE password"),gettext("Dial on demand"),gettext("Idle timeout value"));
                } else {
                    $reqdfields = explode(" ", "pppoe_username pppoe_password");
                    $reqdfieldsn = array(gettext("PPPoE username"),gettext("PPPoE password"));
                }
                do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
                break;
            case "pptp":
                if (!empty($pconfig['pptp_dialondemand'])) {
                    $reqdfields = explode(" ", "pptp_username pptp_password localip pptp_subnet pptp_remote pptp_dialondemand pptp_idletimeout");
                    $reqdfieldsn = array(gettext("PPTP username"),gettext("PPTP password"),gettext("PPTP local IP address"),gettext("PPTP subnet"),gettext("PPTP remote IP address"),gettext("Dial on demand"),gettext("Idle timeout value"));
                } else {
                    $reqdfields = explode(" ", "pptp_username pptp_password localip pptp_subnet pptp_remote");
                    $reqdfieldsn = array(gettext("PPTP username"),gettext("PPTP password"),gettext("PPTP local IP address"),gettext("PPTP subnet"),gettext("PPTP remote IP address"));
                }
                do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
                break;
            case "l2tp":
                if (!empty($pconfig['pptp_dialondemand'])) {
                    $reqdfields = explode(" ", "pptp_username pptp_password pptp_remote pptp_dialondemand pptp_idletimeout");
                    $reqdfieldsn = array(gettext("L2TP username"),gettext("L2TP password"),gettext("L2TP remote IP address"),gettext("Dial on demand"),gettext("Idle timeout value"));
                } else {
                    $reqdfields = explode(" ", "pptp_username pptp_password pptp_remote");
                    $reqdfieldsn = array(gettext("L2TP username"),gettext("L2TP password"),gettext("L2TP remote IP address"));
                }
                do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
                break;
        }

        switch ($pconfig['type6']) {
            case "staticv6":
                $reqdfields = explode(" ", "ipaddrv6 subnetv6 gatewayv6");
                $reqdfieldsn = array(gettext("IPv6 address"),gettext("Subnet bit count"),gettext("Gateway"));
                do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
                break;
            case "dhcp6":
                if (!empty($pconfig['adv_dhcp6_config_file_override'] && !file_exists($pconfig['adv_dhcp6_config_file_override_path']))) {
                    $input_errors[] = sprintf(gettext('The DHCPv6 override file "%s" does not exist.'), $pconfig['adv_dhcp6_config_file_override_path']);
                }
                break;
            case "none":
                if (isset($config['virtualip']['vip'])) {
                    foreach ($config['virtualip']['vip'] as $vip) {
                        if (is_ipaddrv6($vip['subnet']) && $vip['interface'] == $if) {
                            $input_errors[] = gettext("This interface is referenced by IPv6 VIPs. Please delete those before setting the interface to 'none' configuration.");
                        }
                    }
                }
                break;
            case '6rd':
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
                            $input_errors[] = gettext("You can only have one interface configured in 6rd with same prefix.");
                            break;
                        }
                    }
                }
                break;
            case "6to4":
                foreach (array_keys($ifdescrs) as $ifent) {
                    if ($if != $ifent && ($config['interfaces'][$ifent]['ipaddrv6'] == $pconfig['type6'])) {
                        $input_errors[] = sprintf(gettext("You can only have one interface configured as 6to4."), $pconfig['type6']);
                        break;
                    }
                }
                break;
            case "track6":
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
        if (!empty($pconfig['dhcprejectfrom']) && !is_ipaddrv4($pconfig['dhcprejectfrom'])) {
            $input_errors[] = gettext("A valid alias IP address must be specified to reject DHCP Leases from.");
        }

        if ($pconfig['gateway'] != "none" || $pconfig['gatewayv6'] != "none") {
            $match = false;
            if (!empty($config['gateways']['gateway_item'])) {
                foreach($config['gateways']['gateway_item'] as $gateway) {
                    if (in_array($pconfig['gateway'], $gateway)) {
                        $match = true;
                    }
                }
                foreach($config['gateways']['gateway_item'] as $gateway) {
                    if (in_array($pconfig['gatewayv6'], $gateway)) {
                        $match = true;
                    }
                }
            }
            if (!$match) {
                $input_errors[] = gettext("A valid gateway must be specified.");
            }
        }
        if (!empty($pconfig['provider']) && !is_domain($pconfig['provider'])){
            $input_errors[] = gettext("The service name contains invalid characters.");
        }
        if (!empty($pconfig['pppoe_idletimeout']) && !is_numericint($pconfig['pppoe_idletimeout'])) {
            $input_errors[] = gettext("The idle timeout value must be an integer.");
        }

        if (!empty($pconfig['localip']) && !is_ipaddrv4($pconfig['localip'])) {
            $input_errors[] = gettext("A valid PPTP local IP address must be specified.");
        }
        if (!empty($pconfig['pptp_subnet']) && !is_numeric($pconfig['pptp_subnet'])) {
            $input_errors[] = gettext("A valid PPTP subnet bit count must be specified.");
        }
        if (!empty($pconfig['pptp_remote']) && !is_ipaddrv4($pconfig['pptp_remote']) && !is_hostname($pconfig['gateway'][$iface])) {
            $input_errors[] = gettext("A valid PPTP remote IP address must be specified.");
        }
        if (!empty($pconfig['pptp_idletimeout']) && !is_numericint($pconfig['pptp_idletimeout'])) {
            $input_errors[] = gettext("The idle timeout value must be an integer.");
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

            if (stristr($a_interfaces[$if]['if'], "_vlan")) {
                $parentif = get_parent_interface($a_interfaces[$if]['if'])[0];
                $intf_details = legacy_interface_details($parentif);
                if ($intf_details['mtu'] < $pconfig['mtu']) {
                    $input_errors[] = gettext("MTU of a vlan should not be bigger than parent interface.");
                }
            } else {
                foreach ($config['interfaces'] as $idx => $ifdata) {
                    if (($idx == $if) || !preg_match('/_vlan[0-9]/', $ifdata['if'])) {
                        continue;
                    }

                    $realhwif_array = get_parent_interface($ifdata['if']);
                    // Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
                    $parent_realhwif = $realhwif_array[0];

                    if ($parent_realhwif != $a_interfaces[$if]['if']) {
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
            $reqdfields = array("mode");
            $reqdfieldsn = array(gettext("Mode"));
            if ($pconfig['mode'] == 'hostap') {
                $reqdfields[] = "ssid";
                $reqdfieldsn[] = gettext("SSID");
            }
            do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

            // check_wireless_mode (more wireless weirness)
            // validations shouldn't perform actual actions, needs serious fixing at some point
            if ($a_interfaces[$if]['wireless']['mode'] != $pconfig['mode']) {
                if (does_interface_exist(interface_get_wireless_clone($wlanbaseif))) {
                    $clone_count = 1;
                } else {
                    $clone_count = 0;
                }
                if (!empty($config['wireless']['clone'])) {
                    foreach ($config['wireless']['clone'] as $clone) {
                        if ($clone['if'] == $wlanbaseif) {
                            $clone_count++;
                        }
                    }
                }
                if ($clone_count > 1) {
                      $wlanif = get_real_interface($if);
                      $old_wireless_mode = $a_interfaces[$if]['wireless']['mode'];
                      $a_interfaces[$if]['wireless']['mode'] = $pconfig['mode'];
                      if (!interface_wireless_clone("{$wlanif}_", $a_interfaces[$if])) {
                          $input_errors[] = sprintf(gettext("Unable to change mode to %s. You may already have the maximum number of wireless clones supported in this mode."), $wlan_modes[$a_interfaces[$if]['wireless']['mode']]);
                      } else {
                          mwexec("/sbin/ifconfig " . escapeshellarg($wlanif) . "_ destroy");
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
            $new_ppp_config = array();

            // copy physical interface data (wireless is a strange case, partly managed via interface_sync_wireless_clones)
            $new_config["if"] = $old_config["if"];
            if (!empty($old_config['wireless'])) {
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
                    break;
                case "ppp":
                    $new_config['if'] = $pconfig['type'] . $pconfig['ptpid'];
                    $new_config['ipaddr'] = $pconfig['type'];
                    $new_ppp_config['ptpid'] = $pconfig['ptpid'];
                    $new_ppp_config['type'] = $pconfig['type'];
                    $new_ppp_config['if'] = $pconfig['type'].$pconfig['ptpid'];
                    $new_ppp_config['ports'] = $pconfig['ports'];
                    $new_ppp_config['username'] = $pconfig['username'];
                    $new_ppp_config['password'] = base64_encode($pconfig['password']);
                    $new_ppp_config['phone'] = $pconfig['phone'];
                    $new_ppp_config['apn'] = $pconfig['apn'];
                    break;
                case "pppoe":
                    $new_config['if'] = $pconfig['type'].$pconfig['ptpid'];
                    $new_config['ipaddr'] = $pconfig['type'];
                    $new_ppp_config['ptpid'] = $pconfig['ptpid'];
                    $new_ppp_config['type'] = $pconfig['type'];
                    $new_ppp_config['if'] = $pconfig['type'].$pconfig['ptpid'];
                    if (!empty($pconfig['ppp_port'])) {
                        $new_ppp_config['ports'] = $pconfig['ppp_port'];
                    } else {
                        $new_ppp_config['ports'] = $old_config['if'];
                    }
                    $new_ppp_config['username'] = $pconfig['pppoe_username'];
                    $new_ppp_config['password'] = base64_encode($pconfig['pppoe_password']);
                    if (!empty($pconfig['provider'])) {
                        $new_ppp_config['provider'] = $pconfig['provider'];
                    }
                    if (!empty($pconfig['pppoe_hostuniq'])) {
                        $new_ppp_config['hostuniq'] = $pconfig['pppoe_hostuniq'];
                    }
                    $new_ppp_config['ondemand'] = !empty($pconfig['pppoe_dialondemand']);
                    if (!empty($pconfig['pppoe_idletimeout'])) {
                        $new_ppp_config['idletimeout'] = $pconfig['pppoe_idletimeout'];
                    }
                    break;
                case "pptp":
                case "l2tp":
                    $new_config['if'] = $pconfig['type'].$pconfig['ptpid'];
                    $new_config['ipaddr'] = $pconfig['type'];
                    $new_ppp_config['ptpid'] = $pconfig['ptpid'];
                    $new_ppp_config['type'] = $pconfig['type'];
                    $new_ppp_config['if'] = $pconfig['type'].$pconfig['ptpid'];
                    if (!empty($pconfig['ppp_port'])) {
                        $new_ppp_config['ports'] = $pconfig['ppp_port'];
                    } else {
                        $new_ppp_config['ports'] = $old_config['if'];
                    }
                    $new_ppp_config['username'] = $pconfig['pptp_username'];
                    $new_ppp_config['password'] = base64_encode($pconfig['pptp_password']);
                    $new_ppp_config['localip'] = $pconfig['localip'];
                    $new_ppp_config['subnet'] = $pconfig['pptp_subnet'];
                    $new_ppp_config['gateway'] = $pconfig['pptp_remote'];
                    $new_ppp_config['ondemand'] = !empty($pconfig['pptp_dialondemand']);
                    if (!empty($pconfig['pptp_idletimeout'])) {
                        $new_ppp_config['idletimeout'] = $pconfig['pptp_idletimeout'];
                    }
                    break;
            }

            // switch ipv6 config by type
            switch ($pconfig['type6']) {
                case 'staticv6':
                    if (!empty($pconfig['staticv6usev4iface'])) {
                        $new_config['dhcp6usev4iface'] = true;
                    }
                    $new_config['ipaddrv6'] = $pconfig['ipaddrv6'];
                    $new_config['subnetv6'] = $pconfig['subnetv6'];
                    if ($pconfig['gatewayv6'] != 'none') {
                        $new_config['gatewayv6'] = $pconfig['gatewayv6'];
                    }
                    break;
                case 'slaac':
                    if (!empty($pconfig['slaacusev4iface'])) {
                        $new_config['dhcp6usev4iface'] = true;
                    }
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
                    if (!empty($pconfig['dhcp6usev4iface'])) {
                        $new_config['dhcp6usev4iface'] = true;
                    }
                    if (isset($pconfig['dhcp6vlanprio']) && $pconfig['dhcp6vlanprio'] !== '') {
                        $new_config['dhcp6vlanprio'] = $pconfig['dhcp6vlanprio'];
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
                case 'track6':
                    $new_config['ipaddrv6'] = 'track6';
                    $new_config['track6-interface'] = $pconfig['track6-interface'];
                    $new_config['track6-prefix-id'] = 0;
                    if (ctype_xdigit($pconfig['track6-prefix-id--hex'])) {
                        $new_config['track6-prefix-id'] = intval($pconfig['track6-prefix-id--hex'], 16);
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
                $new_config['wireless']['wpa']['wpa_pairwise'] = $pconfig['wpa_pairwise'];
                $new_config['wireless']['wpa']['wpa_group_rekey'] = $pconfig['wpa_group_rekey'];
                $new_config['wireless']['wpa']['wpa_gmk_rekey'] = $pconfig['wpa_gmk_rekey'];
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
                //       this construction implements a lot of weirness (more info interface_sync_wireless_clones)
                $wlanbaseif = interface_get_wireless_base($a_interfaces[$if]['if']);
                if (!empty($pconfig['persistcommonwireless'])) {
                    config_read_array('wireless', 'interfaces', $wlanbaseif);
                } elseif (isset($config['wireless']['interfaces'][$wlanbaseif])) {
                    unset($config['wireless']['interfaces'][$wlanbaseif]);
                }

                // quite obscure this... copies parts of the config
                interface_sync_wireless_clones($new_config, true);
            }

            if (count($new_ppp_config) > 0) {
                // ppp details changed
                $a_ppps[$pppid] = $new_ppp_config;
            } elseif (!empty($a_ppps[$pppid])) {
                // ppp removed
                $new_config['if'] = $a_ppps[$pppid]['ports'];
                unset($a_ppps[$pppid]);
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
                $toapplylist[$if]['ppps'] = $old_ppps;
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
$mediaopts_list = array();
$optlist_intf = get_parent_interface($pconfig['if']);
if (count($optlist_intf) > 0) {
    exec("/sbin/ifconfig -m {$optlist_intf[0]} | grep \"media \"", $mediaopts);
    foreach ($mediaopts as $mediaopt){
        preg_match("/media (.*)/", $mediaopt, $matches);
        if (preg_match("/(.*) mediaopt (.*)/", $matches[1], $matches1)){
            // there is media + mediaopt like "media 1000baseT mediaopt full-duplex"
            array_push($mediaopts_list, $matches1[1] . " " . $matches1[2]);
        } else {
            // there is only media like "media 1000baseT"
            array_push($mediaopts_list, $matches[1]);
        }
    }
}

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
      }
      // when disabled, hide settings.
      $("#enable").click(toggle_allcfg);
      toggle_allcfg();

      //
      $("#type").change(function(){
          $('#staticv4, #dhcp, #pppoe, #pptp, #ppp').hide();
          if ($(this).val() == "l2tp") {
              $("#pptp").show();
          } else {
              $("#" +$(this).val()).show();
          }
          switch ($(this).val()) {
            case "ppp": {
              $('#country').children().remove();
              $('#provider_list').children().remove();
              $('#providerplan').children().remove();
              $.ajax("getserviceproviders.php",{
                success: function(response) {
                  var responseTextArr = response.split("\n");
                  responseTextArr.sort();
                  $.each(responseTextArr, function(index, value) {
                    let country = value.split(':');
                    $('#country').append(new Option(country[0], country[1]));
                  });
                }
              });
              $('#trcountry').removeClass("hidden");
              $("#mtu_calc").show();
              break;
            }
            case "pppoe":
            case "pptp":
              $("#mtu_calc").show();
              break;
            default:
              // hide mtu calculation for non ppp types
              $("#mtu_calc").hide();
          }

      });
      $("#type").change();

      $("#type6").change(function(){
          $('#staticv6, #slaac, #dhcp6, #6rd, #track6').hide();
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

      //
      // new gateway action
      //
      $("#gwsave").click(function(){
          var iface = $('#if').val();
          var name = $('#name').val();
          var descr = $('#gatewaydescr').val();
          var gatewayip = $('#gatewayip').val();
          var ajaxhelper = "&ajaxip=" + escape($('#ipaddr').val()) + "&ajaxnet=" + escape($('#subnet').val());
          var defaultgw = "";
          if ($("#defaultgw").prop('checked')) {
              defaultgw = "&defaultgw=on";
          }
          var fargw = "";
          if ($("#fargw").prop('checked')) {
              fargw = "&fargw=on";
          }
          jQuery.ajax( "system_gateways_edit.php", {
            type: 'post',
            data: 'isAjax=true&ipprotocol=inet' + defaultgw + fargw + ajaxhelper + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip),
            error: function(request, textStatus, errorThrown){
                if (textStatus === "error" && request.getResponseHeader("Content-Type").indexOf("text/plain") === 0) {
                    alert(request.responseText);
                } else {
                    alert("Sorry, we could not create your IPv4 gateway at this time.");
                }
            },
            success: function(response) {
                $("#addgateway").toggleClass("hidden visible");
                var selected = "selected=selected";
                if (!$("#multiwangw").prop('checked')) {
                    selected = "";
                }
                $('#gateway').append($("<option " + selected + "></option>").attr("value",name).text(escape(name) + " - " + gatewayip));
                $('#gateway').selectpicker('refresh');
            }
          });
      });
      $("#gwcancel").click(function(){
          $("#addgateway").toggleClass("hidden visible");
      });

      //
      // new gateway v6 action
      //
      $("#gwsavev6").click(function(){
          var iface = $('#if').val();
          var name = $('#namev6').val();
          var descr = $('#gatewaydescrv6').val();
          var gatewayip = $('#gatewayipv6').val();
          var ajaxhelper = "&ajaxip=" + escape($('#ipaddrv6').val()) + "&ajaxnet=" + escape($('#subnetv6').val());
          var defaultgw = "";
          if ($("#defaultgwv6").prop('checked')) {
              defaultgw = "&defaultgw=on";
          }
          jQuery.ajax( "system_gateways_edit.php", {
            type: 'post',
            data: 'isAjax=true&ipprotocol=inet6' + defaultgw + ajaxhelper + '&interface=' + escape(iface) + '&name=' + escape(name) + '&descr=' + escape(descr) + '&gateway=' + escape(gatewayip),
            error: function(request, textStatus, errorThrown){
                if (textStatus === "error" && request.getResponseHeader("Content-Type").indexOf("text/plain") === 0) {
                    alert(request.responseText);
                } else {
                    alert("Sorry, we could not create your IPv6 gateway at this time.");
                }
            },
            success: function(response) {
                $("#addgatewayv6").toggleClass("hidden visible");
                var selected = "selected=selected";
                if (!$("#multiwangwv6").prop('checked')) {
                    selected = "";
                }
                $('#gatewayv6').append($("<option " + selected + "></option>").attr("value",name).text(escape(name) + " - " + gatewayip));
                $('#gatewayv6').selectpicker('refresh');
            }
          });
      });
      $("#gwcancelv6").click(function(){
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

      // ppp -> country change
      $("#country").change(function(){
          $('#provider_list').children().remove();
          $('#providerplan').children().remove();
          $.ajax("getserviceproviders.php",{
              type: 'post',
              data: {country : $('#country').val()},
              success: function(response) {
                var responseTextArr = response.split("\n");
                responseTextArr.sort();
                $.each(responseTextArr, function(index, value) {
                  $('#provider_list').append(new Option(value, value));
                });
              }
          });
          $('#trprovider').removeClass("hidden");
          $('#trproviderplan').addClass("hidden");
      });

      $('#trprovider').change(function() {
          $('#providerplan').children().remove();
          $('#providerplan').append(new Option('', ''));
          $.ajax('getserviceproviders.php', {
            type: 'post',
            data: {country : jQuery('#country').val(), provider : $('#provider_list').val()},
            success: function(response) {
              var responseTextArr = response.split("\n");
              responseTextArr.sort();
              jQuery.each(responseTextArr, function(index, value) {
                if (value != '') {
                  let providerplan = value.split(':');
                  $('#providerplan').append(new Option(
                    providerplan[0] + ' - ' + providerplan[1],
                    providerplan[1]
                  ));
                }
              });
            }
          });
          $('#trproviderplan').removeClass("hidden");
      });

      $("#trproviderplan").change(function() {
          $.ajax("getserviceproviders.php", {
              type: 'post',
              data: {country : $('#country').val(), provider : $('#provider_list').val(), plan : $('#providerplan').val()},
              success: function(data,textStatus,response) {
                  var xmldoc = response.responseXML;
                  var provider = xmldoc.getElementsByTagName('connection')[0];
                  $('#username').val('');
                  $('#password').val('');
                  if (provider.getElementsByTagName('apn')[0].firstChild.data == "CDMA") {
                      $('#phone').val('#777');
                      $('#apn').val('');
                  } else {
                      $('#phone').val('*99#');
                      $('#apn').val(provider.getElementsByTagName('apn')[0].firstChild.data);
                  }
                  if (provider.getElementsByTagName('username')[0].firstChild != null) {
                      $('#username').val(provider.getElementsByTagName('username')[0].firstChild.data);
                  }
                  if (provider.getElementsByTagName('password')[0].firstChild != null) {
                      $('#password').val(provider.getElementsByTagName('password')[0].firstChild.data);
                  }
              }
          });
      });

      $("#mtu").change(function(){
        // ppp uses an mtu
        if (!isNaN($("#mtu").val()) && $("#mtu").val() > 8) {
            // display mtu used for the ppp(oe) connection
            $("#mtu_calc > label").html($("#mtu").val() - 8 );
        } else {
            // default ppp mtu is 1500 - 8 (header)
            $("#mtu_calc > label").html("1492");
        }
      });
      $("#mtu").change();

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
                          <strong><?= gettext('Enable Interface') ?></strong>
                        </td>
                      </tr>
                      <tr>
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Lock') ?></td>
                        <td>
                          <input id="lock" name="lock" type="checkbox" value="yes" <?=!empty($pconfig['lock']) ? 'checked="checked"' : '' ?>/>
                          <strong><?= gettext('Prevent interface removal') ?></strong>
                        </td>
                      </tr>
                      <tr>
                        <td style="width:22%"><a id="help_for_ifname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Device"); ?></td>
                        <td style="width:78%">
                          <strong><?=$pconfig['if'];?></strong>
                          <div class="hidden" data-for="help_for_ifname">
                            <?= gettext("The real device name of this interface."); ?>
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
<?php
                            $types4 = array("none" => gettext("None"), "staticv4" => gettext("Static IPv4"), "dhcp" => gettext("DHCP"), "ppp" => gettext("PPP"), "pppoe" => gettext("PPPoE"), "pptp" => gettext("PPTP"), "l2tp" => gettext("L2TP"));
                            foreach ($types4 as $key => $opt):?>
                            <option value="<?=$key;?>" <?=$key == $pconfig['type'] ? "selected=\"selected\"" : "";?> ><?=$opt;?></option>
<?php
                            endforeach;?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("IPv6 Configuration Type"); ?></td>
                          <td>
                            <select name="type6" class="selectpicker" data-style="btn-default" id="type6">
<?php
                            $types6 = array("none" => gettext("None"), "staticv6" => gettext("Static IPv6"), "dhcp6" => gettext("DHCPv6"), "slaac" => gettext("SLAAC"), "6rd" => gettext("6rd Tunnel"), "6to4" => gettext("6to4 Tunnel"), "track6" => gettext("Track Interface"));
                            foreach ($types6 as $key => $opt):?>
                              <option value="<?=$key;?>" <?=$key == $pconfig['type6'] ? "selected=\"selected\"" : "";?> ><?=$opt;?></option>
<?php
                            endforeach;?>
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
                          <td><a id="help_for_mtu" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MTU"); ?></td>
                          <td>
                            <input name="mtu" id="mtu" type="text" value="<?=$pconfig['mtu'];?>" />
                            <div id="mtu_calc" style="display:none">
                              <?= gettext('Calculated PPP MTU') ?>: <label></label>
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
                        if (count($mediaopts_list) > 0):?>
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
                            <strong><?= gettext('This interface does not require an intermediate system to act as a gateway') ?></strong>
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
                          <td><a id="help_for_gateway" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('IPv4 Upstream Gateway') ?></td>
                          <td>
                            <select name="gateway" class="selectpicker" data-style="btn-default" data-size="10" id="gateway">
                              <option value="none"><?= gettext('Auto-detect') ?></option>
<?php
                              if (!empty($config['gateways']['gateway_item'])):
                                foreach ($config['gateways']['gateway_item'] as $gateway):
                                  if ($gateway['interface'] == $if && is_ipaddrv4($gateway['gateway'])):
?>
                                  <option value="<?=$gateway['name'];?>" <?= $gateway['name'] == $pconfig['gateway'] ? "selected=\"selected\"" : ""; ?>>
                                    <?=htmlspecialchars($gateway['name']. " - " . $gateway['gateway']);?>
                                  </option>
<?php
                                  endif;
                                endforeach;
                              endif;?>
                            </select>
                            <button type="button" class="btn btn-sm" id="btn_show_add_gateway" title="<?= html_safe(gettext('Add')) ?>" data-toggle="tooltip"><i class="fa fa-plus fa-fw"></i></button>
                            <div class="hidden" id="addgateway">
                              <br/>
                              <table class="table table-striped table-condensed">
                                <tbody>
                                  <tr>
                                    <td colspan="2"><b><?=gettext("Add new gateway"); ?></b></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Default gateway') ?></td>
                                    <td><input type="checkbox" id="defaultgw" name="defaultgw" <?= strtolower($if) == 'wan' ? 'checked="checked"' : '' ?> /></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Far gateway') ?></td>
                                    <td><input type="checkbox" id="fargw" name="fargw" /></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Multi-WAN gateway') ?></td>
                                    <td><input type="checkbox" id="multiwangw" name="multiwangw" /></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Gateway Name') ?></td>
                                    <td><input type="text" id="name" name="name" value="<?= html_safe((empty($pconfig['descr']) ? strtoupper($if) : $pconfig['descr']) . '_GWv4') ?>" /></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Gateway IPv4') ?></td>
                                    <td><input type="text" id="gatewayip" name="gatewayip" /></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Description') ?></td>
                                    <td><input type="text" id="gatewaydescr" name="gatewaydescr" /></td>
                                  </tr>
                                  <tr>
                                    <td></td>
                                    <td>
                                      <div id='savebuttondiv'>
                                        <input class="btn btn-primary" id="gwsave" type="button" value="<?= html_safe(gettext('Save')) ?>" />
                                        <input class="btn btn-default" id="gwcancel" type="button" value="<?= html_safe(gettext('Cancel')) ?>" />
                                      </div>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                            <div class="hidden" data-for="help_for_gateway">
                              <?= gettext('If this interface is a multi-WAN interface, select an existing gateway from the list ' .
                                          'or add a new one using the button above. For single WAN interfaces a gateway must be ' .
                                          'created but set to auto-detect. For a LAN a gateway is not necessary to be set up.') ?>
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
                              <?=gettext("If there is a certain upstream DHCP server that should be ignored, place the IP address or subnet of the DHCP server to be ignored here."); ?>
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
                            <strong><?=gettext("Presets:");?></strong><br/>
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
                          <th colspan="2"><?=gettext("PPP configuration"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><a id="help_for_country" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Service Provider"); ?></td>
                          <td style="width:78%">
                            <table class="table table-condensed">
                              <tr id="trcountry" class="hidden">
                                <td><?=gettext("Country:"); ?></td>
                                <td>
                                  <select name="country" id="country">
                                    <option></option>
                                  </select>
                                </td>
                              </tr>
                              <tr id="trprovider" class="hidden">
                                <td><?=gettext("Provider:"); ?> &nbsp;&nbsp;</td>
                                <td>
                                  <select name="provider_list" id="provider_list">
                                    <option></option>
                                  </select>
                                </td>
                              </tr>
                              <tr id="trproviderplan" class="hidden">
                                <td><?=gettext("Plan:"); ?> &nbsp;&nbsp;</td>
                                <td>
                                  <select name="providerplan" id="providerplan">
                                    <option></option>
                                  </select>
                                </td>
                              </tr>
                            </table>
                            <div class="hidden" data-for="help_for_country">
                              <?=gettext("Select to fill in data for your service provider."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Username"); ?></td>
                          <td>
                            <input name="username" type="text" id="username" value="<?=$pconfig['username'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Password"); ?></td>
                          <td>
                            <input name="password" type="password" id="password" value="<?=$pconfig['password'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Phone Number"); ?></td>
                          <td>
                            <input name="phone" type="text" id="phone" size="12" value="<?=$pconfig['phone'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Access Point Name (APN)"); ?></td>
                          <td>
                            <input name="apn" type="text" id="apn" value="<?=$pconfig['apn'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Modem Port"); ?></td>
                          <td>
                            <select name="ports" id="ports" data-size="10" class="selectpicker" data-style="btn-default">
<?php
                            $portlist = glob("/dev/cua*");
                            $modems = glob("/dev/modem*");
                            $portlist = array_merge($portlist, $modems);
                            foreach ($portlist as $port):
                              if (preg_match("/\.(lock|init)$/", $port)) {
                                  continue;
                              }?>
                              <option value="<?=trim($port);?>" <?=$pconfig['ports'] == $port ? "selected=\"selected\"" : "" ;?>>
                                <?=$port;?>
                              </option>
<?php
                            endforeach;?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><?=gettext("Advanced PPP"); ?></td>
                          <td>
                            <?php if (!empty($a_ppps[$pppid])): ?>
                              <?= sprintf(gettext('%sClick here%s to edit PPP configuration.'), url_safe('<a href="/interfaces_ppps_edit.php?id=%d">', $pppid), '</a>') ?>
                            <?php else: ?>
                              <?= sprintf(gettext("%sClick here%s to create a PPP configuration."), '<a href="/interfaces_ppps_edit.php">', '</a>') ?>
                            <?php endif; ?>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- Section : PPPOE -->
                <div class="tab-content content-box col-xs-12 __mb" id="pppoe" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("PPPoE configuration"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Username"); ?></td>
                          <td style="width:78%">
                              <input name="pppoe_username" type="text" id="pppoe_username" value="<?=$pconfig['pppoe_username'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Password"); ?></td>
                          <td>
                            <input name="pppoe_password" type="password" id="pppoe_password" value="<?=htmlspecialchars($pconfig['pppoe_password']);?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_provider" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Service name"); ?></td>
                          <td>
                            <input name="provider" type="text" id="provider" value="<?=$pconfig['provider'];?>" />
                            <div class="hidden" data-for="help_for_provider">
                              <?=gettext("Hint: this field can usually be left empty"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_pppoe_hostuniq" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Host-Uniq"); ?></td>
                          <td>
                            <input name="pppoe_hostuniq" type="text" id="pppoe_hostuniq" value="<?=$pconfig['pppoe_hostuniq'];?>" />
                            <div class="hidden" data-for="help_for_pppoe_hostuniq">
                              <?= gettext('This field can usually be left empty unless specified by the provider.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_pppoe_dialondemand" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Dial on demand"); ?></td>
                          <td>
                            <input name="pppoe_dialondemand" type="checkbox" id="pppoe_dialondemand" value="enable" <?= !empty($pconfig['pppoe_dialondemand']) ? "checked=\"checked\"" : ""; ?> />
                            <strong><?=gettext("Enable Dial-On-Demand mode"); ?></strong><br />
                            <div class="hidden" data-for="help_for_pppoe_dialondemand">
                              <?=gettext("This option causes the interface to operate in dial-on-demand mode, allowing you to have a 'virtual full time' connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_pppoe_idletimeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Idle timeout"); ?></td>
                          <td>
                            <input name="pppoe_idletimeout" type="text" id="pppoe_idletimeout" value="<?=$pconfig['pppoe_idletimeout'];?>" /> <?=gettext("seconds"); ?>
                            <div class="hidden" data-for="help_for_pppoe_idletimeout">
                              <?=gettext("If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Advanced and MLPPP"); ?></td>
                          <?php if (!empty($a_ppps[$pppid])): ?>
                            <td>
                            <?= sprintf(gettext('%sClick here%s for additional PPPoE configuration options. Save first if you made changes.'), url_safe('<a href="/interfaces_ppps_edit.php?id=%d">', $pppid), '</a>') ?>
                            </td>
                          <?php else: ?>
                            <td>
                            <?= sprintf(gettext('%sClick here%s for advanced PPPoE configuration options and MLPPP configuration.'),'<a href="/interfaces_ppps_edit.php">','</a>') ?>
                            </td>
                          <?php endif; ?>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- Section : PPTP / L2TP -->
                <div class="tab-content content-box col-xs-12 __mb" id="pptp" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("PPTP/L2TP configuration"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Username"); ?></td>
                          <td style="width:78%">
                            <input name="pptp_username" type="text" id="pptp_username" value="<?=$pconfig['pptp_username'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Password"); ?></td>
                          <td>
                            <input name="pptp_password" type="password" id="pptp_password" value="<?=$pconfig['pptp_password'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Local IP address"); ?></td>
                          <td>
                            <table>
                              <tr>
                                <td style="width:348px">
                                  <input name="localip" type="text" id="localip"  value="<?=$pconfig['localip'];?>" />
                                </td>
                                <td>
                                  <select name="pptp_subnet" class="selectpicker" data-width="auto" data-style="btn-default" data-size="10" id="pptp_subnet">
                                    <?php for ($i = 31; $i > 0; $i--): ?>
                                      <option value="<?=$i;?>" <?= $i == $pconfig['pptp_subnet'] ? 'selected="selected"' : ''; ?>>
                                        <?=$i;?>
                                      </option>
                                    <?php endfor; ?>
                                  </select>
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Remote IP address"); ?></td>
                          <td>
                            <input name="pptp_remote" type="text" id="pptp_remote" value="<?=$pconfig['pptp_remote'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_pptp_dialondemand" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Dial on demand"); ?></td>
                          <td>
                            <input name="pptp_dialondemand" type="checkbox" id="pptp_dialondemand" value="enable" <?=!empty($pconfig['pptp_dialondemand']) ? 'checked="checked"' : '' ?> />
                            <strong><?=gettext("Enable Dial-On-Demand mode"); ?></strong><br />
                            <div class="hidden" data-for="help_for_pptp_dialondemand">
                              <?=gettext("This option causes the interface to operate in dial-on-demand mode, allowing you to have a 'virtual full time' connection. The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Idle timeout"); ?></td>
                          <td>
                            <input name="pptp_idletimeout" type="text" id="pptp_idletimeout" value="<?=htmlspecialchars($pconfig['pptp_idletimeout']);?>" /> <?=gettext("seconds"); ?><br /><?=gettext("If no qualifying outgoing packets are transmitted for the specified number of seconds, the connection is brought down. An idle timeout of zero disables this feature."); ?>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Advanced"); ?></td>
                            <td>
                          <?php if (!empty($a_ppps[$pppid])): ?>
                            <?= sprintf(gettext("%sClick here%s for additional PPTP and L2TP configuration options. Save first if you made changes."), url_safe('<a href="/interfaces_ppps_edit.php?id=%d">', $pppid), '</a>') ?>
                          <?php else: ?>
                            <?= sprintf(gettext('%sClick here%s for advanced PPTP and L2TP configuration options.'),'<a href="/interfaces_ppps_edit.php">','</a>') ?>
                          <?php endif; ?>
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
                          <td><a id="help_for_gatewayv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Upstream Gateway"); ?></td>
                          <td>
                            <select name="gatewayv6" class="selectpicker" data-size="10" data-style="btn-default" id="gatewayv6">
                              <option value="none"><?= gettext('Auto-detect') ?></option>
<?php
                              if (!empty($config['gateways']['gateway_item'])):
                                foreach ($config['gateways']['gateway_item'] as $gateway):
                                  if ($gateway['interface'] == $if && is_ipaddrv6($gateway['gateway'])):
?>
                                  <option value="<?=$gateway['name'];?>" <?= $gateway['name'] == $pconfig['gatewayv6'] ? "selected=\"selected\"" : ""; ?>>
                                    <?=htmlspecialchars($gateway['name']. " - " . $gateway['gateway']);?>
                                  </option>
<?php
                                  endif;
                                endforeach;
                              endif;?>
                            </select>
                            <button type="button" class="btn btn-sm" id="btn_show_add_gatewayv6" title="<?= html_safe(gettext('Add')) ?>" data-toggle="tooltip"><i class="fa fa-plus fa-fw"></i></button>
                            <div class="hidden" id="addgatewayv6">
                              <br/>
                              <table class="table table-striped table-condensed">
                                <tbody>
                                  <tr>
                                    <td colspan="2"><b><?=gettext("Add new gateway"); ?></b></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Default gateway') ?></td>
                                    <td><input type="checkbox" id="defaultgwv6" name="defaultgwv6" <?= strtolower($if) == 'wan' ?  'checked="checked"' : '' ?> /></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Multi-WAN gateway') ?></td>
                                    <td><input type="checkbox" id="multiwangwv6" name="multiwangwv6" /></td>
                                  </tr>
                                  <tr>
                                    <td><?= gettext('Gateway Name') ?></td>
                                    <td><input id="namev6" type="text" name="namev6" value="<?= html_safe((empty($pconfig['descr']) ? strtoupper($if) : $pconfig['descr']) . '_GWv6') ?>" /></td>
                                  </tr>
                                  <tr>
                                    <td><?=gettext("Gateway IPv6"); ?></td>
                                    <td><input id="gatewayipv6" type="text" name="gatewayipv6" /></td>
                                  </tr>
                                  <tr>
                                    <td><?=gettext("Description"); ?></td>
                                    <td><input id="gatewaydescrv6" type="text" name="gatewaydescrv6" /></td>
                                  </tr>
                                  <tr>
                                    <td></td>
                                    <td>
                                      <input class="btn btn-primary" id="gwsavev6" type="button" value="<?= html_safe(gettext('Save')) ?>" />
                                      <input class="btn btn-default" id="gwcancelv6" type="button" value="<?= html_safe(gettext('Cancel')) ?>" />
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                            <div class="hidden" data-for="help_for_gatewayv6">
                              <?= gettext('If this interface is a multi-WAN interface, select an existing gateway from the list ' .
                                          'or add a new one using the button above. For single WAN interfaces a gateway must be ' .
                                          'created but set to auto-detect. For a LAN a gateway is not necessary to be set up.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_staticv6usev4iface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use IPv4 connectivity"); ?></td>
                          <td>
                            <input name="staticv6usev4iface" type="checkbox" id="staticv6usev4iface" value="yes" <?=!empty($pconfig['staticv6usev4iface']) ? "checked=\"checked\"" : ""; ?> />
                            <div class="hidden" data-for="help_for_staticv6usev4iface">
                              <?= gettext('Set the IPv6 address on the IPv4 PPP connectivity link.') ?>
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
                          <td><a id="help_for_dhcp6prefixonly" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Request only an IPv6 prefix"); ?></td>
                          <td>
                            <input name="dhcp6prefixonly" type="checkbox" id="dhcp6prefixonly" value="yes" <?=!empty($pconfig['dhcp6prefixonly']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_dhcp6prefixonly">
                              <?= gettext('Only request an IPv6 prefix; do not request an IPv6 address.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_basic">
                          <td><a id="help_for_dhcp6-ia-pd-len" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Prefix delegation size"); ?></td>
                          <td>
                            <select name="dhcp6-ia-pd-len" class="selectpicker" data-style="btn-default" id="dhcp6-ia-pd-len">
<?php
                            foreach(array(
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
                            ) as $bits => $length): ?>
                              <option value="<?=$bits;?>" <?= "{$bits}" === "{$pconfig['dhcp6-ia-pd-len']}" ? 'selected="selected"' : '' ?>>
                                  <?=$length;?>
                              </option>
<?php
                            endforeach;?>
                            </select>
                            <div class="hidden" data-for="help_for_dhcp6-ia-pd-len">
                              <?=gettext("The value in this field is the delegated prefix length provided by the DHCPv6 server. Normally specified by the ISP."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_basic">
                          <td><a id="help_for_dhcp6-ia-pd-send-hint" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Send IPv6 prefix hint"); ?></td>
                          <td>
                            <input name="dhcp6-ia-pd-send-hint" type="checkbox" id="dhcp6-ia-pd-send-hint" value="yes" <?=!empty($pconfig['dhcp6-ia-pd-send-hint']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_dhcp6-ia-pd-send-hint">
                              <?=gettext("Send an IPv6 prefix hint to indicate the desired prefix size for delegation"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_dhcp6usev4iface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use IPv4 connectivity"); ?></td>
                          <td>
                            <input name="dhcp6usev4iface" type="checkbox" id="dhcp6usev4iface" value="yes" <?=!empty($pconfig['dhcp6usev4iface']) ? "checked=\"checked\"" : ""; ?> />
                            <div class="hidden" data-for="help_for_dhcp6usev4iface">
                              <?= gettext('Request the IPv6 information through the IPv4 PPP connectivity link.') ?>
                            </div>
                          </td>
                        </tr>
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
                        <tr class="dhcpv6_advanced">
                          <td><a id="help_for_dhcp6_intf_stmt" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface Statement");?></td>
                          <td>
                            <input name="adv_dhcp6_interface_statement_information_only_enable" type="checkbox" id="adv_dhcp6_interface_statement_information_only_enable" <?=!empty($pconfig['adv_dhcp6_interface_statement_information_only_enable']) ? "checked=\"checked\"" : "";?> />
                            <strong><?=gettext("Information Only"); ?></strong><br/>
                            <div class="hidden" data-for="help_for_dhcp6_intf_stmt">
                              <?=gettext("This statement specifies dhcp6c to only exchange informational configuration parameters with servers. ".
                              "A list of DNS server addresses is an example of such parameters. ".
                              "This statement is useful when the client does not need ".
                              "stateful configuration parameters such as IPv6 addresses or prefixes.");?><br/>
                              <small>
                                <?=gettext("Source: FreeBSD man page");?>
                              </small>
                            </div>
                            <br/>
                            <strong><?=gettext("Send Options"); ?></strong><br />
                            <input name="adv_dhcp6_interface_statement_send_options" type="text" id="adv_dhcp6_interface_statement_send_options" value="<?=$pconfig['adv_dhcp6_interface_statement_send_options'];?>" />
                            <div class="hidden" data-for="help_for_dhcp6_intf_stmt">
                              <?=gettext("The values in this field are DHCP send options to be sent when requesting a DHCP lease. [option declaration [, ...]] <br />" .
                              "Value Substitutions: {interface}, {hostname}, {mac_addr_asciiCD}, {mac_addr_hexCD} <br />" .
                              "Where C is U(pper) or L(ower) Case, and D is \" :-.\" Delimiter (space, colon, hyphen, or period) (omitted for none).") ?>
                            </div>
                            <br />
                            <br />
                            <strong><?=gettext("Request Options"); ?></strong><br />
                            <input name="adv_dhcp6_interface_statement_request_options" type="text" id="adv_dhcp6_interface_statement_request_options" value="<?=$pconfig['adv_dhcp6_interface_statement_request_options'];?>" />
                            <div class="hidden" data-for="help_for_dhcp6_intf_stmt">
                              <?=gettext('The values in this field are DHCP request options to be sent when requesting a DHCP lease. [option [, ...]]') ?>
                            </div>
                            <br />
                            <br />
                            <strong><?=gettext("Script"); ?></strong><br />
                            <input name="adv_dhcp6_interface_statement_script" type="text" id="adv_dhcp6_interface_statement_script" value="<?=htmlspecialchars($pconfig['adv_dhcp6_interface_statement_script']);?>" />
                            <div class="hidden" data-for="help_for_dhcp6_intf_stmt">
                              <?= gettext('The value in this field is the absolute path to a script invoked on certain conditions including when a reply message is received.') ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="dhcpv6_advanced">
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Identity Association");?></td>
                          <td>
                            <input name="adv_dhcp6_id_assoc_statement_address_enable" type="checkbox" id="adv_dhcp6_id_assoc_statement_address_enable" <?=!empty($pconfig['adv_dhcp6_id_assoc_statement_address_enable']) ? "checked=\"checked\"" : "";?>  />
                            <strong><?=gettext("Non-Temporary Address Allocation"); ?></strong>
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
                            <strong><?=gettext("Prefix Delegation"); ?></strong>
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
                            <?=gettext("Prefix Interface "); ?>
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
                <!-- Section : SLAAC -->
                <div class="tab-content content-box col-xs-12 __mb" id="slaac" style="display:none">
                  <div class="table-responsive">
                    <table class="table table-striped opnsense_standard_table_form">
                      <thead>
                        <tr>
                          <th colspan="2"><?=gettext("SLAAC configuration"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td style="width:22%"><a id="help_for_slaacusev4iface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use IPv4 connectivity"); ?></td>
                          <td style="width:78%">
                            <input name="slaacusev4iface" type="checkbox" id="slaacusev4iface" value="yes" <?=!empty($pconfig['slaacusev4iface']) ? "checked=\"checked\"" : ""; ?> />
                            <div class="hidden" data-for="help_for_slaacusev4iface">
                              <?= gettext('Request the IPv6 information through the IPv4 PPP connectivity link.') ?>
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
                              <?=gettext("The value in this field is the 6RD IPv4 prefix length. Normally specified by the ISP. A value of 0 means we embed the entire IPv4 address in the 6RD prefix."); ?>
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
                          <td style="width:22%"><a id="help_for_track6-interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Interface"); ?></td>
                          <td style="width:78%">
                            <select name='track6-interface' class='selectpicker' data-style='btn-default' >
<?php
                            foreach ($ifdescrs as $iface => $ifcfg):
                              switch ($config['interfaces'][$iface]['ipaddrv6']) {
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
                          <td><a id="help_for_track6-prefix-id" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 Prefix ID"); ?></td>
                          <td>
<?php
                            if (empty($pconfig['track6-prefix-id'])) {
                                $pconfig['track6-prefix-id'] = 0;
                            }
                            $track6_prefix_id_hex = !empty($pconfig['track6-prefix-id--hex']) ? $pconfig['track6-prefix-id--hex']: sprintf("%x", $pconfig['track6-prefix-id']);?>
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
<?php
                              foreach($wl_modes as $wl_standard => $wl_channels):?>
                              <option value="<?=$wl_standard;?>" <?=$pconfig['standard'] == $wl_standard ? "selected=\"selected\"" : "";?>>
                                802.<?=$wl_standard;?>
                              </option>
<?php
                              endforeach;?>
                            </select>
                          </td>
                        </tr>
<?php
                        if (isset($wl_modes['11g'])): ?>
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
<?php
                        else: ?>
                          <input name="protmode" type="hidden" id="protmode" value="off" />
<?php
                        endif; ?>
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
                              foreach($wl_modes as $wl_standard => $wl_channels):
                                foreach($wl_channels as $wl_channel):?>
                              <option value="<?=$wl_channel;?>" <?=$pconfig['channel'] == $wl_channel ? "selected=\"selected\" " : "";?>>
                                  <?=$wl_standard;?> - <?=$wl_channel;?>
                                  <?=isset($wl_chaninfo[$wl_channel]) ?  "( " . $wl_chaninfo[$wl_channel][1] . "@" . $wl_chaninfo[$wl_channel][2] . "/" . $wl_chaninfo[$wl_channel][3] . ")" : "";?>
                              </option>
<?php
                                endforeach;
                              endforeach;?>
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
                            <div class="hidden" data-for="help_for_regdomain">
                              <?=gettext("Note: Some cards have a default that is not recognized and require changing the regulatory domain to one in this list for the changes to other regulatory settings to work."); ?>
                            </div>

                            <br /><br />
                            <?=gettext("Country (listed with country code and regulatory domain)"); ?><br />
                            <select name="regcountry" class="selectpicker" data-size="10" data-style="btn-default" id="regcountry">
                              <option <?=empty($pconfig['regcountry']) ? "selected=\"selected\"" : ""; ?> value=""><?=gettext("Default"); ?></option>
<?php
                            foreach($wl_countries as $wl_country_key => $wl_country):?>
                              <option value="<?=$wl_countries_attr[$wl_country_key]['ID'];?>" <?=$pconfig['regcountry'] == $wl_countries_attr[$wl_country_key]['ID'] ?  "selected=\"selected\" " : "";?> >
                                  <?=$wl_country['name'];?> -- ( <?=$wl_countries_attr[$wl_country_key]['ID'];?> <?=strtoupper($wl_countries_attr[$wl_country_key]['rd'][0]['REF']);?> )
                              </option>
<?php
                            endforeach;?>
                            </select>
                            <br />
                            <div class="hidden" data-for="help_for_regdomain">
                              <?=gettext("Note: Any country setting other than \"Default\" will override the regulatory domain setting"); ?>.
                            </div>
                            <br /><br />
                            <?=gettext("Location"); ?><br />
                            <select name="reglocation" class="selectpicker" data-style="btn-default" id="reglocation">
                              <option <?=empty($pconfig['reglocation']) ? "selected=\"selected\"" : ""; ?> value=""><?=gettext("Default"); ?></option>
                              <option <?=$pconfig['reglocation'] == 'indoor' ? "selected=\"selected\"" : ""; ?> value="indoor"><?=gettext("Indoor"); ?></option>
                              <option <?=$pconfig['reglocation'] == 'outdoor' ? "selected=\"selected\"" : ""; ?> value="outdoor"><?=gettext("Outdoor"); ?></option>
                              <option <?=$pconfig['reglocation'] == 'anywhere' ? "selected=\"selected\"" : ""; ?> value="anywhere"><?=gettext("Anywhere"); ?></option>
                            </select>
                            <br /><br />
                            <div class="hidden" data-for="help_for_regdomain">
                              <?=gettext("These settings may affect which channels are available and the maximum transmit power allowed on those channels. Using the correct settings to comply with local regulatory requirements is recommended."); ?>
                              <br />
                              <?=gettext("All wireless networks on this interface will be temporarily brought down when changing regulatory settings. Some of the regulatory domains or country codes may not be allowed by some cards. These settings may not be able to add additional channels that are not already supported."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <th colspan="2"><?=gettext("Network-specific wireless configuration");?></th>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Mode"); ?></td>
                          <td>
                            <select name="mode" class="selectpicker" data-style="btn-default" id="mode">
<?php
                              if (interfaces_test_wireless_capability(get_real_interface($pconfig['if']), 'hostap')): ?>
                              <option <?=$pconfig['mode'] == 'hostap' ? "selected=\"selected\"" : "";?> value="hostap"><?=gettext("Access Point"); ?></option>
<?php
                              endif; ?>
                              <option <?=$pconfig['mode'] == 'bss' ? "selected=\"selected\"" : "";?> value="bss"><?=gettext("Infrastructure (BSS)"); ?></option>
<?php
                              if (interfaces_test_wireless_capability(get_real_interface($pconfig['if']), 'adhoc')): ?>
                              <option <?=$pconfig['mode'] == 'adhoc' ? "selected=\"selected\"" : "";?> value="adhoc"><?=gettext("Ad-hoc (IBSS)"); ?></option>
<?php
                              endif; ?>
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
<?php
                        if (isset($wl_modes['11ng']) || isset($wl_modes['11na'])): ?>
                        <tr>
                          <td><a id="help_for_puremode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Minimum standard"); ?></td>
                          <td>
                            <select name="puremode" class="selectpicker" data-style="btn-default" id="puremode">
                              <option <?=$pconfig['puremode'] == 'any' ? "selected=\"selected\"" : "";?> value="any"><?=gettext("Any"); ?></option>
<?php
                              if (isset($wl_modes['11g'])): ?>
                              <option <?=$pconfig['puremode'] == '11g' ? "selected=\"selected\"" : "";?> value="11g"><?=gettext("802.11g"); ?></option>
<?php
                              endif; ?>
                              <option <?=$pconfig['puremode'] == '11n' ? "selected=\"selected\"" : "";?> value="11n"><?=gettext("802.11n"); ?></option>
                            </select>
                            <div class="hidden" data-for="help_for_puremode">
                              <?=gettext("When operating as an access point, allow only stations capable of the selected wireless standard to associate (stations not capable are not permitted to associate)."); ?>
                            </div>
                          </td>
                        </tr>
<?php
                        elseif (isset($wl_modes['11g'])): ?>
                        <tr>
                          <td><a id="help_for_puremode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.11g only"); ?></td>
                          <td>
                            <input name="puremode" type="checkbox" value="11g"  id="puremode" <?php if ($pconfig['puremode'] == '11g') echo "checked=\"checked\"";?> />
                            <div class="hidden" data-for="help_for_puremode">
                              <?=gettext("When operating as an access point in 802.11g mode, allow only 11g-capable stations to associate (11b-only stations are not permitted to associate)."); ?>
                            </div>
                          </td>
                        </tr>
<?php
                        endif; ?>
                        <tr>
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
                        <tr>
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
                            <strong><?=gettext("Enable WEP"); ?></strong>
                            <table class="table table-condensed">
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
                          <td><a id="help_for_wpa_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WPA"); ?></td>
                          <td>
                            <input name="wpa_enable" type="checkbox" id="wpa_enable" value="yes" <?php if ($pconfig['wpa_enable']) echo "checked=\"checked\""; ?> />
                            <strong><?=gettext("Enable WPA"); ?></strong>
                            <hr/>
                            <?=gettext("WPA Pre-Shared Key"); ?><br/>
                            <input name="passphrase" type="text" id="passphrase" value="<?=$pconfig['passphrase'];?>" />
                            <div class="hidden" data-for="help_for_wpa_enable">
                              <?=gettext("Passphrase must be from 8 to 63 characters."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WPA Mode"); ?></td>
                          <td>
                            <select name="wpa_mode" class="selectpicker" data-style="btn-default" id="wpa_mode">
                              <option <?=$pconfig['wpa_mode'] == '1' ? "selected=\"selected\"" : "";?> value="1"><?=gettext("WPA"); ?></option>
                              <option <?=$pconfig['wpa_mode'] == '2' ? "selected=\"selected\"" : "";?> value="2"><?=gettext("WPA2"); ?></option>
                              <option <?=$pconfig['wpa_mode'] == '3' ? "selected=\"selected\"" : "";?> value="3"><?=gettext("Both"); ?></option>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WPA Key Management Mode"); ?></td>
                          <td>
                            <select name="wpa_key_mgmt" class="selectpicker" data-style="btn-default" id="wpa_key_mgmt">
                              <option <?=$pconfig['wpa_key_mgmt'] == 'WPA-PSK' ? "selected=\"selected\"" : "";?> value="WPA-PSK"><?=gettext("Pre-Shared Key"); ?></option>
                              <option <?=$pconfig['wpa_key_mgmt'] == 'WPA-EAP' ? "selected=\"selected\"" : "";?> value="WPA-EAP"><?=gettext("Extensible Authentication Protocol"); ?></option>
                              <option <?=$pconfig['wpa_key_mgmt'] == 'WPA-PSK WPA-EAP' ? "selected=\"selected\"" : "";?> value="WPA-PSK WPA-EAP"><?=gettext("Both"); ?></option>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_auth_algs" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication"); ?></td>
                          <td>
                            <select name="auth_algs" class="selectpicker" data-style="btn-default" id="auth_algs">
                              <option <?=$pconfig['auth_algs'] == '1' ? "selected=\"selected\"" : "";?> value="1"><?=gettext("Open System Authentication"); ?></option>
                              <option <?=$pconfig['auth_algs'] == '2' ? "selected=\"selected\"" : "";?> value="2"><?=gettext("Shared Key Authentication"); ?></option>
                              <option <?=$pconfig['auth_algs'] == '3' ? "selected=\"selected\"" : "";?> value="3"><?=gettext("Both"); ?></option>
                            </select>
                            <div class="hidden" data-for="help_for_auth_algs">
                              <?=gettext("Note: Shared Key Authentication requires WEP."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WPA Pairwise"); ?></td>
                          <td>
                            <select name="wpa_pairwise" class="selectpicker" data-style="btn-default" id="wpa_pairwise">
                              <option <?=$pconfig['wpa_pairwise'] == 'CCMP TKIP' ? "selected=\"selected\"" : "";?> value="CCMP TKIP"><?=gettext("Both"); ?></option>
                              <option <?=$pconfig['wpa_pairwise'] == 'CCMP' ? "selected=\"selected\"" : "";?> value="CCMP"><?=gettext("AES (recommended)"); ?></option>
                              <option <?=$pconfig['wpa_pairwise'] == 'TKIP' ? "selected=\"selected\"" : "";?> value="TKIP"><?=gettext("TKIP"); ?></option>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_wpa_group_rekey" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Key Rotation"); ?></td>
                          <td>
                            <input name="wpa_group_rekey" type="text" id="wpa_group_rekey" value="<?=!empty($pconfig['wpa_group_rekey']) ? $pconfig['wpa_group_rekey'] : "60";?>" />
                            <div class="hidden" data-for="help_for_wpa_group_rekey">
                              <?=gettext("Allowed values are 1-9999 but should not be longer than Master Key Regeneration time."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_wpa_gmk_rekey" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Master Key Regeneration"); ?></td>
                          <td>
                            <input name="wpa_gmk_rekey" type="text" id="wpa_gmk_rekey" value="<?=!empty($pconfig['wpa_gmk_rekey']) ? $pconfig['wpa_gmk_rekey'] : "3600";?>" />
                            <div class="hidden" data-for="help_for_wpa_gmk_rekey">
                              <?=gettext("Allowed values are 1-9999 but should not be shorter than Key Rotation time."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_wpa_strict_rekey" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Strict Key Regeneration"); ?></td>
                          <td>
                            <input name="wpa_strict_rekey" type="checkbox" value="yes"  id="wpa_strict_rekey" <?php if ($pconfig['wpa_strict_rekey']) echo "checked=\"checked\""; ?> />
                            <div class="hidden" data-for="help_for_wpa_strict_rekey">
                              <?=gettext("Setting this option will force the AP to rekey whenever a client disassociates."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_ieee8021x" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable IEEE802.1X Authentication"); ?></td>
                          <td>
                            <input name="ieee8021x" type="checkbox" value="yes"  id="ieee8021x" <?=!empty($pconfig['ieee8021x']) ? "checked=\"checked\"" : "";?> />
                            <div class="hidden" data-for="help_for_ieee8021x">
                              <?=gettext("Setting this option will enable 802.1x authentication."); ?><br/>
                              <span class="text-danger"><strong><?=gettext("NOTE"); ?>:</strong></span> <?=gettext("this option requires checking the \"Enable WPA box\"."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_auth_server_addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server IP Address"); ?></td>
                          <td>
                            <input name="auth_server_addr" id="auth_server_addr" type="text" value="<?=$pconfig['auth_server_addr'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_addr">
                              <?=gettext("Enter the IP address of the 802.1X Authentication Server. This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_auth_server_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server Port"); ?></td>
                          <td>
                            <input name="auth_server_port" id="auth_server_port" type="text" value="<?=$pconfig['auth_server_port'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_port">
                              <?=gettext("Leave blank for the default 1812 port."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("802.1X Server Shared Secret"); ?></td>
                          <td>
                            <input name="auth_server_shared_secret" id="auth_server_shared_secret" type="text" value="<?=$pconfig['auth_server_shared_secret'];?>" />
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_auth_server_addr2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server IP Address (2)"); ?></td>
                          <td>
                            <input name="auth_server_addr2" id="auth_server_addr2" type="text" value="<?=$pconfig['auth_server_addr2'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_addr2">
                              <?=gettext("Secondary 802.1X Authentication Server IP Address"); ?><br>
                              <?=gettext("Enter the IP address of the 802.1X Authentication Server. This is commonly a Radius server (FreeRadius, Internet Authentication Services, etc.)"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_auth_server_port2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server Port (2)"); ?></td>
                          <td>
                            <input name="auth_server_port2" id="auth_server_port2" type="text" value="<?=$pconfig['auth_server_port2'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_port2">
                              <?=gettext("Secondary 802.1X Authentication Server Port"); ?><br />
                              <?=gettext("Leave blank for the default 1812 port."); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td><a id="help_for_auth_server_shared_secret2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("802.1X Server Shared Secret (2)"); ?></td>
                          <td>
                            <input name="auth_server_shared_secret2" id="auth_server_shared_secret2" type="text" value="<?=$pconfig['auth_server_shared_secret2'];?>" />
                            <div class="hidden" data-for="help_for_auth_server_shared_secret2">
                              <?=gettext("Secondary 802.1X Authentication Server Shared Secret"); ?>
                            </div>
                          </td>
                        </tr>
                        <tr>
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
<?php
                          if ($pconfig['if'] == $a_ppps[$pppid]['if']) : ?>
                            <input name="ppp_port" type="hidden" value="<?=$pconfig['ports'];?>" />
<?php
                          endif; ?>
                          <input name="ptpid" type="hidden" value="<?=$pconfig['ptpid'];?>" />
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
