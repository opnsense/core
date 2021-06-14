<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
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

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/dhcpd.inc");

/*
 * This function will remove entries from dhcpd.leases that would otherwise
 * overlap with static DHCP reservations. If we don't clean these out,
 * then DHCP will print a warning in the logs about a duplicate lease
 *
 * XXX errr: why are we doing this only on this particular page?
 */
function dhcp_clean_leases()
{
    global $config;

    killbypid('/var/dhcpd/var/run/dhcpd.pid', 'TERM', true);

    $leasesfile = dhcpd_dhcpv4_leasesfile();
    if (!file_exists($leasesfile)) {
        return;
    }

    /* Build lease patterns for static MACs */
    $lease_patterns = array();
    foreach (legacy_config_get_interfaces(array("virtual" => false)) as $ifname => $ifarr) {
        if (isset($config['dhcpd'][$ifname]['staticmap'])) {
            foreach($config['dhcpd'][$ifname]['staticmap'] as $static) {
                $lease_patterns[] = '(lease\s*[0-9\.]+\s*\{[^\{\}]*hardware ethernet ' . $static['mac'] . '[^\{\}]*(.*"[^\{\}]*\}|\})\s?)';
            }
        }
    }

    /* Read existing leases file */
    $leases_contents = file_get_contents($leasesfile);

    /* Remove existing leases for static MACs */
    $leases_contents = preg_replace($lease_patterns, '', $leases_contents);

    /* Write out the new leases file */
    $fd = fopen($leasesfile, 'w');
    fwrite($fd, $leases_contents);
    fclose($fd);
}

function validate_partial_mac_list($maclist) {
    $macs = explode(',', $maclist);
    // Loop through and look for invalid MACs.
    foreach ($macs as $mac) {
        if (!is_macaddr($mac, true)) {
            return false;
        }
    }
    return true;
}

function reconfigure_dhcpd()
{
    dhcp_clean_leases();
    system_hosts_generate();
    clear_subsystem_dirty('hosts');
    dhcpd_dhcp4_configure();
    clear_subsystem_dirty('staticmaps');
}

$config_copy_fieldsnames = array('enable', 'staticarp', 'failover_peerip', 'failover_split', 'dhcpleaseinlocaltime','descr',
  'defaultleasetime', 'maxleasetime', 'gateway', 'domain', 'domainsearchlist', 'denyunknown','ignoreuids', 'ddnsdomain',
  'ddnsdomainprimary', 'ddnsdomainkeyname', 'ddnsdomainkey', 'ddnsdomainalgorithm', 'ddnsupdate', 'mac_allow',
  'mac_deny', 'tftp', 'bootfilename', 'ldap', 'netboot', 'nextserver', 'filename', 'filename32', 'filename64',
  'rootpath', 'netmask', 'numberoptions', 'interface_mtu', 'wpad', 'omapi', 'omapiport', 'omapialgorithm', 'omapikey', 'minsecs');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // handle identifiers and action
    if (!empty($_GET['if']) && !empty($config['interfaces'][$_GET['if']]) &&
        isset($config['interfaces'][$_GET['if']]['enable']) &&
        is_ipaddr($config['interfaces'][$_GET['if']]['ipaddr'])) {
        $if = $_GET['if'];
        if (isset($_GET['pool']) && !empty($config['dhcpd'][$_GET['if']]['pool'][$_GET['pool']])) {
            $pool = $_GET['pool'];
        }
    } else {
        /* if no interface is provided this invoke is invalid */
        header(url_safe('Location: /index.php'));
        exit;
    }

    $a_pools = &config_read_array('dhcpd', $if, 'pool');

    if (!empty($_GET['act'])) {
        $act = $_GET['act'];
    } else {
        $act = null;
    }

    // point to source of data (pool or main dhcp section)
    if (isset($pool)) {
        $dhcpdconf = &$a_pools[$pool];
    } elseif ($act == "newpool") {
        $dhcpdconf = array();
    } else {
        $dhcpdconf = &config_read_array('dhcpd', $if);
    }
    $pconfig = array();
    // simple 1-on-1 copy
    foreach ($config_copy_fieldsnames as $fieldname) {
        if (isset($dhcpdconf[$fieldname])) {
            $pconfig[$fieldname] = $dhcpdconf[$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
    // handle booleans
    $pconfig['enable'] =  isset($dhcpdconf['enable']);
    $pconfig['wpad'] =  isset($dhcpdconf['wpad']);
    $pconfig['staticarp'] = isset($dhcpdconf['staticarp']);
    $pconfig['denyunknown'] = isset($dhcpdconf['denyunknown']);
    $pconfig['ignoreuids'] = isset($dhcpdconf['ignoreuids']);
    $pconfig['ddnsupdate'] = isset($dhcpdconf['ddnsupdate']);
    $pconfig['netboot'] = isset($dhcpdconf['netboot']);

    // array conversions
    $pconfig['numberoptions'] = !empty($pconfig['numberoptions']) ? $pconfig['numberoptions'] : array();

    // list items
    $pconfig['range_from'] = !empty($dhcpdconf['range']['from']) ? $dhcpdconf['range']['from'] : "";
    $pconfig['range_to'] = !empty($dhcpdconf['range']['to']) ? $dhcpdconf['range']['to'] : "";
    $pconfig['wins1'] = !empty($dhcpdconf['winsserver'][0]) ? $dhcpdconf['winsserver'][0] : "";
    $pconfig['wins2'] = !empty($dhcpdconf['winsserver'][1]) ? $dhcpdconf['winsserver'][1] : "";
    $pconfig['dns1'] = !empty($dhcpdconf['dnsserver'][0]) ? $dhcpdconf['dnsserver'][0] : "";
    $pconfig['dns2'] = !empty($dhcpdconf['dnsserver'][1]) ? $dhcpdconf['dnsserver'][1] : "";
    $pconfig['ntp1'] = !empty($dhcpdconf['ntpserver'][0]) ? $dhcpdconf['ntpserver'][0] : "";
    $pconfig['ntp2'] = !empty($dhcpdconf['ntpserver'][1]) ? $dhcpdconf['ntpserver'][1] : "";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // handle identifiers and actions
    if (!empty($_POST['if']) && !empty($config['interfaces'][$_POST['if']])) {
        $if = $_POST['if'];
        if (isset($_POST['pool']) && !empty($config['dhcpd'][$_POST['if']]['pool'][$_POST['pool']])) {
            $pool = $_POST['pool'];
        }
    }

    $a_pools = &config_read_array('dhcpd', $if, 'pool');

    if (!empty($_POST['act'])) {
        $act = $_POST['act'];
    } else {
        $act = null;
    }
    $pconfig = $_POST;
    $input_errors = array();

    if (isset($_POST['submit'])) {
        // transform Additional BOOTP/DHCP Options
        $pconfig['numberoptions'] =  array();
        if (isset($pconfig['numberoptions_number'])) {
            $pconfig['numberoptions']['item'] = array();
            foreach ($pconfig['numberoptions_number'] as $opt_seq => $opt_number) {
                if (!empty($opt_number)) {
                    $pconfig['numberoptions']['item'][] = array('number' => $opt_number,
                                                                'type' => $pconfig['numberoptions_type'][$opt_seq],
                                                                'value' => $pconfig['numberoptions_value'][$opt_seq]
                                                          );
                }
            }
        }

        /* input validation */
        $reqdfields = explode(" ", "range_from range_to");
        $reqdfieldsn = array(gettext("Range begin"),gettext("Range end"));

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        if (!is_ipaddrv4($pconfig['range_from'])) {
            $input_errors[] = gettext("A valid range must be specified.");
        }
        if (!is_ipaddrv4($pconfig['range_to'])) {
            $input_errors[] = gettext("A valid range must be specified.");
        }
        if (!empty($pconfig['gateway']) && $pconfig['gateway'] != "none" && !is_ipaddrv4($pconfig['gateway'])) {
            $input_errors[] = gettext("A valid IP address must be specified for the gateway.");
        }
        if ((!empty($pconfig['wins1']) && !is_ipaddrv4($pconfig['wins1'])) || (!empty($pconfig['wins2']) && !is_ipaddrv4($pconfig['wins2']))) {
            $input_errors[] = gettext("A valid IP address must be specified for the primary/secondary WINS servers.");
        }
        $parent_net = find_interface_network(get_real_interface($pconfig['if']));
        if (is_subnetv4($parent_net) && $pconfig['gateway'] && $pconfig['gateway'] != "none") {
            if (!ip_in_subnet($pconfig['gateway'], $parent_net) && !ip_in_interface_alias_subnet($pconfig['if'], $pconfig['gateway'])) {
                $input_errors[] = sprintf(gettext("The gateway address %s does not lie within the chosen interface's subnet."), $pconfig['gateway']);
            }
        }
        if ((!empty($pconfig['dns1']) && !is_ipaddrv4($pconfig['dns1'])) || (!empty($pconfig['dns2']) && !is_ipaddrv4($pconfig['dns2']))) {
            $input_errors[] = gettext("A valid IP address must be specified for the primary/secondary DNS servers.");
        }

        if (!empty($pconfig['defaultleasetime']) && (!is_numeric($pconfig['defaultleasetime']) || ($pconfig['defaultleasetime'] < 60))) {
            $input_errors[] = gettext("The default lease time must be at least 60 seconds.");
        }

        if (!empty($pconfig['maxleasetime']) && (!is_numeric($pconfig['maxleasetime']) || ($pconfig['maxleasetime'] < 60) || ($pconfig['maxleasetime'] <= $pconfig['defaultleasetime']))) {
            $input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
        }

        if (!empty($pconfig['minsecs']) && (!is_numeric($pconfig['minsecs']) || ($pconfig['minsecs'] < 0) || ($pconfig['minsecs'] > 255))) {
            $input_errors[] = gettext("The response delay must be higher than 0 and no more than 255 seconds.");
        }

        if ((!empty($pconfig['ddnsdomain']) && !is_domain($pconfig['ddnsdomain']))) {
            $input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
        }
        if ((!empty($pconfig['ddnsdomainprimary']) && !is_ipaddrv4($pconfig['ddnsdomainprimary']))) {
            $input_errors[] = gettext("A valid primary domain name server IP address must be specified for the dynamic domain name.");
        }
        if (!empty($pconfig['ddnsdomainkey']) && base64_encode(base64_decode($pconfig['ddnsdomainkey'], true)) !== $pconfig['ddnsdomainkey']) {
            $input_errors[] = gettext('You must specify a Base64-encoded domain key.');
        }
        if ((!empty($pconfig['ddnsdomainkey']) && empty($pconfig['ddnsdomainkeyname'])) ||
            (!empty($pconfig['ddnsdomainkeyname']) && empty($pconfig['ddnsdomainkey']))
            ) {
            $input_errors[] = gettext("You must specify both a valid domain key and key name.");
        }
        if (!empty($pconfig['domainsearchlist'])) {
            $domain_array=preg_split("/[ ;]+/",$pconfig['domainsearchlist']);
            foreach ($domain_array as $curdomain) {
                if (!is_domain($curdomain)) {
                    $input_errors[] = gettext("A valid domain search list must be specified.");
                    break;
                }
            }
        }

        // Validate MACs
        if (!empty($pconfig['mac_allow']) && !validate_partial_mac_list($pconfig['mac_allow'])) {
            $input_errors[] = gettext("If you specify a mac allow list, it must contain only valid partial MAC addresses.");
        }
        if (!empty($pconfig['mac_deny']) && !validate_partial_mac_list($pconfig['mac_deny'])) {
            $input_errors[] = gettext("If you specify a mac deny list, it must contain only valid partial MAC addresses.");
        }

        if ((!empty($pconfig['ntp1']) && !is_ipaddrv4($pconfig['ntp1'])) || (!empty($pconfig['ntp2']) && !is_ipaddrv4($pconfig['ntp2']))) {
            $input_errors[] = gettext("A valid IP address must be specified for the primary/secondary NTP servers.");
        }
        if (!empty($pconfig['domain']) && !is_domain($pconfig['domain'])) {
            $input_errors[] = gettext("A valid domain name must be specified for the DNS domain.");
        }
        if (!empty($pconfig['tftp']) && !is_ipaddrv4($pconfig['tftp']) && !is_domain($pconfig['tftp']) && !is_URL($pconfig['tftp'])) {
            $input_errors[] = gettext("A valid IP address or hostname must be specified for the TFTP server.");
        }
        if (!empty($pconfig['nextserver']) && !is_ipaddrv4($pconfig['nextserver'])) {
            $input_errors[] = gettext("A valid IP address must be specified for the network boot server.");
        }

        if (gen_subnet($config['interfaces'][$if]['ipaddr'], $config['interfaces'][$if]['subnet']) == $pconfig['range_from']) {
            $input_errors[] = gettext("You cannot use the network address in the starting subnet range.");
        }
        if (gen_subnet_max($config['interfaces'][$if]['ipaddr'], $config['interfaces'][$if]['subnet']) == $pconfig['range_to']) {
            $input_errors[] = gettext("You cannot use the broadcast address in the ending subnet range.");
        }

        if ($pconfig['omapi'] && (empty($pconfig['omapiport']) || !is_numeric($pconfig['omapiport']) || $pconfig['omapiport'] < 1 || $pconfig['omapiport'] > 65535)) {
            $input_errors[] = gettext("A valid port number must be specified for the OMAPI port.");
        }

        // Disallow a range that includes the virtualip
        if (isset($config['virtualip']['vip'])) {
            foreach($config['virtualip']['vip'] as $vip) {
                if ($vip['interface'] == $if) {
                    if ($vip['subnet'] && is_inrange_v4($vip['subnet'], $pconfig['range_from'], $pconfig['range_to'])) {
                        $input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IP address %s."),$vip['subnet']);
                    }
                }
            }
        }

        $a_maps = &config_read_array('dhcpd', $if, 'staticmap');
        $noip = false;
        foreach ($a_maps as $map) {
            if (empty($map['ipaddr'])) {
                $noip = true;
            }
        }
        if (!empty($pconfig['staticarp']) && $noip) {
            $input_errors[] = gettext("Cannot enable static ARP when you have static map entries without IP addresses. Ensure all static maps have IP addresses and try again.");
        }
        if (!empty($pconfig['interface_mtu']) && (
          (string)((int)$pconfig['interface_mtu']) != $pconfig['interface_mtu'] || $pconfig['interface_mtu'] < 68 || $pconfig['interface_mtu'] > 65535)
        ) {
            $input_errors[] = gettext("A valid MTU value must be specified.");
        }
        if ($pconfig['failover_split'] != "" && (
            (string)((int)$pconfig['failover_split']) != $pconfig['failover_split'] || $pconfig['failover_split'] < 0 || $pconfig['failover_split'] > 256)
        ) {
            $input_errors[] = gettext("Failover split must be a number between 0 and 256.");
        }

        if (is_array($pconfig['numberoptions']['item'])) {
            foreach ($pconfig['numberoptions']['item'] as $numberoption) {
              if ($numberoption['type'] == 'text' && strstr($numberoption['value'], '"')) {
                  $input_errors[] = gettext("Text type cannot include quotation marks.");
              } elseif ($numberoption['type'] == 'string' && !preg_match('/^"[^"]*"$/', $numberoption['value']) && !preg_match('/^[0-9a-f]{2}(?:\:[0-9a-f]{2})*$/i', $numberoption['value'])) {
                  $input_errors[] = gettext("String type must be enclosed in quotes like \"this\" or must be a series of octets specified in hexadecimal, separated by colons, like 01:23:45:67:89:ab:cd:ef");
              } elseif ($numberoption['type'] == 'boolean' && $numberoption['value'] != 'true' && $numberoption['value'] != 'false' && $numberoption['value'] != 'on' && $numberoption['value'] != 'off') {
                  $input_errors[] = gettext("Boolean type must be true, false, on, or off.");
              } elseif ($numberoption['type'] == 'unsigned integer 8' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 255)) {
                  $input_errors[] = gettext("Unsigned 8-bit integer type must be a number in the range 0 to 255.");
              } elseif ($numberoption['type'] == 'unsigned integer 16' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 65535)) {
                  $input_errors[] = gettext("Unsigned 16-bit integer type must be a number in the range 0 to 65535.");
              } elseif ($numberoption['type'] == 'unsigned integer 32' && (!is_numeric($numberoption['value']) || $numberoption['value'] < 0 || $numberoption['value'] > 4294967295) ) {
                  $input_errors[] = gettext("Unsigned 32-bit integer type must be a number in the range 0 to 4294967295.");
              } elseif ($numberoption['type'] == 'signed integer 8' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -128 || $numberoption['value'] > 127)) {
                  $input_errors[] = gettext("Signed 8-bit integer type must be a number in the range -128 to 127.");
              } elseif ($numberoption['type'] == 'signed integer 16' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -32768 || $numberoption['value'] > 32767)) {
                  $input_errors[] = gettext("Signed 16-bit integer type must be a number in the range -32768 to 32767.");
              } elseif ($numberoption['type'] == 'signed integer 32' && (!is_numeric($numberoption['value']) || $numberoption['value'] < -2147483648 || $numberoption['value'] > 2147483647)) {
                  $input_errors[] = gettext("Signed 32-bit integer type must be a number in the range -2147483648 to 2147483647.");
              } elseif ($numberoption['type'] == 'ip-address' && !is_ipaddrv4($numberoption['value']) && !is_hostname($numberoption['value'])) {
                  $input_errors[] = gettext("IP address or host type must be an IP address or host name.");
              }
            }
        }

        if (count($input_errors) == 0) {
            /* make sure the range lies within the current subnet */
            $subnet_start = ip2ulong(long2ip32(ip2long($config['interfaces'][$if]['ipaddr']) & gen_subnet_mask_long($config['interfaces'][$if]['subnet'])));
            $subnet_end = ip2ulong(long2ip32(ip2long($config['interfaces'][$if]['ipaddr']) | (~gen_subnet_mask_long($config['interfaces'][$if]['subnet']))));

            if ((ip2ulong($pconfig['range_from']) < $subnet_start) || (ip2ulong($pconfig['range_from']) > $subnet_end) ||
              (ip2ulong($pconfig['range_to']) < $subnet_start) || (ip2ulong($pconfig['range_to']) > $subnet_end)) {
                $input_errors[] = gettext("The specified range lies outside of the current subnet.");
            }

            if (ip2ulong($pconfig['range_from']) > ip2ulong($pconfig['range_to'])) {
                $input_errors[] = gettext("The range is invalid (first element higher than second element).");
            }

            if (isset($pool) || ($act == "newpool")) {
                $rfrom = $config['dhcpd'][$if]['range']['from'];
                $rto = $config['dhcpd'][$if]['range']['to'];
                if (is_inrange_v4($pconfig['range_from'], $rfrom, $rto) || is_inrange_v4($pconfig['range_to'], $rfrom, $rto)) {
                    $input_errors[] = gettext("The specified range must not be within the DHCP range for this interface.");
                }
            }

            foreach ($a_pools as $id => $p) {
                if (isset($pool) && ($id == $pool)) {
                    continue;
                }
                if (is_inrange_v4($pconfig['range_from'], $p['range']['from'], $p['range']['to']) ||
                    is_inrange_v4($pconfig['range_to'], $p['range']['from'], $p['range']['to'])) {
                    $input_errors[] = gettext("The specified range must not be within the range configured on a DHCP pool for this interface.");
                    break;
                }
            }

            /* make sure that the DHCP Relay isn't enabled on this interface */
            if (isset($config['dhcrelay']['enable']) && (stristr($config['dhcrelay']['interface'], $if) !== false)) {
                $input_errors[] = sprintf(gettext("You must disable the DHCP relay on the %s interface before enabling the DHCP server."),
                                  !empty($config['interfaces'][$if]['descr']) ? htmlspecialchars($config['interfaces'][$if]['descr']) : strtoupper($if));
            }
        }
        // save data
        if (count($input_errors) == 0) {
            $dhcpdconf = array();
            // simple 1-on-1 copy
            foreach ($config_copy_fieldsnames as $fieldname) {
                if (!empty($pconfig[$fieldname]) || $pconfig[$fieldname] === "0") {
                    $dhcpdconf[$fieldname] = $pconfig[$fieldname];
                }
            }
            // handle booleans
            $dhcpdconf['enable'] = !empty($dhcpdconf['enable']);
            $dhcpdconf['staticarp'] = !empty($dhcpdconf['staticarp']);
            $dhcpdconf['denyunknown'] = !empty($dhcpdconf['denyunknown']);
            $dhcpdconf['ignoreuids'] = !empty($dhcpdconf['ignoreuids']);
            $dhcpdconf['ddnsupdate'] = !empty($dhcpdconf['ddnsupdate']);
            $dhcpdconf['netboot'] = !empty($dhcpdconf['netboot']);

            // supply range
            $dhcpdconf['range'] = array();
            $dhcpdconf['range']['from'] = $pconfig['range_from'];
            $dhcpdconf['range']['to'] = $pconfig['range_to'];

            // array types
            $dhcpdconf['winsserver'] = [];
            if (!empty($pconfig['wins1'])) {
                $dhcpdconf['winsserver'][] = $pconfig['wins1'];
            }
            if (!empty($pconfig['wins2'])) {
                $dhcpdconf['winsserver'][] = $pconfig['wins2'];
            }
            $dhcpdconf['dnsserver'] = [];
            if (!empty($pconfig['dns1'])) {
                $dhcpdconf['dnsserver'][] = $pconfig['dns1'];
            }
            if (!empty($pconfig['dns2'])) {
                $dhcpdconf['dnsserver'][] = $pconfig['dns2'];
            }
            $dhcpdconf['ntpserver'] = [];
            if (!empty($pconfig['ntp1'])) {
                $dhcpdconf['ntpserver'][] = $pconfig['ntp1'];
            }
            if (!empty($pconfig['ntp2'])) {
                $dhcpdconf['ntpserver'][] = $pconfig['ntp2'];
            }

            // handle changes
            if (!isset($pool) && $act != "newpool") {
                if (isset($config['dhcpd'][$if]['enable']) != !empty($pconfig['enable'])) {
                    // DHCP has been enabled or disabled.
                    //  The pf ruleset will need to be rebuilt to allow or disallow DHCP.
                    $exec_filter_configure = true;
                }
                $previous = !empty($config['dhcpd'][$if]['failover_peerip']) ? $config['dhcpd'][$if]['failover_peerip'] : "";
                if ($previous != $pconfig['failover_peerip']) {
                    mwexec("/bin/rm -rf /var/dhcpd/var/db/*");
                }
            }
            // save changes to config
            if (isset($pool)) {
                $a_pools[$pool] = $dhcpdconf;
            } elseif ($act == "newpool") {
                $a_pools[] = $dhcpdconf;
            } else {
                // copy structures back in
                foreach (array('pool', 'staticmap') as $fieldname) {
                    if (!empty($config['dhcpd'][$if][$fieldname])) {
                        $dhcpdconf[$fieldname] = $config['dhcpd'][$if][$fieldname];
                    }
                }
                $config['dhcpd'][$if] = $dhcpdconf;
            }
            write_config();
            if (isset($exec_filter_configure)) {
                filter_configure();
            }
            reconfigure_dhcpd();
            header(url_safe('Location: /services_dhcp.php?if=%s', array($if)));
            exit;
        }
    } elseif (isset($_POST['apply'])) {
        // apply changes
        reconfigure_dhcpd();
        header(url_safe('Location: /services_dhcp.php?if=%s', array($if)));
        exit;
    } elseif ($act ==  "del") {
        if (!empty($config['dhcpd'][$if]['staticmap'][$_POST['id']])) {
            unset($config['dhcpd'][$if]['staticmap'][$_POST['id']]);
            write_config();
            if (isset($config['dhcpd'][$if]['enable'])) {
              mark_subsystem_dirty('staticmaps');
              mark_subsystem_dirty('hosts');
            }
        }
        header(url_safe('Location: /services_dhcp.php?if=%s', array($if)));
        exit;
    } elseif ($act ==  "delpool") {
        if (!empty($a_pools[$_POST['id']])) {
            unset($a_pools[$_POST['id']]);
            write_config();
        }
        header(url_safe('Location: /services_dhcp.php?if=%s', array($if)));
        exit;
    }
}

$service_hook = 'dhcpd';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>

<script>
//<![CDATA[
    function show_shownumbervalue() {
        $("#shownumbervaluebox").html('');
        $("#shownumbervalue").show();
    }

    function show_ddns_config() {
        $("#showddnsbox").html('');
        $("#showddns").show();
    }

    function show_maccontrol_config() {
        $("#showmaccontrolbox").html('');
        $("#showmaccontrol").show();
    }

    function show_ntp_config() {
        $("#showntpbox").html('');
        $("#showntp").show();
    }

    function show_tftp_config() {
        $("#showtftpbox").html('');
        $("#showtftp").show();
    }

    function show_ldap_config() {
        $("#showldapbox").html('');
        $("#showldap").show();
    }

    function show_wpad_config() {
        $("#showwpadbox").html('');
        $("#showwpad").show();
    }

    function show_netboot_config() {
        $("#shownetbootbox").html('');
        $("#shownetboot").show();
    }

    function show_omapi_config() {
        $("#showomapibox").html('');
        $("#showomapi").show();
    }
//]]>
</script>

<script>
  $( document ).ready(function() {
    /**
     * Additional BOOTP/DHCP Options extenable table
     */
    function removeRow() {
        if ( $('#numberoptions_table > tbody > tr').length == 1 ) {
            $('#numberoptions_table > tbody > tr:last > td > input').each(function(){
              $(this).val("");
            });
        } else {
            $(this).parent().parent().remove();
        }
    }
    // add new detail record
    $("#addNew").click(function(){
        // copy last row and reset values
        $('#numberoptions_table > tbody').append('<tr>'+$('#numberoptions_table > tbody > tr:last').html()+'</tr>');
        $('#numberoptions_table > tbody > tr:last > td > input').each(function(){
          $(this).val("");
        });
        $(".act-removerow").click(removeRow);
    });
    $(".act-removerow").click(removeRow);

    // delete pool action
    $(".act_delete_pool").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      var intf = $(this).data("if");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("DHCP");?>",
        message: "<?=gettext("Do you really want to delete this pool?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'delpool', id:id, if:intf}, function(data) {
                        location.reload();
                    });
                }
              }]
      });
    });

    // delete static action
    $(".act_delete_static").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      var intf = $(this).data("if");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("DHCP");?>",
        message: "<?=gettext("Do you really want to delete this mapping?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'del', id:id, if:intf}, function(data) {
                        location.reload();
                    });
                }
              }]
      });
    });

    window_highlight_table_option();
  });
</script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <?php if (is_subsystem_dirty('staticmaps')): ?><br/>
        <?php print_info_box_apply(gettext("The static mapping configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
        <?php endif; ?>
        <section class="col-xs-12">
            <?php if (isset($config['dhcrelay']['enable'])): ?>
              <?php print_info_box(gettext("DHCP Relay is currently enabled. Cannot enable the DHCP Server service while the DHCP Relay is enabled on any interface.")); ?>
            <?php else: ?>
            <div class="tab-content content-box col-xs-12">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td style="width:22%"></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      </td>
                    </tr>
<?php
                    if (!isset($pool) && !($act == "newpool")): ?>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable");?> </td>
                      <td>
                        <input name="enable" id="enable" type="checkbox" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : ""; ?> />
                        <strong><?= sprintf(gettext("Enable DHCP server on the %s interface"),!empty($config['interfaces'][$if]['descr']) ? htmlspecialchars($config['interfaces'][$if]['descr']) : strtoupper($if));?></strong>
                      </td>
                    </tr>
<?php
                    else: ?>
                    <tr>
                      <td colspan="2"><?= gettext('Editing Pool-Specific Options. To return to the Interface, click its tab above.') ?></td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Pool Description");?></td>
                      <td>
                        <input name="descr" type="text" id="descr" value="<?=$pconfig['descr'];?>" />
                      </td>
                    </tr>
<?php
                    endif; ?>
                    <tr>
                      <td><a id="help_for_denyunknown" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Deny unknown clients");?></td>
                      <td>
                        <input name="denyunknown" type="checkbox" value="yes" <?=!empty($pconfig['denyunknown']) ? "checked=\"checked\"" : ""; ?> />
                        <div class="hidden" data-for="help_for_denyunknown">
                          <?=gettext("If this is checked, only the clients defined below will get DHCP leases from this server.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_ignoreuids" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Ignore Client UIDs");?></td>
                      <td>
                        <input name="ignoreuids" type="checkbox" value="yes" <?=!empty($pconfig['ignoreuids']) ? "checked=\"checked\"" : ""; ?> />
                        <div class="hidden" data-for="help_for_ignoreuids">
                          <?=gettext('By default, the same MAC can get multiple leases if the requests are sent using different UIDs. To avoid this behavior, check this box and client UIDs will be ignored.');?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Subnet");?></td>
                      <td>
                        <?=gen_subnet($config['interfaces'][$if]['ipaddr'], $config['interfaces'][$if]['subnet']);?>
                      </td>
                    </tr>
                    <tr>
                        <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Subnet mask");?></td>
                        <td>
                          <?=gen_subnet_mask($config['interfaces'][$if]['subnet']);?>
                        </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Available range");?></td>
                      <td>
<?php
                        $range_from = ip2long(long2ip32(ip2long($config['interfaces'][$if]['ipaddr']) & gen_subnet_mask_long($config['interfaces'][$if]['subnet']))) + 1;
                        $range_to = ip2long(long2ip32(ip2long($config['interfaces'][$if]['ipaddr']) | (~gen_subnet_mask_long($config['interfaces'][$if]['subnet'])))) - 1;?>
                        <?=long2ip32($range_from);?> - <?=long2ip32($range_to);?>
<?php
                        if (isset($pool) || ($act == "newpool")): ?>
                        <br />In-use DHCP Pool Ranges:
                          <br /><?=htmlspecialchars($config['dhcpd'][$if]['range']['from']); ?>-<?=htmlspecialchars($config['dhcpd'][$if]['range']['to']);?>
<?php
                          foreach ($a_pools as $p): ?>
                          <br /><?= htmlspecialchars($p['range']['from']); ?>-<?=htmlspecialchars($p['range']['to']); ?>
<?php
                          endforeach;
                        endif;?>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Range");?></td>
                      <td>
                        <table class="table table-condensed">
                            <thead>
                              <tr>
                                <th><?=gettext("from");?></th>
                                <th><?=gettext("to");?></th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td><input name="range_from" type="text" id="range_from" value="<?=$pconfig['range_from'];?>" /></td>
                                <td><input name="range_to" type="text" id="range_to" value="<?=$pconfig['range_to'];?>" /> </td>
                              </tr>
                            </tbody>
                        </table>
                      </td>
                    </tr>
<?php
                    if (!isset($pool) && !($act == "newpool")): ?>
                    <tr>
                      <td><a id="help_for_additionalpools" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Additional Pools");?></td>
                      <td>
                        <table class="table table-condensed">
                            <thead>
                              <tr>
                                <th><?=gettext("Pool Start");?></th>
                                <th><?=gettext("Pool End");?></th>
                                <th><?=gettext("Description");?></th>
                                <th class="text-nowrap">
                                  <a href="services_dhcp.php?if=<?=htmlspecialchars($if);?>&amp;act=newpool" class="btn btn-primary btn-xs"><i class="fa fa-plus fa-fw"></i></a>
                                </th>
                              </tr>
                            </thead>
                            <tbody>
<?php
                            $i = 0;
                            foreach ($a_pools as $poolent): ?>
                            <tr>
                              <td><?=htmlspecialchars($poolent['range']['from']);?></td>
                              <td><?=htmlspecialchars($poolent['range']['to']);?></td>
                              <td><?=htmlspecialchars($poolent['descr']);?></td>
                              <td class="text-nowrap">
                                <a href="services_dhcp.php?if=<?=$if;?>&amp;pool=<?=$i;?>" class="btn btn-xs btn-default"><i class="fa fa-pencil fa-fw"></i></a>
                                <a href="#" data-if="<?=$if;?>" data-id="<?=$i;?>" class="act_delete_pool btn btn-xs btn-default"><i class="fa fa-trash fa-fw"></i></a>
                              </td>
                            </tr>
<?php
                            $i++;
                            endforeach;?>
                            </tbody>
                        </table>
                        <div class="hidden" data-for="help_for_additionalpools">
                            <?=gettext("If you need additional pools of addresses inside of this subnet outside the above Range, they may be specified here."); ?>
                        </div>
                      </td>
                    </tr>
<?php
                    endif; ?>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WINS servers");?></td>
                      <td>
                        <input name="wins1" type="text" value="<?=$pconfig['wins1'];?>" /><br />
                        <input name="wins2" type="text" value="<?=$pconfig['wins2'];?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("DNS servers");?></td>
                      <td>
                        <input name="dns1" type="text" value="<?=$pconfig['dns1'];?>" /><br />
                        <input name="dns2" type="text" value="<?=$pconfig['dns2'];?>" />
                        <div class="hidden" data-for="help_for_dns">
                          <?= gettext('Leave blank to use the system default DNS servers: This interface IP address if a DNS service is enabled or the configured global DNS servers.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_gateway" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Gateway");?></td>
                      <td>
                        <input name="gateway" type="text" class="form-control host" value="<?=$pconfig['gateway'];?>" />
                        <div class="hidden" data-for="help_for_gateway">
                          <?=gettext('The default is to use the IP on this interface of the firewall as the gateway, if a valid (online) gateway has been configured under System->Gateways. '.
                                     'Specify an alternate gateway here if this is not the correct gateway for your network. Type "none" for no gateway assignment.');?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain name");?></td>
                      <td>
                        <input name="domain" type="text" value="<?=$pconfig['domain'];?>" />
                        <div class="hidden" data-for="help_for_domain">
                          <?=gettext("The default is to use the domain name of this system as the default domain name provided by DHCP. You may specify an alternate domain name here.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_domainsearchlist" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain search list");?></td>
                      <td>
                        <input name="domainsearchlist" type="text" id="domainsearchlist" value="<?=$pconfig['domainsearchlist'];?>" />
                        <div class="hidden" data-for="help_for_domainsearchlist">
                          <?=gettext("The DHCP server can optionally provide a domain search list. Use the semicolon character as separator.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_defaultleasetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Default lease time (seconds)")?></td>
                      <td>
                        <input name="defaultleasetime" type="text" id="defaultleasetime" value="<?=$pconfig['defaultleasetime'];?>" />
                        <div class="hidden" data-for="help_for_defaultleasetime">
                          <?=gettext("This is used for clients that do not ask for a specific expiration time."); ?><br />
                          <?=gettext("The default is 7200 seconds.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_maxleasetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Maximum lease time");?> (<?=gettext("seconds");?>)</td>
                      <td>
                        <input name="maxleasetime" type="text" id="maxleasetime" value="<?=$pconfig['maxleasetime'];?>" />
                        <div class="hidden" data-for="help_for_maxleasetime">
                          <?=gettext("This is the maximum lease time for clients that ask for a specific expiration time."); ?><br />
                          <?=gettext("The default is 86400 seconds.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_minsecs" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Response delay");?> (<?=gettext("seconds");?>)</td>
                      <td>
                        <input name="minsecs" type="text" id="minsecs" value="<?=$pconfig['minsecs'];?>" />
                        <div class="hidden" data-for="help_for_minsecs">
                          <?=gettext("This is the minimum number of seconds since a client began trying to acquire a new lease before the DHCP server will respond to its request."); ?><br/>
                          <?=gettext("The default is 0 seconds (no delay).");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_interface_mtu" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface MTU");?></td>
                      <td>
                        <input name="interface_mtu"  type="text" value="<?=$pconfig['interface_mtu']?>" />
                        <div class="hidden" data-for="help_for_interface_mtu">
                          <?=gettext('This option specifies the MTU to use on this interface. The minimum legal value for the MTU is 68.');?>
                        </div>
                      </td>
                    </tr>
<?php
                    if (!isset($pool) && !($act == "newpool")): ?>
                    <tr>
                      <td><a id="help_for_failover_peerip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Failover peer IP:");?></td>
                      <td>
                        <input name="failover_peerip" type="text" class="form-control host" id="failover_peerip" value="<?=$pconfig['failover_peerip'];?>" />
                        <div class="hidden" data-for="help_for_failover_peerip">
                          <?=gettext("Leave blank to disable. Enter the interface IP address of the other machine. Machines must be using CARP. Interface's advskew determines whether the DHCPd process is Primary or Secondary. Ensure one machine's advskew<20 (and the other is >20). Note that changing this value will delete the current leases-database!");?>
                        </div>
                      </td>
                    </tr>
                    <td><a id="help_for_failover_split" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Failover split:");?></td>
                      <td>
                        <input name="failover_split" type="text" class="form-control host" id="failover_split" value="<?=$pconfig['failover_split'];?>" />
                        <div class="hidden" data-for="help_for_failover_split">
                          <?=gettext("Leave blank to use default (128) which should be fine for most cases. ".
                                     "Enter a number (0-256) to specify the load-balancing split between the failover peers, ".
                                     "the default of 128 means both peers will handle approximately 50% of the clients, ".
                                     "256 will make the primary handle all the clients. This value is only used on the primary peer, ".
                                     "leave blank on the secondary.");?>
                        </div>
                      </td>
                    </tr>

                    <tr>
                      <td><a id="help_for_failover_staticarp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Static ARP");?></td>
                      <td>
                        <input type="checkbox" value="yes" name="staticarp" <?=!empty($pconfig['staticarp']) ? " checked=\"checked\"" : ""; ?> />&nbsp;
                        <strong><?=gettext("Enable Static ARP entries");?></strong>
                        <div class="hidden" data-for="help_for_failover_staticarp">
                          <?=gettext("Warning: This option persists even if DHCP server is disabled. Only the machines listed below will be able to communicate with the firewall on this NIC.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_failover_dhcpleaseinlocaltime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Time format change"); ?></td>
                      <td>
                        <input name="dhcpleaseinlocaltime" type="checkbox" id="dhcpleaseinlocaltime" value="yes" <?= !empty($pconfig['dhcpleaseinlocaltime']) ? "checked=\"checked\"" : ""; ?> />
                        <strong><?=gettext("Change DHCP display lease time from UTC to local time."); ?></strong>

                        <div class="hidden" data-for="help_for_failover_dhcpleaseinlocaltime">
                          <?=gettext("Warning: By default DHCP leases are displayed in UTC time. By checking this " .
                          "box DHCP lease time will be displayed in local time and set to time zone selected. This " .
                          "will be used for all DHCP interfaces lease time."); ?>
                        </div>
                      </td>
                    </tr>
<?php
                    endif; ?>
                    <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Dynamic DNS");?></td>
                    <td>
                      <div id="showddnsbox">
                        <input type="button" onclick="show_ddns_config()" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show Dynamic DNS");?>
                      </div>
                      <div id="showddns" style="display:none">
                        <input type="checkbox" value="yes" name="ddnsupdate" <?=!empty($pconfig['ddnsupdate']) ? " checked=\"checked\"" :""; ?> />
                        <strong><?=gettext("Enable registration of DHCP client names in DNS.");?></strong><br />
                        <?=gettext("Enter the dynamic DNS domain which will be used to register client names in the DNS server.");?>
                        <?=gettext("Note: Leave blank to disable dynamic DNS registration.");?><br />
                        <input name="ddnsdomain" type="text" value="<?=$pconfig['ddnsdomain'];?>" />
                        <?=gettext("Enter the primary domain name server IP address for the dynamic domain name.");?><br />
                        <input name="ddnsdomainprimary" type="text" value="<?=$pconfig['ddnsdomainprimary'];?>" />
                        <?=gettext("Enter the dynamic DNS domain key name which will be used to register client names in the DNS server.");?>
                        <input name="ddnsdomainkeyname" type="text" value="<?=$pconfig['ddnsdomainkeyname'];?>" />
                        <?=gettext("Enter the dynamic DNS domain key secret which will be used to register client names in the DNS server.");?>
                        <input name="ddnsdomainkey" type="text" value="<?=$pconfig['ddnsdomainkey'];?>" />
                        <?=gettext("Choose the dynamic DNS domain key algorithm.");?><br />
                        <select name='ddnsdomainalgorithm' id="ddnsdomainalgorithm" class="selectpicker">
<?php
                        foreach (array("hmac-md5", "hmac-sha512") as $algorithm) :
                          $selected = "";
                          if (! empty($pconfig['ddnsdomainalgorithm'])) {
                            if ($pconfig['ddnsdomainalgorithm'] == $algorithm) {
                              $selected = "selected=\"selected\"";
                            }
                          }?>
                          <option value="<?=$algorithm;?>" <?=$selected;?>><?=$algorithm;?></option>
<?php
                        endforeach; ?>
                        </select>
                      </div>
                    </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("MAC Address Control");?></td>
                      <td>
                        <div id="showmaccontrolbox">
                          <input type="button" onclick="show_maccontrol_config()" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show MAC Address Control");?>
                        </div>
                        <div id="showmaccontrol" style="display:none">
                          <?= sprintf(gettext("Enter a list of partial MAC addresses to allow, comma-separated, no spaces, such as %s"), '00:00:00,01:E5:FF') ?>
                          <input name="mac_allow" type="text" id="mac_allow" value="<?= $pconfig['mac_allow'] ?>" />
                          <?= sprintf(gettext("Enter a list of partial MAC addresses to deny access, comma-separated, no spaces, such as %s"), '00:00:00,01:E5:FF') ?>
                          <input name="mac_deny" type="text" id="mac_deny" value="<?= $pconfig['mac_deny'] ?>" /><br />
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("NTP servers");?></td>
                      <td>
                        <div id="showntpbox">
                          <input type="button" onclick="show_ntp_config()" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show NTP configuration");?>
                        </div>
                        <div id="showntp" style="display:none">
                          <input name="ntp1" type="text" id="ntp1" value="<?=$pconfig['ntp1'];?>" /><br />
                          <input name="ntp2" type="text" id="ntp2" value="<?=$pconfig['ntp2'];?>" />
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("TFTP server");?></td>
                      <td>
                        <div id="showtftpbox">
                          <input type="button" onclick="show_tftp_config()" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show TFTP configuration");?>
                        </div>
                        <div id="showtftp" style="display:none">
                          <?=gettext("Set TFTP hostname");?>
                          <input name="tftp" type="text" size="50" value="<?=$pconfig['tftp'];?>" /><br />
                          <?=gettext("Set Bootfile");?>
                          <input name="bootfilename" type="text" value="<?=$pconfig['bootfilename'];?>" /><br />
                          <?=gettext("Leave blank to disable. Enter a full hostname or IP for the TFTP server and optionally a full path for a bootfile.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("LDAP URI");?></td>
                      <td>
                        <div id="showldapbox">
                          <input type="button" onclick="show_ldap_config()" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show LDAP configuration");?>
                        </div>
                        <div id="showldap" style="display:none">
                          <input name="ldap" type="text" id="ldap" size="80" value="<?=$pconfig['ldap'];?>" /><br />
                          <?=sprintf(gettext("Leave blank to disable. Enter a full URI for the LDAP server in the form %s"),'ldap://ldap.example.com/dc=example,dc=com')?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable network booting");?></td>
                      <td>
                        <div id="shownetbootbox">
                          <input type="button" onclick="show_netboot_config()" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show Network booting");?>
                        </div>
                        <div id="shownetboot" style="display:none">
                          <input type="checkbox" value="yes" name="netboot" id="netboot" <?=!empty($pconfig['netboot']) ? " checked=\"checked\"" : ""; ?> />
                          <strong><?=gettext("Enables network booting.");?></strong>
                          <br/><br/>
                          <?=gettext('Set next-server IP');?>
                          <input name="nextserver" type="text" id="nextserver" value="<?=$pconfig['nextserver'];?>" /><br />
                          <?=gettext('Set default bios filename');?>
                          <input name="filename" type="text" id="filename" value="<?=$pconfig['filename'];?>" /><br />
                          <?=gettext('Set UEFI 32bit filename');?>
                          <input name="filename32" type="text" id="filename32" value="<?=$pconfig['filename32'];?>" /><br />
                          <?=gettext('Set UEFI 64bit filename');?>
                          <input name="filename64" type="text" id="filename64" value="<?=$pconfig['filename64'];?>" /><br />
                          <?=gettext("Note: You need both a filename and a boot server configured for this to work!");?><br/>
                          <?=gettext("You will need all three filenames and a boot server configured for UEFI to work!");?>
                          <br/><br/>
                          <?=gettext('Set root-path string');?>
                          <input name="rootpath" type="text" id="rootpath" size="90" value="<?=$pconfig['rootpath'];?>" /><br />
                          <?=gettext("Note: string-format: iscsi:(servername):(protocol):(port):(LUN):targetname");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WPAD");?> </td>
                      <td>
                        <div id="showwpadbox">
                          <input type="button" onclick="show_wpad_config()" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show WPAD");?>
                        </div>
                        <div id="showwpad" style="display:none">
                          <input name="wpad" id="wpad" type="checkbox" value="yes" <?=!empty($pconfig['wpad']) ? "checked=\"checked\"" : ""; ?> />
                          <strong><?= gettext("Enable Web Proxy Auto Discovery") ?></strong>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable OMAPI");?></td>
                      <td>
                        <div id="showomapibox">
                          <input type="button" onclick="show_omapi_config()" class="btn btn-default btn-xs" value="<?=gettext("Advanced");?>" /> - <?=gettext("Show OMAPI configuration");?>
                        </div>
                        <div id="showomapi" style="display:none">
                          <input type="checkbox" value="yes" name="omapi" id="omapi" <?=!empty($pconfig['omapi']) ? " checked=\"checked\"" : ""; ?> />
                          <strong><?=gettext("Enables OMAPI");?></strong>
                          <br/><br/>
                          <?=gettext('OMAPI port');?>
                          <input name="omapiport" type="text" id="omapiport" value="<?=$pconfig['omapiport'];?>" /><br />
                          <?=gettext('Key algorithm');?>
                          <input name="omapialgorithm" type="text" id="omapialgorithm" value="<?=$pconfig['omapialgorithm'];?>" /><br />
                          <?=gettext('OMAPI key');?>
                          <input name="omapikey" type="text" id="omapikey" value="<?=$pconfig['omapikey'];?>" /><br />
                        </div>
                      </td>
                    </tr>
<?php
                    if (!isset($pool) && !($act == "newpool")): ?>
                    <tr>
                      <td><a id="help_for_numberoptions" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Additional Options");?></td>
                      <td>
                        <div id="shownumbervaluebox">
                          <input type="button" onclick="show_shownumbervalue()" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show Additional BOOTP/DHCP Options");?>
                        </div>
                        <div id="shownumbervalue" style="display:none">
                          <table class="table table-striped table-condensed" id="numberoptions_table">
                            <thead>
                              <tr>
                                <th></th>
                                <th id="detailsHeading1"><?=gettext("Number"); ?></th>
                                <th id="detailsHeading3"><?=gettext("Type"); ?></th>
                                <th id="updatefreqHeader" ><?=gettext("Value");?></th>
                              </tr>
                            </thead>
                            <tbody>
<?php
                            if (empty($pconfig['numberoptions']['item'])) {
                                $numberoptions = array();
                                $numberoptions[] = array('number' => null, 'value' => null, 'type' => null);
                            } else {
                                $numberoptions = $pconfig['numberoptions']['item'];
                            }
                            foreach($numberoptions as $item):?>
                              <tr>
                                <td>
                                  <div style="cursor:pointer;" class="act-removerow btn btn-default btn-xs"><i class="fa fa-minus fa-fw"></i></div>
                                </td>
                                <td>
                                  <input name="numberoptions_number[]" type="text" value="<?=$item['number'];?>" />
                                </td>
                                <td>
                                  <select name="numberoptions_type[]">
                                    <option value="text" <?=$item['type'] == "text" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('Text');?>
                                    </option>
                                    <option value="string" <?=$item['type'] == "string" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('String');?>
                                    </option>
                                    <option value="boolean" <?=$item['type'] == "boolean" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('Boolean');?>
                                    </option>
                                    <option value="unsigned integer 8" <?=$item['type'] == "unsigned integer 8" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('Unsigned 8-bit integer');?>
                                    </option>
                                    <option value="unsigned integer 16" <?=$item['type'] == "unsigned integer 16" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('Unsigned 16-bit integer');?>
                                    </option>
                                    <option value="unsigned integer 32" <?=$item['type'] == "unsigned integer 32" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('Unsigned 32-bit integer');?>
                                    </option>
                                    <option value="signed integer 8" <?=$item['type'] == "signed integer 8" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('Signed 8-bit integer');?>
                                    </option>
                                    <option value="signed integer 16" <?=$item['type'] == "signed integer 16" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('Signed 16-bit integer');?>
                                    </option>
                                    <option value="signed integer 32" <?=$item['type'] == "signed integer 32" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('Signed 32-bit integer');?>
                                    </option>
                                    <option value="ip-address" <?=$item['type'] == "ip-address" ? "selected=\"selected\"" : "";?>>
                                      <?=gettext('IP address or host');?>
                                    </option>
                                  </select>
                                </td>
                                <td> <input name="numberoptions_value[]" type="text" value="<?=$item['value'];?>" /> </td>
                              </tr>
<?php
                            endforeach;?>
                            </tbody>
                            <tfoot>
                              <tr>
                                <td colspan="4">
                                  <div id="addNew" style="cursor:pointer;" class="btn btn-default btn-xs"><i class="fa fa-plus fa-fw"></i></div>
                                </td>
                              </tr>
                            </tfoot>
                          </table>
                          <div class="hidden" data-for="help_for_numberoptions">
                          <?= sprintf(gettext("Enter the DHCP option number and the value for each item you would like to include in the DHCP lease information. For a list of available options please visit this %sURL%s."), '<a href="https://www.iana.org/assignments/bootp-dhcp-parameters/" target="_blank">', '</a>') ?>
                          </div>
                        </div>
                      </td>
                    </tr>
<?php
                    endif; ?>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
<?php
                        if ($act == "newpool"): ?>
                        <input type="hidden" name="act" value="newpool" />
<?php
                        endif; ?>
<?php
                        if (isset($pool)): ?>
                        <input type="hidden" name="pool" value="<?=$pool; ?>" />
<?php
                        endif; ?>
                        <input name="if" type="hidden" value="<?=$if;?>" />
                        <input name="submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>"  />
                      </td>
                    </tr>
                  </table>
                </div>
              </form>
            </div>
          </section>
<?php
          if (!isset($pool) && !($act == "newpool")): ?>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td colspan="6"><strong><?=gettext("DHCP Static Mappings for this interface.");?></strong></td>
                  </tr>
                  <tr>
                    <td><?=gettext("Static ARP");?></td>
                    <td><?=gettext("MAC address");?></td>
                    <td><?=gettext("IP address");?></td>
                    <td><?=gettext("Hostname");?></td>
                    <td><?=gettext("Description");?></td>
                    <td class="text-nowrap">
                      <a href="services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>" class="btn btn-primary btn-xs"><i class="fa fa-plus fa-fw"></i></a>
                    </td>
                  </tr>
<?php
                  if (!empty($config['dhcpd'][$if]['staticmap'])):
                    $i = 0;
                    foreach ($config['dhcpd'][$if]['staticmap'] as $mapent): ?>
<?php
                        if ($mapent['mac'] != '' || $mapent['ipaddr'] != ''): ?>
                    <tr>
                      <td>
<?php
                          if (isset($mapent['arp_table_static_entry'])): ?>
                            <i class="fa fa-info-circle fa-fw"></i>
<?php
                          endif; ?>
                      </td>
                      <td>
                        <?=htmlspecialchars($mapent['mac']);?>
                      </td>
                      <td>
                        <?=htmlspecialchars($mapent['ipaddr']);?>
                      </td>
                      <td>
                        <?=htmlspecialchars($mapent['hostname']);?>
                      </td>
                      <td>
                        <?=htmlspecialchars($mapent['descr']);?>
                      </td>
                      <td class="text-nowrap">
                        <a href="services_dhcp_edit.php?if=<?=htmlspecialchars($if);?>&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                        <a href="#" data-if="<?=$if;?>" data-id="<?=$i;?>" class="act_delete_static btn btn-xs btn-default"><i class="fa fa-trash fa-fw"></i></a>
                      </td>
                    </tr>
<?php
                        endif;
                        $i++;
                      endforeach;
                    endif; ?>
                </table>
              </div>
            </div>
          </section>
<?php
          endif; ?>
<?php
        endif; ?>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
