<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2008 Ermal Luçi
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
require_once("system.inc");
require_once("interfaces.inc");

/**
 * list available interfaces for lagg
 * @param null $selected_id selected item index
 * @return array
 */
function available_interfaces($selected_id = null)
{
    global $config;
    // configured interfaces
    $configured_interfaces = array();
    foreach (array_keys(legacy_config_get_interfaces(['virtual' => false])) as $intf) {
        $configured_interfaces[] = get_real_interface($intf);
    }
    // lagg members from other lagg interfaces
    $lagg_member_interfaces = array();
    foreach ($config['laggs']['lagg'] as $lagg_idx => $lagg) {
        if ($lagg_idx == $selected_id) {
            continue;
        }
        foreach (explode(",", $lagg['members']) as $lagg_member) {
            $lagg_member_interfaces[] = get_real_interface($lagg_member);
        }
    }

    $interfaces = array();
    foreach (get_interface_list() as $intf => $intf_info) {
        if (strpos($intf, '_vlan')) {
            // skip vlans
            continue;
        } elseif (in_array($intf, $lagg_member_interfaces)) {
            // skip members of other lagg interfaces
            continue;
        } elseif (in_array($intf, $configured_interfaces)) {
            // skip configured interfaces
            continue;
        }
        $interfaces[$intf] = $intf_info;
    }

    return $interfaces;
}

$laggprotos = array("none", "lacp", "failover", "fec", "loadbalance", "roundrobin");

$a_laggs = &config_read_array('laggs', 'lagg');


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // read form data
    if (!empty($a_laggs[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    $pconfig['laggif'] = isset($a_laggs[$id]['laggif']) ? $a_laggs[$id]['laggif'] : null;
    $pconfig['members'] = isset($a_laggs[$id]['members']) ? explode(",", $a_laggs[$id]['members']) : array();
    $pconfig['proto'] = isset($a_laggs[$id]['proto']) ? $a_laggs[$id]['proto'] : null;
    $pconfig['descr'] = isset($a_laggs[$id]['descr']) ? $a_laggs[$id]['descr'] : null;
    $pconfig['lacp_fast_timeout'] = !empty($a_laggs[$id]['lacp_fast_timeout']);
    $pconfig['mtu'] = isset($a_laggs[$id]['mtu']) ? $a_laggs[$id]['mtu'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate and save form data
    if (!empty($a_laggs[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "members proto");
    $reqdfieldsn = array(gettext("Member interfaces"), gettext("Lagg protocol"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (isset($pconfig['members'])) {
        foreach ($pconfig['members'] as $member) {
            if (!does_interface_exist($member)) {
                $input_errors[] = sprintf(gettext('Interface \'%s\' supplied as member does not exist'), $member);
            }
        }
    }

    if (!in_array($pconfig['proto'], $laggprotos)) {
        $input_errors[] = gettext("Protocol supplied is invalid");
    }

    if (!empty($pconfig['mtu'])) {
        $mtu_low = 576;
        $mtu_high = 65535;
        if ($pconfig['mtu'] < $mtu_low || $pconfig['mtu'] > $mtu_high) {
            $input_errors[] = sprintf(gettext('The MTU must be greater than %s bytes and less than %s.'), $mtu_low, $mtu_high);
        }
    }

    if (count($input_errors) == 0) {
        $lagg = array();
        $lagg['members'] = implode(',', $pconfig['members']);
        $lagg['descr'] = $pconfig['descr'];
        $lagg['laggif'] = $pconfig['laggif'];
        $lagg['proto'] = $pconfig['proto'];
        $lagg['mtu'] = $pconfig['mtu'];
        $lagg['lacp_fast_timeout'] = !empty($pconfig['lacp_fast_timeout']);
        if (isset($id)) {
            $lagg['laggif'] = $a_laggs[$id]['laggif'];
        }

        $lagg['laggif'] = interface_lagg_configure($lagg);
        if ($lagg['laggif'] == "" || !stristr($lagg['laggif'], "lagg")) {
            $input_errors[] = gettext("Error occurred creating interface, please retry.");
        } else {
            if (isset($id)) {
                $a_laggs[$id] = $lagg;
            } else {
                $a_laggs[] = $lagg;
            }

            write_config();
            $confif = convert_real_interface_to_friendly_interface_name($lagg['laggif']);
            if ($confif != '') {
                interface_configure(false, $confif);
            }
            header(url_safe('Location: /interfaces_lagg.php'));
            exit;
        }
    }
}

include("head.inc");
legacy_html_escape_form_data($pconfig);
?>

<body>
  <script>
    $( document ).ready(function() {
        $("#proto").change(function(){
            if ($("#proto").val() == 'lacp') {
                $("#lacp_fast_timeout").parent().parent().show();
                $("#lacp_fast_timeout").prop( "disabled", false );
            } else {
                $("#lacp_fast_timeout").parent().parent().hide();
                $("#lacp_fast_timeout").prop( "disabled", true );
            }
        });
        $("#proto").change();
    });
  </script>

<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <thead>
                  <tr>
                    <td style="width:22%"><strong><?=gettext("LAGG configuration");?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      &nbsp;
                    </td>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><a id="help_for_members" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Parent interface"); ?></td>
                    <td>
                      <select name="members[]" multiple="multiple" class="selectpicker">
<?php
                        foreach (available_interfaces(isset($id) ? $id : null) as $ifn => $ifinfo):?>
                        <option value="<?=$ifn;?>" <?=!empty($pconfig['members']) && in_array($ifn, $pconfig['members']) ? "selected=\"selected\"" : "";?>>
                            <?=$ifn;?> (<?=$ifinfo['mac']?>)
                        </option>
<?php
                        endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_members">
                        <?=gettext("Choose the members that will be used for the link aggregation"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_proto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Lag proto"); ?></td>
                    <td>
                      <select name="proto" class="selectpicker" id="proto">
<?php
                      foreach ($laggprotos as $proto):?>
                        <option value="<?=$proto;?>" <?=$proto == $pconfig['proto'] ? "selected=\"selected\"": "";?>>
                            <?=strtoupper($proto);?>
                        </option>
<?php
                      endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_proto">
                        <ul>
                          <li><b><?=gettext("failover"); ?></b></li>
                                <?=gettext("Sends and receives traffic only through the master port. " .
                                   "If the master port becomes unavailable, the next active port is " .
                                   "used. The first interface added is the master port; any " .
                                   "interfaces added after that are used as failover devices."); ?>
                          <li><b><?=gettext("fec"); ?></b><br /></li>
                                <?=gettext("Supports Cisco EtherChannel. This is a static setup and " .
                                   "does not negotiate aggregation with the peer or exchange " .
                                   "frames to monitor the link."); ?>
                          <li>
                            <b><?=gettext("lacp"); ?></b></li>
                                <?=gettext("Supports the IEEE 802.3ad Link Aggregation Control Protocol " .
                                   "(LACP) and the Marker Protocol. LACP will negotiate a set " .
                                   "of aggregable links with the peer in to one or more Link " .
                                   "Aggregated Groups. Each LAG is composed of ports of the " .
                                   "same speed, set to full-duplex operation. The traffic will " .
                                   "be balanced across the ports in the LAG with the greatest " .
                                   "total speed, in most cases there will only be one LAG which " .
                                   "contains all ports. In the event of changes in physical " .
                                   "connectivity, Link Aggregation will quickly converge to a " .
                                   "new configuration."); ?>
                          <li><b><?=gettext("loadbalance"); ?></b></li>
                                <?=gettext("Balances outgoing traffic across the active ports based on " .
                                   "hashed protocol header information and accepts incoming " .
                                   "traffic from any active port. This is a static setup and " .
                                   "does not negotiate aggregation with the peer or exchange " .
                                   "frames to monitor the link. The hash includes the Ethernet " .
                                   "source and destination address, and, if available, the VLAN " .
                                   "tag, and the IP source and destination address.") ?>
                          <li><b><?=gettext("roundrobin"); ?></b></li>
                                <?=gettext("Distributes outgoing traffic using a round-robin scheduler " .
                                   "through all active ports and accepts incoming traffic from " .
                                   "any active port."); ?>
                          <li><b><?=gettext("none"); ?></b></li>
                                <?=gettext("This protocol is intended to do nothing: It disables any " .
                                   "traffic without disabling the lagg interface itself."); ?>

                        </ul>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_lacp_fast_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Fast timeout"); ?></td>
                    <td>
                      <input name="lacp_fast_timeout" id="lacp_fast_timeout" type="checkbox" value="yes" <?=!empty($pconfig['lacp_fast_timeout']) ? "checked=\"checked\"" : "" ;?>/>
                      <div class="hidden" data-for="help_for_lacp_fast_timeout">
                        <?=gettext("Enable lacp fast-timeout on the interface."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_mtu" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MTU"); ?></td>
                    <td>
                      <input name="mtu" id="mtu" type="text" value="<?=$pconfig['mtu'];?>" />
                      <div class="hidden" data-for="help_for_mtu">
                        <?= gettext("If you leave this field blank, the smallest mtu of this laggs children will be used.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td style="width:22%">&nbsp;</td>
                    <td style="width:78%">
                      <input type="hidden" name="laggif" value="<?=$pconfig['laggif']; ?>" />
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                      <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/interfaces_lagg.php'" />
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
