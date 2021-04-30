<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2010 Gabriel B. <gnoahb@gmail.com>
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

$a_ppps = &config_read_array('ppps', 'ppp');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // read form data
    $pconfig = array();
    if (isset($_GET['id']) && !empty($a_ppps[$_GET['id']])) {
        $id = $_GET['id'];
    }
    // plain 1-on-1 copy
    $copy_fields = array('ptpid', 'type', 'username', 'idletimeout', 'uptime', 'descr', 'simpin', 'pin-wait',
                        'apn', 'apnum', 'phone', 'connect-timeout', 'provider', 'hostuniq');
    foreach ($copy_fields as $fieldname) {
        if (isset($a_ppps[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_ppps[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
    // fields containing array data (comma-separated)
    $explode_fields = array('mtu', 'mru', 'mrru', 'bandwidth', 'localip', 'gateway', 'localip', 'subnet', 'ports');
    foreach ($explode_fields as $fieldname) {
        if (isset($a_ppps[$id][$fieldname])) {
            $pconfig[$fieldname] = explode(",", $a_ppps[$id][$fieldname]);
        } else {
            $pconfig[$fieldname] = array();
        }
    }

    // boolean fields
    $bool_fields = array('ondemand', 'shortseq', 'acfcomp', 'protocomp', 'vjcomp', 'tcpmssfix');
    foreach ($bool_fields as $fieldname) {
        $pconfig[$fieldname] = isset($a_ppps[$id][$fieldname]);
    }
    // special cases
    $pconfig['password'] = isset($a_ppps[$id]['password']) ? base64_decode($a_ppps[$id]['password']) : null;
    $pconfig['initstr'] = isset($a_ppps[$id]['initstr']) ? base64_decode($a_ppps[$id]['initstr']) : null;
    $pconfig['null_service'] = (isset($a_ppps[$id]['provider']) && empty($a_ppps[$id]['provider']));

    if ($pconfig['ptpid'] == null) {
        $pconfig['ptpid'] = interfaces_ptpid_next();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // save form data
    if (isset($_POST['id']) && !empty($a_ppps[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    switch($pconfig['type']) {
        case "ppp":
            $reqdfields = explode(" ", "ports phone");
            $reqdfieldsn = array(gettext("Link Interface(s)"),gettext("Phone Number"));
            do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
            break;
        case "pppoe":
            if (!empty($pconfig['ondemand'])) {
                $reqdfields = explode(" ", "ports username password ondemand");
                $reqdfieldsn = array(gettext("Link Interface(s)"),gettext("Username"),gettext("Password"),gettext("Dial on demand"),gettext("Idle timeout value"));
            } else {
                $reqdfields = explode(" ", "ports username password");
                $reqdfieldsn = array(gettext("Link Interface(s)"),gettext("Username"),gettext("Password"));
            }
            do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
            break;
        case "l2tp":
        case "pptp":
            if (!empty($pconfig['ondemand'])) {
                $reqdfields = explode(" ", "ports username password localip subnet gateway ondemand idletimeout");
                $reqdfieldsn = array(gettext("Link Interface(s)"),gettext("Username"),gettext("Password"),gettext("Local IP address"),gettext("Subnet"),gettext("Remote IP address"),gettext("Dial on demand"),gettext("Idle timeout value"));
            } else {
                $reqdfields = explode(" ", "ports username password localip subnet gateway");
                $reqdfieldsn = array(gettext("Link Interface(s)"),gettext("Username"),gettext("Password"),gettext("Local IP address"),gettext("Subnet"),gettext("Remote IP address"));
            }
            do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
            break;
        default:
            $input_errors[] = gettext("Please choose a Link Type.");
            break;
    }

    if (!isset($pconfig['ports'])) {
        $pconfig['ports'] = array();
    }

    if ($pconfig['type'] == "ppp" && count($pconfig['ports']) > 1) {
        $input_errors[] = gettext("Multilink connections (MLPPP) using the PPP link type is not currently supported. Please select only one Link Interface.");
    }
    if (!empty($pconfig['provider']) && !is_domain($pconfig['provider'])) {
        $input_errors[] = gettext("The Service name contains invalid characters.");
    }
    if (!empty($pconfig['provider']) && !empty($pconfig['null_service'])) {
        $input_errors[] = gettext("Do not specify both a Service name and a NULL Service name.");
    }
    if (($pconfig['idletimeout'] != "") && !is_numericint($pconfig['idletimeout'])) {
        $input_errors[] = gettext("The idle timeout value must be an integer.");
    }

    foreach ($pconfig['ports'] as $iface_idx => $iface) {
        if (!empty($pconfig['localip'][$iface_idx]) && !is_ipaddr($pconfig['localip'][$iface_idx])) {
            $input_errors[] = sprintf(gettext("A valid local IP address must be specified for %s."), $iface);
        }
        if (!empty($pconfig['gateway'][$iface_idx]) && !is_ipaddr($pconfig['gateway'][$iface_idx])) {
            $input_errors[] = sprintf(gettext("A valid gateway IP address must be specified for %s."), $iface);
        }
        if (!empty($pconfig['bandwidth'][$iface_idx]) && !is_numericint($pconfig['bandwidth'][$iface_idx])) {
            $input_errors[] = sprintf(gettext("The bandwidth value for %s must be an integer."), $iface);
        }

        if (!empty($pconfig['mtu'][$iface_idx]) && $pconfig['mtu'][$iface_idx] < 576) {
            $input_errors[] = sprintf(gettext("The MTU for %s must be greater than 576 bytes."), $iface);
        }
        if (!empty($pconfig['mru'][$iface_idx]) && $pconfig['mru'][$iface_idx] < 576) {
            $input_errors[] = sprintf(gettext("The MRU for %s must be greater than 576 bytes."), $iface);
        }
    }

    if (count($input_errors) == 0) {
        $ppp = array();
        $ppp['ptpid'] = $pconfig['ptpid'];
        $ppp['type'] = $pconfig['type'];
        $ppp['if'] = $ppp['type'].$ppp['ptpid'];
        $ppp['ports'] = implode(',', $pconfig['ports']);
        $ppp['username'] = $pconfig['username'];
        $ppp['password'] = base64_encode($pconfig['password']);
        $ppp['ondemand'] = !empty($pconfig['ondemand']);
        if (!empty($pconfig['idletimeout'])) {
            $ppp['idletimeout'] = $pconfig['idletimeout'];
        }
        $ppp['uptime'] = !empty($pconfig['uptime']);
        if (!empty($pconfig['descr'])) {
            $ppp['descr'] = $pconfig['descr'];
        }

        // Loop through fields associated with a individual link/port and make an array of the data
        $port_fields = array("localip", "gateway", "subnet", "bandwidth", "mtu", "mru", "mrru");
        $port_data = array();
        foreach ($pconfig['ports'] as $iface_idx => $iface) {
            foreach ($port_fields as $field_label) {
                if (!isset($port_data[$field_label])) {
                    $port_data[$field_label] = array();
                }
                if (isset($pconfig[$field_label][$iface_idx])) {
                    $port_data[$field_label][] = $pconfig[$field_label][$iface_idx];
                }
            }
        }
        switch($pconfig['type']) {
            case "ppp":
                if (!empty($pconfig['initstr'])) {
                    $ppp['initstr'] = base64_encode($pconfig['initstr']);
                }
                if (!empty($pconfig['simpin'])) {
                  $ppp['simpin'] = $pconfig['simpin'];
                  $ppp['pin-wait'] = $pconfig['pin-wait'];
                }
                if (!empty($pconfig['apn'])) {
                  $ppp['apn'] = $pconfig['apn'];
                  $ppp['apnum'] = $pconfig['apnum'];
                }
                $ppp['phone'] = $pconfig['phone'];
                $ppp['localip'] = implode(',', $port_data['localip']);
                $ppp['gateway'] = implode(',', $port_data['gateway']);
                if (!empty($pconfig['connect-timeout'])) {
                    $ppp['connect-timeout'] = $pconfig['connect-timeout'];
                }
                break;
            case "pppoe":
                if (!empty($pconfig['provider'])) {
                    $ppp['provider'] = $pconfig['provider'];
                } else {
                    $ppp['provider'] = !empty($pconfig['null_service']);
                }
                if (!empty($pconfig['hostuniq'])) {
                    $ppp['hostuniq'] = $pconfig['hostuniq'];
                }
                break;
            case "pptp":
            case "l2tp":
                $ppp['localip'] = implode(',', $port_data['localip']);
                $ppp['subnet'] = implode(',', $port_data['subnet']);
                $ppp['gateway'] = implode(',', $port_data['gateway']);
                break;
            default:
                break;
        }

        $ppp['shortseq'] = !empty($pconfig['shortseq']);
        $ppp['acfcomp'] = !empty($pconfig['acfcomp']);
        $ppp['protocomp'] = !empty($pconfig['protocomp']);
        $ppp['vjcomp'] = !empty($pconfig['vjcomp']);
        $ppp['tcpmssfix'] = !empty($pconfig['tcpmssfix']);
        $ppp['bandwidth'] = implode(',', $port_data['bandwidth']);
        $ppp['mtu'] = implode(',', $port_data['mtu']);
        $ppp['mru'] = implode(',', $port_data['mru']);
        $ppp['mrru'] = implode(',', $port_data['mrru']);

        if (isset($id)) {
            $a_ppps[$id] = $ppp;
        }  else {
            $a_ppps[] = $ppp;
        }

        write_config();

        $iflist = get_configured_interface_with_descr();
        foreach ($iflist as $pppif => $ifdescr) {
            if ($config['interfaces'][$pppif]['if'] == $ppp['if']) {
                interface_ppps_configure($pppif);
            }
        }

        header(url_safe('Location: /interfaces_ppps.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");
?>

<body>
  <script>
    $(document).ready(function () {
        // change type
        $("#type").change(function () {
          $('#ppp,#ppp_adv,#pppoe,#hostuniqopt,#ppp_provider,#phone_num,#apn_').hide();
          $('#ports > [data-type="serial"]').hide();
          $('#ports > [data-type="serial"]').prop('disabled', true);
          $('#ports > [data-type="interface"]').hide();
          $('#ports > [data-type="interface"]').prop('disabled', true);
          switch($("#type").val()) {
            case "ppp":
              $('#ppp,#ppp_adv,#ppp_provider,#phone_num,#apn_').show();
              $('#ports > [data-type="serial"]').show();
              $('#ports > [data-type="serial"]').prop('disabled', false);
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
              $("#interface_details").hide();
              break;
            case "pppoe":
              $('#pppoe').show();
              $('#hostuniqopt').show();
              // fall through to show interface items
            default:
              $('#ports > [data-type="interface"]').show();
              $('#ports > [data-type="interface"]').prop('disabled', false);
              $("#interface_details").show();
              break;
          }
          $("#ports").selectpicker('refresh');
        });
        $("#type").change();

        // change interfaces / ports selection
        $("#ports").change(function () {
            for (i=0; i <= $("#ports").children().length; ++i) {
                if ($('#ports :selected').length > i) {
                    $(".intf_select_"+i).prop('disabled', false);
                    $(".intf_select_"+i).show();
                } else {
                    $(".intf_select_"+i).prop('disabled', true);
                    $(".intf_select_"+i).hide();
                }
            }
            // add item text
            var i=0;
            $('#ports :selected').each(function () {
              $(".intf_select_txt_"+i).html('( '+ $(this).val() + ' )');
              i++;
            });
        });
        $("#ports").change();

        // advanced options
        $("#show_advanced").click(function () {
            $(".act_show_advanced").show();
            $("#show_advanced_opt").hide();
        });

        // ppp -> country change
        $("#country").change(function () {
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
    });
  </script>
<?php include("fbegin.inc"); ?>
    <section class="page-content-main">
      <div class="container-fluid">
        <div class="row">
          <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
          <section class="col-xs-12">
            <form method="post" name="iform" id="iform">
              <div class="tab-content content-box col-xs-12 __mb">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <thead>
                      <tr>
                        <td style="width:22%"><strong><?=gettext("PPPs configuration");?></strong></td>
                        <td style="width:78%; text-align:right">
                          <small><?=gettext("full help"); ?> </small>
                          <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                          &nbsp;
                        </td>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Link Type"); ?></td>
                        <td>
                          <select name="type" class="selectpicker" id="type">
<?php
                          $types = array("ppp" => "PPP", "pppoe" => "PPPoE", "pptp" => "PPTP",  "l2tp" => "L2TP");
                          foreach ($types as $key => $opt):?>
                            <option value="<?=$key;?>" <?=$key == $pconfig['type'] ? "selected=\"selected\"" : "";?>><?=$opt;?></option>
<?php
                          endforeach;?>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_ports" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?= gettext("Link interface(s)"); ?></td>
                        <td>
                          <select class="selectpicker" multiple="multiple" size="3" name="ports[]" id="ports" >
<?php
                          foreach (legacy_serial_devices() as $port => $port_info):?>
                            <option data-type="serial" value="<?=$port;?>" <?=in_array($port, $pconfig['ports']) ? "selected=\"selected\"" : "";?> >
                              <?=$port;?>  <?=!empty($port_info['descr']) ?  "(".$port_info['descr'].")" : "" ;?>
                            </option>
<?php
                          endforeach;?>
<?php
                          $portlist = get_interface_list();
                          $iflist = get_configured_interface_with_descr();
                          $portlist = array_merge($portlist, $iflist);

                          if (isset($config['vlans']['vlan'])) {
                              foreach ($config['vlans']['vlan'] as $vlan) {
                                  $portlist[$vlan['vlanif']] = $vlan;
                              }
                          }
                          foreach ($portlist as $intf_key => $intf_value):?>
                          <option data-type="interface" value="<?=$intf_key;?>" <?=in_array($intf_key, $pconfig['ports']) ? "selected=\"selected\"" : "";?> >
                            <?=$intf_key;?> <?=isset($intf_value['mac']) ? '('.$intf_value['mac'].')' : "";?>
                          </option>
<?php
                          endforeach;?>
                          </select>
                          <div class="hidden" data-for="help_for_ports">
                            <?= gettext("Select at least two interfaces for Multilink (MLPPP) connections."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Description"); ?></td>
                        <td>
                          <input name="descr" type="text"  value="<?=$pconfig['descr'];?>" />
                          <div class="hidden" data-for="help_for_descr">
                            <?= gettext("You may enter a description here for your reference. Description will appear in the \"Interfaces Assign\" select lists."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr id="ppp_provider">
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
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Username"); ?></td>
                        <td>
                          <input name="username" type="text" id="username" value="<?=$pconfig['username'];?>" />
                        </td>
                      </tr>
                      <tr>
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Password"); ?></td>
                        <td>
                          <input name="password" type="password" id="password" value="<?=$pconfig['password'];?>" />
                        </td>
                      </tr>
                      <tr style="display:none" id="phone_num">
                        <td><a id="help_for_phone" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Phone Number"); ?></td>
                        <td>
                          <input name="phone" type="text" id="phone" value="<?=$pconfig['phone'];?>" />
                          <div class="hidden" data-for="help_for_phone">
                            <?= gettext("Note: Typically *99# for GSM networks and #777 for CDMA networks"); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" id="apn_">
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Access Point Name (APN)"); ?></td>
                        <td>
                          <input name="apn" type="text" id="apn" value="<?=$pconfig['apn'];?>" />
                        </td>
                      </tr>
                      <tr style="display:none" id="pppoe">
                        <td><a id="help_for_provider" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Service name"); ?></td>
                        <td>
                          <input name="provider" type="text" id="provider" value="<?=$pconfig['provider'];?>" />&nbsp;&nbsp;
                          <input type="checkbox" value="on" id="null_service" name="null_service" <?=!empty($pconfig['null_service']) ? "checked=\"checked\"" : ""; ?> /> <?= gettext("Configure a NULL Service name"); ?>
                          <div class="hidden" data-for="help_for_provider">
                            <?= gettext("Hint: this field can usually be left empty. Service name will not be configured if this field is empty. Check the \"Configure NULL\" box to configure a blank Service name."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" id="hostuniqopt">
                        <td><a id="help_for_hostuniq" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Host-Uniq"); ?></td>
                        <td>
                          <input name="hostuniq" type="text" id="hostuniq" value="<?=$pconfig['hostuniq'];?>" />
                          <div class="hidden" data-for="help_for_hostuniq">
                            <?= gettext('This field can usually be left empty unless specified by the provider.') ?>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <table class="table table-striped" id="interface_details" style="display:none">
                    <tbody>
<?php
                      for ($intf_idx=0; $intf_idx <= count($portlist) ; ++$intf_idx):?>
                      <tr style="display:none" class="intf_select_<?=$intf_idx;?>">
                        <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Local IP");?> <span class="intf_select_txt_<?=$intf_idx;?>"> </span></td>
                        <td style="width:78%">
                          <input name="localip[]" type="text" class="intf_select_<?=$intf_idx;?>" value="<?=isset($pconfig['localip'][$intf_idx]) ? $pconfig['localip'][$intf_idx] : "";?>" />
                          /
                          <select name="subnet[]" class="intf_select_<?=$intf_idx;?>">
                          <?php for ($i = 31; $i > 0; $i--): ?>
                            <option value="<?=$i;?>" <?= isset($pconfig['subnet'][$intf_idx]) && $i == $pconfig['subnet'][$intf_idx] ? "selected=\"selected\"" : "";?>>
                              <?=$i;?>
                            </option>
                          <?php endfor; ?>
                          </select>
                        </td>
                      </tr>
                      <tr style="display:none" class="intf_select_<?=$intf_idx;?>">
                        <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Gateway");?> <span class="intf_select_txt_<?=$intf_idx;?>"> </span></td>
                        <td style="width:78%">
                          <input name="gateway[]" type="text" class="intf_select_<?=$intf_idx;?>" value="<?=isset($pconfig['gateway'][$intf_idx]) ? $pconfig['gateway'][$intf_idx] : "";?>" />
                        </td>
                      </tr>
<?php
                      endfor;?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Advanced (button, show options) -->
              <div class="tab-content content-box col-xs-12 __mb" id="show_advanced_opt">
                <div class="table-responsive">
                  <table class="table table-striped" >
                    <tbody>
                      <tr>
                        <td style="width:22%">&nbsp;</td>
                        <td style="width:78%">
                          <input type="button" id="show_advanced" value="<?= html_safe(gettext('Show advanced options')) ?>" class="btn btn-default btn-xs"/>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="tab-content content-box col-xs-12 __mb" >
                <div class="table-responsive">
                  <!-- Advanced PPP -->
                  <table class="table table-striped" id="ppp_adv" style="display:none">
                    <thead>
                      <tr style="display:none" class="act_show_advanced">
                        <th colspan="2"><?= gettext("Advanced Options"); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr style="display:none" class="act_show_advanced">
                        <td style="width:22%"><a id="help_for_apnum" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("APN number (optional)"); ?></td>
                        <td style="width:78%">
                          <input name="apnum" type="text" id="apnum" value="<?=$pconfig['apnum'];?>" />
                          <div class="hidden" data-for="help_for_apnum">
                            <?= gettext("Note: Defaults to 1 if you set APN above. Ignored if you set no APN above."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("SIM PIN"); ?></td>
                        <td>
                          <input name="simpin" type="text" id="simpin" value="<?=$pconfig['simpin'];?>" />
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_pin-wait" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("SIM PIN wait"); ?></td>
                        <td>
                          <input name="pin-wait" type="text" cid="pin-wait"  value="<?=$pconfig['pin-wait'];?>" />
                          <div class="hidden" data-for="help_for_pin-wait">
                            <?= gettext("Note: Time to wait for SIM to discover network after PIN is sent to SIM (seconds)."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_initstr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Init String"); ?></td>
                        <td>
                          <input type="text" id="initstr" name="initstr" value="<?=$pconfig['initstr'];?>" />
                          <div class="hidden" data-for="help_for_initstr">
                            <?= gettext("Note: Enter the modem initialization string here. Do NOT include the \"AT\"" .
                          " string at the beginning of the command. Many modern USB 3G modems don't need an initialization string."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_connect-timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Connection Timeout"); ?></td>
                        <td>
                          <input name="connect-timeout" type="text" id="connect-timeout" value="<?=$pconfig['connect-timeout'];?>" />
                          <div class="hidden" data-for="help_for_connect-timeout">
                            <?= gettext("Note: Enter timeout in seconds for connection to be established (sec.) Default is 45 sec."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_uptime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Uptime Logging"); ?></td>
                        <td>
                          <input type="checkbox" value="on" id="uptime" name="uptime" <?=!empty($pconfig['uptime']) ? "checked=\"checked\"" : ""; ?> />
                          <?= gettext("Enable persistent logging of connection uptime."); ?>
                          <div class="hidden" data-for="help_for_uptime">
                            <?= gettext("This option causes cumulative uptime to be recorded and displayed on the Status Interfaces page."); ?>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <!-- Advanced (all) -->
                  <table class="table table-striped" >
                    <tbody>
                      <tr style="display:none" class="act_show_advanced">
                        <td style="width:22%"><a id="help_for_ondemand" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Dial On Demand"); ?></td>
                        <td style="width:78%">
                          <input type="checkbox" value="on" id="ondemand" name="ondemand" <?=!empty($pconfig['ondemand']) ? "checked=\"checked\"" : ""; ?> />
                          <?= gettext("Enable Dial-on-Demand mode"); ?>
                          <div class="hidden" data-for="help_for_ondemand">
                            <?= gettext("This option causes the interface to operate in dial-on-demand mode. Do NOT enable if you want your link to be always up. " .
                            "The interface is configured, but the actual connection of the link is delayed until qualifying outgoing traffic is detected."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_idletimeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Idle Timeout"); ?></td>
                        <td>
                          <input name="idletimeout" type="text" id="idletimeout" value="<?=$pconfig['idletimeout'];?>" />
                          <div class="hidden" data-for="help_for_idletimeout">
                            <?= gettext("(seconds) Default is 0, which disables the timeout feature."); ?><br /><br />
                            <?= gettext("If no incoming or outgoing packets are transmitted for the entered number of seconds the connection is brought down.");?>
                            <br /><?=gettext("When the idle timeout occurs, if the dial-on-demand option is enabled, mpd goes back into dial-on-demand mode. Otherwise, the interface is brought down and all associated routes removed."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_vjcomp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Compression"); ?></td>
                        <td>
                          <input type="checkbox" value="on" id="vjcomp" name="vjcomp" <?= !empty($pconfig['vjcomp']) ? 'checked="checked"' : '' ?> />
                          <?= gettext("Disable vjcomp(compression) (auto-negotiated by default)."); ?>
                          <div class="hidden" data-for="help_for_vjcomp">
                            <?=gettext("This option enables Van Jacobson TCP header compression, which saves several bytes per TCP data packet. " .
                              "You almost always want this option. This compression ineffective for TCP connections with enabled modern extensions like time " .
                              "stamping or SACK, which modify TCP options between sequential packets.");?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_tcpmssfix" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("TCPmssFix"); ?></td>
                        <td>
                          <input type="checkbox" value="on" id="tcpmssfix" name="tcpmssfix" <?=!empty($pconfig['tcpmssfix']) ? "checked=\"checked\"" : ""; ?> />
                          <?= gettext("Disable tcpmssfix (enabled by default)."); ?>
                          <div class="hidden" data-for="help_for_tcpmssfix">
                            <?=gettext("This option causes mpd to adjust incoming and outgoing TCP SYN segments so that the requested maximum segment size is not greater than the amount ".
                              "allowed by the interface MTU. This is necessary in many setups to avoid problems caused by routers that drop ICMP Datagram Too Big messages. Without these messages, ".
                              "the originating machine sends data, it passes the rogue router then hits a machine that has an MTU that is not big enough for the data. Because the IP Don't Fragment option is set, ".
                              "this machine sends an ICMP Datagram Too Big message back to the originator and drops the packet. The rogue router drops the ICMP message and the originator never ".
                              "gets to discover that it must reduce the fragment size or drop the IP Don't Fragment option from its outgoing data.");?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_shortseq" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ShortSeq");?></td>
                        <td>
                          <input type="checkbox" value="on" id="shortseq" name="shortseq" <?=!empty($pconfig['shortseq']) ? "checked=\"checked\"" : ""; ?> />
                          <?= gettext("Disable shortseq (auto-negotiated by default)."); ?>
                          <div class="hidden" data-for="help_for_shortseq">
                            <?= gettext("This option is only meaningful if multi-link PPP is negotiated. It proscribes shorter multi-link fragment headers, saving two bytes on every frame. " .
                            "It is not necessary to disable this for connections that are not multi-link."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_acfcomp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ACFComp"); ?></td>
                        <td>
                          <input type="checkbox" value="on" id="acfcomp" name="acfcomp" <?=!empty($pconfig['acfcomp']) ? "checked=\"checked\"" : ""; ?> />
                          <?= gettext("Disable acfcomp (compression) (auto-negotiated by default)."); ?>
                          <div class="hidden" data-for="help_for_acfcomp">
                            <?= gettext("Address and control field compression. This option only applies to asynchronous link types. It saves two bytes per frame."); ?>
                          </div>
                        </td>
                      </tr>
                      <tr style="display:none" class="act_show_advanced">
                        <td><a id="help_for_protocomp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ProtoComp"); ?></td>
                        <td>
                          <input type="checkbox" value="on" id="protocomp" name="protocomp" <?=!empty($pconfig['protocomp']) ? "checked=\"checked\"" :""; ?> />
                          <?= gettext("Disable protocomp (compression) (auto-negotiated by default)."); ?>
                          <div class="hidden" data-for="help_for_protocomp">
                            <?= gettext("Protocol field compression. This option saves one byte per frame for most frames."); ?>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <table class="table table-striped act_show_advanced" style="display:none">
                    <tbody>
<?php
                      for ($intf_idx=0; $intf_idx <= count($portlist); ++$intf_idx):?>
                      <tr style="display:none" class="intf_select_<?=$intf_idx;?>">
                        <td style="width:22%"> <a id="help_for_link_<?=$intf_idx;?>" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Link Parameters");?> <span class="intf_select_txt_<?=$intf_idx;?>"> </span></td>
                        <td style="width:78%">
                          <table class="table table-striped table-condensed">
                            <tr>
                              <td><?=gettext("Bandwidth");?></td>
                              <td>
                                <input name="bandwidth[]" class="intf_select_<?=$intf_idx;?>" type="text" value="<?=isset($pconfig['bandwidth'][$intf_idx]) ? $pconfig['bandwidth'][$intf_idx] : "";?>" />
                              </td>
                            </tr>
                            <tr>
                              <td><?=gettext("MTU"); ?></td>
                              <td>
                                <input name="mtu[]" class="intf_select_<?=$intf_idx;?>" type="text" value="<?=isset($pconfig['mtu'][$intf_idx]) ? $pconfig['mtu'][$intf_idx] : "";?>" />
                              </td>
                            </tr>
                            <tr>
                              <td><?=gettext("MRU"); ?></td>
                              <td>
                                <input name="mru[]" class="intf_select_<?=$intf_idx;?>" type="text" value="<?=isset($pconfig['mru'][$intf_idx]) ? $pconfig['mru'][$intf_idx] : "";?>" />
                              </td>
                            </tr>
                            <tr>
                              <td><?=gettext("MRRU"); ?></td>
                              <td>
                                <input name="mrru[]" class="intf_select_<?=$intf_idx;?>" type="text" value="<?=isset($pconfig['mrru'][$intf_idx]) ? $pconfig['mrru'][$intf_idx] : "";?>" />
                              </td>
                            </tr>
                          </table>
                          <div class="hidden" data-for="help_for_link_<?=$intf_idx;?>">
                            <ul>
                              <li><?=gettext("Bandwidth: Set ONLY for MLPPP connections and ONLY when links have different bandwidths.");?></li>
                              <li><?=gettext("MTU: MTU will default to 1492.");?></li>
                              <li><?=gettext("MRU: MRU will be auto-negotiated by default.");?></li>
                              <li><?=gettext("MRRU: Set ONLY for MLPPP connections. MRRU will be auto-negotiated by default.");?></li>
                            </ul>
                          </div>
                        </td>
                      </tr>
<?php
                      endfor;?>
                    </tbody>
                  </table>
                  <table class="table table-striped">
                    <tbody>
                      <tr>
                        <td style="width:22%">&nbsp;</td>
                        <td style="width:78%">
                          <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                          <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/interfaces_ppps.php'" />
                          <input name="ptpid" type="hidden" value="<?=$pconfig['ptpid'];?>" />
                          <?php if (isset($id)): ?>
                            <input name="id" type="hidden" value="<?=$id;?>" />
                          <?php endif; ?>
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

<?php include("foot.inc"); ?>
