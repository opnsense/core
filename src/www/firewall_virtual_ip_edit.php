<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
 * Copyright (C) 2004-2005 Scott Ullrich <sullrich@gmail.com>
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

/**
 * find max vhid
 */
function find_last_used_vhid() {
    global $config;
    $vhid = 0;
    if (isset($config['virtualip']['vip'])) {
        foreach($config['virtualip']['vip'] as $vip) {
            if(!empty($vip['vhid']) && $vip['vhid'] > $vhid) {
                $vhid = $vip['vhid'];
            }
        }
    }
    return $vhid;
}


// create new vip array if none existent
$a_vip = &config_read_array('virtualip', 'vip');


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // input record id, if valid
    if (isset($_GET['dup']) && isset($a_vip[$_GET['dup']]))  {
        $configId = $_GET['dup'];
        $after = $configId;
    } elseif (isset($_GET['id']) && isset($a_vip[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    }
    $pconfig = array();
    $form_fields = array('mode', 'vhid', 'advskew', 'advbase', 'password', 'subnet', 'subnet_bits'
                        , 'descr' ,'type', 'interface', 'gateway' );

    if (isset($configId)) {
        // 1-on-1 copy of config data
        foreach ($form_fields as $fieldname) {
            if (isset($a_vip[$configId][$fieldname])) {
                $pconfig[$fieldname] = $a_vip[$configId][$fieldname] ;
            }
        }
    }

    // initialize empty form fields
    foreach ($form_fields as $fieldname) {
        if (!isset($pconfig[$fieldname])) {
            $pconfig[$fieldname] = null ;
        }
    }
}  elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    // input record id, if valid
    if (isset($pconfig['id']) && isset($a_vip[$pconfig['id']])) {
        $id = $pconfig['id'];
    }
    if (!empty($config['interfaces'][$pconfig['interface']]) && !empty($config['interfaces'][$pconfig['interface']]['if'])) {
        $selected_interface = $config['interfaces'][$pconfig['interface']]['if'];
    } else {
        $selected_interface = array();
    }
    // perform form validations
    $reqdfields = array("mode");
    $reqdfieldsn = array(gettext("Type"));
    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (isset($id) && $pconfig['mode'] != $a_vip[$id]['mode']) {
        $input_errors[] = gettext("Virtual IP mode may not be changed for an existing entry.");
    } else {
        if (isset($pconfig['subnet'])) {
            $pconfig['subnet'] = trim($pconfig['subnet']);
            if (!is_ipaddr($pconfig['subnet'])) {
                $input_errors[] = gettext("A valid IP address must be specified.");
            } else {
                $ignore_if = isset($id) ? $a_vip[$id]['interface'] : $pconfig['interface'];
                if (is_ipaddr_configured($pconfig['subnet'], $ignore_if)) {
                    $input_errors[] = gettext("This IP address is being used by another interface or VIP.");
                }
            }
        }
        if (!empty($pconfig['gateway']) && !is_ipaddr($pconfig['gateway'])) {
            $input_errors[] = gettext("A valid gateway IP address must be specified.");
        }

        /* ipalias and carp should not use network or broadcast address */
        if ($pconfig['mode'] == "ipalias" || $pconfig['mode'] == "carp") {
            if (is_ipaddrv4($pconfig['subnet']) && $pconfig['subnet_bits'] != '32' && $pconfig['subnet_bits'] != '31') {
                $network_addr = gen_subnet($pconfig['subnet'], $pconfig['subnet_bits']);
                $broadcast_addr = gen_subnet_max($pconfig['subnet'], $pconfig['subnet_bits']);
                if (isset($network_addr) && $pconfig['subnet'] == $network_addr) {
                    $input_errors[] = gettext("You cannot use the network address for this VIP");
                } else if (isset($broadcast_addr) && $pconfig['subnet'] == $broadcast_addr) {
                    $input_errors[] = gettext("You cannot use the broadcast address for this VIP");
                }
            }
        }

        /* make sure new ip is within the subnet of a valid ip
         * on one of our interfaces (wan, lan optX)
         */
        if ($pconfig['mode'] == 'carp') {
            /* verify against reusage of vhids */
            foreach($config['virtualip']['vip'] as $vipId => $vip) {
               if (isset($vip['vhid']) &&  $vip['vhid'] == $pconfig['vhid'] && $vip['mode'] == 'carp' && $vip['interface'] == $pconfig['interface'] && $vipId != $id) {
                   $input_errors[] = sprintf(gettext("VHID %s is already in use on interface %s. Pick a unique number on this interface."),$pconfig['vhid'], convert_friendly_interface_to_friendly_descr($pconfig['interface']));
               }
            }
            if (empty($pconfig['password'])) {
                $input_errors[] = gettext("You must specify a CARP password that is shared between the two VHID members.");
            }
            if (empty($pconfig['vhid'])) {
               $input_errors[] = gettext('A VHID must be selected for this CARP VIP.');
            }
            if (substr($selected_interface,0, 2) === 'lo') {
                $input_errors[] = gettext('For this type of VIP loopback is not allowed.');
            }
        } else if ($pconfig['mode'] != 'ipalias' && substr($selected_interface,0, 2) === 'lo') {
            $input_errors[] = gettext('For this type of VIP loopback is not allowed.');
        } elseif ($pconfig['mode'] == 'ipalias' && !empty($pconfig['vhid'])) {
            $carpvip_found = false;
            foreach($config['virtualip']['vip'] as $vipId => $vip) {
                if ($vip['interface'] == $pconfig['interface'] && $vip['vhid'] == $pconfig['vhid'] && $vip['mode'] == 'carp') {
                    $carpvip_found = true ;
                }
            }
            if (!$carpvip_found) {
                $input_errors[] = sprintf(gettext("VHID %s must be defined on interface %s as a CARP VIP first."),$pconfig['vhid'], convert_friendly_interface_to_friendly_descr($pconfig['interface']));
            }
        }
    }

    if (count($input_errors) == 0) {
        $vipent = array();
        // defaults
        $vipent['type'] = "single";
        $vipent['subnet_bits'] = "32";
        // 1-on-1 copy attributes
        foreach (array('mode', 'interface', 'descr', 'type', 'subnet_bits', 'subnet', 'vhid'
                      ,'advskew','advbase','password', 'gateway') as $fieldname) {
            if (isset($pconfig[$fieldname]) && $pconfig[$fieldname] != "") {
                $vipent[$fieldname] = $pconfig[$fieldname];
            }
        }

        if (!empty($pconfig['noexpand'])) {
            // noexpand, only used for proxyarp
            $vipent['noexpand'] = true;
        }

        // virtual ip UI keeps track of its changes in a separate file
        // (which is only use on apply in firewall_virtual_ip)
        // add or change this administration here.
        // Not the nicest thing to do, but we keep it for now.
        if (file_exists('/tmp/.firewall_virtual_ip.apply')) {
            $toapplylist = unserialize(file_get_contents('/tmp/.firewall_virtual_ip.apply'));
        } else {
            $toapplylist = array();
        }
        if (isset($id)) {
            // save existing content before changing it
            $toapplylist[$id] = $a_vip[$id];
        } else {
            // new entry, no old data
            $toapplylist[count($a_vip)] = array();
        }

        if (isset($id)) {
            /* modify all virtual IP rules with this address */
            for ($i = 0; isset($config['nat']['rule'][$i]); $i++) {
                if (isset($config['nat']['rule'][$i]['destination']['address']) && $config['nat']['rule'][$i]['destination']['address'] == $a_vip[$id]['subnet']) {
                    $config['nat']['rule'][$i]['destination']['address'] = $vipent['subnet'];
                }
            }
        }

        // update or insert item in config
        if (isset($id)) {
            $a_vip[$id] = $vipent;
        } else {
            $a_vip[] = $vipent;
        }
        write_config();
        mark_subsystem_dirty('vip');
        file_put_contents('/tmp/.firewall_virtual_ip.apply', serialize($toapplylist));
        header(url_safe('Location: /firewall_virtual_ip.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>

<?php include("fbegin.inc");?>

<script>
$( document ).ready(function() {
    $("#mode").change(function(){
        //$("#subnet").attr('disabled', true);
        $("#type").attr('disabled', true);
        $("#gateway").attr('disabled', true);
        $("#subnet_bits").attr('disabled', true);
        $("#noexpand").attr('disabled', true);
        $("#password").attr('disabled', true);
        $("#vhid").attr('disabled', true);
        $("#advskew").attr('disabled', true);
        $("#advbase").attr('disabled', true);
        $("#noexpand").attr('disabled', true);
        $("#noexpandrow").addClass("hidden");
        $("#max_vhid").attr('disabled', true);

        switch ($(this).val()) {
            case "ipalias":
              $("#type").prop("selectedIndex",0);
              $("#gateway").attr('disabled', false);
              $("#vhid").attr('disabled', false);
              $("#subnet_bits").attr('disabled', false);
              $("#typenote").html("<?= html_safe(gettext('Please provide a single IP address.')) ?>");
              break;
            case "carp":
              $("#type").prop("selectedIndex",0);
              $("#subnet_bits").attr('disabled', false);
              $("#password").attr('disabled', false);
              $("#vhid").attr('disabled', false);
              $("#advskew").attr('disabled', false);
              $("#advbase").attr('disabled', false);
              $("#max_vhid").attr('disabled', false);
              $("#typenote").html("<?= html_safe(gettext('This must be the network\'s subnet mask. It does not specify a CIDR range.')) ?>");
              break;
            case "proxyarp":
              $("#type").attr('disabled', false);
              $("#subnet_bits").attr('disabled', false);
              $("#noexpand").attr('disabled', false);
              $("#noexpandrow").removeClass("hidden");
              $("#typenote").html("<?= html_safe(gettext('This is a CIDR block of proxy ARP addresses.')) ?>");
              break;
            case "other":
              $("#type").attr('disabled', false);
              $("#subnet_bits").attr('disabled', false);
              $("#typenote").html("<?= html_safe(gettext('This must be the network\'s subnet mask. It does not specify a CIDR range.')) ?>");
              break;
        }
        // refresh selectpickers
        setTimeout(function(){
            $('.selectpicker').selectpicker('refresh');
          }, 100);
    });

    $("#max_vhid").click(function(event){
        event.preventDefault();
        $("#vhid").val($(this).data('vhid'));
        $("#vhid").selectpicker('refresh');
    });

    // toggle initial mode change
    $("#mode").change();

    // IPv4/IPv6 select
    hook_ipv4v6('ipv4v6net', 'network-id');
});

</script>


  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="content-box tab-content">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <thead></thead>
                <tbody>
                  <tr>
                    <td style="width:22%"><strong><?=gettext("Edit Virtual IP");?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_mode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Mode');?></td>
                    <td>
                      <select id="mode" name="mode" class="selectpicker" data-width="auto" data-live-search="true">
                        <option value="ipalias" <?=$pconfig['mode'] == "ipalias" ? "selected=\"selected\"" : ""; ?>><?=gettext("IP Alias");?></option>
                        <option value="carp" <?=$pconfig['mode'] == "carp" ? "selected=\"selected\"" : ""; ?>><?=gettext("CARP");?></option>
                        <option value="proxyarp" <?=$pconfig['mode'] == "proxyarp" ? "selected=\"selected\"" : ""; ?>><?=gettext("Proxy ARP");?></option>
                        <option value="other" <?=$pconfig['mode'] == "other" ? "selected=\"selected\"" : ""; ?>><?=gettext("Other");?></option>
                      </select>
                      <div class="hidden" data-for="help_for_mode">
                        <?=gettext("Proxy ARP and other type Virtual IPs cannot be bound to by anything running on the firewall, such as IPsec, OpenVPN, etc. Use a CARP or IP Alias type address for these cases.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Interface");?></td>
                    <td>
                      <select name="interface" class="selectpicker" data-width="auto">
<?php
                      $interfaces = legacy_config_get_interfaces(array('virtual' => false));
                      $interfaces['lo0'] = array('descr' => 'Loopback');
                      foreach ($interfaces as $iface => $ifcfg): ?>
                        <option value="<?=$iface;?>" <?= $iface == $pconfig['interface'] ? 'selected="selected"' : '' ?>>
                          <?= htmlspecialchars($ifcfg['descr']) ?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                      <td><?=gettext("IP Address(es)");?></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type");?></td>
                      <td>
                        <select name="type" class="selectpicker" data-width="auto" id="type">
                            <option value="single" <?=(!empty($pconfig['subnet_bits']) && $pconfig['subnet_bits'] == 32) || !isset($pconfig['subnet']) ? "selected=\"selected\"" : "";?>>
                              <?=gettext("Single address");?>
                            </option>
                            <option value="network" <?=empty($pconfig['subnet_bits']) || $pconfig['subnet_bits'] != 32 || isset($pconfig['subnet']) ? "selected=\"selected\"" : "";?>>
                              <?=gettext("Network");?></option>
                            </select>
                      </td>
                  </tr>
                  <tr>
                      <td><a id="help_for_address" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Address");?></td>
                      <td>
                        <table style="border:0;">
                          <tr>
                            <td style="width:348px">
                              <input name="subnet" type="text" class="form-control" id="subnet" size="28" value="<?=$pconfig['subnet'];?>" />
                            </td>
                            <td>
                              <select name="subnet_bits" data-network-id="subnet" class="selectpicker ipv4v6net" data-size="10"  data-width="auto" id="subnet_bits">
<?php for ($i = 128; $i >= 1; $i--): ?>
                                  <option value="<?=$i;?>" <?= $i == $pconfig['subnet_bits'] ? "selected=\"selected\"" :""; ?>>
                                    <?=$i;?>
                                  </option>
<?php endfor ?>
                              </select>
                            </td>
                          </tr>
                        </table>
                        <div class="hidden" data-for="help_for_address">
                            <i id="typenote"></i>
                        </div>
                      </td>
                  </tr>
                  <tr>
                      <td><a id="help_for_gateway" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Gateway");?></td>
                      <td>
                          <input name="gateway" type="text" class="form-control" id="gateway" value="<?=$pconfig['gateway'];?>" />
                          <div class="hidden" data-for="help_for_gateway">
                            <?=gettext("For some interface types a gateway is required to configure an IP Alias (ppp/pppoe/tun), leave this field empty for all other interface types.");?>
                          </div>
                      </td>
                  </tr>
                  <tr id="noexpandrow">
                      <td><a id="help_for_noexpand" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Expansion");?> </td>
                      <td>
                          <input id="noexpand" name="noexpand" type="checkbox" class="form-control unknown" id="noexpand" <?= !empty($pconfig['noexpand']) ? "checked=\"checked\"" : "" ; ?> />
                          <div class="hidden" data-for="help_for_noexpand">
                            <?=gettext("Disable expansion of this entry into IPs on NAT lists (e.g. 192.168.1.0/24 expands to 256 entries.");?>
                          </div>
                  </tr>
                  <tr>
                    <td><a id="help_for_password" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Virtual IP Password");?></td>
                    <td>
                      <input type='password'  name='password' id="password" value="<?=$pconfig['password'];?>" />
                      <div class="hidden" data-for="help_for_password">
                        <?=gettext("Enter the VHID group password.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_vhid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("VHID Group");?></td>
                    <td>
                      <select id='vhid' name='vhid' class="selectpicker" data-size="10" data-width="auto">
                          <option value=""><?= gettext('none') ?></option>
                        <?php for ($i = 1; $i <= 255; $i++): ?>
                          <option value="<?=$i;?>" <?= $i == $pconfig['vhid'] ?  "selected=\"selected\"" : ""; ?>>
                            <?=$i;?>
                          </option>
                        <?php endfor; ?>
                      </select>
                      <button type="button" data-vhid="<?=find_last_used_vhid() + 1;?>" id="max_vhid" class="btn btn-default btn-cs">
                        <?=gettext("Select an unassigned VHID");?>
                      </button>
                      <div class="hidden" data-for="help_for_vhid">
                        <?=gettext("Enter the VHID group that the machines will share.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_adv" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Advertising Frequency");?></td>
                    <td>
                      <?=gettext("Base");?>:
                      <select id='advbase' name='advbase' class="selectpicker" data-size="10" data-width="auto">
                        <?php for ($i = 1; $i <= 254; $i++): ?>
                          <option value="<?=$i;?>" <?=$i == $pconfig['advbase'] ? "selected=\"selected\"" :""; ?>>
                            <?=$i;?>
                          </option>
                        <?php endfor; ?>
                      </select>
                      <?=gettext("Skew");?>:
                      <select id='advskew' name='advskew' class="selectpicker" data-size="10" data-width="auto">
                        <?php for ($i = 0; $i <= 254; $i++): ?>
                          <option value="<?=$i;?>" <?php if ($i == $pconfig['advskew']) echo "selected=\"selected\""; ?>>
                            <?=$i;?>
                          </option>
                        <?php endfor; ?>
                      </select>

                      <div class="hidden" data-for="help_for_adv">
                        <br/>
                        <?=gettext("The frequency that this machine will advertise. 0 usually means master. Otherwise the lowest combination of both values in the cluster determines the master.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                    <td>
                      <input name="descr" type="text" class="form-control unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_adv">
                        <?=gettext("You may enter a description here for your reference (not parsed).");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                      <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/firewall_virtual_ip.php'" />
                      <?php if (isset($id) && $a_vip[$id]): ?>
                        <input name="id" type="hidden" value="<?=$id;?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
