<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2011 Warren Baker <warren@decoy.co.za>
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
require_once("plugins.inc.d/unbound.inc");

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
    $pconfig['aclname'] = isset($id) && !empty($a_acls[$id]['aclname']) ? $a_acls[$id]['aclname'] : '';
    $pconfig['aclaction'] = isset($id) && !empty($a_acls[$id]['aclaction']) ? $a_acls[$id]['aclaction'] : '';
    $pconfig['description'] = isset($id) && !empty($a_acls[$id]['description']) ? $a_acls[$id]['description'] : '';
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
        plugins_configure('dhcp');
        clear_subsystem_dirty('unbound');
        header(url_safe('Location: /services_unbound_acls.php'));
        exit;
    } elseif (!empty($act) && $act == "del") {
        if (isset($id) && !empty($a_acls[$id])) {
            unset($a_acls[$id]);
            write_config();
            mark_subsystem_dirty('unbound');
        }
        header(url_safe('Location: /services_unbound_acls.php'));
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

            write_config();
            mark_subsystem_dirty('unbound');
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
<script>
  $( document ).ready(function() {
    /**
     *  Aliases
     */
    function removeRow() {
        if ( $('#acl_networks_table > tbody > tr').length == 1 ) {
            $('#acl_networks_table > tbody > tr:last > td > input').each(function(){
              $(this).val('');
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
          $(this).val('');
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
        title: "<?= gettext('Unbound') ?>",
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
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <?php if (is_subsystem_dirty('unbound')): ?>
        <?php print_info_box_apply(gettext('The Unbound configuration has been changed.') . ' ' . gettext('You must apply the changes in order for them to take effect.')) ?>
        <?php endif; ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12 __mb">
            <form method="post" name="iform" id="iform">
<?php
              if ($act=="new" || $act=="edit"): ?>
              <input name="id" type="hidden" value="<?=$id;?>" />
              <input name="act" type="hidden" value="<?=$act;?>" />
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><strong><?=ucwords(sprintf(gettext("%s Access List"),$act));?></strong></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_aclname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Access List name");?></td>
                  <td>
                    <input name="aclname" type="text" value="<?=$pconfig['aclname'];?>" />
                    <div class="hidden" data-for="help_for_aclname">
                      <?=gettext("Provide an Access List name.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_aclaction" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Action");?></td>
                  <td>
                    <select name="aclaction" class="selectpicker">
                      <option value="allow" <?= $pconfig['aclaction'] == "allow" ? 'selected="selected"' : ''; ?>>
                      <?=gettext("Allow");?>
                      </option>
                      <option value="deny" <?= $pconfig['aclaction'] == "deny" ? 'selected="selected"' : ''; ?>>
                      <?=gettext("Deny");?>
                      </option>
                      <option value="refuse" <?= $pconfig['aclaction'] == "refuse" ? 'selected="selected"' : ''; ?>>
                      <?=gettext("Refuse");?>
                      </option>
                      <option value="allow snoop" <?= $pconfig['aclaction'] == "allow snoop" ? 'selected="selected"' : ''; ?>>
                      <?=gettext("Allow Snoop");?>
                      </option>
                      <option value="deny nonlocal" <?= $pconfig['aclaction'] == "deny nonlocal" ? 'selected="selected"' : ''; ?>>
                      <?=gettext("Deny Non-local");?>
                      </option>
                      <option value="refuse nonlocal" <?= $pconfig['aclaction'] == "refuse nonlocal" ? 'selected="selected"' : ''; ?>>
                      <?=gettext("Refuse Non-local");?>
                      </option>
                    </select>
                    <div class="hidden" data-for="help_for_aclaction">
                        <?=gettext("Choose what to do with DNS requests that match the criteria specified below.");?> <br />
                        <?=gettext("Deny: This action stops queries from hosts within the netblock defined below.")?> <br />
                        <?=gettext("Refuse: This action also stops queries from hosts within the netblock defined below, but sends a DNS rcode REFUSED error message back to the client.")?> <br />
                        <?=gettext("Allow: This action allows queries from hosts within the netblock defined below.")?> <br />
                        <?=gettext("Allow Snoop: This action allows recursive and nonrecursive access from hosts within the netblock defined below. Used for cache snooping and ideally should only be configured for your administrative host.")?> <br />
                        <?=gettext("Deny Non-local: Allow only authoritative local-data queries from hosts within the netblock defined below. Messages that are disallowed are dropped.")?> <br />
                        <?=gettext("Refuse Non-local: Allow only authoritative local-data queries from hosts within the netblock defined below. Sends a DNS rcode REFUSED error message back to the client for messages that are disallowed.")?>
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
                      foreach ($acl_networks as $item_idx => $item):?>
                        <tr>
                          <td>
                            <div style="cursor:pointer;" class="act-removerow btn btn-default btn-xs"><i class="fa fa-minus fa-fw"></i></div>
                          </td>
                          <td>
                            <input name="acl_networks_acl_network[]" type="text" id="acl_network_<?=$item_idx;?>" value="<?=$item['acl_network'];?>" />
                          </td>
                          <td>
                            <select name="acl_networks_mask[]" data-network-id="acl_network_<?=$item_idx;?>" class="selectpicker ipv4v6net" data-size="10" data-width="auto" id="mask<?=$item_idx;?>">
<?php for ($i = 128; $i >= 0; $i--): ?>
                              <option value="<?=$i;?>" <?= $item['mask'] == $i ? 'selected="selected"' : ''?>>
                                <?=$i;?>
                              </option>
<?php endfor ?>
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
                            <div id="addNew" style="cursor:pointer;" class="btn btn-default btn-xs"><i class="fa fa-plus fa-fw"></i></div>
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
                    <div class="hidden" data-for="help_for_description">
                      <?=gettext("You may enter a description here for your reference.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                      &nbsp;<br />&nbsp;
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Save')) ?>" />
                      <input type="button" class="btn btn-default" value="<?= html_safe(gettext('Cancel')) ?>" onclick="window.location.href='/services_unbound_acls.php'" />
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
                    <th><?=gettext("Access List Name"); ?></th>
                    <th><?=gettext("Action"); ?></th>
                    <th><?=gettext("Network"); ?></th>
                  </tr>
                </thead>
                <tbody>
<?php foreach (unbound_acls_subnets() as $subnet): ?>
                  <tr>
                    <td><?= gettext('Internal') ?></td>
                    <td><?= gettext('Allow') ?></td>
                    <td><?= $subnet ?></td>
                  </tr>
<?php endforeach ?>
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
                    <th class="text-nowrap">
<?php if (!isset($_GET['act'])): ?>
                      <a href="services_unbound_acls.php?act=new" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                        <i class="fa fa-plus fa-fw"></i>
                      </a>
<?php endif ?>
                    </th>
                  </tr>
                </thead>
                <tbody>
<?php
                  $i = 0;
                  foreach ($a_acls as $acl):?>
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
                    <td class="text-nowrap">
                      <a href="services_unbound_acls.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                      <a href="#" data-id="<?=$i;?>" class="act_delete_acl btn btn-xs btn-default"><i class="fa fa-trash fa-fw"></i></a>
                    </td>
                  </tr>
<?php
                  $i++;
                  endforeach;?>
                </tbody>
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
