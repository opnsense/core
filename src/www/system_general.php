<?php

/*
  Copyright (C) 2014-2015 Deciso B.V.
  Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("unbound.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");
require_once("services.inc");

function get_locale_list()
{
    $locales = array();

    /* first one is the default */
    $locales['en_US'] = gettext('English');
    $locales['zh_CN'] = gettext('Chinese (Simplified)');
    $locales['fr_FR'] = gettext('French');
    $locales['de_DE'] = gettext('German');
    $locales['ja_JP'] = gettext('Japanese');
    $locales['mn_MN'] = gettext('Mongolian');
    $locales['es_CO'] = gettext('Spanish');

    return $locales;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['dns1gw'] = null;
    $pconfig['dns2gw'] = null;
    $pconfig['dns3gw'] = null;
    $pconfig['dns4gw'] = null ;
    $pconfig['theme'] = null;
    $pconfig['language'] = null;
    $pconfig['timezone'] = "Etc/UTC";
    $pconfig['timeservers'] = "pool.ntp.org";
    $pconfig['mirror'] = 'default';
    $pconfig['flavour'] = 'default';

    $pconfig['hostname'] = $config['system']['hostname'];
    $pconfig['domain'] = $config['system']['domain'];
    if (isset($config['system']['dnsserver'])) {
        list($pconfig['dns1'],$pconfig['dns2'],$pconfig['dns3'],$pconfig['dns4']) = $config['system']['dnsserver'];
    } else {
        list($pconfig['dns1'],$pconfig['dns2'],$pconfig['dns3'],$pconfig['dns4']) = null;
    }
    foreach (array('dns1gw', 'dns2gw', 'dns3gw', 'dns4gw') as $dnsgwopt) {
        if (!empty($config['system'][$dnsgwopt])) {
            $pconfig[$dnsgwopt] = $config['system'][$dnsgwopt];
        } else {
            $pconfig[$dnsgwopt] = "none";
        }
    }

    $pconfig['dnsallowoverride'] = isset($config['system']['dnsallowoverride']);
    $pconfig['timezone'] = $config['system']['timezone'];
    $pconfig['timeservers'] = $config['system']['timeservers'];
    if (isset($config['system']['theme'])) {
        $pconfig['theme'] = $config['system']['theme'];
    }
    if (isset($config['system']['language'])) {
        $pconfig['language'] = $config['system']['language'];
    }
    $pconfig['dnslocalhost'] = isset($config['system']['dnslocalhost']);

    if (isset($config['system']['firmware']['mirror'])) {
        $pconfig['mirror'] = $config['system']['firmware']['mirror'];
    }

    if (isset($config['system']['firmware']['flavour'])) {
        $pconfig['flavour'] = $config['system']['firmware']['flavour'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['timezone']) && $pconfig['timezone'] <> $_POST['timezone']) {
        filter_pflog_start();
    }
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

    $ignore_posted_dnsgw = array();

    for ($dnscounter=1; $dnscounter<5; $dnscounter++){
      $dnsname="dns{$dnscounter}";
      $dnsgwname="dns{$dnscounter}gw";
      if (!empty($pconfig[$dnsname]) && !is_ipaddr($pconfig[$dnsname])) {
        $input_errors[] = gettext("A valid IP address must be specified for DNS server $dnscounter.");
      } elseif(!empty($pconfig[$dnsgwname]) && $pconfig[$dnsgwname] <> "none") {
            // A real gateway has been selected.
            if (is_ipaddr($pconfig[$dnsname])) {
                if ((is_ipaddrv4($pconfig[$dnsname])) && (validate_address_family($pconfig[$dnsname], $pconfig[$dnsgwname]) === false )) {
                    $input_errors[] = gettext("You can not specify IPv6 gateway '{$pconfig[$dnsgwname]}' for IPv4 DNS server '{$pconfig[$dnsname]}'");
                }
                if ((is_ipaddrv6($pconfig[$dnsname])) && (validate_address_family($pconfig[$dnsname], $pconfig[$dnsgwname]) === false )) {
                    $input_errors[] = gettext("You can not specify IPv4 gateway '{$pconfig[$dnsgwname]}' for IPv6 DNS server '{$pconfig[$dnsname]}'");
                }
            } else {
                // The user selected a gateway but did not provide a DNS address. Be nice and set the gateway back to "none".
                $ignore_posted_dnsgw[$dnsgwname] = true;
            }
      }
    }
    /* XXX cranky low-level call, please refactor */
    $direct_networks_list = explode(' ', filter_get_direct_networks_list(filter_generate_optcfg_array()));
    for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
        $dnsitem = "dns{$dnscounter}";
        $dnsgwitem = "dns{$dnscounter}gw";
        if (!empty($pconfig[$dnsgwitem])) {
            if(interface_has_gateway($pconfig[$dnsgwitem])) {
                foreach($direct_networks_list as $direct_network) {
                    if(ip_in_subnet($_POST[$dnsitem], $direct_network)) {
                        $input_errors[] = sprintf(gettext("You can not assign a gateway to DNS '%s' server which is on a directly connected network."),$pconfig[$dnsitem]);
                    }
                }
            }
        }
    }

    # it's easy to have a little too much whitespace in the field, clean it up for the user before processing.
    $pconfig['timeservers'] = preg_replace('/[[:blank:]]+/', ' ', $pconfig['timeservers']);
    $pconfig['timeservers'] = trim($pconfig['timeservers']);
    foreach (explode(' ', $pconfig['timeservers']) as $ts) {
        if (!is_domain($ts)) {
            $input_errors[] = gettext("A NTP Time Server name may only contain the characters a-z, 0-9, '-' and '.'.");
        }
    }

    if (count($input_errors) == 0) {
      $config['system']['hostname'] = $pconfig['hostname'];
      $config['system']['domain'] = $pconfig['domain'];
      $config['system']['timezone'] = $pconfig['timezone'];
      $config['system']['timeservers'] = strtolower($pconfig['timeservers']);
      $config['theme'] =  $pconfig['theme'];

      if (!empty($pconfig['language']) && $pconfig['language'] != $config['system']['language']) {
          $config['system']['language'] = $pconfig['language'];
          set_language($config['system']['language']);
      }

      if (!isset($config['system']['firmware'])) {
          $config['system']['firmware'] = array();
      }
      if ($pconfig['mirror'] == 'default') {
          if (isset($config['system']['firmware']['mirror'])) {
              /* default does not set anything for backwards compat */
              unset($config['system']['firmware']['mirror']);
          }
      } else {
          $config['system']['firmware']['mirror'] = $pconfig['mirror'];
      }
      if ($pconfig['flavour'] == 'default') {
          if (isset($config['system']['firmware']['flavour'])) {
              /* default does not set anything for backwards compat */
              unset($config['system']['firmware']['flavour']);
          }
      } else {
          $config['system']['firmware']['flavour'] = $pconfig['flavour'];
      }

      /* XXX - billm: these still need updating after figuring out how to check if they actually changed */
      $olddnsservers = $config['system']['dnsserver'];
      $config['system']['dnsserver'] = array();
      foreach (array('dns1', 'dns2', 'dns3', 'dns4') as $dnsopt) {
          if (!empty($pconfig[$dnsopt])) {
              $config['system']['dnsserver'][] = $pconfig[$dnsopt];
          }
      }
      $olddnsallowoverride = !empty($config['system']['dnsallowoverride']);

      $config['system']['dnsallowoverride'] = !empty($pconfig['dnsallowoverride']);

      if($pconfig['dnslocalhost'] == "yes") {
          $config['system']['dnslocalhost'] = true;
      } elseif (isset($config['system']['dnslocalhost'])) {
          unset($config['system']['dnslocalhost']);
      }

      /* which interface should the dns servers resolve through? */
      $outdnscounter = 0;
      for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
          $dnsname="dns{$dnscounter}";
          $dnsgwname="dns{$dnscounter}gw";
          $olddnsgwname = !empty($config['system'][$dnsgwname]) ? $config['system'][$dnsgwname] : "none" ;

          if ($ignore_posted_dnsgw[$dnsgwname]) {
              $thisdnsgwname = "none";
          } else {
              $thisdnsgwname = $pconfig[$dnsgwname];
          }

          // "Blank" out the settings for this index, then we set them below using the "outdnscounter" index.
          $config['system'][$dnsgwname] = "none";
          $pconfig[$dnsgwname] = "none";
          $pconfig[$dnsname] = "";

          if (!empty($_POST[$dnsname])) {
              // Only the non-blank DNS servers were put into the config above.
              // So we similarly only add the corresponding gateways sequentially to the config (and to pconfig), as we find non-blank DNS servers.
              // This keeps the DNS server IP and corresponding gateway "lined up" when the user blanks out a DNS server IP in the middle of the list.
              $outdnscounter++;
              $outdnsname="dns{$outdnscounter}";
              $outdnsgwname="dns{$outdnscounter}gw";
              $pconfig[$outdnsname] = $_POST[$dnsname];
              if(!empty($_POST[$dnsgwname])) {
                  $config['system'][$outdnsgwname] = $thisdnsgwname;
                  $pconfig[$outdnsgwname] = $thisdnsgwname;
              } else {
                  // Note: when no DNS GW name is chosen, the entry is set to "none", so actually this case never happens.
                  unset($config['system'][$outdnsgwname]);
                  $pconfig[$outdnsgwname] = "";
              }
          }
          if ($olddnsgwname != "none" && ($olddnsgwname != $thisdnsgwname || $olddnsservers[$dnscounter-1] != $_POST[$dnsname])) {
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

      system_hostname_configure();
      system_hosts_generate();
      system_resolvconf_generate();
      if (isset($config['dnsmasq']['enable'])) {
          services_dnsmasq_configure();
      } elseif (isset($config['unbound']['enable'])) {
          services_unbound_configure();
      }
      system_timezone_configure();
      system_firmware_configure();
      system_ntp_configure();

      if ($olddnsallowoverride != $config['system']['dnsallowoverride']) {
          configd_run("dns reload");
      }

      // Reload the filter - plugins might need to be run.
      filter_configure();

      $savemsg = get_std_save_message();
    }

    unset($ignore_posted_dnsgw);
}


legacy_html_escape_form_data($pconfig);
$pgtitle = array(gettext("System"),gettext("Settings"),gettext("General"));
include("head.inc");

?>

<body>
    <?php include("fbegin.inc"); ?>

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
      <div class="content-box tab-content">
        <form action="system_general.php" method="post">
          <table class="table table-striped">
            <tr>
              <td width="22%"><strong><?=gettext("System");?></strong></td>
              <td  width="78%" align="right">
                <small><?=gettext("full help"); ?> </small>
                <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_hostname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hostname"); ?></td>
              <td>
                <input name="hostname" type="text" size="40" value="<?=$pconfig['hostname'];?>" />
                <div class="hidden" for="help_for_hostname">
                  <?=gettext("Name of the firewall host, without domain part"); ?>
                  <br />
                  <?=gettext("e.g."); ?> <em><?=gettext("firewall");?></em>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain"); ?></td>
              <td>
                <input name="domain" type="text" value="<?=$pconfig['domain'];?>" />
                <div class="hidden" for="help_for_domain">
                  <?=gettext("Do not use 'local' as a domain name. It will cause local hosts running mDNS (avahi, bonjour, etc.) to be unable to resolve local hosts not running mDNS."); ?>
                  <br />
                  <?=gettext("e.g."); ?> <em><?=gettext("mycorp.com, home, office, private, etc."); ?></em>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_dnsservers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS servers"); ?></td>
              <td>
                <table class="table table-striped table-condensed">
                  <thead>
                    <tr>
                      <th><?=gettext("DNS Server"); ?></th>
                      <th><?=gettext("Use gateway"); ?></th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                    for ($dnscounter=1; $dnscounter<5; $dnscounter++):
                      $dnsgw = "dns{$dnscounter}gw";?>
                    <tr>
                      <td>
                        <input name="dns<?=$dnscounter;?>" type="text" value="<?=$pconfig['dns'.$dnscounter];?>" />
                      </td>
                      <td>
                        <select name='<?="dns{$dnscounter}gw";?>' class='selectpicker' data-width="200px">
                          <option value="none" <?=$pconfig[$dnsgw] == "none" ? "selected=\"selected\"" :"";?>>
                            <?=gettext("none");?>
                          </option>
<?php
                          foreach(return_gateways_array() as $gwname => $gwitem):
                            if(is_ipaddrv4(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && is_ipaddrv6($gwitem['gateway'])) {
                              continue;
                            }
                            if(is_ipaddrv6(lookup_gateway_ip_by_name($pconfig[$dnsgw])) && is_ipaddrv4($gwitem['gateway'])) {
                              continue;
                            }?>
                            <option value="<?=$gwname;?>" <?=$pconfig[$dnsgw] == $gwname ? "selected=\"selected\"" : "" ;?>>
                              <?=$gwname;?> - <?=$gwitem['friendlyiface'];?> - <?$gwitem['gateway'];?>
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
                <div class="hidden" for="help_for_dnsservers">
                  <?=gettext("Enter IP addresses to be used by the system for DNS resolution. " .
                  "These are also used for the DHCP service, DNS forwarder and for PPTP VPN clients."); ?>
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
                <input name="dnsallowoverride" type="checkbox" value="yes" <?=$pconfig['dnsallowoverride'] ? "checked=\"checked\"" : ""; ;?> />
                <strong>
                  <?=gettext("Allow DNS server list to be overridden by DHCP/PPP on WAN"); ?>
                </strong>
                <div class="hidden" for="help_for_dnsservers_opt">
                  <?php printf(gettext("If this option is set, %s will " .
                  "use DNS servers assigned by a DHCP/PPP server on WAN " .
                  "for its own purposes (including the DNS forwarder). " .
                  "However, they will not be assigned to DHCP and PPTP " .
                  "VPN clients."), $g['product_name']); ?>
                </div>
                <br/>
                <input name="dnslocalhost" type="checkbox" value="yes" <?=$pconfig['dnslocalhost'] ? "checked=\"checked\"" : ""; ?> />
                <strong>
                  <?=gettext("Do not use the DNS Forwarder as a DNS server for the firewall"); ?>
                </strong>
                <div class="hidden" for="help_for_dnsservers_opt">
                  <?=gettext("By default localhost (127.0.0.1) will be used as the first DNS server where the DNS Forwarder or DNS Resolver is enabled and set to listen on Localhost, so system can use the local DNS service to perform lookups. ".
                  "Checking this box omits localhost from the list of DNS servers."); ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_timezone" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Time zone"); ?></td>
              <td>
                <select name="timezone" id="timezone" class="selectpicker" data-style="btn-default" data-live-search="true">
<?php
                  foreach (get_zoneinfo() as $value):
                    if(strstr($value, "GMT")) {
                      continue;
                    }?>
                  <option value="<?=htmlspecialchars($value);?>" <?=$value == $pconfig['timezone'] ? "selected=\"selected\"" :"";?>>
                    <?=htmlspecialchars($value);?>
                  </option>
<?php
                  endforeach; ?>
                </select>
                <div class="hidden" for="help_for_timezone">
                  <?=gettext("Select the location closest to you"); ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_ntp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("NTP time server"); ?></td>
              <td width="78%" class="vtable">
                <input name="timeservers" type="text" class="formfld unknown" value="<?=$pconfig['timeservers'];?>" />
                <div class="hidden" for="help_for_ntp">
                  <?=gettext("Use a space to separate multiple hosts (only one " .
                  "required). Remember to set up at least one DNS server " .
                  "if you enter a host name here!"); ?>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_language" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Language");?></td>
              <td>
                <select name="language" class="selectpicker" data-style="btn-default" data-width="auto">
<?php
                  foreach (get_locale_list() as $lcode => $ldesc):?>
                  <option value="<?=$lcode;?>" <?=$lcode == $pconfig['language'] ? "selected=\"selected\"" : "";?>>
                    <?=$ldesc;?>
                  </option>
<?php
                  endforeach;?>
                </select>
                <div class="hidden" for="help_for_language">
                  <strong>
                    <?=gettext("Choose a language for the webConfigurator"); ?>
                  </strong>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_theme" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Theme"); ?></td>
              <td>
                <select name="theme" class="selectpicker" data-width="auto">
<?php
                  $curtheme = get_current_theme();
                  foreach (return_dir_as_array('/usr/local/opnsense/www/themes/') as $file):?>
                  <option <?=$file == $curtheme ? "selected=\"selected\"" : "";?>>
                    <?=$file;?>
                  </option>
<?php
                  endforeach; ?>
                </select>
                <div class="hidden" for="help_for_theme">
                  <strong>
                    <?=gettext("This will change the look and feel of"); ?>
                    <?=$g['product_name'];?>.
                  </strong>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_mirror" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firmware Mirror"); ?></td>
              <td>
                <select name="mirror" class="selectpicker" data-width="auto">
<?php
                foreach (get_firmware_mirrors() as $mcode => $mdesc):?>
                  <option value="<?=$mcode;?>" <?=$mcode == $pconfig['mirror'] ? "selected=\"selected\"":"";?>>
                    <?=$mdesc;?>
                  </option>
<?php
                 endforeach;?>
                </select>
                <div class="hidden" for="help_for_mirror">
                  <strong>
                    <?=gettext("Select an alternate firmware mirror."); ?>
                  </strong>
                </div>
              </td>
            </tr>
            <tr>
              <td><a id="help_for_flavour" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Firmware Flavour"); ?></td>
              <td width="78%" class="vtable">
                <select name="flavour" class="selectpicker" data-style="btn-default" data-width="auto">
<?php
                foreach (get_firmware_flavours() as $fcode => $fdesc):?>
                  <option value="<?=$fcode;?>" <?=$fcode == $pconfig['flavour'] ? "selected=\"selected\"" : "" ;?>>
                    <?=$fdesc;?>
                  </option>
<?php
                 endforeach;?>
                </select>
                <div class="hidden" for="help_for_flavour">
                  <strong>
                    <?=gettext("Select the firmware cryptography flavour."); ?>
                  </strong>
                </div>
              </td>
            </tr>
            <tr>
              <td></td>
              <td>
                <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
              </td>
            </tr>
          </table>
        </form>
      </div>
    </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
