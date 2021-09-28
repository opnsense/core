<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
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
require_once("filter.inc");

$a_bridges = &config_read_array('bridges', 'bridged');

// interface list
$ifacelist = array();
foreach (legacy_config_get_interfaces(array('virtual' => false, "enable" => true)) as $intf => $intfdata) {
    if (substr($intfdata['if'], 0, 3) != 'gre' && substr($intfdata['if'], 0, 2) != 'lo') {
        $ifacelist[$intf] = $intfdata['descr'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // read form data
    if (!empty($a_bridges[$_GET['id']])) {
        $id = $_GET['id'];
    }
    // copy fields 1-on-1
    $copy_fields = array('descr', 'bridgeif', 'maxaddr', 'timeout', 'maxage','fwdelay', 'hellotime', 'priority', 'proto', 'holdcnt', 'span');
    foreach ($copy_fields as $fieldname) {
        if (isset($a_bridges[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_bridges[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }

    // bool fields
    $pconfig['enablestp'] = !empty($a_bridges[$id]['enablestp']);
    $pconfig['linklocal'] = !empty($a_bridges[$id]['linklocal']);

    // simple array fields
    $array_fields = array('members', 'stp', 'edge', 'autoedge', 'ptp', 'autoptp', 'static', 'private');
    foreach ($array_fields as $fieldname) {
        if (!empty($a_bridges[$id][$fieldname])) {
            $pconfig[$fieldname] = explode(',', $a_bridges[$id][$fieldname]);
        } else {
            $pconfig[$fieldname] = array();
        }
    }

    // array key/value sets
    if (!empty($a_bridges[$id]['ifpriority'])) {
        foreach (explode(",", $a_bridges[$id]['ifpriority']) as $cfg) {
            list ($key, $value)  = explode(":", $cfg);
            $pconfig['ifpriority_'.$key] = $value;
        }
    }
    if (!empty($a_bridges[$id]['ifpathcost'])) {
        foreach (explode(",", $a_bridges[$id]['ifpathcost']) as $cfg) {
            list ($key, $value)  = explode(":", $cfg);
            $pconfig['ifpathcost_'.$key] = $value;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // save / validate formdata
    if (!empty($a_bridges[$_POST['id']])) {
        $id = $_POST['id'];
    }

    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "members");
    $reqdfieldsn = array(gettext("Member Interfaces"));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['maxage']) && !is_numeric($pconfig['maxage'])) {
        $input_errors[] = gettext("Maxage needs to be an integer between 6 and 40.");
    }
    if (!empty($pconfig['maxaddr']) && !is_numeric($pconfig['maxaddr'])) {
        $input_errors[] = gettext("Maxaddr needs to be an integer.");
    }
    if (!empty($pconfig['timeout']) && !is_numeric($pconfig['timeout'])) {
        $input_errors[] = gettext("Timeout needs to be an integer.");
    }
    if (!empty($pconfig['fwdelay']) && !is_numeric($pconfig['fwdelay'])) {
        $input_errors[] = gettext("Forward Delay needs to be an integer between 4 and 30.");
    }
    if (!empty($pconfig['hellotime']) && !is_numeric($pconfig['hellotime'])) {
        $input_errors[] = gettext("Hello time for STP needs to be an integer between 1 and 2.");
    }
    if (!empty($pconfig['priority']) && !is_numeric($pconfig['priority'])) {
        $input_errors[] = gettext("Priority for STP needs to be an integer between 0 and 61440.");
    }
    if (!empty($pconfig['holdcnt']) && !is_numeric($pconfig['holdcnt'])) {
        $input_errors[] = gettext("Transmit Hold Count for STP needs to be an integer between 1 and 10.");
    }
    foreach ($ifacelist as $ifn => $ifdescr) {
        if (!empty($pconfig['ifpriority_'.$ifn]) && !is_numeric($pconfig['ifpriority_'.$ifn])) {
            $input_errors[] = sprintf(gettext("%s interface priority for STP needs to be an integer between 0 and 240."), $ifdescr);
        }
        if (!empty($pconfig['ifpathcost_'.$ifn]) && !is_numeric($pconfig['ifpathcost_'.$ifn])) {
            $input_errors[] = sprintf(gettext("%s interface path cost for STP needs to be an integer between 1 and 200000000."), $ifdescr);
        }
    }

    if (is_array($pconfig['members'])) {
        foreach($pconfig['members'] as $ifmembers) {
            if (empty($config['interfaces'][$ifmembers])) {
                $input_errors[] = gettext("A member interface passed does not exist in configuration");
            }
            if (!empty($config['interfaces'][$ifmembers]['wireless']['mode']) && $config['interfaces'][$ifmembers]['wireless']['mode'] != "hostap") {
                $input_errors[] = gettext("Bridging a wireless interface is only possible in hostap mode.");
            }
            if ($pconfig['span'] != "none" && $pconfig['span'] == $ifmembers) {
                $input_errors[] = gettext("Span interface cannot be part of the bridge. Remove the span interface from bridge members to continue.");
            }
        }
    }

    if (count($input_errors) == 0) {
        $bridge = [];

        // booleans
        foreach (['enablestp', 'linklocal'] as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $bridge[$fieldname] = true;
            }
        }

        // 1 on 1 copy
        $copy_fields = array('descr', 'maxaddr', 'timeout', 'bridgeif', 'maxage','fwdelay', 'hellotime', 'priority', 'proto', 'holdcnt');
        foreach ($copy_fields as $fieldname) {
            if (isset($pconfig[$fieldname]) && $pconfig[$fieldname] != '') {
                $bridge[$fieldname] = $pconfig[$fieldname];
            } else {
                $bridge[$fieldname] = null;
            }
        }
        if ($pconfig['span'] != 'none') {
            $bridge['span'] = $pconfig['span'];
        }

        // simple array fields
        $array_fields = array('members', 'stp', 'edge', 'autoedge', 'ptp', 'autoptp', 'static', 'private');
        foreach ($array_fields as $fieldname) {
            if(!empty($pconfig[$fieldname])) {
                $bridge[$fieldname] = implode(',', $pconfig[$fieldname]);
            }
        }

        // array key/value sets
        $bridge['ifpriority'] = '';
        $bridge['ifpathcost'] = '';
        foreach ($ifacelist as $ifn => $ifdescr) {
            if (isset($pconfig['ifpriority_'.$ifn]) && $pconfig['ifpriority_' . $ifn] != '') {
                if (!empty($bridge['ifpriority'])) {
                    $bridge['ifpriority'] .= ',';
                }
                $bridge['ifpriority'] .= $ifn . ':' . $pconfig['ifpriority_' . $ifn];
            }
            if (isset($pconfig['ifpathcost_' . $ifn]) && $pconfig['ifpathcost_' . $ifn] != '') {
                if (!empty($bridge['ifpathcost'])) {
                    $bridge['ifpathcost'] .= ',';
                }
                $bridge['ifpathcost'] .= $ifn . ':' . $pconfig['ifpathcost_' . $ifn];
            }
        }

        interface_bridge_configure($bridge);
        ifgroup_setup();
        if ($bridge['bridgeif'] == "" || !stristr($bridge['bridgeif'], "bridge")) {
            $input_errors[] = gettext("Error occurred creating interface, please retry.");
        } else {
            if (isset($id)) {
                $a_bridges[$id] = $bridge;
            } else {
                $a_bridges[] = $bridge;
            }
            write_config();
            $confif = convert_real_interface_to_friendly_interface_name($bridge['bridgeif']);
            if ($confif != '') {
                interface_configure(false, $confif);
            }
            header(url_safe('Location: /interfaces_bridge.php'));
            exit;
        }
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<script>
$(document).ready(function() {
  // advanced options
  $("#show_advanced").click(function(){
      $(".act_show_advanced").show();
      $("#show_advanced_opt").hide();
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
                      <td style="width:22%"><strong><?=gettext("Bridge configuration");?></strong></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                        &nbsp;
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><a id="help_for_members" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Member interfaces"); ?></td>
                      <td>
                        <select name="members[]" multiple="multiple" class="selectpicker" data-size="5" data-live-search="true">
<?php
                        $bridge_interfaces = array();
                        foreach ($a_bridges as $idx => $bridge_item) {
                            if (!isset($id) || $idx != $id) {
                                $bridge_interfaces = array_merge(explode(',', $bridge_item['members']), $bridge_interfaces);
                            }
                        }
                        foreach ($ifacelist as $ifn => $ifinfo):
                            if (!in_array($ifn, $bridge_interfaces)):?>
                            <option value="<?=$ifn;?>" <?=!empty($pconfig['members']) && in_array($ifn, $pconfig['members']) ? 'selected="selected"' : "";?>>
                                <?=$ifinfo;?>
                            </option>
<?php
                            endif;
                        endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_members">
                          <?=gettext("Interfaces participating in the bridge."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                      <td>
                        <input type="text" name="descr" value="<?=$pconfig['descr'];?>" />
                        <div class="hidden" data-for="help_for_descr">
                          <?=gettext("You may enter a description here for your reference (not parsed).");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_linklocal" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Link-local address') ?></td>
                      <td>
                        <input type="checkbox" name="linklocal" <?= !empty($pconfig['linklocal']) ? 'checked="checked"' : '' ?> />
                        <?= gettext('Enable link-local address') ?>
                        <div class="hidden" data-for="help_for_linklocal">
                          <?= gettext('By default, link-local addresses for bridges are disabled. You can enable them manually using this option. ' .
                            'However, when a bridge interface has IPv6 addresses, IPv6 addresses on a member interface will be automatically ' .
                            'removed before the interface is added.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr id="show_advanced_opt">
                      <td></td>
                      <td>
                        <input type="button" id="show_advanced" class="btn btn-xs btn-default" value="<?= html_safe(gettext('Show advanced options')) ?>" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Advanced / RSTP/STP -->
            <div class="tab-content content-box col-xs-12 __mb act_show_advanced" style="display:none">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <td colspan="2"><strong><?=gettext("Spanning Tree Protocol");?> (<?=gettext("RSTP/STP"); ?>)</strong></td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td style="width:22%"><a id="help_for_enablestp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable");?></td>
                      <td style="width:78%">
                        <input type="checkbox" name="enablestp" <?= !empty($pconfig['enablestp']) ? 'checked="checked"' : "";?> />
                        <div class="hidden" data-for="help_for_enablestp">
                          <?=gettext("Enable spanning tree options for this bridge."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_proto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol"); ?></td>
                      <td>
                        <select name="proto" id="proto" class="selectpicker">
                          <option value="rstp" <?=$pconfig['proto'] == "rstp" ? "selected=\"selected\"" : "";?> >
                            <?=gettext("RSTP");?>
                          </option>
                          <option value="stp" <?=$pconfig['proto'] == "stp" ? "selected=\"selected\"" : "";?> >
                            <?=gettext("STP");?>
                          </option>
                        </select>
                        <div class="hidden" data-for="help_for_proto">
                          <?=gettext("Protocol used for spanning tree."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_stp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("STP interfaces"); ?></td>
                      <td>
                        <select name="stp[]" class="selectpicker" multiple="multiple" size="3" data-live-search="true">
<?php
                        foreach ($ifacelist as $ifn => $ifdescr):?>
                          <option value="<?= $ifn ?>" <?= !empty($pconfig['stp']) && in_array($ifn, $pconfig['stp']) ? 'selected="selected"' : '' ?> >
                              <?=$ifdescr;?>
                          </option>
<?php
                        endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_stp" >
                         <?=gettext("Enable Spanning Tree Protocol on interface. The if_bridge(4) " .
                         "driver has support for the IEEE 802.1D Spanning Tree Protocol " .
                         "(STP). STP is used to detect and remove loops in a " .
                         "network topology."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_maxage" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Valid time"); ?> (<?=gettext("seconds"); ?>)</td>
                      <td>
                        <input name="maxage" type="text" value="<?=$pconfig['maxage'];?>" />
                        <div class="hidden" data-for="help_for_maxage">
                         <?=gettext("Set the time that a Spanning Tree Protocol configuration is " .
                         "valid. The default is 20 seconds. The minimum is 6 seconds and " .
                         "the maximum is 40 seconds."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_fwdelay" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Forward time"); ?> (<?=gettext("seconds"); ?>)</td>
                      <td>
                        <input name="fwdelay" type="text" value="<?=$pconfig['fwdelay'];?>" />
                        <div class="hidden" data-for="help_for_fwdelay">
                         <?=gettext("Set the time that must pass before an interface begins forwarding " .
                         "packets when Spanning Tree is enabled. The default is 15 seconds. The minimum is 4 seconds and the maximum is 30 seconds."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_hellotime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hello time"); ?> (<?=gettext("seconds"); ?>)</td>
                      <td>
                        <input name="hellotime" type="text" value="<?=$pconfig['hellotime'];?>" />
                        <div class="hidden" data-for="help_for_hellotime">
                         <?=gettext("Set the time between broadcasting of Spanning Tree Protocol configuration messages. The hello time may only be changed when " .
                         "operating in legacy STP mode. The default is 2 seconds. The minimum is 1 second and the maximum is 2 seconds."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_priority" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Priority"); ?></td>
                      <td>
                        <input name="priority" type="text" value="<?=$pconfig['priority'];?>" />
                        <div class="hidden" data-for="help_for_priority">
                         <?=gettext("Set the bridge priority for Spanning Tree. The default is 32768. " .
                         "The minimum is 0 and the maximum is 61440."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_holdcnt" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hold count"); ?></td>
                      <td>
                        <input name="holdcnt" type="text" value="<?=$pconfig['holdcnt'];?>" />
                        <div class="hidden" data-for="help_for_holdcnt">
                         <?=gettext("Set the transmit hold count for Spanning Tree. This is the number " .
                         "of packets transmitted before being rate limited. The " .
                         "default is 6. The minimum is 1 and the maximum is 10."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_intf_priority" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Priority"); ?></td>
                      <td>
                        <table class="table table-striped table-condensed">
<?php
                        foreach ($ifacelist as $ifn => $ifdescr):?>
                          <tr>
                            <td><?=$ifdescr;?></td>
                            <td>
                                <input name="ifpriority_<?=$ifn;?>" type="text" value="<?=isset($pconfig['ifpriority_'.$ifn]) ? $pconfig['ifpriority_'.$ifn] : "";?>" />
                            </td>
                          </tr>
<?php
                        endforeach;?>
                        </table>
                        <div class="hidden" data-for="help_for_intf_priority">
                         <?=gettext("Set the Spanning Tree priority of interface to value. " .
                         "The default is 128. The minimum is 0 and the maximum is 240. Increments of 16."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_intf_pathcost" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Path cost"); ?></td>
                      <td>
                        <table class="table table-striped table-condensed">
<?php
                        foreach ($ifacelist as $ifn => $ifdescr):?>
                          <tr>
                            <td><?=$ifdescr;?></td>
                            <td>
                                <input name="ifpathcost_<?=$ifn;?>" type="text" value="<?=isset($pconfig['ifpathcost_'.$ifn]) ? $pconfig['ifpathcost_'.$ifn] : "";?>" />
                            </td>
                          </tr>
<?php
                        endforeach;?>
                        </table>
                        <div class="hidden" data-for="help_for_intf_pathcost">
                         <?=gettext("Set the Spanning Tree path cost of interface to value. The " .
                         "default is calculated from the link speed. To change a previously selected path cost back to automatic, set the cost to 0. ".
                         "The minimum is 1 and the maximum is 200000000."); ?>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Advanced options-->
            <div class="tab-content content-box col-xs-12 __mb act_show_advanced" style="display:none">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <td colspan="2"><strong><?=gettext("Advanced options");?></strong></td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td style="width:22%"><a id="help_for_maxaddr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Cache size"); ?> (<?=gettext("entries"); ?>)</td>
                      <td style="width:78%">
                        <input name="maxaddr" type="text" value="<?=$pconfig['maxaddr'];?>" />
                      <div class="hidden" data-for="help_for_maxaddr">
                        <?=gettext("Set the size of the bridge address cache to size. The default is .100 entries."); ?>
                      </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Cache entry expire time"); ?> (<?=gettext("seconds"); ?>)</td>
                      <td>
                        <input name="timeout" type="text" value="<?=$pconfig['timeout'];?>" />
                        <div class="hidden" data-for="help_for_timeout">
                         <?=gettext("Set the timeout of address cache entries to this number of seconds. If " .
                         "seconds is zero, then address cache entries will not be expired. " .
                         "The default is 240 seconds."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_span" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Span port"); ?></td>
                      <td>
                        <select name="span" class="selectpicker" data-live-search="true">
                          <option value="none"><?=gettext("None"); ?></option>
<?php
                          foreach ($ifacelist as $ifn => $ifdescr):?>
                          <option value="<?=$ifn;?>" <?=$ifn == $pconfig['span'] ? "selected=\"selected\"" : "";?>>
                            <?=$ifdescr;?>
                          </option>
<?php
                          endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_span">
                         <?=gettext("Add the interface named by interface as a span port on the " .
                         "bridge. Span ports transmit a copy of every frame received by " .
                         "the bridge. This is most useful for snooping a bridged network " .
                         "passively on another host connected to one of the span ports of " .
                         "the bridge."); ?><br/>
                         <span class="text-warning"><strong><?=gettext("Note:"); ?><br /></strong></span>
                         <?=gettext("The span interface cannot be part of the bridge member interfaces."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_edge" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Edge ports"); ?></td>
                      <td>
                        <select name="edge[]" class="selectpicker" multiple="multiple" size="3" data-live-search="true">
<?php
                          foreach ($ifacelist as $ifn => $ifdescr):?>
                          <option value="<?=$ifn;?>" <?=!empty($pconfig['edge']) && in_array($ifn, $pconfig['edge']) ? "selected=\"selected\"" : "";?>>
                            <?=$ifdescr;?>
                          </option>
<?php
                          endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_edge">
                          <?=gettext("Set interface as an edge port. An edge port connects directly to " .
                          "end stations and cannot create bridging loops in the network; this " .
                          "allows it to transition straight to forwarding."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_autoedge" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Auto Edge ports"); ?></td>
                      <td>
                        <select name="autoedge[]" class="selectpicker" multiple="multiple" size="3" data-live-search="true">
<?php
                          foreach ($ifacelist as $ifn => $ifdescr):?>
                          <option value="<?=$ifn;?>" <?=!empty($pconfig['autoedge']) && in_array($ifn, $pconfig['autoedge']) ? "selected=\"selected\"" : "";?>>
                            <?=$ifdescr;?>
                          </option>
<?php
                          endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_autoedge">
                          <?=gettext("Allow interface to automatically detect edge status. This is the " .
                            "default for all interfaces added to a bridge."); ?><br/>
                            <span class="text-warning"><strong><?=gettext("Note:"); ?><br /></strong></span>
                            <?=gettext("This will disable the autoedge status of interfaces."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_ptp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("PTP ports"); ?></td>
                      <td>
                        <select name="ptp[]" class="selectpicker" multiple="multiple" size="3" data-live-search="true">
<?php
                          foreach ($ifacelist as $ifn => $ifdescr):?>
                          <option value="<?=$ifn;?>" <?=!empty($pconfig['ptp']) && in_array($ifn, $pconfig['ptp']) ? 'selected="selected"' : "";?>>
                            <?=$ifdescr;?>
                          </option>
<?php
                          endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_ptp">
                         <?=gettext("Set the interface as a point-to-point link. This is required for " .
                         "straight transitions to forwarding and should be enabled on a " .
                         "direct link to another RSTP-capable switch."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_autoptp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Auto PTP ports"); ?></td>
                      <td>
                        <select name="autoptp[]" class="selectpicker" multiple="multiple" size="3" data-live-search="true">
<?php
                          foreach ($ifacelist as $ifn => $ifdescr):?>
                          <option value="<?=$ifn;?>" <?=!empty($pconfig['autoptp']) && in_array($ifn, $pconfig['autoptp']) ? 'selected="selected"' : "";?>>
                            <?=$ifdescr;?>
                          </option>
<?php
                          endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_autoptp">
                         <?=gettext("Automatically detect the point-to-point status on interface by " .
                         "checking the full duplex link status. This is the default for " .
                         "interfaces added to the bridge."); ?><br/>
                         <span class="text-warning"><strong><?=gettext("Note:"); ?><br /></strong></span>
                         <?=gettext("The interfaces selected here will be removed from default autoedge status."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_static" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Sticky ports"); ?></td>
                      <td>
                        <select name="static[]" class="selectpicker" multiple="multiple" size="3" data-live-search="true">
<?php
                          foreach ($ifacelist as $ifn => $ifdescr):?>
                          <option value="<?=$ifn;?>" <?=!empty($pconfig['static']) && in_array($ifn, $pconfig['static']) ? "selected=\"selected\"" : "";?>>
                            <?=$ifdescr;?>
                          </option>
<?php
                          endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_static">
                          <?=gettext('Mark an interface as a "sticky" interface. Dynamically learned ' .
                          "address entries are treated as static once entered into the cache. " .
                          "Sticky entries are never aged out of the cache or " .
                          "replaced, even if the address is seen on a different interface."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_private" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Private ports"); ?></td>
                      <td>
                        <select name="private[]" class="selectpicker" multiple="multiple" size="3" data-live-search="true">
<?php
                          foreach ($ifacelist as $ifn => $ifdescr):?>
                          <option value="<?=$ifn;?>" <?=!empty($pconfig['private']) && in_array($ifn, $pconfig['private']) ? "selected=\"selected\"" : "";?>>
                            <?=$ifdescr;?>
                          </option>
<?php
                          endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_private">
                          <?=gettext('Mark an interface as a "private" interface. A private interface does not forward any traffic to any other port that is also ' .
                          "a private interface."); ?>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Advanced / RSTP/STP -->
            <div class="tab-content content-box col-xs-12 __mb">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tbody>
                    <tr>
                      <td style="width:22%">&nbsp;</td>
                      <td style="width:78%">
                        <input type="hidden" name="bridgeif" value="<?=$pconfig['bridgeif']; ?>" />
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                        <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/interfaces_bridge.php'" />
<?php if (isset($id)): ?>
                        <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php endif; ?>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </form>
        </section>
      </div>
    </div>
  </section>

<?php

include 'foot.inc';
