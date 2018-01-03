<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2011 Warren Baker <warren@decoy.co.za>
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
require_once("system.inc");
require_once("services.inc");
require_once("interfaces.inc");

$a_acls = &config_read_array('unbound', 'acls');


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_acls[$_GET['id']])) {
        $id = $_GET['id'];
    }
    if (!empty($_GET['act'])) {
        $act = $_GET['act'];
    } else {
        $act = null;
    }
    $pconfig = array();
    $pconfig['aclname'] = isset($id) && !empty($a_acls[$id]['aclname']) ? $a_acls[$id]['aclname'] : "";
    $pconfig['aclaction'] = isset($id) && !empty($a_acls[$id]['aclaction']) ? $a_acls[$id]['aclaction'] : "";
    $pconfig['description'] = isset($id) && !empty($a_acls[$id]['description']) ? $a_acls[$id]['description'] : "";
    $pconfig['row'] = isset($id) && !empty($a_acls[$id]['row']) ? $a_acls[$id]['row'] : array();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    if (isset($_POST['id']) && !empty($a_acls[$_POST['id']])) {
        $id = $_POST['id'];
    }
    if (!empty($_POST['act'])) {
        $act = $_POST['act'];
    } else {
        $act = null;
    }

    if (!empty($pconfig['apply'])) {
        unbound_configure_do();
        services_dhcpd_configure();
        clear_subsystem_dirty('unbound');
        header(url_safe('Location: /services_unbound_acls.php'));
        exit;
    } elseif (!empty($act) && $act == "del") {
        if (isset($id) && !empty($a_acls[$id])) {
            unset($a_acls[$id]);
            write_config();
            mark_subsystem_dirty('unbound');
        }
        exit;
    } else {
        // transform networks into row items
        $pconfig['row'] = array();
        foreach ($pconfig['acl_networks_acl_network'] as $acl_network_idx => $acl_network) {
            if (!empty($acl_network)) {
                $pconfig['row'][] = array('acl_network' => $acl_network,
                                          'mask' => $pconfig['acl_networks_mask'][$acl_network_idx],
                                          'description' => $pconfig['acl_networks_description'][$acl_network_idx]
                                        );
            }
        }

        // validate form data
        foreach ($pconfig['row'] as $row) {
            if (!is_ipaddr($row['acl_network'])) {
                $input_errors[] = gettext("You must enter a valid network IP address for {$row['acl_network']}.");
            } elseif (!is_subnet($row['acl_network']."/".$row['mask'])) {
                $input_errors[] = gettext("You must enter a valid netmask for {$row['acl_network']}/{$row['mask']}.");
            }
        }
        // save form data
        if (count($input_errors) == 0) {
            $acl_entry = array();
            $acl_entry['aclname'] = $pconfig['aclname'];
            $acl_entry['aclaction'] = $pconfig['aclaction'];
            $acl_entry['description'] = $pconfig['description'];
            $acl_entry['row'] = $pconfig['row'];

            if (isset($id)) {
                $a_acls[$id] = $acl_entry;
            } else {
                $a_acls[] = $acl_entry;
            }
            mark_subsystem_dirty("unbound");
            write_config();
            header(url_safe('Location: /services_unbound_acls.php'));
            exit;
        }
    }
}

$service_hook = 'unbound';
legacy_html_escape_form_data($pconfig);
include("head.inc");

?>

<body>
<script type="text/javascript">
  $( document ).ready(function() {
    /**
     *  Aliases
     */
    function removeRow() {
        if ( $('#acl_networks_table > tbody > tr').length == 1 ) {
            $('#acl_networks_table > tbody > tr:last > td > input').each(function(){
              $(this).val("");
            });
        } else {
            $(this).parent().parent().remove();
        }
    }
    // add new detail record
    $("#addNew").click(function(){
        // copy last row and reset values
        $('#acl_networks_table > tbody').append('<tr>'+$('#acl_networks_table > tbody > tr:last').html()+'</tr>');
        $('#acl_networks_table > tbody > tr:last > td > input').each(function(){
          $(this).val("");
        });
        //  link network / cidr
        var item_cnt = $('#acl_networks_table > tbody > tr').length;
        $('#acl_networks_table > tbody > tr:last > td:eq(1) > input').attr('id', 'acl_network_n'+item_cnt);
        $('#acl_networks_table > tbody > tr:last > td:eq(2) > select').data('network-id', 'acl_network_n'+item_cnt);
        $(".act-removerow").click(removeRow);
        // hookin ipv4/v6 for new item
        hook_ipv4v6('ipv4v6net', 'network-id');
    });
    $(".act-removerow").click(removeRow);
    // hook in, ipv4/ipv6 selector events
    hook_ipv4v6('ipv4v6net', 'network-id');

    // delete ACL action
    $(".act_delete_acl").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("DNS Resolver");?>",
        message: "<?=gettext("Do you really want to delete this access list?"); ?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'del', id:id}, function(data) {
                        location.reload();
                    });
                }
              }]
      });
    });

  });
</script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
        if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors);
        if (isset($savemsg)) print_info_box($savemsg);
        if (is_subsystem_dirty("unbound")) print_info_box_apply(gettext("The configuration for the DNS Resolver, has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
        ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
<?php
              if($act=="new" || $act=="edit"): ?>
              <input name="id" type="hidden" value="<?=$id;?>" />
              <input name="act" type="hidden" value="<?=$act;?>" />
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td width="22%"><strong><?=ucwords(sprintf(gettext("%s Access List"),$act));?></strong></td>
                  <td width="78%" align="right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_aclname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Access List name");?></td>
                  <td>
                    <input name="aclname" type="text" value="<?=$pconfig['aclname'];?>" />
                    <div class="hidden" for="help_for_aclname">
                      <?=gettext("Provide an Access List name.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_aclaction" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Action");?></td>
                  <td>
                    <select name="aclaction" class="selectpicker">
                      <option value="allow" <?= $pconfig['aclaction'] == "allow" ? "selected=\"selected\"" : ""; ?>>
                      <?=gettext("Allow");?>
                      </option>
                      <option value="deny" <?= $pconfig['aclaction'] == "deny" ? "selected=\"selected\"" : ""; ?>>
                      <?=gettext("Deny");?>
                      </option>
                      <option value="refuse" <?= $pconfig['aclaction'] == "refuse" ? "selected=\"selected\"" : ""; ?>>
                      <?=gettext("Refuse");?>
                      </option>
                      <option value="allow snoop" <?= $pconfig['aclaction'] == "allow snoop" ? "selected=\"selected\"" : ""; ?>>
                      <?=gettext("Allow Snoop");?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_aclaction">
                        <?=gettext("Choose what to do with DNS requests that match the criteria specified below.");?> <br />
                        <?=gettext("Deny: This action stops queries from hosts within the netblock defined below.")?> <br />
                        <?=gettext("Refuse: This action also stops queries from hosts within the netblock defined below, but sends a DNS rcode REFUSED error message back to the client.")?> <br />
                        <?=gettext("Allow: This action allows queries from hosts within the netblock defined below.")?> <br />
                        <?=gettext("Allow Snoop: This action allows recursive and nonrecursive access from hosts within the netblock defined below. Used for cache snooping and ideally should only be configured for your administrative host.")?> <br />
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Networks");?></td>
                  <td>
                    <table class="table table-striped table-condensed" id="acl_networks_table">
                      <thead>
                        <tr>
                          <th></th>
                          <th><?=gettext("Network"); ?></th>
                          <th><?=gettext("CIDR"); ?></th>
                          <th><?=gettext("Description");?></th>
                        </tr>
                      </thead>
                      <tbody>
<?php
                      if (empty($pconfig['row'])) {
                          $acl_networks = array();
                          $acl_networks[] = array('acl_network' => null, 'mask' => 32, 'description' => null);
                      } else {
                          $acl_networks = $pconfig['row'];
                      }
                      foreach($acl_networks as $item_idx => $item):?>
                        <tr>
                          <td>
                            <div style="cursor:pointer;" class="act-removerow btn btn-default btn-xs" alt="remove"><span class="glyphicon glyphicon-minus"></span></div>
                          </td>
                          <td>
                            <input name="acl_networks_acl_network[]" type="text" id="acl_network_<?=$item_idx;?>" value="<?=$item['acl_network'];?>" />
                          </td>
                          <td>
                            <select name="acl_networks_mask[]" data-network-id="acl_network_<?=$item_idx;?>" class="ipv4v6net" id="mask<?=$item_idx;?>">
<?php
                              for ($i = 128; $i > 0; $i--):?>
                              <option value="<?=$i;?>" <?= $item['mask'] == $i ?  "selected=\"selected\"" : ""?>>
                                <?=$i;?>
                              </option>
<?php
                              endfor;?>
                            </select>
                          </td>
                          <td>
                            <input name="acl_networks_description[]" type="text" value="<?=$item['description'];?>" />
                          </td>
                        </tr>
<?php
                      endforeach;?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <td colspan="4">
                            <div id="addNew" style="cursor:pointer;" class="btn btn-default btn-xs" alt="add"><span class="glyphicon glyphicon-plus"></span></div>
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                  <td>
                    <input name="description" type="text" value="<?=$pconfig['description'];?>" />
                    <div class="hidden" for="help_for_description">
                      <?=gettext("You may enter a description here for your reference.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                      &nbsp;<br />&nbsp;
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='/services_unbound_acls.php'" />
                  </td>
                </tr>
              </table>
            </form>
<?php
            else:?>
            <form method="post" name="iform" id="iform">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th colspan="4"><?=gettext("From General settings");?></th>
                  </tr>
                  <tr>
                    <th><?=gettext("Access List Name"); ?></th>
                    <th><?=gettext("Action"); ?></th>
                    <th><?=gettext("Network"); ?></th>
                    <th><a href="services_unbound.php" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a></th>
                  </tr>
                </thead>
                <body>
<?php
                  // collect networks where automatic rules will be created for
                  if (!empty($config['unbound']['active_interface'])) {
                      $active_interfaces = array_flip(explode(",", $config['unbound']['active_interface']));
                  } else {
                      $active_interfaces = get_configured_interface_with_descr();
                  }
                  $automatic_allowed = array();
                  foreach($active_interfaces as $ubif => $ifdesc) {
                      $ifip = get_interface_ip($ubif);
                      if (!empty($ifip)) {
                          $subnet_bits = get_interface_subnet($ubif);
                          $subnet_ip = gen_subnet($ifip, $subnet_bits);
                          if (!empty($subnet_bits) && !empty($subnet_ip)) {
                              $automatic_allowed[] = "{$subnet_ip}/{$subnet_bits}";
                          }
                      }
                      $ifip = get_interface_ipv6($ubif);
                      if (!empty($ifip)) {
                          $subnet_bits = get_interface_subnetv6($ubif);
                          $subnet_ip = gen_subnetv6($ifip, $subnet_bits);
                          if (!empty($subnet_bits) && !empty($subnet_ip)) {
                              $automatic_allowed[] = "{$subnet_ip}/{$subnet_bits}";
                          }
                      }
                  }
                  foreach ($automatic_allowed as $network):?>
                  <tr>
                    <td><?=gettext("Internal");?></td>
                    <td><?=gettext("allow");?></td>
                    <td><?=$network;?></td>
                    <td></td>
                  </tr>
<?php
                  endforeach;?>
                </tbody>
              </table>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?=gettext("Access List Name"); ?></th>
                    <th><?=gettext("Action"); ?></th>
                    <th><?=gettext("Description"); ?></th>
                    <th></th>
                  </tr>
                </thead>
<?php
                  $i = 0;
                  foreach($a_acls as $acl):?>
                  <tr>
                    <td>
                      <?=htmlspecialchars($acl['aclname']);?>
                    </td>
                    <td>
                      <?=htmlspecialchars($acl['aclaction']);?>
                    </td>
                    <td>
                      <?=htmlspecialchars($acl['description']);?>
                    </td>
                    <td>
                      <a href="services_unbound_acls.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
                      <a href="#" data-id="<?=$i;?>" class="act_delete_acl btn btn-xs btn-default"><i class="fa fa-trash text-muted"></i></a>
                    </td>
                  </tr>
<?php
                  $i++;
                  endforeach;?>
                <tfoot>
                  <tr>
                    <td colspan="3"></td>
                    <td>
                      <a href="services_unbound_acls.php?act=new" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="4">
                      <p>
                        <?=gettext("Access Lists to control access to the DNS Resolver can be defined here.");?>
                      </p>
                    </td>
                  </tr>
                </tfoot>
              </table>
<?php
            endif; ?>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
