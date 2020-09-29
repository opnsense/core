<?php

/*
 *  Copyright (C) 2016-2020 Franco Fichtner <franco@opnsense.org>
 *  Copyright (C) 2014-2016 Deciso B.V.
 *  Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 *  Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
 *  All rights reserved.
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
require_once("plugins.inc.d/dhcpd.inc");

function val_int_in_range($value, $min, $max) {
    return (((string)(int)$value) == $value) && $value >= $min && $value <= $max;
}

$advanced_options = array('AdvDefaultLifetime', 'AdvValidLifetime', 'AdvPreferredLifetime', 'AdvRDNSSLifetime', 'AdvDNSSLLifetime', 'AdvRouteLifetime', 'AdvLinkMTU');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['if']) && !empty($config['interfaces'][$_GET['if']])) {
        $if = $_GET['if'];
    } else {
        /* if no interface is provided this invoke is invalid */
        header(url_safe('Location: /index.php'));
        exit;
    }

    $pconfig = array();
    $config_copy_fieldsnames = array('ramode', 'rapriority', 'rainterface', 'ramininterval', 'ramaxinterval', 'radomainsearchlist');
    $config_copy_fieldsnames = array_merge($advanced_options, $config_copy_fieldsnames);
    foreach ($config_copy_fieldsnames as $fieldname) {
        if (isset($config['dhcpdv6'][$if][$fieldname])) {
            $pconfig[$fieldname] = $config['dhcpdv6'][$if][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
    // boolean
    $pconfig['rasamednsasdhcp6'] = isset($config['dhcpdv6'][$if]['rasamednsasdhcp6']);
    $pconfig['radefault'] = empty($config['dhcpdv6'][$if]['ranodefault']) ? true : null;

    // defaults
    if (empty($pconfig['ramininterval'])) {
        $pconfig['ramininterval'] = 200;
    }
    if (empty($pconfig['ramaxinterval'])) {
        $pconfig['ramaxinterval'] = 600;
    }

    // arrays
    $pconfig['radns1'] = !empty($config['dhcpdv6'][$if]['radnsserver'][0]) ? $config['dhcpdv6'][$if]['radnsserver'][0] : null;
    $pconfig['radns2'] = !empty($config['dhcpdv6'][$if]['radnsserver'][1]) ? $config['dhcpdv6'][$if]['radnsserver'][1] : null;

    // csvs
    if (!empty($config['dhcpdv6'][$if]['raroutes'])) {
        $pconfig['raroutes'] = explode(',', $config['dhcpdv6'][$if]['raroutes']);
    } else {
        $pconfig['raroutes'] = array();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['if']) && !empty($config['interfaces'][$_POST['if']])) {
        $if = $_POST['if'];
    }

    /* input validation */
    $input_errors = array();
    $pconfig = $_POST;

    $pconfig['raroutes'] = array();
    foreach ($pconfig['route_address'] as $idx => $address) {
        if (!empty($address)) {
            $route = "{$address}/{$pconfig['route_bits'][$idx]}";
            if (!is_subnetv6($route)) {
                $input_errors[] = sprintf(gettext('An invalid subnet route was supplied: %s'), $route);
            }
            $pconfig['raroutes'][] = $route;
        }
    }

    if ((!empty($pconfig['radns1']) && !is_ipaddrv6($pconfig['radns1'])) || ($pconfig['radns2'] && !is_ipaddrv6($pconfig['radns2']))) {
        $input_errors[] = gettext("A valid IPv6 address must be specified for the primary/secondary DNS servers.");
    }
    if (!empty($pconfig['radomainsearchlist'])) {
        $domain_array=preg_split("/[ ;]+/",$pconfig['radomainsearchlist']);
        foreach ($domain_array as $curdomain) {
            if (!is_domain($curdomain)) {
                $input_errors[] = gettext("A valid domain search list must be specified.");
                break;
            }
        }
    }

    if (!val_int_in_range($pconfig['ramaxinterval'], 4, 1800)) {
        $input_errors[] = sprintf(gettext('Maximum interval must be between %s and %s seconds.'), 4, 1800);
    } else {
        if (!val_int_in_range($pconfig['ramininterval'], 3, intval($pconfig['ramaxinterval'] * 0.75))) {
            $input_errors[] = sprintf(gettext('Minimum interval must be between %s and %s seconds.'), 3, intval($pconfig['ramaxinterval'] * 0.75));
        }
        if (!empty($pconfig['AdvDefaultLifetime']) && !val_int_in_range($pconfig['AdvDefaultLifetime'], $pconfig['ramaxinterval'], 9000)) {
            $input_errors[] = sprintf(gettext('AdvDefaultLifetime must be between %s and %s seconds.'), $pconfig['ramaxinterval'], 9000);
        }
        if (!empty($pconfig['AdvValidLifetime']) && !val_int_in_range($pconfig['AdvValidLifetime'], 1, 4294967295)) {
            $input_errors[] = sprintf(gettext('AdvValidLifetime must be between %s and %s seconds.'),  1, 4294967295);
        }
        if (!empty($pconfig['AdvPreferredLifetime']) && !val_int_in_range($pconfig['AdvPreferredLifetime'], 1, 4294967295)) {
            $input_errors[] = sprintf(gettext('AdvPreferredLifetime must be between %s and %s seconds.'),  1, 4294967295);
        }
        if (!empty($pconfig['AdvRDNSSLifetime']) && !val_int_in_range($pconfig['AdvRDNSSLifetime'], $pconfig['ramaxinterval'], $pconfig['ramaxinterval'] * 2)) {
            $input_errors[] = sprintf(gettext('AdvRDNSSLifetime must be between %s and %s seconds.'),  $pconfig['ramaxinterval'], $pconfig['ramaxinterval'] * 2);
        }
        if (!empty($pconfig['AdvDNSSLLifetime']) && !val_int_in_range($pconfig['AdvDNSSLLifetime'], $pconfig['ramaxinterval'], $pconfig['ramaxinterval'] * 2)) {
            $input_errors[] = sprintf(gettext('AdvDNSSLLifetime must be between %s and %s seconds.'),  $pconfig['ramaxinterval'], $pconfig['ramaxinterval'] * 2);
        }
        if (!empty($pconfig['AdvRouteLifetime']) && !val_int_in_range($pconfig['AdvRouteLifetime'], 1, 4294967295)) {
            $input_errors[] = sprintf(gettext('AdvRouteLifetime must be between %s and %s seconds.'),  1, 4294967295);
        }
        $mtu_low = 1280;
        $mtu_high = 65535;
        if (!empty($pconfig['AdvLinkMTU']) && !val_int_in_range($pconfig['AdvLinkMTU'], $mtu_low, $mtu_high)) {
            $input_errors[] = sprintf(gettext('AdvLinkMTU must be between %s and %s bytes.'),  $mtu_low, $mtu_high);
        }
    }



    if (count($input_errors) == 0) {
        config_read_array('dhcpdv6', $if);

        $config['dhcpdv6'][$if]['ramode'] = $pconfig['ramode'];
        $config['dhcpdv6'][$if]['rapriority'] = $pconfig['rapriority'];
        $config['dhcpdv6'][$if]['ramininterval'] = $pconfig['ramininterval'];
        $config['dhcpdv6'][$if]['ramaxinterval'] = $pconfig['ramaxinterval'];

        if (!empty($pconfig['rainterface'])) {
            $config['dhcpdv6'][$if]['rainterface'] = $pconfig['rainterface'];
        } elseif (isset($config['dhcpdv6'][$if]['rainterface'])) {
            unset($config['dhcpdv6'][$if]['rainterface']);
        }

        # flipped in GUI on purpose
        if (empty($pconfig['radefault'])) {
            $config['dhcpdv6'][$if]['ranodefault'] = true;
        } elseif (isset($config['dhcpdv6'][$if]['ranodefault'])) {
            unset($config['dhcpdv6'][$if]['ranodefault']);
        }

        $config['dhcpdv6'][$if]['radomainsearchlist'] = $pconfig['radomainsearchlist'];
        $config['dhcpdv6'][$if]['radnsserver'] = array();
        if (!empty($pconfig['radns1'])) {
            $config['dhcpdv6'][$if]['radnsserver'][] = $pconfig['radns1'];
        }
        if ($pconfig['radns2']) {
            $config['dhcpdv6'][$if]['radnsserver'][] = $pconfig['radns2'];
        }
        $config['dhcpdv6'][$if]['rasamednsasdhcp6'] = !empty($pconfig['rasamednsasdhcp6']);

        if (count($pconfig['raroutes'])) {
            $config['dhcpdv6'][$if]['raroutes'] = implode(',', $pconfig['raroutes']);
        } elseif (isset($config['dhcpdv6'][$if]['raroutes'])) {
            unset($config['dhcpdv6'][$if]['raroutes']);
        }

        foreach ($advanced_options as $advopt) {
            if (isset($pconfig[$advopt]) && $pconfig[$advopt] != "") {
                $config['dhcpdv6'][$if][$advopt] = $pconfig[$advopt];
            } elseif (isset($config['dhcpdv6'][$if][$advopt])) {
                unset($config['dhcpdv6'][$if][$advopt]);
            }
        }

        write_config();
        dhcpd_radvd_configure();
        $savemsg = get_std_save_message();
    }
}

$service_hook = 'radvd';

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>

<script>
  $( document ).ready(function() {
    /**
     * Additional BOOTP/DHCP Options extenable table
     */
    function removeRow() {
        if ( $('#maintable > tbody > tr').length == 1 ) {
            $('#maintable > tbody > tr:last > td > input').each(function () { $(this).val(""); });
        } else {
            $(this).parent().parent().remove();
        }
    }
    // add new detail record
    function addRow() {
        // copy last row and reset values
        $('#maintable > tbody > tr:last > td > label').removeClass('act-addrow').addClass('act-removerow');
        $('#maintable > tbody > tr:last > td > label').unbind('click');
        $('#maintable > tbody > tr:last > td > label').click(removeRow);
        $('#maintable > tbody > tr:last > td > label > span:first').removeClass('fa-plus').addClass('fa-minus');
        $('#maintable > tbody').append('<tr>'+$('#maintable > tbody > tr:last').html()+'</tr>');
        $('#maintable > tbody > tr:last > td > input').each(function () { $(this).val(""); });
        $('#maintable > tbody > tr:last > td > label').removeClass('act-removerow').addClass('act-addrow');
        $('#maintable > tbody > tr:last > td > label').unbind('click');
        $('#maintable > tbody > tr:last > td > label').click(addRow);
        $('#maintable > tbody > tr:last > td > label > span:first').removeClass('fa-minus').addClass('fa-plus');
    }
    $(".act-removerow").click(removeRow);
    $(".act-addrow").click(addRow);
    if ($("#has_advanced").val() != "" ) {
       $(".advanced_opt").show();
    }
    $("#show_advanced_opt").click(function(e){
        e.preventDefault();
        $(".advanced_opt").show();
        $(this).closest('tr').hide();
    });
});
</script>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td style="width:22%"><a id="help_for_ramode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Router Advertisements");?></td>
                    <td style="width:78%">
                      <select name="ramode">
                        <option value="disabled" <?=$pconfig['ramode'] == "disabled" ? "selected=\"selected\"" : ""; ?> >
                          <?=gettext("Disabled");?>
                        </option>
                        <option value="router" <?=$pconfig['ramode'] == "router" ? "selected=\"selected\"" : ""; ?> >
                          <?=gettext("Router Only");?>
                        </option>
                        <option value="unmanaged" <?=$pconfig['ramode'] == "unmanaged" ? "selected=\"selected\"" : ""; ?> >
                          <?=gettext("Unmanaged");?>
                        </option>
                        <option value="managed" <?=$pconfig['ramode'] == "managed" ? "selected=\"selected\"" : ""; ?> >
                          <?=gettext("Managed");?>
                        </option>
                        <option value="assist" <?=$pconfig['ramode'] == "assist" ? "selected=\"selected\"" : ""; ?> >
                          <?=gettext("Assisted");?>
                        </option>
                        <option value="stateless" <?=$pconfig['ramode'] == "stateless" ? "selected=\"selected\"" : ""; ?> >
                          <?=gettext("Stateless");?>
                        </option>
                      </select>
                      <div class="hidden" data-for="help_for_ramode">
                        <?= gettext('Select which flags to set in Router Advertisements sent from this interface.') ?>
                        <?= gettext('Use "Router Only" to disable Stateless Address Autoconfiguration (SLAAC) and DHCPv6, "Unmanaged" for SLAAC (A flag), ' .
                            '"Managed" for Stateful DHCPv6 (M+O flags), "Assisted" for Stateful DHCPv6 and SLAAC (M+O+A flags), ' .
                            'or "Stateless" for Stateless DHCPv6 and SLAAC (O+A flags).') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_rapriority" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Router Priority");?></td>
                    <td>
                      <select name="rapriority" id="rapriority">
                        <option value="low" <?= $pconfig['rapriority'] == "low" ? "selected=\"selected\"" : ""; ?> >
                          <?=gettext("Low");?>
                        </option>
                        <option value="medium" <?= empty($pconfig['rapriority']) || $pconfig['rapriority'] == "medium" ? "selected=\"selected\"" : ""; ?>>
                          <?=gettext("Normal");?>
                        </option>
                        <option value="high" <?= $pconfig['rapriority'] == "high" ? "selected=\"selected\"" : ""; ?>>
                          <?=gettext("High");?>
                        </option>
                      </select>
                      <div class="hidden" data-for="help_for_rapriority">
                        <?= sprintf(gettext("Select the Priority for the Router Advertisement (RA) Daemon."))?>
                      </div>
                    </td>
                  </tr>
<?php
                    $carplist = get_configured_carp_interface_list();
                    $carplistif = array();
                    if (count($carplist) > 0) {
                      foreach ($carplist as $ifname => $vip) {
                        if ((preg_match("/^{$if}_/", $ifname)) && (is_ipaddrv6($vip)))
                          $carplistif[$ifname] = $vip;
                      }
                    } ?>
                  <tr>
                    <td><a id="help_for_rainterface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("RA Interface");?></td>
                    <td>
                      <select name="rainterface" id="rainterface">
                        <option value="" <?= empty($pconfig['rainterface']) ? 'selected="selected"' : '' ?>><?= strtoupper($if) . " (" . gettext('dynamic') . ")" ?></option>
                        <option value="static" <?= $pconfig['rainterface'] == 'static' ? 'selected="selected"' : '' ?>><?= strtoupper($if)  . " (" . gettext('static') . ")" ?></option>
<?php foreach ($carplistif as $ifname => $vip): ?>
                        <option value="<?= html_safe($ifname) ?>" <?= $pconfig['rainterface'] == $ifname ? 'selected="selected"' : '' ?>><?= strtoupper($ifname) . " ($vip)" ?></option>
<?php endforeach ?>
                      </select>
                      <div class="hidden" data-for="help_for_rainterface">
                        <?= sprintf(gettext("Select the Interface for the Router Advertisement (RA) Daemon."))?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Advertise Default Gateway') ?></td>
                    <td>
                      <input id="radefault" name="radefault" type="checkbox" value="yes" <?= !empty($pconfig['radefault']) ? 'checked="checked"' : '' ?>/>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_raroutes" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Advertise Routes') ?></td>
                    <td>
                      <table class="table table-striped table-condensed" id="maintable">
                        <thead>
                          <tr>
                            <th></th>
                            <th><?= gettext('Prefix') ?></th>
                            <th><?= gettext('Length') ?></th>
                          </tr>
                        </thead>
                        <tbody>
<?php
                        $pconfig['raroutes'][] = '';
                        foreach ($pconfig['raroutes'] as $item):
                          $parts = explode('/', $item);
                          if (count($parts) > 1) {
                              $sn_bits = intval($parts[1]);
                          } else {
                              $sn_bits = null;
                          }
                          $sn_address = $parts[0];
                          ?>
                          <tr>
                            <td>
<?php if (!empty($item)): ?>
                              <label class="act-removerow btn btn-default btn-xs">
                                <span class="fa fa-minus"></span>
                                <span class="sr-only"><?= gettext('Remove') ?></span>
                              </label>
<?php else: ?>
                              <label class="act-addrow btn btn-default btn-xs">
                                <span class="fa fa-plus"></span>
                                <span class="sr-only"><?= gettext('Add') ?></span>
                              </label>
<?php endif ?>
                            </td>
                            <td>
                              <input name="route_address[]" type="text" value="<?=$sn_address;?>" />
                            </td>
                            <td>
                              <select name="route_bits[]">
<?php for ($i = 128; $i >= 0; $i -= 1): ?>
                                <option value="<?= $i ?>" <?= $sn_bits === $i ? 'selected="selected"' : '' ?>><?= $i ?></option>
<?php endfor ?>
                              </select>
                            </td>
                          </tr>
<?php
                        endforeach ?>
                        </tbody>
                      </table>
                      <div class="hidden" data-for="help_for_raroutes">
                        <?= gettext('Routes are specified in CIDR format. The prefix of a route definition should be network prefix; it can be used to advertise more specific routes to the hosts.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS servers");?></td>
                    <td>
                      <input name="radns1" type="text" value="<?=$pconfig['radns1'];?>" /><br />
                      <input name="radns2" type="text" value="<?=$pconfig['radns2'];?>" />
                      <div class="hidden" data-for="help_for_radns">
                        <?= gettext('Leave blank to use the system default DNS servers: This interface IP address if a DNS service is enabled or the configured global DNS servers.') ?>
                      </div>
                      <br />
                      <input id="rasamednsasdhcp6" name="rasamednsasdhcp6" type="checkbox" value="yes" <?=!empty($pconfig['rasamednsasdhcp6']) ? "checked='checked'" : "";?> />
                      <strong><?= gettext("Use the DNS settings of the DHCPv6 server"); ?></strong>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_radomainsearchlist" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain search list");?></td>
                    <td>
                      <input name="radomainsearchlist" type="text" id="radomainsearchlist" size="28" value="<?=$pconfig['radomainsearchlist'];?>" />
                      <div class="hidden" data-for="help_for_radomainsearchlist">
                        <?=gettext("The default is to use the domain name of this system as the DNSSL option in Router Advertisements. You may optionally specify one or multiple domain(s) here. Use the semicolon character as separator.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_ramininterval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Minimum Interval') ?></td>
                    <td>
                      <input name="ramininterval" type="text" id="ramininterval" size="28" value="<?=$pconfig['ramininterval'];?>" />
                      <div class="hidden" data-for="help_for_ramininterval">
                        <?= gettext('The minimum time allowed between sending unsolicited multicast router advertisements from the interface, in seconds.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_ramaxinterval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Maximum Interval') ?></td>
                    <td>
                      <input name="ramaxinterval" type="text" id="ramaxinterval" size="28" value="<?=$pconfig['ramaxinterval'];?>" />
                      <div class="hidden" data-for="help_for_ramaxinterval">
                        <?= gettext('The maximum time allowed between sending unsolicited multicast router advertisements from the interface, in seconds.') ?>
                      </div>
                    </td>
                  </tr>
<?php
                  $has_advanced = false;
                  foreach ($advanced_options as $advopt):
                      $has_advanced = ($has_advanced || !empty($pconfig[$advopt]));?>
                  <tr style="display:none;" class="advanced_opt">
                    <td><i class="fa fa-info-circle text-muted"></i> <?=$advopt;?></td>
                    <td>
                      <input name="<?=$advopt;?>" type="text" id="<?=$advopt;?>" value="<?=!empty($pconfig[$advopt]) ? $pconfig[$advopt] :"" ;?>" />
                    </td>
                  </tr>
<?php
                  endforeach;
                  if (!$has_advanced):?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Advanced");?></td>
                    <td>
                      <button id="show_advanced_opt" class="btn btn-xs btn-default"><?= gettext('Show advanced options') ?></button>
                    </td>
                  </tr>
<?php
                  endif;?>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input id="has_advanced" type="hidden" value="<?=$has_advanced ? "X": "";?>">
                      <input name="if" type="hidden" value="<?=$if;?>" />
                      <input name="Submit" type="submit" class="formbtn btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
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
