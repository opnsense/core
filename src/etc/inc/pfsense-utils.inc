<?php

/*
    Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
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
    RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

/****f* legacy/is_private_ip
 * NAME
 *   is_private_ip
 * INPUTS
 *  none
 * RESULT
 *   returns true if an ip address is in a private range
 ******/
function is_private_ip($iptocheck)
{
    $isprivate = false;
    $ip_private_list=array(
      "10.0.0.0/8",
      "100.64.0.0/10",
      "172.16.0.0/12",
      "192.168.0.0/16",
    );
    foreach($ip_private_list as $private) {
        if (ip_in_subnet($iptocheck,$private)==true) {
            $isprivate = true;
        }
    }
    return $isprivate;
}

/****f* legacy/get_dns_servers
 * NAME
 *   get_dns_servers - get system dns servers
 * INPUTS
 *   $dns_servers - an array of the dns servers
 * RESULT
 *   null
 ******/
function get_dns_servers() {
    $dns_servers = array();
    $dns_s = file("/etc/resolv.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($dns_s as $dns) {
        $matches = "";
        if (preg_match("/nameserver (.*)/", $dns, $matches)) {
            $dns_servers[] = $matches[1];
        }
    }
    return array_unique($dns_servers);
}

/*
 * get_carp_interface_status($carpinterface): returns the status of a carp ip
 */
function get_carp_interface_status($carpinterface) {
    $carp_query = "";

    /* XXX: Need to fidn a better way for this! */
    list ($interface, $vhid) = explode("_vip", $carpinterface);
    $interface = get_real_interface($interface);
    exec("/sbin/ifconfig $interface | /usr/bin/grep -v grep | /usr/bin/grep carp: | /usr/bin/grep 'vhid {$vhid}'", $carp_query);
    foreach($carp_query as $int) {
        if (stristr($int, "MASTER")) {
            return gettext("MASTER");
        } elseif (stristr($int, "BACKUP")) {
            return gettext("BACKUP");
        } elseif(stristr($int, "INIT")) {
            return gettext("INIT");
        }
    }
    return;
}

/*
 *  restore_config_section($section_name, new_contents): restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.
 */
function restore_config_section($section_name, $new_contents)
{
    global $config;
    $tmpxml = '/tmp/tmpxml';

    $fout = fopen($tmpxml, 'w');
    fwrite($fout, $new_contents);
    fclose($fout);

    $xml = parse_xml_config($tmpxml, null);
    if (isset($xml['pfsense'])) {
        $xml = $xml['pfsense'];
    } elseif (isset($xml['m0n0wall'])) {
        $xml = $xml['m0n0wall'];
    } elseif (isset($xml['opnsense'])) {
        $xml = $xml['opnsense'];
    }
    if (isset($xml[$section_name])) {
        $section_xml = $xml[$section_name];
    } else {
        $section_xml = -1;
    }

    @unlink($tmpxml);

    if ($section_xml === -1) {
        return false;
    }

    $config[$section_name] = &$section_xml;
    write_config(sprintf(gettext("Restored %s of config file (maybe from CARP partner)"), $section_name));
    disable_security_checks();

    return true;
}

function setup_serial_port($sync = true)
{
    global $config;

    $serialspeed = (!empty($config['system']['serialspeed']) && is_numeric($config['system']['serialspeed'])) ? $config['system']['serialspeed'] : '115200';
    $serial_enabled = isset($config['system']['enableserial']);

    // ** serial console - write out /boot.config
    if ($serial_enabled) {
        file_put_contents('/boot.config', "-S{$serialspeed} -D\n");
    } elseif (file_exists('/boot.config')) {
        unlink('/boot.config');
    }

    // ** console settings in /boot/loader.conf
    $new_boot_config = array();
    $new_boot_config['boot_multicons'] = $serial_enabled ? '"YES"' : null;
    $new_boot_config['boot_serial'] = $serial_enabled ? '"YES"' : null;
    if ($serial_enabled) {
        $primaryconsole = !empty($config['system']['primaryconsole']) ? $config['system']['primaryconsole'] : "";
        $new_boot_config['console'] = $primaryconsole == "video" ? '"vidconsole,comconsole"' : '"comconsole,vidconsole"';
    } else {
        $new_boot_config['console'] = null;
    }
    $new_boot_config['comconsole_speed'] = '"'.$serialspeed.'"';
    $new_boot_config['hw.usb.no_pf'] = '"1"';
    $new_boot_config['autoboot_delay'] = '"3"';

    $new_loader_conf = "";
    // construct OPNsense config for /boot/loader.conf
    foreach ($new_boot_config as $param => $value) {
        if (!empty($value)) {
            $new_loader_conf .= "{$param}={$value}\n";
        }
    }
    // copy non matched settings in /boot/loader.conf
    foreach (explode("\n", @file_get_contents('/boot/loader.conf')) as $line) {
        if (!empty($line) && !array_key_exists(trim(explode('=', $line)[0]), $new_boot_config)) {
            $new_loader_conf .= $line . "\n";
        }
    }
    @file_put_contents('/boot/loader.conf', $new_loader_conf);

    // ** setup /etc/ttys

    // minimize chances of /etc/ttys corruption, keep a copy of the original ttys file
    if (!file_exists('/etc/ttys.opnsense') || filesize('/etc/ttys.opnsense') < 100) {
        copy('/etc/ttys', '/etc/ttys.opnsense');
    }

    $fd = fopen("/etc/ttys", "w");
    $on_off = $serial_enabled ? 'on' : 'off';
    if (isset($config['system']['disableconsolemenu'])) {
        $console_type = 'Pc';
        $serial_type = 'std.' . $serialspeed;
    } else {
        $console_type = 'al.Pc';
        $serial_type = 'al.' . $serialspeed;
    }
    foreach(explode("\n", file_get_contents("/etc/ttys.opnsense")) as $tty) {
        if (stristr($tty, "ttyv0")) {
            fwrite($fd, "ttyv0  \"/usr/libexec/getty {$console_type}\"  cons25  on  secure\n");
        } elseif (stristr($tty, "ttyu0")) {
            fwrite($fd, "ttyu0  \"/usr/libexec/getty {$serial_type}\"  cons25  {$on_off}  secure\n");
        } elseif (!empty($tty)) {
            fwrite($fd, $tty . "\n");
        }
    }
    fclose($fd);

    if ($sync) {
        reload_ttys();
    }
}

function reload_ttys()
{
    /* force init(8) to reload /etc/ttys */
    exec('/bin/kill -HUP 1');
}

function is_fqdn($fqdn) {
    $hostname = false;
    if (preg_match("/[-A-Z0-9\.]+\.[-A-Z0-9\.]+/i", $fqdn)) {
        $hostname = true;
    }
    if (preg_match("/\.\./", $fqdn)) {
        $hostname = false;
    }
    if (preg_match("/^\./i", $fqdn)) {
        $hostname = false;
    }
    if (preg_match("/\//i", $fqdn)) {
        $hostname = false;
    }
    return($hostname);
}

/* This xml 2 array function is courtesy of the php.net comment section on xml_parse.
 * it is roughly 4 times faster then our existing pfSense parser but due to the large
 * size of the RRD xml dumps this is required.
 * The reason we do not use it for pfSense is that it does not know about array fields
 * which causes it to fail on array fields with single items. Possible Todo?
 */
function xml2array($contents, $get_attributes = 1, $priority = 'tag')
{
    if (!function_exists('xml_parser_create'))
    {
        return array ();
    }
    $parser = xml_parser_create('');
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values) {
        return; //Hmm...
    }
    $xml_array = array ();
    $parents = array ();
    $opened_tags = array ();
    $arr = array ();
    $current = & $xml_array;
    $repeated_tag_index = array ();
    foreach ($xml_values as $data) {
        unset ($attributes, $value);
        extract($data);
        $result = array ();
        $attributes_data = array ();
        if (isset ($value))
        {
            if ($priority == 'tag') {
                $result = $value;
            } else {
                $result['value'] = $value;
            }
        }
        if (isset ($attributes) and $get_attributes) {
            foreach ($attributes as $attr => $val) {
                if ($priority == 'tag') {
                    $attributes_data[$attr] = $val;
                } else {
                    $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
                }
            }
        }
        if ($type == "open") {
            $parent[$level -1] = & $current;
            if (!is_array($current) || (!in_array($tag, array_keys($current)))) {
                $current[$tag] = $result;
                if ($attributes_data) {
                    $current[$tag . '_attr'] = $attributes_data;
                }
                $repeated_tag_index[$tag . '_' . $level] = 1;
                $current = & $current[$tag];
            } else {
                if (isset ($current[$tag][0])) {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    $repeated_tag_index[$tag . '_' . $level]++;
                } else {
                    $current[$tag] = array ($current[$tag], $result );
                    $repeated_tag_index[$tag . '_' . $level] = 2;
                    if (isset ($current[$tag . '_attr'])) {
                        $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                        unset ($current[$tag . '_attr']);
                    }
                }
                $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                $current = & $current[$tag][$last_item_index];
            }
        } elseif ($type == "complete") {
            if (!isset ($current[$tag])) {
                $current[$tag] = $result;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                if ($priority == 'tag' and $attributes_data) {
                    $current[$tag . '_attr'] = $attributes_data;
                }
            } else {
                if (isset ($current[$tag][0]) and is_array($current[$tag])) {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    if ($priority == 'tag' and $get_attributes and $attributes_data) {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level]++;
                } else {
                    $current[$tag] = array ($current[$tag], $result );
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $get_attributes) {
                        if (isset ($current[$tag . '_attr'])) {
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset ($current[$tag . '_attr']);
                        }
                        if ($attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                    }
                    $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                }
            }
        } elseif ($type == 'close') {
            $current = & $parent[$level -1];
        }
    }
    return ($xml_array);
}

/* Returns the calculated bit length of the prefix delegation from the WAN interface */
/* DHCP-PD is variable, calculate from the prefix-len on the WAN interface */
/* 6rd is variable, calculate from 64 - (v6 prefixlen - (32 - v4 prefixlen)) */
/* 6to4 is 16 bits, e.g. 65535 */
function calculate_ipv6_delegation_length($if) {
    global $config;

    if(!isset($config['interfaces'][$if]) || !is_array($config['interfaces'][$if])) {
        return false;
    } elseif (!isset($config['interfaces'][$if]['ipaddrv6'])) {
        return 0;
    }

    switch($config['interfaces'][$if]['ipaddrv6']) {
        case "6to4":
            $pdlen = 16;
            break;
        case "6rd":
            $rd6cfg = $config['interfaces'][$if];
            $rd6plen = explode("/", $rd6cfg['prefix-6rd']);
            $pdlen = (64 - ($rd6plen[1] + (32 - $rd6cfg['prefix-6rd-v4plen'])));
            break;
        case "dhcp6":
            $dhcp6cfg = $config['interfaces'][$if];
            $pdlen = $dhcp6cfg['dhcp6-ia-pd-len'];
            break;
        default:
            $pdlen = 0;
            break;
    }
    return $pdlen;
}
