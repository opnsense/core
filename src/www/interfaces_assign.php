<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2004 Jim McBeath
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
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
require_once("rrd.inc");
require_once("system.inc");
require_once("interfaces.inc");

function link_interface_to_vlans($int)
{
    global $config;

    if (isset($config['vlans']['vlan'])) {
        foreach ($config['vlans']['vlan'] as $vlan) {
            if ($int == $vlan['if']) {
                interfaces_bring_up($int);
            }
        }
    }
}


function list_interfaces() {
    global $config;
    $interfaces  = array();

    // define config sections to fetch interfaces from.
    $config_sections = array();
    $config_sections['wireless.clone'] = array('descr' => 'cloneif,descr', 'key' => 'cloneif', 'format' => '%s (%s)');
    $config_sections['vlans.vlan'] = array('descr' => 'tag,if,descr', 'format' => gettext('vlan %s on %s') . ' (%s)', 'key' => 'vlanif');
    $config_sections['bridges.bridged'] = array('descr' => 'bridgeif, descr', 'key' => 'bridgeif', 'format' => '%s (%s)');
    $config_sections['gifs.gif'] = array('descr' => 'remote-addr,descr', 'key' => 'gifif', 'format' => 'gif %s (%s)');
    $config_sections['gres.gre'] = array('descr' => 'remote-addr,descr', 'key' => 'greif', 'format' => 'gre %s (%s)');
    $config_sections['laggs.lagg'] = array('descr' => 'laggif,descr', 'key' => 'laggif', 'format' => '%s (%s)', 'fields' => 'members');
    $config_sections['ppps.ppp'] = array('descr' => 'if,ports,descr,username', 'key' => 'if','format' => '%s (%s) - %s %s', 'fields' => 'type');

    // add physical network interfaces
    foreach (get_interface_list() as $key => $intf_item) {
        if (match_wireless_interface($key)) {
            continue;
        }
        if (preg_match('/_stf$/', $key)) {
            continue;
        }
        $interfaces[$key] = array('descr' => $key . ' (' . $intf_item['mac'] . ')', 'section' => 'interfaces');
    }
    // collect interfaces from defined config sections
    foreach ($config_sections as $key => $value) {
        $cnf_location = explode(".", $key);
        if (!empty($config[$cnf_location[0]][$cnf_location[1]])) {
            foreach ($config[$cnf_location[0]][$cnf_location[1]] as $cnf_item) {
                $interface_item = array("section" => $key);
                // construct item description
                $descr = array();
                foreach (explode(',', $value['descr']) as $fieldname) {
                    if (isset($cnf_item[trim($fieldname)])) {
                        $descr[] = $cnf_item[trim($fieldname)];
                    } else {
                        $descr[] = "";
                    }
                }
                if (!empty($value['format'])) {
                    $interface_item['descr'] = vsprintf($value['format'], $descr);
                } else {
                    $interface_item['descr'] = implode(" ", $descr);
                }
                // copy requested additional fields into temp structure
                if (isset($value['fields'])) {
                    foreach (explode(',', $value['fields']) as $fieldname) {
                        if (isset($cnf_item[$fieldname])) {
                            $interface_item[$fieldname] = $cnf_item[$fieldname];
                        }
                    }
                }
                $interface_item['ifdescr'] = !empty($cnf_item['descr']) ? $cnf_item['descr'] : null;
                $interfaces[$cnf_item[$value['key']]] = $interface_item;
            }
        }
    }
    // XXX: get_interface_list() should probably be replaced at some point to avoid traversing through the config
    //       for all these virtual interfaces
    $loopbacks = iterator_to_array((new \OPNsense\Interfaces\Loopback())->loopback->iterateItems());
    foreach ($loopbacks as $loopback) {
        $interfaces["lo".(string)$loopback->deviceId] = array(
          'descr' => sprintf("lo%s (%s)", $loopback->deviceId,  $loopback->description),
          'ifdescr' => sprintf("%s", $loopback->description),
          'section' => 'loopback');
    }

    // enforce constraints
    foreach ($interfaces as $intf_id => $intf_details) {
        // LAGG members cannot be assigned
        if (isset($intf_details['members']) && $intf_details['section'] == 'laggs.lagg') {
            foreach (explode(',', ($intf_details['members'])) as $intf) {
                if (isset($interfaces[trim($intf)])) {
                    unset($interfaces[trim($intf)]);
                }
            }
        }
    }

    return $interfaces;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    if (isset($_POST['add_x']) && isset($_POST['if_add'])) {
        /* if interface is already used redirect */
        foreach (legacy_config_get_interfaces() as $ifname => $ifdata) {
            if ($ifdata['if'] == $_POST['if_add']) {
                header(url_safe('Location: /interfaces_assign.php'));
                exit;
            }
        }

        /* find next free optional interface number */
        for ($i = 1; $i <= count($config['interfaces']); $i++) {
            if (empty($config['interfaces']["opt{$i}"])) {
                break;
            }
        }

        $newifname = 'opt' . $i;
        $descr = !empty($_POST['new_entry_descr']) ? $_POST['new_entry_descr'] : 'OPT' . $i;
        $config['interfaces'][$newifname] = array();
        $config['interfaces'][$newifname]['descr'] = preg_replace('/[^a-z_0-9]/i', '', $descr);
        $config['interfaces'][$newifname]['if'] = $_POST['if_add'];
        $interfaces = list_interfaces();
        if ($interfaces[$_POST['if_add']]['section'] == 'ppps.ppp') {
            $config['interfaces'][$newifname]['ipaddr'] = $interfaces[$_POST['if_add']]['type'];
        }
        if (match_wireless_interface($_POST['if_add'])) {
            $config['interfaces'][$newifname]['wireless'] = array();
            interface_sync_wireless_clones($config['interfaces'][$newifname], false);
        }

        write_config();
        header(url_safe('Location: /interfaces_assign.php'));
        exit;
    } elseif (!empty($_POST['id']) && !empty($_POST['action']) && $_POST['action'] == 'del' & !empty($config['interfaces'][$_POST['id']]) ) {
        // ** Delete interface **
        $id = $_POST['id'];
        if (link_interface_to_group($id)) {
            $input_errors[] = gettext("The interface is part of a group. Please remove it from the group to continue");
        } elseif (link_interface_to_bridge($id)) {
            $input_errors[] = gettext("The interface is part of a bridge. Please remove it from the bridge to continue");
        } elseif (link_interface_to_gre($id)) {
            $input_errors[] = gettext("The interface is part of a gre tunnel. Please delete the tunnel to continue");
        } elseif (link_interface_to_gif($id)) {
            $input_errors[] = gettext("The interface is part of a gif tunnel. Please delete the tunnel to continue");
        } else {
            // no validation errors, delete entry
            unset($config['interfaces'][$id]['enable']);
            $realid = get_real_interface($id);
            interface_bring_down($id);   /* down the interface */

            unset($config['interfaces'][$id]);  /* delete the specified OPTn or LAN*/

            if (isset($config['dhcpd'][$id])) {
                unset($config['dhcpd'][$id]);
                plugins_configure('dhcp', false, array('inet'));
            }
            if (isset($config['dhcpdv6'][$id])) {
                unset($config['dhcpdv6'][$id]);
                plugins_configure('dhcp', false, array('inet6'));
            }
            if (isset($config['filter']['rule'])) {
                foreach ($config['filter']['rule'] as $x => $rule) {
                    if ($rule['interface'] == $id) {
                        unset($config['filter']['rule'][$x]);
                    }
                }
            }
            if (isset($config['nat']['rule'])) {
                foreach ($config['nat']['rule'] as $x => $rule) {
                    if ($rule['interface'] == $id) {
                        unset($config['nat']['rule'][$x]['interface']);
                    }
                }
            }

            write_config();

            /* If we are in firewall/routing mode (not single interface)
             * then ensure that we are not running DHCP on the wan which
             * will make a lot of ISP's unhappy.
             */
            if (!empty($config['interfaces']['lan']) && !empty($config['dhcpd']['wan']) && !empty($config['dhcpd']['wan']) ) {
                unset($config['dhcpd']['wan']);
            }
            link_interface_to_vlans($realid);
            header(url_safe('Location: /interfaces_assign.php'));
            exit;
        }
    } elseif (isset($_POST['Submit'])) {
        // ** Change interface **
        /* input validation */
        /* Build a list of the port names so we can see how the interfaces map */
        $portifmap = array();
        $interfaces = list_interfaces();
        foreach ($interfaces as $portname => $portinfo) {
            $portifmap[$portname] = array();
        }

        /* Go through the list of ports selected by the user,
        build a list of port-to-interface mappings in portifmap */
        foreach ($_POST as $ifname => $ifport) {
            if ($ifname == 'lan' || $ifname == 'wan' || substr($ifname, 0, 3) == 'opt') {
                $portifmap[$ifport][] = strtoupper($ifname);
            }
        }

        /* Deliver error message for any port with more than one assignment */
        foreach ($portifmap as $portname => $ifnames) {
            if (count($ifnames) > 1) {
              $errstr = sprintf(gettext('Port %s was assigned to %d interfaces:'), $portname, count($ifnames));
              foreach ($portifmap[$portname] as $ifn) {
                  $errstr .= " " . $ifn;
              }
              $input_errors[] = $errstr;
            } elseif (count($ifnames) == 1 && preg_match('/^bridge[0-9]/', $portname) && isset($config['bridges']['bridged'])) {
                foreach ($config['bridges']['bridged'] as $bridge) {
                    if ($bridge['bridgeif'] != $portname) {
                        continue;
                    }

                    $members = explode(",", strtoupper($bridge['members']));
                    foreach ($members as $member) {
                        if ($member == $ifnames[0]) {
                            $input_errors[] = sprintf(gettext("You cannot set port %s to interface %s because this interface is a member of %s."), $portname, $member, $portname);
                            break;
                        }
                    }
                }
            }
        }

        if (isset($config['vlans']['vlan'])) {
            foreach ($config['vlans']['vlan'] as $vlan) {
                if (!does_interface_exist($vlan['if'])) {
                    $input_errors[] = sprintf(gettext("VLAN parent interface %s does not exist."), $vlan['if']);
                }
            }
        }

        if (count($input_errors) == 0) {
          /* No errors detected, so update the config */
          $changes = 0;
          foreach ($_POST as $ifname => $ifport) {
              if (!is_array($ifport) && ($ifname == 'lan' || $ifname == 'wan' || substr($ifname, 0, 3) == 'opt')) {
                  $reloadif = false;
                  if (!empty($config['interfaces'][$ifname]['if']) && $config['interfaces'][$ifname]['if'] != $ifport) {
                      interface_bring_down($ifname);
                      /* Mark this to be reconfigured in any case. */
                      $reloadif = true;
                  }
                  $config['interfaces'][$ifname]['if'] = $ifport;
                  if ($interfaces[$ifport]['section'] == 'ppps.ppp') {
                      $config['interfaces'][$ifname]['ipaddr'] = $interfaces[$ifport]['type'];
                  }

                  foreach (plugins_devices() as $device) {
                      if (!isset($device['configurable']) || $device['configurable'] == true) {
                          continue;
                      }
                      if (preg_match('/' . $device['pattern'] . '/', $ifport)) {
                          unset($config['interfaces'][$ifname]['ipaddr']);
                          unset($config['interfaces'][$ifname]['subnet']);
                          unset($config['interfaces'][$ifname]['ipaddrv6']);
                          unset($config['interfaces'][$ifname]['subnetv6']);
                      }
                  }

                  /* check for wireless interfaces, set or clear ['wireless'] */
                  if (match_wireless_interface($ifport)) {
                      config_read_array('interfaces', $ifname, 'wireless');
                  } elseif (isset($config['interfaces'][$ifname]['wireless'])) {
                      unset($config['interfaces'][$ifname]['wireless']);
                  }

                  /* make sure there is a descr for all interfaces */
                  if (!isset($config['interfaces'][$ifname]['descr'])) {
                      $config['interfaces'][$ifname]['descr'] = strtoupper($ifname);
                  }


                  if ($reloadif) {
                      if (match_wireless_interface($ifport)) {
                          interface_sync_wireless_clones($config['interfaces'][$ifname], false);
                      }
                      /* Reload all for the interface. */
                      interface_configure(false, $ifname, true);
                      // count changes
                      $changes++;
                  }
              }
          }
          write_config();
          if ($changes > 0) {
              // reload filter, rrd when interfaces have changed (original from apply action)
              filter_configure();
              rrd_configure();
          }
          header(url_safe('Location: /interfaces_assign.php'));
          exit;
        }
    }
}

/* collect (unused) interfaces */
$interfaces = list_interfaces();
legacy_html_escape_form_data($interfaces);
$unused_interfaces= array();
$all_interfaces = legacy_config_get_interfaces();
$ifdetails = legacy_interfaces_details();
$intfkeys = array_keys($interfaces);
natcasesort($intfkeys);
foreach ($intfkeys as $portname) {
    $portused = false;
    if (!empty($ifdetails[$portname]) && !empty($ifdetails[$portname]['status'])) {
        $interfaces[$portname]['status'] = $ifdetails[$portname]['status'];
    }
    foreach ($all_interfaces as $ifname => $ifdata) {
        if ($ifdata['if'] == $portname) {
            $portused = true;
            break;
        }
    }
    if (!$portused) {
        $unused_interfaces[$portname] = $interfaces[$portname];
    }
}

include("head.inc");
?>

<body>
  <script>
  $( document ).ready(function() {
    // link delete buttons
    $(".act_delete").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Interfaces");?>",
        message: "<?=gettext("Do you really want to delete this interface?"); ?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val(id);
                    $("#action").val("del");
                    $("#iform").submit()
                }
              }]
      });
    });

    $("#if_add").change(function(event){
        event.preventDefault();
        let descr = $("#if_add option:selected").data('ifdescr');
        if (descr) {
            $("#new_entry_descr").val(descr);
        }
    });

  });
  </script>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
      if (isset($input_errors) && count($input_errors) > 0) {
          print_input_errors($input_errors);
      }?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form  method="post" name="iform" id="iform">
              <input type="hidden" id="action" name="action" value="">
              <input type="hidden" id="id" name="id" value="">

              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?=gettext("Interface"); ?></th>
                      <th><?=gettext("Network port"); ?></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  foreach (legacy_config_get_interfaces(array("virtual" => false)) as $ifname => $iface):?>
                      <?=legacy_html_escape_form_data($iface);?>
                      <tr>
                        <td>
                          <strong><u><span onclick="location.href='/interfaces.php?if=<?=$ifname;?>'" style="cursor: pointer;"><?=$iface['descr'];?></span></u></strong>
                        </td>
                        <td>
                          <select name="<?=$ifname;?>" id="<?=$ifname;?>"  class="selectpicker" data-size="10">
<?php
                          foreach ($interfaces as $portname => $portinfo):?>
                            <option data-icon="fa fa-plug <?=$portinfo['status'] == 'no carrier' ? "text-danger": "text-success";?>"
                                    value="<?=$portname;?>"  <?= $portname == $iface['if'] ? " selected=\"selected\"" : "";?>>
                              <?=$portinfo['descr'];?>
                            </option>
<?php
                          endforeach;?>
                          </select>
                        </td>
                        <td>
<?php
                          if (empty($iface['lock'])): ?>
                          <button title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip" data-id="<?=$ifname;?>" class="btn btn-default act_delete" type="submit">
                            <i class="fa fa-trash fa-fw"></i>
                          </button>
<?php
                          endif ?>
                        </td>
                      </tr>
<?php
                      endforeach;
                      if (count($unused_interfaces) > 0):?>
                      <tr>
                        <td><?= gettext('New interface:') ?></td>
                        <td>
                          <select name="if_add" id="if_add" class="selectpicker" data-size="10">
<?php
                          foreach ($unused_interfaces as $portname => $portinfo): ?>
                            <option data-icon="fa fa-plug <?=$portinfo['status'] == 'no carrier' ? "text-danger": "text-success";?>"
                                    data-ifdescr="<?=!empty($portinfo['ifdescr']) ? $portinfo['ifdescr'] : '';?>"
                                    value="<?=$portname;?>">
                                    <?=$portinfo['descr'];?>
                            </option>
<?php
                          endforeach; ?>
                          </select>
                          <div class="form-group">
                            <label for="new_entry_descr"><?=gettext("Description");?></label>
                            <input id="new_entry_descr" name="new_entry_descr" type="text" class="form-control">
                          </form>
                        </td>
                        <td>
                          <button name="add_x" type="submit" value="<?=$portname;?>" class="btn btn-primary" title="<?= html_safe(gettext('Add')) ?>" data-toggle="tooltip">
                            <i class="fa fa-plus fa-fw"></i>
                          </button>
                        </td>
                      </tr>
<?php
                      endif; ?>
                      <tr>
                        <td colspan="2"></td>
                        <td>
                          <button name="Submit" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc");
