<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
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

$all_intf_details = legacy_interfaces_details();
$a_gateways = (new \OPNsense\Routing\Gateways($all_intf_details))->gatewaysIndexedByName();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();

    if (isset($_GET['savemsg'])) {
        $savemsg = htmlspecialchars(gettext($_GET['savemsg']));
    }

    $pconfig['dnsallowoverride'] = isset($config['system']['dnsallowoverride']);
    if (!empty($config['system']['dnsallowoverride_exclude'])) {
        $pconfig['dnsallowoverride_exclude'] = explode(",", $config['system']['dnsallowoverride_exclude']);
    } else {
        $pconfig['dnsallowoverride_exclude'] = array();
    }
    $pconfig['dnslocalhost'] = isset($config['system']['dnslocalhost']);
    $pconfig['domain'] = $config['system']['domain'];
    $pconfig['hostname'] = $config['system']['hostname'];
    $pconfig['language'] = $config['system']['language'];
    $pconfig['prefer_ipv4'] = isset($config['system']['prefer_ipv4']);
    $pconfig['store_intermediate_certs'] = isset($config['system']['store_intermediate_certs']);
    $pconfig['theme'] = $config['theme'];
    $pconfig['timezone'] = empty($config['system']['timezone']) ? 'Etc/UTC' : $config['system']['timezone'];

    $pconfig['gw_switch_default'] = isset($config['system']['gw_switch_default']);

    for ($dnscounter = 1; $dnscounter < 9; $dnscounter++) {
        $dnsname = "dns{$dnscounter}";
        $pconfig[$dnsname] = !empty($config['system']['dnsserver'][$dnscounter - 1]) ? $config['system']['dnsserver'][$dnscounter - 1] : null;

        $dnsgwname= "dns{$dnscounter}gw";
        $pconfig[$dnsgwname] = !empty($config['system'][$dnsgwname]) ? $config['system'][$dnsgwname] : 'none';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "hostname domain");
    $reqdfieldsn = array(gettext("Hostname"),gettext("Domain"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['hostname']) && !is_hostname($pconfig['hostname'])) {
        $input_errors[] = gettext("The hostname may only contain the characters a-z, 0-9 and '-'.");
    }
    if (!empty($pconfig['domain']) && !is_domain($pconfig['domain'])) {
        $input_errors[] = gettext("The domain may only contain the characters a-z, 0-9, '-' and '.'.");
    }

    /* collect direct attached networks and static routes */
    $direct_networks_list = array();
    foreach ($all_intf_details as $ifname => $ifcnf) {
        foreach ($ifcnf['ipv4'] as $addr) {
            $direct_networks_list[] = gen_subnet($addr['ipaddr'], $addr['subnetbits']) . "/{$addr['subnetbits']}";
        }
        foreach ($ifcnf['ipv6'] as $addr) {
            $direct_networks_list[] = gen_subnetv6($addr['ipaddr'], $addr['subnetbits']) . "/{$addr['subnetbits']}";
        }
    }
    foreach (get_staticroutes() as $netent) {
        $direct_networks_list[] = $netent['network'];
    }

    for ($dnscounter = 1; $dnscounter < 9; $dnscounter++) {
        $dnsname = "dns{$dnscounter}";
        $dnsgwname = "dns{$dnscounter}gw";

        if (!empty($pconfig[$dnsname]) && !is_ipaddr($pconfig[$dnsname])) {
            $input_errors[] = sprintf(gettext('A valid IP address must be specified for DNS server "%s".'), $dnscounter);
            continue;
        }

        if (!empty($pconfig[$dnsgwname]) && $pconfig[$dnsgwname] != 'none') {
            if (is_ipaddr($pconfig[$dnsname])) {
                if (is_ipaddrv4($pconfig[$dnsname]) && $a_gateways[$pconfig[$dnsgwname]]['ipprotocol'] != 'inet') {
                    $input_errors[] = gettext("You can not specify IPv6 gateway '{$pconfig[$dnsgwname]}' for IPv4 DNS server '{$pconfig[$dnsname]}'");
                    continue;
                }
                if (is_ipaddrv6($pconfig[$dnsname]) && $a_gateways[$pconfig[$dnsgwname]]['ipprotocol'] != 'inet6') {
                    $input_errors[] = gettext("You can not specify IPv4 gateway '{$pconfig[$dnsgwname]}' for IPv6 DNS server '{$pconfig[$dnsname]}'");
                    continue;
                }
            } else {
                $input_errors[] = sprintf(gettext('A valid IP address must be specified for DNS server "%s".'), $dnscounter);
                continue;
            }

            $af = is_ipaddrv6($pconfig[$dnsname]) ? 'inet6' : 'inet';

            foreach ($direct_networks_list as $direct_network) {
                if ($af == 'inet' && !is_subnetv4($direct_network)) {
                    continue;
                } elseif ($af == 'inet6' && !is_subnetv6($direct_network)) {
                    continue;
                }
                if (ip_in_subnet($pconfig[$dnsname], $direct_network)) {
                      $input_errors[] = sprintf(gettext('You can not assign a gateway to DNS server "%s" which is on a directly connected network.'), $pconfig[$dnsname]);
                      break;
                }
            }
        }
    }

    if (count($input_errors) == 0) {
        $config['system']['domain'] = $pconfig['domain'];
        $config['system']['hostname'] = $pconfig['hostname'];
        $config['system']['language'] = $pconfig['language'];
        $config['system']['timezone'] = $pconfig['timezone'];
        $config['theme'] =  $pconfig['theme'];

        if (!empty($pconfig['prefer_ipv4'])) {
            $config['system']['prefer_ipv4'] = true;
        } elseif (isset($config['system']['prefer_ipv4'])) {
            unset($config['system']['prefer_ipv4']);
        }

        $config['system']['store_intermediate_certs'] = !empty($pconfig['store_intermediate_certs']);

        if (!empty($pconfig['dnsallowoverride'])) {
            $config['system']['dnsallowoverride'] = true;
            $config['system']['dnsallowoverride_exclude'] = empty($pconfig['dnsallowoverride_exclude']) ? "" : implode(",", $pconfig['dnsallowoverride_exclude']);
        } elseif (isset($config['system']['dnsallowoverride'])) {
            unset($config['system']['dnsallowoverride']);
            if (isset($config['system']['dnsallowoverride_exclude'])) {
                unset($config['system']['dnsallowoverride_exclude']);
            }
        }

        if ($pconfig['dnslocalhost'] == 'yes') {
            $config['system']['dnslocalhost'] = true;
        } elseif (isset($config['system']['dnslocalhost'])) {
            unset($config['system']['dnslocalhost']);
        }

        if (!empty($pconfig['gw_switch_default'])) {
            $config['system']['gw_switch_default'] = true;
        } elseif (isset($config['system']['gw_switch_default'])) {
            unset($config['system']['gw_switch_default']);
        }

        $olddnsservers = $config['system']['dnsserver'];
        $config['system']['dnsserver'] = array();

        $outdnscounter = 0;
        for ($dnscounter = 1; $dnscounter < 9; $dnscounter++) {
            $dnsname="dns{$dnscounter}";
            $dnsgwname="dns{$dnscounter}gw";
            $olddnsgwname = !empty($config['system'][$dnsgwname]) ? $config['system'][$dnsgwname] : 'none';
            $thisdnsgwname = $pconfig[$dnsgwname];

            if (!empty($pconfig[$dnsname])) {
                $config['system']['dnsserver'][] = $pconfig[$dnsname];
            }
            $config['system'][$dnsgwname] = "none";
            if (!empty($pconfig[$dnsgwname])) {
                // The indexes used to save the item don't have to correspond to the ones in the config, but since
                // we always redirect after save, the configuration content is read after a successfull change.
                $outdnscounter++;
                $outdnsgwname="dns{$outdnscounter}gw";
                $config['system'][$outdnsgwname] = $thisdnsgwname;
            }
            if ($olddnsgwname != "none" && ($olddnsgwname != $thisdnsgwname || $olddnsservers[$dnscounter-1] != $pconfig[$dnsname])) {
                // A previous DNS GW name was specified. It has now gone or changed, or the DNS server address has changed.
                // Remove the route. Later calls will add the correct new route if needed.
                if (is_ipaddrv4($olddnsservers[$dnscounter-1])) {
                    mwexec("/sbin/route delete " . escapeshellarg($olddnsservers[$dnscounter-1]));
                } else {
                    if (is_ipaddrv6($olddnsservers[$dnscounter-1])) {
                        mwexec("/sbin/route delete -inet6 " . escapeshellarg($olddnsservers[$dnscounter-1]));
                    }
                }
            }
        }

        write_config();

        /* time zone change first */
        system_timezone_configure();
        system_trust_configure();

        prefer_ipv4_or_ipv6();
        system_hostname_configure();
        system_hosts_generate();
        system_resolvconf_generate();
        plugins_configure('dns');
        plugins_configure('dhcp');
        filter_configure();

        header(url_safe('Location: /system_general.php?savemsg=%s', array('The changes have been applied successfully.')));
        exit;
    }
}

legacy_html_escape_form_data($a_gateways);
legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>
    <?php include("fbegin.inc"); ?>

<script>
//<![CDATA[
$( document ).ready(function() {
    // unhide advanced
    $("#dnsallowoverride").change(function(event){
        event.preventDefault();
        if ($("#dnsallowoverride").is(':checked')) {
            $("#dnsallowoverride_exclude").show();
        } else {
            $("#dnsallowoverride_exclude").hide();
        }
    });
    $("#dnsallowoverride").change();
});
//]]>
</script>
<!-- row -->
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
    if (isset($savemsg)) {
        print_info_box($savemsg);
    }
?>
    <section class="col-xs-12">
      <form method="post">
        <div class="content-box tab-content __mb">
          <table class="table table-striped opnsense_standard_table_form">
            <tr>
              <td style="width:22%"><strong><?= gettext('System') ?></strong></td>
              <td style="width:78%; text-align:right">
                <small><?=gettext("full help"); ?> </small>
                <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_hostname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hostname"); ?></td>
              <td>
                <input name="hostname" type="text" size="40" value="<?=$pconfig['hostname'];?>" />
                <div class="hidden" data-for="help_for_hostname">
                  <?=gettext("Name of the firewall host, without domain part"); ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain"); ?></td>
              <td>
                <input name="domain" type="text" value="<?=$pconfig['domain'];?>" />
                <div class="hidden" data-for="help_for_domain">
                  <?=gettext("Do not use 'local' as a domain name. It will cause local hosts running mDNS (avahi, bonjour, etc.) to be unable to resolve local hosts not running mDNS."); ?>
                  <br />
                  <?=sprintf(gettext("e.g. %smycorp.com, home, office, private, etc.%s"),'<em>','</em>') ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_timezone" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Time zone"); ?></td>
              <td>
                <select name="timezone" id="timezone" data-size="10" class="selectpicker" data-style="btn-default" data-live-search="true">
<?php
                  foreach (get_zoneinfo() as $value): ?>
                  <option value="<?=htmlspecialchars($value);?>" <?= $value == $pconfig['timezone'] ? 'selected="selected"' : '' ?>>
                    <?=htmlspecialchars($value);?>
                  </option>
<?php
                  endforeach; ?>
                </select>
                <div class="hidden" data-for="help_for_timezone">
                  <?=gettext("Select the location closest to you"); ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_language" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Language");?></td>
              <td>
                <select name="language" class="selectpicker" data-style="btn-default">
<?php
                  foreach (get_locale_list() as $lcode => $ldesc):?>
                  <option value="<?=$lcode;?>" <?= $lcode == $pconfig['language'] ? 'selected="selected"' : '' ?>>
                    <?=$ldesc;?>
                  </option>
<?php
                  endforeach;?>
                </select>
                <div class="hidden" data-for="help_for_language">
                  <?= gettext('Choose a language for the web GUI.') ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_theme" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Theme"); ?></td>
              <td>
                <select name="theme" class="selectpicker">
<?php
                foreach (glob('/usr/local/opnsense/www/themes/*', GLOB_ONLYDIR) as $file):
                  $file = basename($file);?>
                  <option <?= $file == $pconfig['theme'] ? 'selected="selected"' : '' ?>>
                    <?=$file;?>
                  </option>
<?php
                endforeach; ?>
                </select>
                <div class="hidden" data-for="help_for_theme">
                  <?= gettext('This will change the look and feel of the GUI.') ?>
                </div>
              </td>
            </tr>
          </table>
        </div>

        <div class="content-box tab-content __mb">
          <table class="table table-striped opnsense_standard_table_form">
            <tr>
              <td style="width:22%"><strong><?= gettext('Trust') ?></strong></td>
              <td style="width:78%"></td>
            </tr>
            <tr>
              <td><a id="help_for_trust_store_intermediate_certs" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Store intermediate"); ?></td>
              <td>
                <input name="store_intermediate_certs" type="checkbox" id="store_intermediate_certs" <?= !empty($pconfig['store_intermediate_certs']) ? "checked=\"checked\"" : "";?> />
                <div class="hidden" data-for="help_for_trust_store_intermediate_certs">
                  <?=gettext(
                    "Allow local defined intermediate certificate authorities to be used in the local trust store. ".
                    "We advise to only store root certificates to prevent cross signed ones causing breakage when included but expired later in the chain."
                  ); ?>
                </div>
              </td>
            </tr>
          </table>
        </div>


        <div class="content-box tab-content __mb">
          <table class="table table-striped opnsense_standard_table_form">
            <tr>
              <td style="width:22%"><strong><?= gettext('Networking') ?></strong></td>
              <td style="width:78%"></td>
            </tr>
            <tr>
              <td><a id="help_for_prefer_ipv4" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Prefer IPv4 over IPv6"); ?></td>
              <td>
                <input name="prefer_ipv4" type="checkbox" id="prefer_ipv4" value="yes" <?= !empty($pconfig['prefer_ipv4']) ? "checked=\"checked\"" : "";?> />
                <?=gettext("Prefer to use IPv4 even if IPv6 is available"); ?>
                <div class="hidden" data-for="help_for_prefer_ipv4">
                  <?=gettext("By default, if a hostname resolves IPv6 and IPv4 addresses ".
                                      "IPv6 will be used, if you check this option, IPv4 will be " .
                                      "used instead of IPv6."); ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_dnsservers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS servers"); ?></td>
              <td>
                <table class="table table-striped table-condensed">
                  <thead>
                    <tr>
                      <th style="width:350px;"><?=gettext("DNS Server"); ?></th>
                      <th><?=gettext("Use gateway"); ?></th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                    for ($dnscounter = 1; $dnscounter < 9; $dnscounter++):
                      $dnsgw = "dns{$dnscounter}gw";?>
                    <tr>
                      <td>
                        <input name="dns<?=$dnscounter;?>" type="text" value="<?=$pconfig['dns'.$dnscounter];?>" />
                      </td>
                      <td>
                        <select name='<?="dns{$dnscounter}gw";?>' class='selectpicker' data-size="10" data-width="200px">
                          <option value="none" <?= $pconfig[$dnsgw] == 'none' ? 'selected="selected"' : '' ?>>
                            <?=gettext("none");?>
                          </option>
<?php
                          foreach($a_gateways as $gwname => $gwitem):?>
                            <option value="<?=$gwname;?>" <?=$pconfig[$dnsgw] == $gwname ? 'selected="selected"' : '' ?>>
                              <?=$gwname;?> - <?=$gwitem['interface'];?> - <?=$gwitem['gateway'];?>
                            </option>
<?php
                             endforeach;?>

                          </select>
                        </td>
                      </tr>
<?php
                    endfor; ?>
                  </tbody>
                </table>
                <div class="hidden" data-for="help_for_dnsservers">
                  <?=gettext("Enter IP addresses to be used by the system for DNS resolution. " .
                  "These are also used for the DHCP service, DNS services and for PPTP VPN clients."); ?>
                  <br />
                  <br />
                  <?=gettext("In addition, optionally select the gateway for each DNS server. " .
                  "When using multiple WAN connections there should be at least one unique DNS server per gateway."); ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_dnsservers_opt" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS server options"); ?></td>
              <td>
                <input name="dnsallowoverride" id="dnsallowoverride" type="checkbox" value="yes" <?= $pconfig['dnsallowoverride'] ? 'checked="checked"' : '' ?>/>
                <?=gettext("Allow DNS server list to be overridden by DHCP/PPP on WAN"); ?>
                <div class="hidden" data-for="help_for_dnsservers_opt">
                  <?= gettext("If this option is set, DNS servers " .
                  "assigned by a DHCP/PPP server on WAN will be used " .
                  "for their own purposes (including the DNS services). " .
                  "However, they will not be assigned to DHCP clients. " .
                  "Since this option concerns all interfaces retrieving dynamic dns entries, you can exclude " .
                  "items from the list below.") ?>
                </div>
                <div id="dnsallowoverride_exclude" style="display:none">
                  <hr/>
                  <strong><?=gettext("Exclude interfaces");?></strong>
                  <br/>
                  <select name="dnsallowoverride_exclude[]" class="selectpicker" data-style="btn-default" data-live-search="true"  multiple="multiple">
<?php
                  foreach (legacy_config_get_interfaces(array('virtual' => false, "enable" => true)) as $iface => $ifcfg):?>
                    <option value="<?=$iface;?>" <?=in_array($iface, $pconfig['dnsallowoverride_exclude']) ? "selected='selected'" : "";?>>
                      <?= $ifcfg['descr'] ?>
                    </option>
<?php
                  endforeach;?>
                  </select>
                </div>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <input name="dnslocalhost" type="checkbox" value="yes" <?=$pconfig['dnslocalhost'] ? "checked=\"checked\"" : ""; ?> />
                <?= gettext('Do not use the local DNS service as a nameserver for this system') ?>
                <div class="hidden" data-for="help_for_dnsservers_opt">
                  <?=gettext("By default localhost (127.0.0.1) will be used as the first nameserver when e.g. Dnsmasq or Unbound is enabled, so system can use the local DNS service to perform lookups. ".
                  "Checking this box omits localhost from the list of DNS servers."); ?>
                </div>
              </td>
            </tr>
              <tr>
                <td><a id="help_for_gw_switch_default" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Gateway switching') ?></td>
                <td>
                  <input name="gw_switch_default" type="checkbox" id="gw_switch_default" value="yes" <?= !empty($pconfig['gw_switch_default']) ? 'checked="checked"' : '' ?> />
                  <?=gettext("Allow default gateway switching"); ?>
                  <div class="hidden" data-for="help_for_gw_switch_default">
                    <?= gettext('If the link where the default gateway resides fails switch the default gateway to another available one.') ?>
                  </div>
                </td>
              </tr>
          </table>
        </div>
        <div class="content-box tab-content">
          <table class="table table-striped opnsense_standard_table_form">
            <tr>
              <td style="width:22%"></td>
              <td>
                <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
              </td>
            </tr>
          </table>
        </div>
      </form>
    </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
