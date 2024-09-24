<?php

/*
 * Copyright (C) 2014-2022 Deciso B.V.
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
require_once("system.inc");
require_once("interfaces.inc");

function link_interface_to_group($int)
{
    global $config;

    $result = [];

    if (isset($config['ifgroups']['ifgroupentry'])) {
        foreach ($config['ifgroups']['ifgroupentry'] as $group) {
            if (in_array($int, preg_split('/[ |,]+/', $group['members']))) {
                $result[$group['ifname']] = $int;
            }
        }
    }

    return $result;
}

function list_devices($devices)
{
    global $config;

    $interfaces = [];

    /* add physical network interfaces */
    foreach (get_interface_list() as $key => $item) {
        $interfaces[$key] = ['descr' => $key . ' (' . $item['mac'] . ')', 'type' => 'hardware'];
    }

    /* add virtual network interfaces */
    foreach ($devices as $device) {
        if (!empty($device['names'])) {
            foreach ($device['names'] as $key => $values) {
                if (!empty($values)) {
                    $interfaces[$key] = $values;
                    $interfaces[$key]['type'] = $device['type'];
                }
            }
        }
    }

    /* enforce constraints */
    foreach ($interfaces as $id => $details) {
        if (!empty($details['exclude'])) {
            foreach ($details['exclude'] as $device) {
                if (isset($interfaces[$device])) {
                    unset($interfaces[$device]);
                }
            }
        }
    }

    return $interfaces;
}

$a_devices = plugins_devices();
$interfaces = list_devices($a_devices);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = [];
    if (isset($_POST['add_x']) && isset($_POST['if_add'])) {
        foreach (legacy_config_get_interfaces() as $ifname => $ifdata) {
            /* if interface is already used redirect */
            if ($ifdata['if'] == $_POST['if_add']) {
                header(url_safe('Location: /interfaces_assign.php'));
                exit;
            }
        }

        $is_ppp = false;
        foreach (config_read_array('ppps', 'ppp') as $ppp) {
            if ($ppp['if'] = $_POST['if_add']) {
                $is_ppp = true;
                break;
            }
        }

        if (!$is_ppp && !does_interface_exist($_POST['if_add'])) {
            $input_errors[] = sprintf(gettext('The interface "%s" does not exist. Make sure to apply its configuration first.'), $_POST['if_add']);
        }

        if (count($input_errors) == 0) {
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
            switch ($interfaces[$_POST['if_add']]['type']) {
                case 'ppp':
                    $config['interfaces'][$newifname]['ipaddr'] = $interfaces[$_POST['if_add']]['ipaddr'];
                    break;
                case 'wlan':
                    $config['interfaces'][$newifname]['wireless'] = [];
                    interface_sync_wireless_clones($config['interfaces'][$newifname], false);
                    break;
                default:
                    break;
            }

            write_config();

            header(url_safe('Location: /interfaces_assign.php'));
            exit;
        }
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
            interface_reset($id);

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
                    /* XXX this doesn't match floating rules with multiple values */
                    if (isset($rule['interface']) && $rule['interface'] == $id) {
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

            unset($config['interfaces'][$id]);

            write_config();

            header(url_safe('Location: /interfaces_assign.php'));
            exit;
        }
    } elseif (isset($_POST['Submit'])) { // ** Change interface **
        /* build a list of the device names so we can see how the interfaces map as submitted */
        $dev_if_map = [];
        foreach ($interfaces as $device => $unused) {
            $dev_if_map[$device] = [];
        }
        foreach ($_POST as $ifname => $ifdev) {
            if ($ifname == 'lan' || $ifname == 'wan' || substr($ifname, 0, 3) == 'opt') {
                $dev_if_map[$ifdev][] = $ifname;
            }
        }

        /* check the requested configuration for consistency */
        foreach ($dev_if_map as $devname => $ifnames) {
            if (count($ifnames) > 1) {
                $input_errors[] = sprintf(gettext('Device %s was assigned to %d interfaces: %s'), $devname, count($ifnames), implode(', ', $dev_if_map[$devname]));
            } elseif (count($ifnames) == 1 && preg_match('/^bridge[0-9]/', $devname)) {
                foreach (interface_parent_devices($devname, true) as $member) {
                    if ($member == $ifnames[0]) {
                        $input_errors[] = sprintf(gettext('You cannot set device %s to interface %s because it cannot be a member of itself.'), $devname, $member);
                        break;
                    }
                }
            }
        }

        /* prevent save when interface doesn't exist in case the user was pointed here after a config import */
        foreach ($_POST as $ifname => $ifdev) {
            if ($ifname == 'lan' || $ifname == 'wan' || substr($ifname, 0, 3) == 'opt') {
                if (!empty($ifdev) && !isset($interfaces[$ifdev])) {
                    $input_errors[] = sprintf(gettext("Device %s does not exist."), $ifdev);
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
          $changes = 0;

          foreach ($_POST as $ifname => $ifdev) {
              if (!is_array($ifdev) && ($ifname == 'lan' || $ifname == 'wan' || substr($ifname, 0, 3) == 'opt')) {
                  $reloadif = false;
                  if (!empty($config['interfaces'][$ifname]['if']) && $config['interfaces'][$ifname]['if'] != $ifdev) {
                      interface_reset($ifname);
                      $reloadif = true;
                  }
                  $config['interfaces'][$ifname]['if'] = $ifdev;

                  switch ($interfaces[$ifdev]['type']) {
                      case 'ppp':
                          $config['interfaces'][$ifname]['ipaddr'] = $interfaces[$ifdev]['ipaddr'];
                          break;
                      case 'wlan':
                          if (strpos($config['interfaces'][$ifname]['if'], '_wlan') === false) {
                              /* change from implied clone to explicit */
                              $config['interfaces'][$ifname]['if'] .= '_wlan0';
                          }
                          break;
                      default:
                          break;
                  }

                  foreach ($a_devices as $device) {
                      if ($device['configurable']) {
                          continue;
                      }
                      if (preg_match('/' . $device['pattern'] . '/', $ifdev)) {
                          unset($config['interfaces'][$ifname]['ipaddr']);
                          unset($config['interfaces'][$ifname]['subnet']);
                          unset($config['interfaces'][$ifname]['ipaddrv6']);
                          unset($config['interfaces'][$ifname]['subnetv6']);
                      }
                  }

                  /* set or clear wireless configuration */
                  if ($interfaces[$ifdev]['type'] == 'wlan') {
                      config_read_array('interfaces', $ifname, 'wireless');
                  } elseif (isset($config['interfaces'][$ifname]['wireless'])) {
                      unset($config['interfaces'][$ifname]['wireless']);
                  }

                  /* make sure there is a descr for all interfaces */
                  if (!isset($config['interfaces'][$ifname]['descr'])) {
                      $config['interfaces'][$ifname]['descr'] = strtoupper($ifname);
                  }

                  if ($reloadif) {
                      if (isset($config['interfaces'][$ifname]['wireless'])) {
                          interface_sync_wireless_clones($config['interfaces'][$ifname], false);
                      }
                      /* Reload all for the interface. */
                      interface_configure(false, $ifname, true);
                      // count changes
                      $changes++;
                  }
              }
          }
          /* XXX huh? */
          write_config();
          if ($changes > 0) {
              filter_configure();
          }
          header(url_safe('Location: /interfaces_assign.php'));
          exit;
        }
    }
}

legacy_html_escape_form_data($interfaces);
$unused_interfaces= array();
$all_interfaces = legacy_config_get_interfaces();
$ifdetails = legacy_interfaces_details();
$intfkeys = array_keys($interfaces);
natcasesort($intfkeys);
foreach ($intfkeys as $devname) {
    $devused = false;
    if (!empty($ifdetails[$devname]) && !empty($ifdetails[$devname]['status'])) {
        $interfaces[$devname]['status'] = $ifdetails[$devname]['status'];
    } elseif (empty($ifdetails[$devname])) {
        $interfaces[$devname]['status'] = 'no carrier';
    } else {
        /* quirky value to populate status key for virtual interfaces */
        $interfaces[$devname]['status'] = 'likely up';
    }
    foreach ($all_interfaces as $ifname => $ifdata) {
        if ($ifdata['if'] == $devname) {
            $devused = true;
            break;
        }
    }
    if (!$devused) {
        $unused_interfaces[$devname] = $interfaces[$devname];
    }
}

/* add missing interfaces so people know what to change */
foreach (legacy_config_get_interfaces(array("virtual" => false)) as $iface) {
    if (!empty($iface['if']) && !isset($interfaces[$iface['if']])) {
        $interfaces[$iface['if']] = [
            'descr' => sprintf('%s (%s)', $iface['if'], 'missing'),
            'status' => 'missing'
        ];
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
          <form method="post" name="iform" id="iform">
            <div class="tab-content content-box col-xs-12 __mb">
              <input type="hidden" id="action" name="action" value="">
              <input type="hidden" id="id" name="id" value="">
              <div class="table-responsive">
                <table class="table table-striped table-condensed table-hover">
                  <thead>
                    <tr>
                      <th><?= gettext('Interface') ?></th>
                      <th><?= gettext('Identifier') ?>
                          <span data-toggle="tooltip" title="<?=gettext("Technical identifier of the interface, used by hasync for example");?>">
                            <i style="cursor: pointer;" class="fa fa-question-circle" data-toggle="collapse" ></i>
                          </span>
                      </th>
                      <th><?= gettext('Device') ?></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
<?php foreach (legacy_config_get_interfaces(['virtual' => false]) as $ifname => $iface): ?>
                      <?= legacy_html_escape_form_data($iface) ?>
                      <tr>
                        <td>
                          <a href="/interfaces.php?if=<?= html_safe($ifname) ?>">[<?= $iface['descr'] ?>]</a>
                        </td>
                        <td><?= $ifname ?></td>
                        <td>
                          <select name="<?=$ifname;?>" id="<?=$ifname;?>"  class="selectpicker" data-size="10">
<?php foreach ($interfaces as $device => $info): ?>
                            <option data-icon="fa fa-plug <?=in_array($info['status'], ['no carrier', 'missing']) ? "text-danger": "text-success";?>"
                                    value="<?=$device;?>"  <?= $device == $iface['if'] ? " selected=\"selected\"" : "";?>>
                              <?=$info['descr'];?>
                            </option>
<?php endforeach ?>
                          </select>
                        </td>
                        <td>
<?php if (empty($iface['lock'])): ?>
                          <button title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip" data-id="<?=$ifname;?>" class="btn btn-default act_delete" type="submit">
                            <i class="fa fa-trash fa-fw"></i>
                          </button>
<?php endif ?>
                        </td>
                      </tr>
<?php endforeach ?>
                   </tbody>
                   <tfoot>
                      <tr>
                        <td colspan="2"></td>
                        <td><button name="Submit" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button></td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            <div class="tab-content content-box col-xs-12">
                <div class="table-responsive">
                <table class="table table-striped table-condensed">
<?php if (count($unused_interfaces)): ?>
                  <thead>
                    <tr>
                      <th colspan="2"><i class="fa fa-plus fa-fw"></i> <?= gettext('Assign a new interface') ?></th>
                    </tr>
                  </thead>
                  <tbody>
                      <tr>
                        <th><?= gettext('Device') ?></td>
                        <td>
                          <select name="if_add" id="if_add" class="selectpicker" data-size="10">
<?php foreach ($unused_interfaces as $device => $info): ?>
                            <option data-icon="fa fa-plug <?=$info['status'] == 'no carrier' ? "text-danger": "text-success";?>"
                                    data-ifdescr="<?=!empty($info['ifdescr']) ? $info['ifdescr'] : '';?>"
                                    value="<?=$device;?>">
                                    <?=$info['descr'];?>
                            </option>
<?php endforeach ?>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <th><?= gettext('Description') ?></th>
                        <td>
                          <input id="new_entry_descr" name="new_entry_descr" type="text" class="form-control">
                        </td>
                      </tr>
                  </tbody>
                  <tfoot>
                      <tr>
                        <td></td>
                        <td>
                          <button name="add_x" type="submit" class="btn btn-primary"><?= gettext('Add') ?></button>
                        </td>
                      </tr>
                  </tfoot>
<?php else: ?>
                  <thead>
                    <tr>
                      <th colspan="2"><i class="fa fa-minus fa-fw"></i> <?= gettext('No devices available for assignment') ?></th>
                    </tr>
                  </thead>
<?php endif ?>
                </table>
              </div>
            </div>
          </form>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc");
