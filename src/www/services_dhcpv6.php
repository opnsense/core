<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
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

function reconfigure_dhcpd()
{
    system_hosts_generate();
    clear_subsystem_dirty('hosts');
    dhcpd_dhcp_configure(false, 'inet6');
    clear_subsystem_dirty('staticmaps');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // handle identifiers and action
    if (!empty($_GET['if']) && !empty($config['interfaces'][$_GET['if']])) {
        $if = $_GET['if'];
    } else {
        /* if no interface is provided this invoke is invalid */
        header(url_safe('Location: /index.php'));
        exit;
    }

    $pconfig = array();

    if (!empty($config['dhcpdv6'][$if]['range'])) {
        $pconfig['range_from'] = $config['dhcpdv6'][$if]['range']['from'];
        $pconfig['range_to'] = $config['dhcpdv6'][$if]['range']['to'];
    }
    if (!empty($config['dhcpdv6'][$if]['prefixrange'])) {
        $pconfig['prefixrange_from'] = $config['dhcpdv6'][$if]['prefixrange']['from'];
        $pconfig['prefixrange_to'] = $config['dhcpdv6'][$if]['prefixrange']['to'];
        $pconfig['prefixrange_length'] = $config['dhcpdv6'][$if]['prefixrange']['prefixlength'];
    }
    $config_copy_fieldsnames = array('defaultleasetime', 'maxleasetime', 'domain', 'domainsearchlist', 'ddnsdomain',
        'ddnsdomainprimary', 'ddnsdomainkeyname', 'ddnsdomainkey', 'ddnsdomainalgorithm', 'bootfile_url', 'netmask',
        'numberoptions', 'dhcpv6leaseinlocaltime', 'staticmap', 'minsecs');
    foreach ($config_copy_fieldsnames as $fieldname) {
        if (isset($config['dhcpdv6'][$if][$fieldname])) {
            $pconfig[$fieldname] = $config['dhcpdv6'][$if][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
    // handle booleans
    $pconfig['enable'] = isset($config['dhcpdv6'][$if]['enable']);
    $pconfig['ddnsupdate'] = isset($config['dhcpdv6'][$if]['ddnsupdate']);
    $pconfig['netboot'] = isset($config['dhcpdv6'][$if]['netboot']);

    // handle arrays
    $pconfig['staticmap'] = empty($pconfig['staticmap']) ? array() : $pconfig['staticmap'];
    $pconfig['numberoptions'] = empty($pconfig['numberoptions']) ? array() : $pconfig['numberoptions'];
    $pconfig['dns1'] = !empty($config['dhcpdv6'][$if]['dnsserver'][0]) ? $config['dhcpdv6'][$if]['dnsserver'][0] : "";
    $pconfig['dns2'] = !empty($config['dhcpdv6'][$if]['dnsserver'][1]) ? $config['dhcpdv6'][$if]['dnsserver'][1] : "";
    $pconfig['ntp1'] = !empty($config['dhcpdv6'][$if]['ntpserver'][0]) ? $config['dhcpdv6'][$if]['ntpserver'][0] : "";
    $pconfig['ntp2'] = !empty($config['dhcpdv6'][$if]['ntpserver'][1]) ? $config['dhcpdv6'][$if]['ntpserver'][1] : "";

    // backward compatibility: migrate 'domain' to 'domainsearchlist'
    if (empty($pconfig['domainsearchlist'])) {
        $pconfig['domainsearchlist'] = $pconfig['domain'];
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // handle identifiers and actions
    if (!empty($_POST['if']) && !empty($config['interfaces'][$_POST['if']])) {
        $if = $_POST['if'];
    }
    if (!empty($_POST['act'])) {
        $act = $_POST['act'];
    } else {
        $act = null;
    }
    $pconfig = $_POST;
    $input_errors = array();

    if (isset($pconfig['submit'])) {
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

        if (!empty($pconfig['prefixrange_from']) && !is_ipaddrv6($pconfig['prefixrange_from']) ||
            !empty($pconfig['prefixrange_to']) && !is_ipaddrv6($pconfig['prefixrange_to'])) {
            $input_errors[] = gettext("A valid prefix range must be specified.");
        }
        if (!empty($pconfig['range_from']) && empty($pconfig['range_to']) ||
            empty($pconfig['range_from']) && !empty($pconfig['range_to'])) {
            $input_errors[] = gettext("A valid range must be specified.");
        } elseif (!empty($pconfig['range_from']) && !is_ipaddrv6($pconfig['range_from']) ||
            !empty($pconfig['range_to']) && !is_ipaddrv6($pconfig['range_to'])) {
            $input_errors[] = gettext("A valid range must be specified.");
        } else {
            /* Disallow a range that includes the virtualip */
            if (!empty($config['virtualip']['vip'])) {
                foreach($config['virtualip']['vip'] as $vip) {
                    if ($vip['interface'] == $if) {
                        if (!empty($vip['subnetv6']) && is_inrange_v6($vip['subnetv6'], $pconfig['range_from'], $pconfig['range_to'])) {
                            $input_errors[] = sprintf(gettext("The subnet range cannot overlap with virtual IPv6 address %s."),$vip['subnetv6']);
                        }
                    }
                }
            }
        }

        if (!empty($pconfig['gateway']) && !is_ipaddrv6($pconfig['gateway'])) {
            $input_errors[] = gettext("A valid IPv6 address must be specified for the gateway.");
        }
        if ((!empty($pconfig['dns1']) && !is_ipaddrv6($pconfig['dns1'])) || (!empty($pconfig['dns2']) && !is_ipaddrv6($pconfig['dns2']))) {
            $input_errors[] = gettext("A valid IPv6 address must be specified for the primary/secondary DNS servers.");
        }

        if (!empty($pconfig['defaultleasetime']) && (!is_numeric($pconfig['defaultleasetime']) || ($pconfig['defaultleasetime'] < 60))) {
            $input_errors[] = gettext("The default lease time must be at least 60 seconds.");
        }
        if (!empty($pconfig['maxleasetime']) && (!is_numeric($pconfig['maxleasetime']) || ($pconfig['maxleasetime'] < 60) || ($pconfig['maxleasetime'] <= $_POST['defaultleasetime']))) {
            $input_errors[] = gettext("The maximum lease time must be at least 60 seconds and higher than the default lease time.");
        }
        if (!empty($pconfig['minsecs']) && (!is_numeric($pconfig['minsecs']) || ($pconfig['minsecs'] < 0) || ($pconfig['minsecs'] > 255))) {
            $input_errors[] = gettext("The response delay must be at least 0 and no more than 255 seconds.");
        }
        if (!empty($pconfig['ddnsdomain']) && !is_domain($pconfig['ddnsdomain'])) {
            $input_errors[] = gettext("A valid domain name must be specified for the dynamic DNS registration.");
        }
        if (!empty($pconfig['ddnsdomainprimary']) && !is_ipaddrv6($pconfig['ddnsdomainprimary'])) {
            $input_errors[] = gettext("A valid primary domain name server IPv6 address must be specified for the dynamic domain name.");
        }
        if (!empty($pconfig['ddnsdomainkey']) && base64_encode(base64_decode($pconfig['ddnsdomainkey'], true)) !== $pconfig['ddnsdomainkey']) {
            $input_errors[] = gettext('You must specify a Base64-encoded domain key.');
        }
        if ((!empty($pconfig['ddnsdomainkey']) && empty($pconfig['ddnsdomainkeyname'])) ||
          (!empty($pconfig['ddnsdomainkeyname']) && empty($pconfig['ddnsdomainkey']))) {
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

        if ((!empty($pconfig['ntp1']) && !is_ipaddrv6($pconfig['ntp1'])) || (!empty($pconfig['ntp2']) && !is_ipaddrv6($pconfig['ntp2']))) {
            $input_errors[] = gettext("A valid IPv6 address must be specified for the primary/secondary NTP servers.");
        }
        if (!empty($pconfig['bootfile_url']) && !is_URL($pconfig['bootfile_url'])) {
            $input_errors[] = gettext("A valid URL must be specified for the network bootfile.");
        }

        if (count($input_errors) == 0) {
            /* make sure the range lies within the current subnet */
            list ($ifcfgip, $ifcfgsn) = explode('/', find_interface_networkv6(get_real_interface($if, 'inet6'), false));
            $subnet_start = gen_subnetv6($ifcfgip, $ifcfgsn);
            $subnet_end = gen_subnetv6_max($ifcfgip, $ifcfgsn);

            $range_from = $pconfig['range_from'];
            $range_to = $pconfig['range_to'];

            if (isset($config['interfaces'][$if]['dhcpd6track6allowoverride'])) {
                $range_from = make_ipv6_64_address($ifcfgip, $pconfig['range_from']);
                $range_to = make_ipv6_64_address($ifcfgip, $pconfig['range_to']);
            }

            if (!empty($pconfig['range_from']) && !empty($pconfig['range_to'])) {
                if (is_ipaddrv6($ifcfgip) && !empty($pconfig['range_from']) && !empty($pconfig['range_to'])) {
                    if ((!is_inrange_v6($range_from, $subnet_start, $subnet_end)) ||
                        (!is_inrange_v6($range_to, $subnet_start, $subnet_end))) {
                        $input_errors[] = gettext("The specified range lies outside of the current subnet.");
                    }
                }

                /* "from" cannot be higher than "to" */
                if (inet_pton($pconfig['range_from']) > inet_pton($pconfig['range_to'])) {
                    $input_errors[] = gettext("The range is invalid (first element higher than second element).");
                }

                /* Verify static mappings do not overlap:
                   - available DHCP range
                   - prefix delegation range (FIXME: still need to be completed) */
                $dynsubnet_start = inet_pton($pconfig['range_from']);
                $dynsubnet_end = inet_pton($pconfig['range_to']);
                if (!empty($config['dhcpdv6'][$if]['staticmap'])) {
                    foreach ($config['dhcpdv6'][$if]['staticmap'] as $map) {
                        if (!empty($map['ipaddrv6']) && inet_pton($map['ipaddrv6']) > $dynsubnet_start && inet_pton($map['ipaddrv6']) < $dynsubnet_end) {
                            $input_errors[] = sprintf(gettext("The DHCP range cannot overlap any static DHCP mappings."));
                            break;
                        }
                    }
                }
            }

            /* make sure that the DHCP Relay isn't enabled on this interface */
            if (isset($config['dhcrelay'][$if]['enable'])) {
                $input_errors[] = sprintf(gettext("You must disable the DHCP relay on the %s interface before enabling the DHCP server."),
                    !empty($config['interfaces'][$if]['descr']) ? htmlspecialchars($config['interfaces'][$if]['descr']) : strtoupper($if));
            }
        }

        if (count($input_errors) == 0) {
            config_read_array('dhcpdv6', $if);
            $dhcpdconf = array();

            // simple 1-on-1 copy
            $config_copy_fieldsnames = array('defaultleasetime', 'maxleasetime', 'netmask', 'domainsearchlist',
              'ddnsdomain', 'ddnsdomainprimary', 'ddnsdomainkeyname', 'ddnsdomainkey', 'ddnsdomainalgorithm', 'bootfile_url',
              'dhcpv6leaseinlocaltime', 'minsecs');
            foreach ($config_copy_fieldsnames as $fieldname) {
                if (!empty($pconfig[$fieldname])) {
                    $dhcpdconf[$fieldname] = $pconfig[$fieldname];
                }
            }

            $dhcpdv6_enable_changed = !empty($pconfig['enable']) != !empty($config['dhcpdv6'][$if]['enable']);

            // boolean types
            $dhcpdconf['netboot'] = !empty($pconfig['netboot']);
            $dhcpdconf['enable'] = !empty($pconfig['enable']);
            $dhcpdconf['ddnsupdate'] = !empty($pconfig['ddnsupdate']);

            // array types
            $dhcpdconf['range'] = array();
            $dhcpdconf['range']['from'] = $pconfig['range_from'];
            $dhcpdconf['range']['to'] = $pconfig['range_to'];
            $dhcpdconf['prefixrange'] = array();
            $dhcpdconf['prefixrange']['from'] = $pconfig['prefixrange_from'];
            $dhcpdconf['prefixrange']['to'] = $pconfig['prefixrange_to'];
            $dhcpdconf['prefixrange']['prefixlength'] = $pconfig['prefixrange_length'];
            $dhcpdconf['dnsserver'] = array();
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
            $dhcpdconf['numberoptions'] = $pconfig['numberoptions'];

            // copy structures back in
            foreach (array('staticmap') as $fieldname) {
                if (!empty($config['dhcpdv6'][$if][$fieldname])) {
                    $dhcpdconf[$fieldname] = $config['dhcpdv6'][$if][$fieldname];
                }
            }
            // router advertisement data lives in the same spot, copy
            foreach (array_keys($config['dhcpdv6'][$if]) as $fieldname) {
                if ((substr($fieldname, 0, 2) == 'ra' || substr($fieldname, 0, 3) == 'Adv') && !in_array($fieldname, array_keys($dhcpdconf))) {
                    $dhcpdconf[$fieldname] = $config['dhcpdv6'][$if][$fieldname];
                }
            }
            $config['dhcpdv6'][$if] = $dhcpdconf;

            write_config();

            reconfigure_dhcpd();
            if ($dhcpdv6_enable_changed) {
                filter_configure();
            }

            header(url_safe('Location: /services_dhcpv6.php?if=%s', array($if)));
            exit;
        }
    } elseif (isset($pconfig['apply'])) {
        reconfigure_dhcpd();
        header(url_safe('Location: /services_dhcpv6.php?if=%s', array($if)));
        exit;
    } elseif ($act == "del") {
        if (!empty($config['dhcpdv6'][$if]['staticmap'][$_POST['id']])) {
            unset($config['dhcpdv6'][$if]['staticmap'][$_POST['id']]);
            write_config();
            if (isset($config['dhcpdv6'][$if]['enable'])) {
                mark_subsystem_dirty('staticmapsv6');
                mark_subsystem_dirty('hosts');
            }
        }
        exit;
    }
}

$service_hook = 'dhcpd6';

legacy_html_escape_form_data($pconfig);

include("head.inc");

list ($wifcfgip, $networkv6) = interfaces_primary_address6($if);
$wifcfgsn = explode('/', $networkv6)[1];

if (isset($config['interfaces'][$if]['dhcpd6track6allowoverride'])) {
    $prefix_array = array();
    $prefix_array = explode(':', $wifcfgip);
    $prefix_array[4] = '0';
    $prefix_array[5] = '0';
    $prefix_array[6] = '0';
    $prefix_array[7] = '0';
    $wifprefix = Net_IPv6::compress(implode(':', $prefix_array));
    $pdlen = calculate_ipv6_delegation_length($config['interfaces'][$if]['track6-interface']) - 1;
}

?>
<body>
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
  });
</script>

<script>
  function show_shownumbervalue() {
    $("#shownumbervaluebox").hide();
    $("#shownumbervalue").show();
  }

  function show_ddns_config() {
    $("#showddnsbox").hide();
    $("#showddns").show();
  }
  function show_ntp_config() {
    $("#showntpbox").hide();
    $("#showntp").show();
  }

  function show_netboot_config() {
    $("#shownetbootbox").hide();
    $("#shownetboot").show();
  }
</script>


<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <?php if (is_subsystem_dirty('staticmaps')): ?><p>
        <?php print_info_box_apply(gettext("The static mapping configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
        <?php endif; ?>
        <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <form method="post" name="iform" id="iform">
              <?php if (!empty($config['dhcrelay6']['enabled'])): ?>
              <?php print_content_box(gettext('DHCP Relay is currently enabled. Cannot enable the DHCP Server service while the DHCP Relay is enabled on any interface.')); ?>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td style="width:22%"></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i>  <?=gettext("Enable");?></td>
                      <td>
                        <input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) echo "checked=\"checked\""; ?> />
                        <strong><?= sprintf(gettext("Enable DHCPv6 server on " . "%s " ."interface"),!empty($config['interfaces'][$if]['descr']) ? htmlspecialchars($config['interfaces'][$if]['descr']) : strtoupper($if));?></strong>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Subnet");?></td>
                      <td><?= gen_subnetv6($wifcfgip, $wifcfgsn) ?></td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Subnet mask");?></td>
                      <td><?= htmlspecialchars($wifcfgsn) ?> <?= gettext('bits') ?></td>
                    </tr>
<?php if (isset($config['interfaces'][$if]['dhcpd6track6allowoverride'])): ?>
                     <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Current LAN IPv6 prefix");?></td>
                      <td><?= htmlspecialchars($wifprefix) ?></td>
                    </tr>
<?php if ($pdlen >= 0): ?>
                     <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Available prefix delegation size");?></td>
                      <td><?= 64 - $pdlen ?></td>
                    </tr>
<?php endif ?>
<?php endif ?>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Available range");?></td>
                      <td>
<?php
                        $range_from = gen_subnetv6($wifcfgip, $wifcfgsn);
                        $range_from++;
                        $range_to = gen_subnetv6_max($wifcfgip, $wifcfgsn);?>
                        <?=$range_from;?> - <?=$range_to;?>
<?php if (isset($config['interfaces'][$if]['dhcpd6track6allowoverride'])): ?>
                        <br/>
                        <?= gettext('Prefix subnet will be prefixed to the available range.') ?>
<?php endif ?>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_range" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Range");?></td>
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
                        <div class="hidden" data-for="help_for_range">
                            <?= gettext("When using a static LAN address, the range should be entered using the full IPv6 address. " .
                            "When using a delegated LAN address, only enter the suffix part (i.e. ::1:2:3:4)."); ?>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_prefixrange" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Prefix Delegation Range");?></td>
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
                              <td><input name="prefixrange_from" type="text" id="range_from" value="<?=$pconfig['prefixrange_from'];?>" /></td>
                              <td><input name="prefixrange_to" type="text" id="range_to" value="<?=$pconfig['prefixrange_to'];?>" /> </td>
                            </tr>
                            <tr>
                              <td>
                                <strong><?=gettext("Prefix Delegation Size"); ?>:</strong>
                                <select name="prefixrange_length" id="prefixrange_length">
                                  <option value="48" <?=$pconfig['prefixrange_length'] == 48 ? "selected=\"selected\"" : ""; ?>>48</option>
                                  <option value="52" <?=$pconfig['prefixrange_length'] == 52 ? "selected=\"selected\"" : ""; ?>>52</option>
                                  <option value="56" <?=$pconfig['prefixrange_length'] == 56 ? "selected=\"selected\"" : ""; ?>>56</option>
                                  <option value="60" <?=$pconfig['prefixrange_length'] == 60 ? "selected=\"selected\"" : ""; ?>>60</option>
                                  <option value="62" <?=$pconfig['prefixrange_length'] == 62 ? "selected=\"selected\"" : ""; ?>>62</option>
                                  <option value="63" <?=$pconfig['prefixrange_length'] == 63 ? "selected=\"selected\"" : ""; ?>>63</option>
                                  <option value="64" <?=$pconfig['prefixrange_length'] == 64 ? "selected=\"selected\"" : ""; ?>>64</option>
                                </select>
                              </td>
                              <td></td>
                          </tbody>
                        </table>
                        <div class="hidden" data-for="help_for_prefixrange">
                          <?= gettext("You can define a Prefix range here for DHCP Prefix Delegation. This allows for assigning networks to subrouters. " .
                          "The start and end of the range must end on boundaries of the prefix delegation size."); ?>
                           <?= gettext("Ensure that any prefix delegation range does not overlap the LAN prefix range."); ?>
                          <br/><br/>
                          <?= gettext('The system does not check the validity of your entry against the selected mask - please refer to an online net ' .
                            'calculator to ensure you have entered a correct range if the dhcpd6 server fails to start.') ?>
<?php if (isset($config['interfaces'][$if]['dhcpd6track6allowoverride'])): ?>
                          <br/><br/>
                          <?= gettext('When using a tracked interface then please only enter the range itself. i.e. ::xx. For example, for a /60 subnet from ::20 to ::40.') ?>
<?php endif ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_dns" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS servers");?></td>
                      <td>
                        <input name="dns1" type="text" id="dns1" value="<?=$pconfig['dns1'];?>" /><br />
                        <input name="dns2" type="text" id="dns2" value="<?=$pconfig['dns2'];?>" />
                        <div class="hidden" data-for="help_for_dns">
                          <?= gettext('Leave blank to use the system default DNS servers: This interface IP address if a DNS service is enabled or the configured global DNS servers.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_domainsearchlist" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain search list");?></td>
                      <td>
                        <input name="domainsearchlist" type="text" id="domainsearchlist" value="<?=$pconfig['domainsearchlist'];?>" />
                        <div class="hidden" data-for="help_for_domainsearchlist">
                          <?=gettext("The default is to use the domain name of this system as the domain search list option provided by DHCPv6. You may optionally specify one or multiple domain(s) here. Use the semicolon character as separator. The first domain in this list will also be used for DNS registration of DHCP static mappings (if enabled).");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_defaultleasetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Default lease time");?> (<?=gettext("seconds");?>)</td>
                      <td>
                        <input name="defaultleasetime" type="text" value="<?=$pconfig['defaultleasetime'];?>" />
                        <div class="hidden" data-for="help_for_defaultleasetime">
                          <?=gettext("This is used for clients that do not ask for a specific expiration time."); ?><br />
                          <?=gettext("The default is 7200 seconds.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_maxleasetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Maximum lease time");?> (<?=gettext("seconds");?>)</td>
                      <td>
                        <input name="maxleasetime" type="text" id="maxleasetime" size="10" value="<?=$pconfig['maxleasetime'];?>" />
                        <div class="hidden" data-for="help_for_maxleasetime">
                          <?=gettext("This is the maximum lease time for clients that ask for a specific expiration time."); ?><br />
                          <?=gettext("The default is 86400 seconds.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_minsecs" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Response delay");?> (<?=gettext("seconds");?>)</td>
                      <td>
                       <input name="minsecs" type="text" value="<?=$pconfig['minsecs'];?>" />
                        <div class="hidden" data-for="help_for_minsecs">
                          <?=gettext("This is the minimum number of seconds since a client began trying to acquire a new lease before the DHCP server will respond to its request."); ?><br />
                          <?=gettext("The default is 0 seconds (no delay).");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_dhcpv6leaseinlocaltime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Time format change"); ?></td>
                      <td>
                        <input name="dhcpv6leaseinlocaltime" type="checkbox" id="dhcpv6leaseinlocaltime" value="yes" <?=!empty($pconfig['dhcpv6leaseinlocaltime']) ? "checked=\"checked\"" : ""; ?> />
                        <strong>
                          <?=gettext("Change DHCPv6 display lease time from UTC to local time."); ?>
                        </strong>
                        <div class="hidden" data-for="help_for_dhcpv6leaseinlocaltime">
                          <?=gettext("By default DHCPv6 leases are displayed in UTC time. By checking this box DHCPv6 lease time will be displayed in local time and set to time zone selected. This will be used for all DHCPv6 interfaces lease time."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Dynamic DNS");?></td>
                      <td>
                        <div id="showddnsbox">
                          <input type="button" onclick="show_ddns_config()" value="<?= html_safe(gettext('Advanced')) ?>" class="btn btn-xs btn-default"/> - <?=gettext("Show Dynamic DNS");?>
                        </div>
                        <div id="showddns" style="display:none">
                          <input type="checkbox" value="yes" name="ddnsupdate" id="ddnsupdate" <?php if ($pconfig['ddnsupdate']) echo " checked=\"checked\""; ?> />&nbsp;
                          <b><?=gettext("Enable registration of DHCP client names in DNS.");?></b><br />
                          <?=gettext("Note: Leave blank to disable dynamic DNS registration.");?><br />
                          <?=gettext("Enter the dynamic DNS domain which will be used to register client names in the DNS server.");?>
                          <input name="ddnsdomain" type="text" id="ddnsdomain" value="<?=$pconfig['ddnsdomain'];?>" />
                          <?=gettext("Enter the primary domain name server IP address for the dynamic domain name.");?><br />
                          <input name="ddnsdomainprimary" type="text" id="ddnsdomainprimary" size="20" value="<?=$pconfig['ddnsdomainprimary'];?>" />
                          <?=gettext("Enter the dynamic DNS domain key name which will be used to register client names in the DNS server.");?>
                          <input name="ddnsdomainkeyname" type="text" id="ddnsdomainkeyname" size="20" value="<?=$pconfig['ddnsdomainkeyname'];?>" />
                          <?=gettext("Enter the dynamic DNS domain key secret which will be used to register client names in the DNS server.");?>
                          <input name="ddnsdomainkey" type="text" id="ddnsdomainkey" size="20" value="<?=$pconfig['ddnsdomainkey'];?>" />
                          <?=gettext("Choose the dynamic DNS domain key algorithm.");?><br />
                          <select name='ddnsdomainalgorithm' id="ddnsdomainalgorithm" class="selectpicker">
<?php
                          foreach (array("hmac-md5", "hmac-sha512") as $algorithm) :?>
                              <option value="<?=$algorithm;?>" <?=$pconfig['ddnsdomainalgorithm'] == $algorithm ? "selected=\"selected\"" :"";?>>
                                <?=$algorithm;?>
                              </option>
<?php
                          endforeach; ?>
                          </select>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("NTP servers");?></td>
                      <td>
                        <div id="showntpbox">
                          <input type="button" onclick="show_ntp_config()" value="<?= html_safe(gettext('Advanced')) ?>" class="btn btn-xs btn-default"/> - <?=gettext("Show NTP configuration");?>
                        </div>
                        <div id="showntp" style="display:none">
                          <input name="ntp1" type="text" id="ntp1" value="<?=$pconfig['ntp1'];?>" /><br />
                          <input name="ntp2" type="text" id="ntp2" value="<?=$pconfig['ntp2'];?>" />
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable network booting");?></td>
                      <td>
                        <div id="shownetbootbox">
                          <input type="button" onclick="show_netboot_config()" value="<?= html_safe(gettext('Advanced')) ?>" class="btn btn-xs btn-default"/> - <?=gettext("Show Network booting");?>
                        </div>
                        <div id="shownetboot" style="display:none">
                          <input type="checkbox" value="yes" name="netboot" id="netboot" <?=!empty($pconfig['netboot']) ? 'checked="checked"' : ""; ?> />
                          <b><?=gettext("Enables network booting.");?></b>
                          <br/>
                          <?=gettext("Enter the Bootfile URL");?>
                          <input name="bootfile_url" type="text" id="bootfile_url" value="<?=$pconfig['bootfile_url'];?>" />
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Additional BOOTP/DHCP Options");?></td>
                      <td>
                        <div id="shownumbervaluebox">
                          <input type="button" onclick="show_shownumbervalue()" value="<?= html_safe(gettext('Advanced')) ?>" class="btn btn-xs btn-default"/> - <?=gettext("Show Additional BOOTP/DHCP Options");?>
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
                          <?= sprintf(gettext("Enter the DHCP option number and the value for each item you would like to include in the DHCP lease information. For a list of available options please visit this %sURL%s."),'<a href="https://www.iana.org/assignments/bootp-dhcp-parameters/" target="_blank">','</a>') ?>
                          </div>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                        <input name="if" type="hidden" value="<?=$if;?>" />
                        <input name="submit" type="submit" class="formbtn btn btn-primary" value="<?=html_safe(gettext('Save'));?>"/>
                      </td>
                    </tr>
                  </table>
                </div>
              </form>
            </div>
          </section>

          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
                <div class="table-responsive">
                  <table class="tabcont table table-striped" style="width:100%; border:0;">
                    <tr>
                      <th colspan="5"><?= gettext('DHCPv6 Static Mappings for this interface.') ?></th>
                    </tr>
                    <tr>
                      <td><?=gettext("DUID");?></td>
                      <td><?=gettext("IPv6 address");?></td>
                      <td><?=gettext("Hostname");?></td>
                      <td><?=gettext("Description");?></td>
                      <td class="text-nowrap">
                        <a href="services_dhcpv6_edit.php?if=<?=$if;?>" class="btn btn-default btn-xs"><i class="fa fa-plus fa-fw"></i></a>
                      </td>
                    </tr>
<?php
                    if (!empty($config['dhcpdv6'][$if]['staticmap'])):
                      $i = 0;
                      foreach ($config['dhcpdv6'][$if]['staticmap'] as $mapent): ?>
                    <tr>
                      <td><?=htmlspecialchars($mapent['duid']);?></td>
                      <td><?=isset($mapent['ipaddrv6']) ? htmlspecialchars($mapent['ipaddrv6']) : "";?></td>
                      <td><?=htmlspecialchars($mapent['hostname']);?></td>
                      <td><?=htmlspecialchars($mapent['descr']);?></td>
                      <td class="text-nowrap">
                        <a href="services_dhcpv6_edit.php?if=<?=$if;?>&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                        <button type="button" data-if="<?=$if;?>" data-id="<?=$i;?>" class="act_delete_static btn btn-xs btn-default"><i class="fa fa-trash fa-fw"></i></button>
                      </td>
                    </tr>
<?php
                      $i++;
                      endforeach;
                    endif; ?>
                  </table>
                </div>
<?php
                endif; ?>
              </div>
          </section>
        </div>
      </div>
    </section>
<?php include("foot.inc"); ?>
