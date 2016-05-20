<?php

/*
        Copyright (C) 2014-2016 Deciso B.V.
        Copyright (C) 2005-2008 Bill Marquette <bill.marquette@gmail.com>.
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


if (empty($config['load_balancer']['lbpool']) || !is_array($config['load_balancer']['lbpool'])) {
    $config['load_balancer']['lbpool'] = array();
}
$a_pool = &$config['load_balancer']['lbpool'];


$copy_fields = array('name', 'mode', 'descr', 'port', 'retry', 'monitor', 'servers', 'serversdisabled');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_pool[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();

    // copy fields
    foreach ($copy_fields as $fieldname) {
        if (isset($id) && isset($a_pool[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_pool[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }

    // init arrays
    $pconfig['servers'] = is_array($pconfig['servers']) ? $pconfig['servers'] : array();
    $pconfig['serversdisabled'] = is_array($pconfig['serversdisabled']) ? $pconfig['serversdisabled'] : array();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_pool[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $pconfig = $_POST;
    $input_errors = array();
    /* input validation */
    $reqdfields = explode(" ", "name mode port monitor servers");
    $reqdfieldsn = array(gettext("Name"),gettext("Mode"),gettext("Port"),gettext("Monitor"),gettext("Server List"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    /* Ensure that our pool names are unique */
    for ($i=0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
        if ($pconfig['name'] == $config['load_balancer']['lbpool'][$i]['name'] && $i != $id) {
            $input_errors[] = gettext("This pool name has already been used.  Pool names must be unique.");
        }
    }

    if (strpos($pconfig['name'], " ") !== false) {
        $input_errors[] = gettext("You cannot use spaces in the 'name' field.");
    }

    if (in_array($pconfig['name'], $reserved_table_names)) {
        $input_errors[] = sprintf(gettext("The name '%s' is a reserved word and cannot be used."), $_POST['name']);
    }

    if (is_alias($pconfig['name'])) {
        $input_errors[] = sprintf(gettext("Sorry, an alias is already named %s."), $_POST['name']);
    }

    if (!is_portoralias($pconfig['port'])) {
        $input_errors[] = gettext("The port must be an integer between 1 and 65535, or a port alias.");
    }

    // May as well use is_port as we want a positive integer and such.
    if (!empty($pconfig['retry']) && !is_port($pconfig['retry'])) {
        $input_errors[] = gettext("The retry value must be an integer between 1 and 65535.");
    }

    if (is_array($pconfig['servers'])) {
        foreach($pconfig['servers'] as $svrent) {
            if (!is_ipaddr($svrent) && !is_subnetv4($svrent)) {
                $input_errors[] = sprintf(gettext("%s is not a valid IP address or IPv4 subnet (in \"enabled\" list)."), $svrent);
            } elseif (is_subnetv4($svrent) && subnet_size($svrent) > 64) {
                $input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses (in \"enabled\" list)."), $svrent);
            }
        }
    }
    if (is_array($pconfig['serversdisabled'])) {
        foreach($pconfig['serversdisabled'] as $svrent) {
            if (!is_ipaddr($svrent) && !is_subnetv4($svrent)) {
                $input_errors[] = sprintf(gettext("%s is not a valid IP address or IPv4 subnet (in \"disabled\" list)."), $svrent);
            } elseif (is_subnetv4($svrent) && subnet_size($svrent) > 64) {
                $input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses (in \"disabled\" list)."), $svrent);
            }
        }
    }
    $m = array();
    for ($i=0; isset($config['load_balancer']['monitor_type'][$i]); $i++) {
        $m[$config['load_balancer']['monitor_type'][$i]['name']] = $config['load_balancer']['monitor_type'][$i];
    }
    if (!isset($m[$pconfig['monitor']])) {
        $input_errors[] = gettext("Invalid monitor chosen.");
    }
    if (count($input_errors) == 0) {
        $poolent = array();
        foreach ($copy_fields as $fieldname) {
            $poolent[$fieldname] = $pconfig[$fieldname];
        }

        if (isset($id)) {
            /* modify all virtual servers with this name */
            for ($i = 0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
                if ($config['load_balancer']['virtual_server'][$i]['lbpool'] == $a_pool[$id]['name']) {
                    $config['load_balancer']['virtual_server'][$i]['lbpool'] = $poolent['name'];
                }
            }
            $a_pool[$id] = $poolent;
        } else {
            $a_pool[] = $poolent;
        }

        mark_subsystem_dirty('loadbalancer');
        write_config();
        header("Location: load_balancer_pool.php");
        exit;    }
}



$service_hook = 'relayd';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
  <!-- push all available (nestable) aliases in a hidden select box -->
  <select class="hidden" id="aliases">
  <?php
      if (!empty($config['aliases']['alias'])):
        foreach ($config['aliases']['alias'] as $alias):
          if ($alias['type'] == 'port'):?>
          <option data-type="<?=$alias['type'];?>" value="<?=htmlspecialchars($alias['name']);?>"><?=htmlspecialchars($alias['name']);?></option>
  <?php
          endif;
        endforeach;
      endif;
  ?>
  </select>

<?php include("fbegin.inc"); ?>
  <script type="text/javascript">
  $( document ).ready(function() {
      // init port type ahead
      var all_aliases = [];
      $("#aliases > option").each(function(){
          all_aliases.push($(this).val())
      });
      $("#port").typeahead({ source: all_aliases });

      $("#mode").change(function(event){
          event.preventDefault();
          if ($('#mode').val() == 'failover' && $('#servers option').length > 1){
              $('#servers option:not(:first)').remove().appendTo('#serversdisabled');
          }
      });
      $("#btn_add_to_pool").click(function(event){
          event.preventDefault();
          if ($('#mode').val() != 'failover' || $('#servers option').length == 0){
              $('#servers').append($('<option>', { value : $("#ipaddr").val() }).text($("#ipaddr").val()));
          } else {
              $('#serversdisabled').append($('<option>', { value : $("#ipaddr").val() }).text($("#ipaddr").val()));
          }
      });

      $("#moveToEnabled").click(function(event){
          event.preventDefault();
          if ($('#mode').val() != 'failover' ||
              ($('#servers option').length == 0 && $('#serversdisabled option:selected').length == 1))
          {
              $('#serversdisabled option:selected').remove().appendTo('#servers');
          }
      });
      $("#moveToDisabled").click(function(event){
          event.preventDefault();
          $('#servers option:selected').remove().appendTo('#serversdisabled');
      });

      $("#btn_del_serversdisabled").click(function(event){
          event.preventDefault();
          $('#serversdisabled option:selected').remove();
      });

      $("#btn_del_servers").click(function(event){
          event.preventDefault();
          $('#servers option:selected').remove();
      });

      $("#save").click(function(){
          $('#servers option').prop('selected', true);
          $('#serversdisabled option').prop('selected', true);
      });
  });
  </script>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td>
                      <strong><?=gettext("Add/edit - Pool entry"); ?></strong>
                    </td>
                    <td align="right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Name"); ?></td>
                    <td>
                      <input name="name" type="text" value="<?=$pconfig['name'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Mode"); ?></td>
                    <td>
                      <select id="mode" name="mode">
                        <option value="loadbalance" <?=$pconfig['mode'] == "loadbalance" ? "selected=\"selected\"" : "";?>>
                          <?=gettext("Load Balance");?>
                        </option>
                        <option value="failover"  <?=$pconfig['mode'] == "failover" ? "selected=\"selected\"" : "";?>>
                          <?=gettext("Manual Failover");?>
                        </option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" <?php if(isset($pconfig['descr'])) echo "value=\"{$pconfig['descr']}\"";?> size="64" />
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Port"); ?></td>
                    <td>
                      <input type="text" id="port"  name="port" value="<?=$pconfig['port'];?>"/>
                      <div class="hidden" for="help_for_port">
                        <?=gettext("This is the port your servers are listening on."); ?><br />
                        <?=gettext("You may also specify a port alias listed in Firewall -&gt; Aliases here."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_retry" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Retry"); ?></td>
                    <td>
                      <input name="retry" type="text" value="<?=$pconfig['retry'];?>"/>
                      <div for="help_for_retry" class="hidden">
                        <?=gettext("Optionally specify how many times to retry checking a server before declaring it down."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2"><strong><?=gettext("Add item to pool"); ?></strong></td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Monitor"); ?></td>
                    <td>
                      <select id="monitor" name="monitor">
<?php
                      if (!empty($config['load_balancer']['monitor_type'])):
                        foreach ($config['load_balancer']['monitor_type'] as $monitor):?>
                        <option value="<?=$monitor['name'];?>" <?=$monitor['name'] == $pconfig['monitor'] ? "selected=\"selected\"" : "";?>>
                          <?=$monitor['name'];?>
                        </option>
<?php
                        endforeach;
                      endif;?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server IP Address"); ?></td>
                    <td>
                      <div class="input-group">
                        <span class="input-group-btn">
                          <button class="btn btn-default" id="btn_add_to_pool" type="button"><?=gettext("Add to pool");?></button>
                        </span>
                        <input class="form-control" id="ipaddr" type="text">
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2"><strong><?=gettext("Current Pool Members"); ?></strong></td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Members"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <thead>
                          <tr>
                            <th><?=gettext("Pool Disabled"); ?></th>
                            <th></th>
                            <th><?=gettext("Enabled (default)"); ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>
                              <select id="serversdisabled" name="serversdisabled[]" multiple="multiple">
<?php
                              foreach ($pconfig['serversdisabled'] as $svrent):?>
                                <option value="<?=$svrent;?>"><?=$svrent;?> </option>
<?php
                              endforeach;?>
                              </select>
                              <hr/>
                              <button id="btn_del_serversdisabled" class="btn btn-default btn-xs" data-toggle="tooltip"><span class="fa fa-trash text-muted"></span></button>
                            </td>
                            <td>
                              <button class="btn btn-default btn-xs" id="moveToDisabled"><span class="glyphicon glyphicon-arrow-left"></span></button><br />
                              <button class="btn btn-default btn-xs" id="moveToEnabled"><span class="glyphicon glyphicon-arrow-right"></span></button>
                            </td>
                            <td>
                              <select id="servers" name="servers[]" multiple="multiple">
<?php
                              foreach ($pconfig['servers'] as $svrent):?>
                                <option value="<?=$svrent;?>"><?=$svrent;?> </option>
<?php
                              endforeach;?>
                              </select>
                              <hr/>
                              <button id="btn_del_servers" class="btn btn-default btn-xs" data-toggle="tooltip"><span class="fa fa-trash text-muted"></span></button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td width="78%">
                      <br />
                      <input id="save" name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>"/>
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/load_balancer_pool.php');?>'" />
                      <?php if (isset($id) && (empty($_GET['act']) || $_GET['act'] != 'dup')): ?>
                      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
