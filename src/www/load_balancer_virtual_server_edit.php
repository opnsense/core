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
require_once("vslb.inc");
require_once("interfaces.inc");

if (empty($config['load_balancer']) || !is_array($config['load_balancer'])) {
    $config['load_balancer'] = array();
}
if (empty($config['load_balancer']['virtual_server']) || !is_array($config['load_balancer']['virtual_server'])) {
    $config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];


$copy_fields=array('name', 'descr', 'poolname', 'port', 'sitedown', 'ipaddr', 'mode', 'relay_protocol');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_vs[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    // copy fields
    foreach ($copy_fields as $fieldname) {
        if (isset($id) && isset($a_vs[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_vs[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_vs[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $pconfig = $_POST;
    $input_errors = array();

    /* input validation */
    switch($pconfig['mode']) {
        case "redirect":
            $reqdfields = explode(" ", "ipaddr name mode");
            $reqdfieldsn = array(gettext("IP Address"),gettext("Name"),gettext("Mode"));
            break;
        case "relay":
            $reqdfields = explode(" ", "ipaddr name mode relay_protocol");
            $reqdfieldsn = array(gettext("IP Address"),gettext("Name"),gettext("Relay Protocol"));
            break;
    }

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    for ($i=0; isset($config['load_balancer']['virtual_server'][$i]); $i++) {
        if (($pconfig['name'] == $config['load_balancer']['virtual_server'][$i]['name']) && ($i != $id)) {
            $input_errors[] = gettext("This virtual server name has already been used. Virtual server names must be unique.");
        }
    }

    if (preg_match('/[ \/]/', $pconfig['name'])) {
        $input_errors[] = gettext("You cannot use spaces or slashes in the 'name' field.");
    }

    if ($pconfig['port'] != "" && !is_portoralias($pconfig['port'])) {
        $input_errors[] = gettext("The port must be an integer between 1 and 65535, a port alias, or left blank.");
    }

    if (!is_ipaddroralias($pconfig['ipaddr']) && !is_subnetv4($pconfig['ipaddr'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid IP address, IPv4 subnet, or alias."), $_POST['ipaddr']);
    } elseif (is_subnetv4($pconfig['ipaddr']) && subnet_size($pconfig['ipaddr']) > 64) {
        $input_errors[] = sprintf(gettext("%s is a subnet containing more than 64 IP addresses."), $pconfig['ipaddr']);
    }

    if ((strtolower($pconfig['relay_protocol']) == "dns") && !empty($pconfig['sitedown'])) {
        $input_errors[] = gettext("You cannot select a Fall Back Pool when using the DNS relay protocol.");
    }

    if (count($input_errors) == 0) {
        $vsent = array();
        foreach ($copy_fields as $fieldname) {
            $vsent[$fieldname] = $pconfig[$fieldname];
        }
        if ($vsent['sitedown'] == "") {
            unset($vsent['sitedown']);
        }
        if ($vsent['mode'] != 'relay'){
            // relay protocol only applies to relay
            unset($vsent['relay_protocol']);
        }

        if (isset($id)) {
            if ($a_vs[$id]['name'] != $pconfig['name']) {
                /* Because the VS name changed, mark the old name for cleanup. */
                cleanup_lb_mark_anchor($a_vs[$id]['name']);
            }
            $a_vs[$id] = $vsent;
        } else {
            $a_vs[] = $vsent;
        }

        mark_subsystem_dirty('loadbalancer');
        write_config();

        header(url_safe('Location: /load_balancer_virtual_server.php'));
        exit;
    }
}

$service_hook = 'relayd';
legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>
  <script type="text/javascript">
    $( document ).ready(function() {
      // collect all known aliases per type
      var all_aliases = {};
      $("#aliases > option").each(function(){
          if (all_aliases[$(this).data('type')] == undefined) {
              all_aliases[$(this).data('type')] = [];
          }
          all_aliases[$(this).data('type')].push($(this).val())
      });

      $("#ipadd").typeahead({ source: all_aliases['host'] });
      $("#port").typeahead({ source: all_aliases['port'] });

      $("#mode").change(function(){
        if ($(this).val() == 'redirect') {
            $("#protocol").hide();
        } else {
            $("#protocol").show();
        }
      });
      $("#mode").change();

    });
  </script>
  <!-- push all available (nestable) aliases in a hidden select box -->
  <select class="hidden" id="aliases">
  <?php
      if (!empty($config['aliases']['alias'])):
        foreach ($config['aliases']['alias'] as $alias):
          if ($alias['type'] == 'host' || $alias['type'] == 'port'):?>
          <option data-type="<?=$alias['type'];?>" value="<?=htmlspecialchars($alias['name']);?>">
            <?=htmlspecialchars($alias['name']);?>
          </option>
  <?php
          endif;
        endforeach;
      endif;
  ?>
  </select>

<?php include("fbegin.inc"); ?>
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
                      <td width="22%">
                        <strong><?=gettext("Add/edit - Virtual Server entry"); ?></strong>
                      </td>
                      <td width="78%" align="right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Name"); ?></td>
                      <td>
                        <input name="name" type="text" value="<?=$pconfig['name'];?>"/>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Description"); ?></td>
                      <td>
                        <input name="descr" type="text" value="<?=$pconfig['descr'];?>"/>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_ipaddr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP Address"); ?></td>
                      <td>
                        <input type="text" id="ipadd" name="ipaddr" value="<?=$pconfig['ipaddr'];?>" />
                        <div class="hidden" for="help_for_ipaddr">
                          <?=gettext("This is normally the WAN IP address that you would like the server to listen on. All connections to this IP and port will be forwarded to the pool cluster."); ?>
                          <br /><?=gettext("You may also specify a host alias listed in Firewall -&gt; Aliases here."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Port"); ?></td>
                      <td>
                        <input type="text" name="port" id="port" value="<?=$pconfig['port'];?>" />
                        <div class="hidden" for="help_for_port">
                          <?=gettext("This is the port that the clients will connect to. All connections to this port will be forwarded to the pool cluster."); ?>
                          <br /><?=gettext("If left blank, listening ports from the pool will be used."); ?>
                          <br /><?=gettext("You may also specify a port alias listed in Firewall -&gt; Aliases here."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Virtual Server Pool"); ?></td>
                      <td>
<?php
                        if(count($config['load_balancer']['lbpool']) == 0): ?>
                          <b><?=gettext("NOTE:"); ?></b> <?=gettext("Please add a pool on the Pools tab to use this feature."); ?>
<?php
                        else: ?>
                        <select id="poolname" name="poolname">
<?php
                        foreach($config['load_balancer']['lbpool'] as $pool):?>
                          <option value="<?=htmlspecialchars($pool['name']);?>" <?=$pool['name'] == $pconfig['poolname'] ? " selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($pool['name']);?>
                          </option>
<?php
                        endforeach;?>
                        </select>
<?php
                        endif; ?>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_sitedown" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Fall Back Pool"); ?></td>
                      <td>
<?php
                        if(empty($config['load_balancer']['lbpool']) || count($config['load_balancer']['lbpool']) == 0): ?>
                        <b><?=gettext("NOTE:"); ?></b> <?=gettext("Please add a pool on the Pools tab to use this feature."); ?>
<?php
                        else: ?>
                        <select id="sitedown" name="sitedown">
                          <option value=""<?=empty($pconfig['sitedown']) ? "selected=\"selected\"" : ''?>>
                            <?=gettext("none"); ?>
                          </option>
<?php
                        foreach($config['load_balancer']['lbpool'] as $pool):?>
                          <option value="<?=htmlspecialchars($pool['name']);?>" <?=$pool['name'] == $pconfig['sitedown'] ? " selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($pool['name']);?>
                          </option>
<?php
                        endforeach;?>

                        </select>
<?php
                        endif; ?>
                        <div class="hidden" for="help_for_sitedown">
                          <?=gettext("The server pool to which clients will be redirected if *ALL* servers in the Virtual Server Pool are offline."); ?>
                          <br /><?=gettext("This option is NOT compatible with the DNS relay protocol."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_mode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Mode");?></td>
                      <td>
                        <select name="mode" id="mode">
                          <option value="redirect"  <?=$pconfig['mode'] != 'relay' ? " selected=\"selected\"" : ""?>>
                            <?=gettext("Redirect");?>
                          </option>
                          <option value="relay" <?=$pconfig['mode'] == 'relay' ? " selected=\"selected\"" : ""?>>
                            <?=gettext("Relay");?>
                          </option>
                        </select>
                        <div class="hidden" for="help_for_mode">
                          <strong><?=gettext("Redirect");?></strong><br/>
                          <?=gettext("Redirections are translated to pf(4) rdr-to rules for stateful forwarding to a target host from a health-checked table on layer 3.");?>
                          <strong><?=gettext("Relay");?></strong><br/>
                          <?=gettext("Relays allow application layer load balancing, TLS acceleration, and general purpose TCP proxying on layer 7.");?>
                        </div>
                      </td>
                    </tr>
                    <tr id="protocol">
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Relay Protocol"); ?></td>
                      <td>
                        <select name="relay_protocol">
                          <option value="tcp" <?=$pconfig['relay_protocol'] == "tcp" ? " selected=\"selected\"" : "";?>>
                            <?=gettext("TCP");?>
                          </option>
                          <option value="dns" <?=$pconfig['relay_protocol'] == "dns" ? " selected=\"selected\"" : "";?>>
                            <?=gettext("DNS");?>
                          </option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                        <input name="Save" type="submit" class="btn btn-primary" value="<?=gettext("Submit"); ?>" />
                        <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/load_balancer_virtual_server.php');?>'" />
                        <?php if (isset($id) && (empty($_GET['act'] ) || $_GET['act'] != 'dup')): ?>
                          <input name="id" type="hidden" value="<?=$id;?>" />
                        <?php endif; ?>
                      </td>
                    </tr>
                    <tfoot>
                      <tr>
                        <td colspan="2">
                          <span class="text-danger"><strong><?=gettext("Note:"); ?></strong></span>
                          <?=gettext("Don't forget to add a firewall rule for the virtual server/pool after you're finished setting it up."); ?>
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </form>
            </div>
          </section>
        </div>
      </div>
    </section>
<?php include("foot.inc"); ?>
