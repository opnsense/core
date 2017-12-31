<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
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
require_once("services.inc");
require_once("interfaces.inc");

$a_gateway_groups = &config_read_array('gateways', 'gateway_group');
$a_gateways = return_gateways_array();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && isset($a_gateway_groups[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    } elseif (isset($_GET['dup']) && isset($a_gateway_groups[$_GET['dup']])) {
        $configId = $_GET['dup'];
    }
    $pconfig=array();
    if (isset($configId)) {
        $pconfig['name'] = $a_gateway_groups[$configId]['name'];
        $pconfig['item'] = &$a_gateway_groups[$configId]['item'];
        $pconfig['descr'] = $a_gateway_groups[$configId]['descr'];
        $pconfig['trigger'] = $a_gateway_groups[$configId]['trigger'];
    } else {
        $pconfig['name'] = null;
        $pconfig['descr'] = null;
        $pconfig['trigger'] = null;
        $pconfig['item'] = array();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($_POST['id']) && isset($a_gateway_groups[$_POST['id']])) {
        $id = $_POST['id'];
    }

    $input_errors = array();
    /* input validation */
    $reqdfields = explode(" ", "name");
    $reqdfieldsn = explode(",", "Name");

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (empty($pconfig['name'])) {
        $input_errors[] = gettext("A valid gateway group name must be specified.");
    }

    $valid = is_validaliasname($pconfig['name']);
    if ($valid === false) {
        $input_errors[] = sprintf(gettext('The name must be less than 32 characters long and may only consist of the following characters: %s'), 'a-z, A-Z, 0-9, _');
    } elseif ($valid === null) {
        $input_errors[] = sprintf(gettext('The name cannot be the internally reserved keyword "%s".'), $pconfig['name']);
    }

    if (!empty($pconfig['name'])) {
        /* check for overlaps */
        if (is_array($a_gateway_groups)) {
            foreach ($a_gateway_groups as $gateway_group) {
                if (isset($id) && ($a_gateway_groups[$id]) && ($a_gateway_groups[$id] === $gateway_group)) {
                    if ($gateway_group['name'] != $pconfig['name']) {
                        $input_errors[] = gettext("Changing name on a gateway group is not allowed.");
                    }
                    continue;
                }

                if ($gateway_group['name'] == $pconfig['name']) {
                    $input_errors[] = sprintf(gettext('A gateway group with this name "%s" already exists.'), $pconfig['name']);
                    break;
                }
            }
        }
    }

    /* Build list of items in group with priority */
    $pconfig['item'] = array();
    foreach ($a_gateways as $gwname => $gateway) {
        if (isset($pconfig[$gwname]) && $pconfig[$gwname] > 0) {
            $vipname = "{$gwname}_vip";
            /* we have a priority above 0 (disabled), add item to list */
            $pconfig['item'][] = "{$gwname}|{$pconfig[$gwname]}|{$pconfig[$vipname]}";
        }
        /* check for overlaps */
        if ($pconfig['name'] == $gwname) {
            $input_errors[] = sprintf(gettext('A gateway group cannot have the same name with a gateway "%s" please choose another name.'), $pconfig['name']);
        }

    }
    if (count($pconfig['item']) == 0) {
        $input_errors[] = gettext("No gateway(s) have been selected to be used in this group");
    }

    if (count($input_errors) == 0) {
        $gateway_group = array();
        $gateway_group['name'] = $pconfig['name'];
        $gateway_group['item'] = $pconfig['item'];
        $gateway_group['trigger'] = $pconfig['trigger'];
        $gateway_group['descr'] = $pconfig['descr'];

        if (isset($id)) {
            $a_gateway_groups[$id] = $gateway_group;
        } else {
            $a_gateway_groups[] = $gateway_group;
        }

        mark_subsystem_dirty('staticroutes');
        mark_subsystem_dirty('gwgroup.' . $gateway_group['name']);

        write_config();

        header(url_safe('Location: /system_gateway_groups.php'));
        exit;
    }
}

legacy_html_escape_form_data($a_gateways);
legacy_html_escape_form_data($pconfig);

$service_hook = 'apinger';

include("head.inc");

?>

<script type="text/javascript">
$( document ).ready(function() {
    // force protocol on initial selection (only relevant for new items)
    $("select.act-tier-change").change(function(){
      var proto = $(this).data('proto');
      $("select.act-tier-change").each(function(){
            if ($(this).data('proto') != proto) {
                $(this).val('0');
            }
      });
      $('.selectpicker').selectpicker('refresh');
    });
});
</script>
<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
} ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <div class="table-responsive">
              <form method="post" name="iform" id="iform">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%"></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Group Name"); ?></td>
                    <td>
                      <input name="name" type="text" size="20" value="<?=$pconfig['name'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_gatewayprio" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Gateway Priority"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td><?= gettext('Gateway') ?></td>
                          <td><?= gettext('Tier') ?></td>
                          <td><?= gettext('Virtual IP') ?></td>
                          <td><?= gettext('Description') ?></td>
                        </tr>
<?php
                        foreach ($a_gateways as $gwname => $gateway):
                        if (!empty($pconfig['item'])) {
                            $af = explode("|", $pconfig['item'][0]);
                            $family = $a_gateways[$af[0]]['ipprotocol'];
                            if ($gateway['ipprotocol'] != $family) {
                                continue;
                            }
                        }
?>
                        <tr>
                          <td><strong><?=$gateway['name'];?></strong></td>
                          <td>
                            <select name="<?=$gwname;?>" class="selectpicker act-tier-change" data-width='auto' data-proto="<?=$gateway['ipprotocol'];?>">
<?php
                              for ($tierId = 0 ; $tierId < 6 ; ++$tierId):
                                $is_selected = false;
                                foreach ((array)$pconfig['item'] as $item) {
                                    $itemsplit = explode("|", $item);
                                    if ($itemsplit[0] == $gwname && $itemsplit[1] == $tierId) {
                                        $is_selected = true;
                                    }
                                }
?>
                                <option value="<?=$tierId;?>" <?=$is_selected ? "selected=\"selected\"" : "";?>>
                                    <?=$tierId == 0 ? gettext("Never") : sprintf(gettext("Tier %d"), $tierId) ;?>
                                </option>
<?php
                              endfor;?>
                            </select>
                          </td>
                          <td>
                            <select name="<?=$gwname;?>_vip" class="selectpicker" data-width="auto">
<?php
                              $selected_key = 'address';
                              foreach ((array)$pconfig['item'] as $item) {
                                  $itemsplit = explode("|", $item);
                                  if ($itemsplit[0] == $gwname) {
                                      $selected_key = $itemsplit[2];
                                      break;
                                  }
                              }?>
                              <option value="address" <?=$selected_key == "address" ? "selected=\"selected\"" :"";?> >
                                <?=gettext("Interface Address");?>
                              </option>
<?php
                              foreach (get_configured_carp_interface_list() as $vip => $address):
                                  if (!preg_match("/^{$gateway['friendlyiface']}_/i", $vip)) {
                                      continue;
                                  }
                                  if (($gateway['ipprotocol'] == "inet") && (!is_ipaddrv4($address))) {
                                      continue;
                                  }
                                  if (($gateway['ipprotocol'] == "inet6") && (!is_ipaddrv6($address))) {
                                      continue;
                                  }?>
                                  <option value="<?=$vip;?>" <?=$selected_key == $vip ? "selected=\"selected\"" :"";?> >
                                    <?=$vip;?> - <?=$address;?>
                                  </option>
<?php
                              endforeach;?>

                            </select>
                          </td>
                          <td><strong><?=$gateway['descr'];?></strong></td>
                        </tr>
<?php
                        endforeach;?>
                      </table>
                      <output for="help_for_gatewayprio" class="hidden">
                          <br>
                          <strong><?=gettext("Link Priority"); ?></strong> <br />
                          <?=gettext("The priority selected here defines in what order failover and balancing of links will be done. " .
                                                  "Multiple links of the same priority will balance connections until all links in the priority will be exhausted. " .
                                                  "If all links in a priority level are exhausted we will use the next available link(s) in the next priority level.") ?>

                          <br />
                          <strong><?=gettext("Virtual IP"); ?></strong> <br />
                          <?=gettext("The virtual IP field selects what (virtual) IP should be used when this group applies to a local Dynamic DNS, IPsec or OpenVPN endpoint") ?>
                      </output>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_triggerlvl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Trigger Level"); ?></td>
                    <td>
                      <select name='trigger' class='selectpicker'>
                        <option value="down" <?=$pconfig['trigger'] == "down" ? "selected=\"selected\"" :"";?> ><?=gettext("Member Down");?></option>
                        <option value="downloss" <?=$pconfig['trigger'] == "downloss" ? "selected=\"selected\"" :"";?> ><?=gettext("Packet Loss");?></option>
                        <option value="downlatency" <?=$pconfig['trigger'] == "downlatency" ? "selected=\"selected\"" :"";?> ><?=gettext("High Latency");?></option>
                        <option value="downlosslatency" <?=$pconfig['trigger'] == "downlosslatency" ? "selected=\"selected\"" :"";?> ><?=gettext("Packet Loss or High Latency");?></option>
                      </select>
                      <output for="help_for_triggerlvl" class="hidden">
                        <?=gettext("When to trigger exclusion of a member"); ?>
                      </output>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <output for="help_for_descr" class="hidden">
                        <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                      </output>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='/system_gateway_groups.php'" />
<?php
                      if (isset($id)) :?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
<?php
                      endif; ?>
                    </td>
                  </tr>
                </table>
              </form>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
