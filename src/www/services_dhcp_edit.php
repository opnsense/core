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
require_once("interfaces.inc");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // handle identifiers and action
    if (!empty($_GET['if']) && !empty($config['interfaces'][$_GET['if']])) {
        $if = $_GET['if'];
    } else {
        header(url_safe('Location: /services_dhcp.php'));
        exit;
    }
    if (isset($if) && isset($_GET['id']) && !empty($config['dhcpd'][$if]['staticmap'][$_GET['id']])) {
        $id = $_GET['id'];
    }

    // read form data
    $pconfig = array();
    $config_copy_fieldnames = array('mac', 'cid', 'hostname', 'filename', 'rootpath', 'descr', 'arp_table_static_entry',
      'defaultleasetime', 'maxleasetime', 'gateway', 'domain', 'domainsearchlist', 'winsserver', 'dnsserver', 'ddnsdomain',
      'ddnsupdate', 'ntpserver', 'tftp', 'bootfilename', 'ipaddr', 'winsserver', 'dnsserver');
    foreach ($config_copy_fieldnames as $fieldname) {
        if (isset($if) && isset($id) && isset($config['dhcpd'][$if]['staticmap'][$id][$fieldname])) {
            $pconfig[$fieldname] = $config['dhcpd'][$if]['staticmap'][$id][$fieldname];
        } elseif (isset($_GET[$fieldname])) {
            $pconfig[$fieldname] = $_GET[$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }

    // handle array types
    if (isset($pconfig['winsserver'][0])) {
        $pconfig['wins1'] = $pconfig['winsserver'][0];
    }
    if (isset($pconfig['winsserver'][1])) {
        $pconfig['wins2'] = $pconfig['winsserver'][1];
    }
    if (isset($pconfig['dnsserver'][0])) {
        $pconfig['dns1'] = $pconfig['dnsserver'][0];
    }
    if (isset($pconfig['dnsserver'][1])) {
        $pconfig['dns2'] = $pconfig['dnsserver'][1];
    }
    if (isset($pconfig['ntpserver'][0])) {
        $pconfig['ntp1'] = $pconfig['ntpserver'][0];
    }
    if (isset($pconfig['ntpserver'][1])) {
        $pconfig['ntp2'] = $pconfig['ntpserver'][1];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;

    // handle identifiers and actions
    if (!empty($pconfig['if']) && !empty($config['interfaces'][$pconfig['if']])) {
        $if = $pconfig['if'];
    }
    if (!empty($config['dhcpd'][$if]['staticmap'][$pconfig['id']])) {
        $id = $pconfig['id'];
    }

    $a_maps = &config_read_array('dhcpd', $if, 'staticmap');
    $input_errors = array();

    /* input validation */
    $reqdfields = array();
    $reqdfieldsn = array();
    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    /* either MAC or Client-ID must be specified */
    if (empty($pconfig['mac']) && empty($pconfig['cid'])) {
        $input_errors[] = gettext("Either MAC address or Client identifier must be specified");
    }

    /* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
    $pconfig['mac'] = strtolower(str_replace("-", ":", $pconfig['mac']));

    if (!empty($pconfig['hostname'])) {
        preg_match("/\-\$/", $pconfig['hostname'], $matches);
        if ($matches) {
            $input_errors[] = gettext("The hostname cannot end with a hyphen according to RFC952");
        }
        if (!is_hostname($pconfig['hostname'])) {
            $input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
        } elseif (strpos($pconfig['hostname'],'.')) {
            $input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
        }
    }
    if (!empty($pconfig['ipaddr']) && !is_ipaddr($_POST['ipaddr'])) {
        $input_errors[] = gettext("A valid IP address must be specified.");
    }
    if (!empty($pconfig['mac']) && !is_macaddr($pconfig['mac'])) {
        $input_errors[] = gettext("A valid MAC address must be specified.");
    }
    if (isset($config['dhcpd'][$if]['staticarp']) && empty($pconfig['ipaddr'])) {
        $input_errors[] = gettext("Static ARP is enabled. You must specify an IP address.");
    }

    /* check for overlaps */
    if (!empty($pconfig['domain'])) {
        $this_fqdn = $pconfig['hostname'] . "." . $pconfig['domain'];
    } elseif (!empty($if) && !empty($config['dhcpd'][$if]['domain'])) {
        $this_fqdn = $pconfig['hostname'] . "." . $config['dhcpd'][$if]['domain'];
    } else {
        $this_fqdn = $pconfig['hostname'] . "." . $config['system']['domain'];
    }
    foreach ($a_maps as $mapent) {
        if (isset($id) && ($a_maps[$id] === $mapent)) {
            continue;
        }
        if (empty($mapent['hostname'])) {
            $fqdn = "";
        } elseif (!empty($mapent['domain'])) {
            $fqdn = $mapent['hostname'] . "." . $mapent['domain'];
        } elseif (!empty($if) && !empty($config['dhcpd'][$if]['domain'])) {
            $fqdn = $mapent['hostname'] . "." . $config['dhcpd'][$if]['domain'];
        } else {
            $fqdn = $mapent['hostname'] . "." . $config['system']['domain'];
        }

        if (($fqdn == $this_fqdn)  ||
            (($mapent['mac'] == $pconfig['mac']) && $mapent['mac']) ||
            (($mapent['ipaddr'] == $pconfig['ipaddr']) && $mapent['ipaddr'] ) ||
            (($mapent['cid'] == $pconfig['cid']) && $mapent['cid'])) {
            $input_errors[] = gettext("This Hostname, IP, MAC address or Client identifier already exists.");
            break;
        }
    }

    $parent_net = find_interface_network(get_real_interface($if));

    if (!empty($pconfig['ipaddr'])) {
      if (!ip_in_subnet($pconfig['ipaddr'], $parent_net)) {
          $ifcfgdescr = convert_friendly_interface_to_friendly_descr($if);
          $input_errors[] = sprintf(gettext('The IP address must lie in the %s subnet.'), $ifcfgdescr);
      }
    }

    if (!empty($pconfig['gateway']) && $pconfig['gateway'] != "none" && !is_ipaddrv4($pconfig['gateway'])) {
        $input_errors[] = gettext("A valid IP address must be specified for the gateway.");
    }

    if ((!empty($pconfig['wins1']) && !is_ipaddrv4($pconfig['wins1'])) ||
      (!empty($pconfig['wins2']) && !is_ipaddrv4($pconfig['wins2']))) {
        $input_errors[] = gettext("A valid IP address must be specified for the primary/secondary WINS servers.");
    }

    if (is_subnetv4($parent_net) && $pconfig['gateway'] != "none" &&  !empty($pconfig['gateway'])) {
        if (!ip_in_subnet($pconfig['gateway'], $parent_net) && !ip_in_interface_alias_subnet($if, $pconfig['gateway'])) {
            $input_errors[] = sprintf(gettext("The gateway address %s does not lie within the chosen interface's subnet."), $_POST['gateway']);
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
    if (!empty($pconfig['ddnsdomain']) && !is_domain($pconfig['ddnsdomain'])) {
        $input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
    }
    if (!empty($pconfig['domainsearchlist'])) {
        $domain_array=preg_split("/[ ;]+/", $pconfig['domainsearchlist']);
        foreach ($domain_array as $curdomain) {
            if (!is_domain($curdomain)) {
                $input_errors[] = gettext("A valid domain search list must be specified.");
                break;
            }
        }
    }

    if ((!empty($pconfig['ntp1']) && !is_ipaddrv4($pconfig['ntp1'])) || (!empty($pconfig['ntp2']) && !is_ipaddrv4($pconfig['ntp2']))) {
        $input_errors[] = gettext("A valid IP address must be specified for the primary/secondary NTP servers.");
    }
    if (!empty($pconfig['tftp']) && !is_ipaddrv4($pconfig['tftp']) && !is_domain($pconfig['tftp']) && !is_URL($pconfig['tftp'])) {
        $input_errors[] = gettext("A valid IP address or hostname must be specified for the TFTP server.");
    }
    if ((!empty($pconfig['nextserver']) && !is_ipaddrv4($pconfig['nextserver']))) {
        $input_errors[] = gettext("A valid IP address must be specified for the network boot server.");
    }

    if (count($input_errors) == 0){
        $mapent = array();
        $config_copy_fieldnames = array('mac', 'cid', 'ipaddr', 'hostname', 'descr', 'filename', 'rootpath',
          'arp_table_static_entry', 'defaultleasetime', 'maxleasetime', 'gateway', 'domain', 'domainsearchlist',
          'ddnsdomain', 'ddnsupdate', 'tftp', 'bootfilename', 'winsserver', 'dnsserver');

        foreach ($config_copy_fieldnames as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $mapent[$fieldname] = $pconfig[$fieldname];
            }
        }

        // boolean
        $mapent['arp_table_static_entry'] = !empty($pconfig['arp_table_static_entry']);
        $mapent['ddnsupdate'] = !empty($pconfig['ddnsupdate']);

        // arrays
        $mapent['winsserver'] = array();
        if (!empty($pconfig['wins1'])) {
            $mapent['winsserver'][] = $pconfig['wins1'];
        }
        if (!empty($pconfig['wins2'])) {
            $mapent['winsserver'][] = $pconfig['wins2'];
        }

        $mapent['dnsserver'] = array();
        if (!empty($pconfig['dns1'])) {
            $mapent['dnsserver'][] = $_POST['dns1'];
        }
        if (!empty($pconfig['dns2'])) {
            $mapent['dnsserver'][] = $_POST['dns2'];
        }

        $mapent['ntpserver'] = array();
        if (!empty($pconfig['ntp1'])) {
            $mapent['ntpserver'][] = $pconfig['ntp1'];
        }
        if (!empty($pconfig['ntp2'])) {
            $mapent['ntpserver'][] = $pconfig['ntp2'];
        }

        if (isset($id)) {
            $a_maps[$id] = $mapent;
        } else {
            $a_maps[] = $mapent;
        }

        usort($config['dhcpd'][$if]['staticmap'], function ($a, $b) {
            return ipcmp($a['ipaddr'], $b['ipaddr']);
        });

        write_config();

        if (isset($config['dhcpd'][$if]['enable'])) {
            mark_subsystem_dirty('staticmaps');
            mark_subsystem_dirty('hosts');
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
  function show_ddns_config() {
    $("#showddnsbox").hide();
    $("#showddns").show();
  }

  function show_ntp_config() {
    $("#showntpbox").hide();
    $("#showntp").show();
  }

  function show_tftp_config() {
    $("#showtftpbox").hide();
    $("#showtftp").show();
  }
//]]>
</script>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="content-box">
          <form method="post" name="iform" id="iform">
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><strong><?=gettext("Static DHCP Mapping");?></strong></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_mac" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MAC address");?></td>
                  <td>
                    <input name="mac" id="mac" type="text" value="<?=$pconfig['mac'];?>" />
<?php
                    $ip = getenv('REMOTE_ADDR');
                    $mac = `/usr/sbin/arp -an | grep {$ip} | /usr/bin/head -n1 | /usr/bin/cut -d" " -f4`;
                    $mac = str_replace("\n","",$mac);?>
                    <a onclick="$('#mac').val('<?=$mac?>');" href="#"><?=gettext("Copy my MAC address");?></a>
                    <div class="hidden" data-for="help_for_mac">
                      <?=gettext("Enter a MAC address in the following format: "."xx:xx:xx:xx:xx:xx");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Client identifier");?></td>
                  <td>
                    <input name="cid" type="text" value="<?=$pconfig['cid'];?>" />
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_ipaddr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP address");?></td>
                  <td>
                    <input name="ipaddr" type="text" value="<?=$pconfig['ipaddr'];?>" />
                    <div class="hidden" data-for="help_for_ipaddr">
                      <?=gettext("If an IPv4 address is entered, the address must be within the interface subnet.");?>
                      <br />
                      <?=gettext("If no IPv4 address is given, one will be dynamically allocated from the pool.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_hostname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hostname");?></td>
                  <td>
                    <input name="hostname" type="text" value="<?=$pconfig['hostname'];?>" />
                    <div class="hidden" data-for="help_for_hostname">
                      <?=gettext("Name of the host, without domain part.");?>
                      <?=gettext("If no IP address is given above, hostname will not be visible to DNS services with lease registration enabled.");?>
                    </div>
                  </td>
                </tr>
<?php
                if (isset($config['dhcpd'][$if]['netboot'])):?>
                <tr>
                  <td><a id="help_for_filename" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Netboot Filename') ?></td>
                  <td>
                    <input name="filename" type="text" id="filename" size="20" value="<?=$pconfig['filename'];?>" />
                    <div class="hidden" data-for="help_for_filename">
                      <?= gettext('Name of the file that should be loaded when this host boots off of the network, overrides setting on main page.') ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_rootpath" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Root Path') ?></td>
                  <td>
                    <input name="rootpath" type="text" value="<?=$pconfig['rootpath'];?>" />
                    <div class="hidden" data-for="help_for_rootpath">
                      <?= gettext("Enter the root-path-string, overrides setting on main page.") ?>
                    </div>
                  </td>
                </tr>
<?php
                endif;?>
                <tr>
                  <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                  <td>
                    <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" data-for="help_for_descr">
                      <?=gettext("You may enter a description here for your reference (not parsed).");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_arp_table_static_entry" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ARP Table Static Entry");?></td>
                  <td>
                    <input name="arp_table_static_entry" id="arp_table_static_entry" type="checkbox" value="yes" <?=!empty($pconfig['arp_table_static_entry']) ? "checked=\"checked\"" : ""; ?> />
                    <div class="hidden" data-for="help_for_arp_table_static_entry">
                      <?=gettext('Create a static ARP table entry for this MAC and IP address pair.');?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WINS servers");?></td>
                  <td>
                    <input name="wins1" type="text" value="<?=$pconfig['wins1'];?>" /><br />
                    <input name="wins2" type="text" value="<?=$pconfig['wins2'];?>" />
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS servers");?></td>
                  <td>
                    <input name="dns1" type="text" value="<?=$pconfig['dns1'];?>" /><br/>
                    <input name="dns2" type="text" value="<?=$pconfig['dns2'];?>" />
                    <div class="hidden" data-for="help_for_dns">
                      <?= gettext('Leave blank to use the system default DNS servers: This interface IP address if a DNS service is enabled or the configured global DNS servers.') ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_gateway" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Gateway");?></td>
                  <td>
                    <input name="gateway" type="text" value="<?=$pconfig['gateway'];?>" />
                    <div class="hidden" data-for="help_for_gateway">
                      <?=gettext('The default is to use the IP on this interface of the firewall as the gateway. '.
                                 'Specify an alternate gateway here if this is not the correct gateway for your network. ' .
                                 'Type "none" for no gateway assignment.');?>
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
                    <input name="domainsearchlist" type="text" id="domainsearchlist" size="20" value="<?=$pconfig['domainsearchlist'];?>" />
                    <div class="hidden" data-for="help_for_domainsearchlist">
                      <?=gettext("The DHCP server can optionally provide a domain search list. Use the semicolon character as separator.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_defaultleasetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Default lease time");?> (<?=gettext("seconds");?>)</td>
                  <td>
                    <input name="defaultleasetime" type="text" id="deftime" size="10" value="<?=$pconfig['defaultleasetime'];?>" />
                    <div class="hidden" data-for="help_for_defaultleasetime">
                      <?=gettext("This is used for clients that do not ask for a specific " ."expiration time."); ?><br />
                      <?=gettext("The default is 7200 seconds.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_maxleasetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Maximum lease time");?> (<?=gettext("seconds");?>)</td>
                  <td>
                    <input name="maxleasetime" type="text" value="<?=$pconfig['maxleasetime'];?>" />
                    <div class="hidden" data-for="help_for_maxleasetime">
                      <?=gettext("This is the maximum lease time for clients that ask"." for a specific expiration time."); ?><br />
                      <?=gettext("The default is 86400 seconds.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Dynamic DNS");?></td>
                  <td>
                    <div id="showddnsbox">
                      <input type="button" onclick="show_ddns_config()" class="btn btn-xs btn-default" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show Dynamic DNS");?>
                    </div>
                    <div id="showddns" style="display:none">
                      <input type="checkbox" value="yes" name="ddnsupdate" id="ddnsupdate" <?=!empty($pconfig['ddnsupdate']) ? "checked=\"checked\"" : ""; ?> />
                      <b><?=gettext("Enable registration of DHCP client names in DNS.");?></b><br />
                      <?=gettext("Note: Leave blank to disable dynamic DNS registration.");?><br />
                      <?=gettext("Enter the dynamic DNS domain which will be used to register client names in the DNS server.");?>
                      <input name="ddnsdomain" type="text" id="ddnsdomain" size="20" value="<?=$pconfig['ddnsdomain'];?>" />
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("NTP servers");?></td>
                  <td>
                    <div id="showntpbox">
                      <input type="button" onclick="show_ntp_config()" class="btn btn-xs btn-default" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show NTP configuration");?>
                    </div>
                    <div id="showntp" style="display:none">
                      <input name="ntp1" type="text" id="ntp1" size="20" value="<?=$pconfig['ntp1'];?>" /><br />
                      <input name="ntp2" type="text" id="ntp2" size="20" value="<?=$pconfig['ntp2'];?>" />
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("TFTP server");?></td>
                  <td>
                    <div id="showtftpbox">
                      <input type="button" onclick="show_tftp_config()" class="btn btn-xs btn-default" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show TFTP configuration");?>
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
                  <td></td>
                  <td>
                    <input name="Submit" type="submit" class="formbtn btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                    <input type="button" class="formbtn btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/services_dhcp.php?if=<?= html_safe($if) ?>'" />
<?php
                  if (isset($id)): ?>
                    <input name="id" type="hidden" value="<?=$id;?>" />
<?php
                  endif; ?>
                    <input name="if" type="hidden" value="<?=$if;?>" />
                  </td>
                </tr>
              </table>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
