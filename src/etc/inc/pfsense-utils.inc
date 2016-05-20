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
 *  backup_config_section($section): returns as an xml file string of
 *                                   the configuration section
 */
function backup_config_section($section_name) {
    global $config;
    $new_section = &$config[$section_name];
    /* generate configuration XML */
    $xmlconfig = dump_xml_config($new_section, $section_name);
    $xmlconfig = str_replace("<?xml version=\"1.0\"?>", "", $xmlconfig);
    return $xmlconfig;
}

/* KEEP THIS: unbreaks syntax highlighting <?php */

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

/*
 * host_firmware_version(): Return the versions used in this install
 */
function host_firmware_version()
{
    global $config;

    return array(
      'firmware' => array('version' => file_get_contents('/usr/local/opnsense/version/opnsense')),
      'kernel' => array('version' => file_get_contents('/usr/local/opnsense/version/opnsense-update.kernel')),
      'base' => array('version' => file_get_contents('/usr/local/opnsense/version/opnsense-update.base')),
      'config_version' => $config['version']
    );
}

function reload_all()
{
    configd_run('service reload all', true);
}

function reload_interfaces()
{
    configd_run('interface reload');
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


/* Any PPPoE servers enabled? */
function is_pppoe_server_enabled()
{
    global $config;
    $pppoeenable = false;
    if (!isset($config['pppoes']['pppoe']) || !is_array($config['pppoes']['pppoe'])) {
        return false;
    }

    foreach ($config['pppoes']['pppoe'] as $pppoes) {
        if ($pppoes['mode'] == 'server') {
            $pppoeenable = true;
        }
    }

    return $pppoeenable;
}

function add_hostname_to_watch($hostname) {
    if (!is_dir("/var/db/dnscache")) {
        mkdir("/var/db/dnscache");
    }
    $result = array();
    if((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
        $domrecords = array();
        $domips = array();
        exec("host -t A " . escapeshellarg($hostname), $domrecords, $rethost);
        if($rethost == 0) {
            foreach($domrecords as $domr) {
                $doml = explode(" ", $domr);
                $domip = $doml[3];
                /* fill array with domain ip addresses */
                if(is_ipaddr($domip)) {
                    $domips[] = $domip;
                }
            }
        }
        sort($domips);
        $contents = "";
        if(! empty($domips)) {
            foreach($domips as $ip) {
                $contents .= "$ip\n";
            }
        }
        file_put_contents("/var/db/dnscache/$hostname", $contents);
        /* Remove empty elements */
        $result = array_filter(explode("\n", $contents), 'strlen');
    }
    return $result;
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

function load_kernel_module()
{
    $mods = array(
        'carp',
    );

    foreach ($mods as $mod) {
        mwexecf('/sbin/kldload %s', $mod);
    }
}

function load_crypto_module()
{
    global $config;

    if (!empty($config['system']['crypto_hardware'])) {
        log_error(sprintf('Loading %s cryptographic accelerator module.', $config['system']['crypto_hardware']));
        mwexecf('/sbin/kldload %s', $config['system']['crypto_hardware'], true);
    }

    if (isset($config['system']['cryptodev_enable'])) {
        log_error('Loading cryptodev kernel module.');
        mwexecf('/sbin/kldload %s', 'cryptodev', true);
    }
}

function load_thermal_module()
{
    global $config;

    if (!empty($config['system']['thermal_hardware'])) {
        log_error(sprintf('Loading %s thermal monitor module.', $config['system']['thermal_hardware']));
        mwexecf('/sbin/kldload %s', $config['system']['thermal_hardware'], true);
    }
}

function download_file($url, $destination, $verify_ssl = false, $connect_timeout = 60, $timeout = 0)
{
    global $config, $g;

    $fp = fopen($destination, "wb");

    if (!$fp) {
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if (!$verify_ssl) {
        /* leave the curl defaults untouched when verify is used */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/usr/local/opnsense/version/opnsense")));

    @curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);
    curl_close($ch);

    return ($http_code == 200) ? true : $http_code;
}

function update_alias_names_upon_change($section, $field, $new_alias_name, $origname)
{
    global $config;
    if (!$origname) {
        return;
    }

    $sectionref = &$config;
    foreach($section as $sectionname) {
        if (is_array($sectionref) && isset($sectionref[$sectionname])) {
            $sectionref = &$sectionref[$sectionname];
        } else {
            return;
        }
    }

    if (is_array($sectionref)) {
        foreach($sectionref as $itemkey => $item) {
            $fieldfound = true;
            $fieldref = &$sectionref[$itemkey];
            foreach($field as $fieldname) {
                if (is_array($fieldref) && isset($fieldref[$fieldname])) {
                    $fieldref = &$fieldref[$fieldname];
                } else {
                    $fieldfound = false;
                    break;
                }
            }
            if ($fieldfound && $fieldref == $origname) {
                $fieldref = $new_alias_name;
            }
        }
    }
}

function process_alias_urltable($name, $url, $freq, $forceupdate = false)
{
    global $config;

    $urltable_prefix = "/var/db/aliastables/";
    $urltable_filename = $urltable_prefix . $name . ".txt";

    // Make the aliases directory if it doesn't exist
    if (!file_exists($urltable_prefix)) {
        mkdir($urltable_prefix);
    } elseif (!is_dir($urltable_prefix)) {
        unlink($urltable_prefix);
        mkdir($urltable_prefix);
    }
    if (empty($freq)) {
        $freq = 1;
    }
    $update_freq = ($freq * 86400) - 90;
    // If the file doesn't exist or is older than update_freq days, fetch a new copy.
    if (!file_exists($urltable_filename) || ((time() - filemtime($urltable_filename)) > $update_freq) || $forceupdate) {
        // open file handle to output file, in case the process takes a lot of time, make sure there's a file before
        // filter starts to load. Also helps for tracking progress.
        //
        // todo: rethink alias_expand_urltable in filter.inc , its probably not a very good idea to download and
        //       process files during boot.
        $output_file_handle = fopen($urltable_filename, 'w');
        $alias_type = alias_get_type($name);
        // Try to fetch the URL supplied
        @unlink("{$urltable_filename}.tmp");
        $verify_ssl = isset($config['system']['checkaliasesurlcert']);
        if (download_file($url, "{$urltable_filename}.tmp", $verify_ssl)) {
            foreach (preg_split('/[\n\r]+/', file_get_contents("{$urltable_filename}.tmp"), -1, PREG_SPLIT_NO_EMPTY) as $line) {
                $line = trim($line); // remove leading spaces
                if ($line[0] != '#') {
                    // cleanse line item
                    $line = preg_split('/\s+/', $line)[0];
                    if ($alias_type == "urltable_ports") {
                        // todo: add proper validation for ports here
                        fwrite($output_file_handle, "{$line}\n");
                    } else {
                        // validate or resolve line items, skip unparseable content
                        if (is_subnet($line) || is_ipaddr($line)) {
                            fwrite($output_file_handle, "{$line}\n");
                        } elseif (is_hostname($line)) {
                            foreach (array(DNS_AAAA, DNS_A) as $dns_type) {
                                // normally dns_get_record should be able to use DNS_A + DNS_AAAA
                                // but for some strange reason not on our platform...
                                $dns_records = @dns_get_record($line, $dns_type);
                                if ($dns_records) {
                                    foreach ($dns_records as $dns_record) {
                                        if (!empty($dns_record['ipv6'])) {
                                            fwrite($output_file_handle, $dns_record['ipv6'] . "\n");
                                        } elseif (!empty($dns_record['ip'])) {
                                            fwrite($output_file_handle, $dns_record['ip'] . "\n");
                                        }
                                    }
                                }
                            }
                        }
                        fflush($output_file_handle);
                    }
                }
            }
            fclose($output_file_handle);

            if ($alias_type == "urltable_ports") {
                $ports = explode("\n", file_get_contents($urltable_filename));
                $ports = group_ports($ports);
                file_put_contents($urltable_filename, implode("\n", $ports));
            }
            @unlink("{$urltable_filename}.tmp");
        } else {
          touch($urltable_filename);
        }
        return true;
    } else {
        // File exists, and it doesn't need updated.
        return -1;
    }
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

/* sort by interface only, retain the original order of rules that apply to
   the same interface */
function filter_rules_sort() {
    global $config;

    /* mark each rule with the sequence number (to retain the order while sorting) */
    for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
        $config['filter']['rule'][$i]['seq'] = $i;
    }
    usort($config['filter']['rule'], "filter_rules_compare");
    /* strip the sequence numbers again */
    for ($i = 0; isset($config['filter']['rule'][$i]); $i++) {
        unset($config['filter']['rule'][$i]['seq']);
    }
}
function filter_rules_compare($a, $b) {
    if (isset($a['floating']) && isset($b['floating'])) {
        return $a['seq'] - $b['seq'];
    } elseif (isset($a['floating'])) {
        return -1;
    } elseif (isset($b['floating'])) {
        return 1;
    } elseif ($a['interface'] == $b['interface']) {
        return $a['seq'] - $b['seq'];
    } else {
        return compare_interface_friendly_names($a['interface'], $b['interface']);
    }
}


/****f* legacy/load_mac_manufacturer_table
 * NAME
 *   load_mac_manufacturer_table
 * INPUTS
 *   none
 * RESULT
 *   returns associative array with MAC-Manufacturer pairs
 ******/
function load_mac_manufacturer_table() {
    /* load MAC-Manufacture data from the file */
    $macs = false;
    if (file_exists("/usr/local/share/nmap/nmap-mac-prefixes")) {
        $macs=file("/usr/local/share/nmap/nmap-mac-prefixes");
    }
    if ($macs){
        foreach ($macs as $line){
            if (preg_match('/([0-9A-Fa-f]{6}) (.*)$/', $line, $matches)){
                /* store values like this $mac_man['000C29']='VMware' */
                $mac_man["$matches[1]"]=$matches[2];
            }
        }
        return $mac_man;
    } else {
        return -1;
    }

}

/****f* legacy/is_ipaddr_configured
 * NAME
 *   is_ipaddr_configured
 * INPUTS
 *   IP Address to check.
 * RESULT
 *   returns true if the IP Address is
 *   configured and present on this device.
*/
function is_ipaddr_configured($ipaddr, $ignore_if = "", $check_localip = false, $check_subnets = false) {
    global $config;

    $isipv6 = is_ipaddrv6($ipaddr);
    if ($check_subnets) {
        $iflist = get_configured_interface_list();
        foreach ($iflist as $if => $ifname) {
            if ($ignore_if == $if) {
                continue;
            }

            if ($isipv6 === true) {
                $bitmask = get_interface_subnetv6($if);
                $subnet = gen_subnetv6(get_interface_ipv6($if), $bitmask);
            } else {
                $bitmask = get_interface_subnet($if);
                $subnet = gen_subnet(get_interface_ip($if), $bitmask);
            }

            if (ip_in_subnet($ipaddr, $subnet . '/' . $bitmask)) {
                return true;
            }
        }
    } else {
        if ($isipv6 === true) {
            $interface_list_ips = get_configured_ipv6_addresses();
        } else {
            $interface_list_ips = get_configured_ip_addresses();
        }
        foreach($interface_list_ips as $if => $ilips) {
            /* Also ignore CARP interfaces, it'll be checked below */
            if ($ignore_if == $if || strstr($ignore_if, "_vip")) {
                continue;
            }
            if (strcasecmp($ipaddr, $ilips) == 0) {
                return true;
            }
        }
    }

    $interface_list_vips = get_configured_vips_list(true);
    foreach ($interface_list_vips as $id => $vip) {
        if ($ignore_if == $vip['if']) {
            continue;
        }
        if (strcasecmp($ipaddr, $vip['ipaddr']) == 0) {
            return true;
        }
    }

    if ($check_localip) {
        if (is_array($config['pptpd']) && !empty($config['pptpd']['localip']) && (strcasecmp($ipaddr, $config['pptpd']['localip']) == 0)) {
            return true;
        }

        if (!is_array($config['l2tp']) && !empty($config['l2tp']['localip']) && (strcasecmp($ipaddr, $config['l2tp']['localip']) == 0)) {
            return true;
        }
    }

    return false;
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
