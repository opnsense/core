<?php

/*
  Copyright (C) 2014-2015 Deciso B.V.
  Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
  Copyright (C) 2010 Scott Ullrich
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
require_once("filter.inc");
require_once("interfaces.inc");
require_once("pfsense-utils.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_routes.php');

if (!isset($config['staticroutes']) || !is_array($config['staticroutes'])) {
    $config['staticroutes'] = array();
}

if (!isset($config['staticroutes']['route']) || !is_array($config['staticroutes']['route'])) {
    $config['staticroutes']['route'] = array();
}

$a_routes = &$config['staticroutes']['route'];
$a_gateways = return_gateways_array(true, true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && isset($a_routes[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    } elseif (isset($_GET['dup']) && isset($a_routes[$_GET['dup']])) {
        $configId = $_GET['dup'];
    }
    $pconfig = array();

    if (isset($configId)) {
        list($pconfig['network'],$pconfig['network_subnet']) =
            explode('/', $a_routes[$configId]['network']);
        $pconfig['gateway'] = $a_routes[$configId]['gateway'];
        $pconfig['descr'] = $a_routes[$configId]['descr'];
        $pconfig['disabled'] = isset($a_routes[$configId]['disabled']);
    } else {
        $pconfig['network'] = null;
        $pconfig['network_subnet'] = null;
        $pconfig['gateway'] = null;
        $pconfig['disabled'] = false;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && isset($a_routes[$_POST['id']])) {
        $id = $_POST['id'];
    }
    global $aliastable;

    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "network network_subnet gateway");
    $reqdfieldsn = explode(
        ",",
        gettext("Destination network") . "," .
        gettext("Destination network bit count") . "," .
        gettext("Gateway")
    );

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (($pconfig['network'] && !is_ipaddr($pconfig['network']) && !is_alias($pconfig['network']))) {
        $input_errors[] = gettext("A valid IPv4 or IPv6 destination network must be specified.");
    }
    if (($_POST['network_subnet'] && !is_numeric($pconfig['network_subnet']))) {
        $input_errors[] = gettext("A valid destination network bit count must be specified.");
    }
    if (($_POST['gateway']) && is_ipaddr($pconfig['network'])) {
        if (!isset($a_gateways[$pconfig['gateway']])) {
            $input_errors[] = gettext("A valid gateway must be specified.");
        }
        if (!validate_address_family($pconfig['network'], lookup_gateway_ip_by_name($pconfig['gateway']))) {
            $input_errors[] = gettext("The gateway '{$a_gateways[$pconfig['gateway']]['gateway']}' is a different Address Family as network '{$pconfig['network']}'.");
        }
    }

    /* check for overlaps */
    $current_targets = get_staticroutes(true);
    $new_targets = array();
    if (is_ipaddrv6($pconfig['network'])) {
        $osn = gen_subnetv6($pconfig['network'], $pconfig['network_subnet']) . "/" . $pconfig['network_subnet'];
        $new_targets[] = $osn;
    }
    if (is_ipaddrv4($pconfig['network'])) {
        if ($pconfig['network_subnet'] > 32) {
            $input_errors[] = gettext("A IPv4 subnet can not be over 32 bits.");
        } else {
            $osn = gen_subnet($pconfig['network'], $pconfig['network_subnet']) . "/" . $pconfig['network_subnet'];
            $new_targets[] = $osn;
        }
    } elseif (is_alias($pconfig['network'])) {
        $osn = $pconfig['network'];
        foreach (preg_split('/\s+/', $aliastable[$osn]) as $tgt) {
            if (is_ipaddrv4($tgt)) {
                $tgt .= "/32";
            }
            if (is_ipaddrv6($tgt)) {
                $tgt .= "/128";
            }
            if (!is_subnet($tgt)) {
                continue;
            }
            if (!is_subnetv6($tgt)) {
                continue;
            }
            $new_targets[] = $tgt;
        }
    }
    if (!isset($id)) {
        $id = count($a_routes);
    }
    $oroute = $a_routes[$id];
    $old_targets = array();
    if (!empty($oroute)) {
        if (is_alias($oroute['network'])) {
            foreach (filter_expand_alias_array($oroute['network']) as $tgt) {
                if (is_ipaddrv4($tgt)) {
                    $tgt .= "/32";
                } elseif (is_ipaddrv6($tgt)) {
                    $tgt .= "/128";
                }
                if (!is_subnet($tgt)) {
                    continue;
                }
                $old_targets[] = $tgt;
            }
        } else {
            $old_targets[] = $oroute['network'];
        }
    }

    $overlaps = array_intersect($current_targets, $new_targets);
    $overlaps = array_diff($overlaps, $old_targets);
    if (count($overlaps)) {
        $input_errors[] = gettext("A route to these destination networks already exists") . ": " . implode(", ", $overlaps);
    }

    if (is_array($config['interfaces'])) {
        foreach ($config['interfaces'] as $if) {
            if (is_ipaddrv4($pconfig['network'])
                && isset($if['ipaddr']) && isset($if['subnet'])
                && is_ipaddrv4($if['ipaddr']) && is_numeric($if['subnet'])
                && ($_POST['network_subnet'] == $if['subnet'])
                && (gen_subnet($pconfig['network'], $pconfig['network_subnet']) == gen_subnet($if['ipaddr'], $if['subnet']))) {
                    $input_errors[] = sprintf(gettext("This network conflicts with address configured on interface %s."), $if['descr']);
            } elseif (is_ipaddrv6($pconfig['network'])
                && isset($if['ipaddrv6']) && isset($if['subnetv6'])
                && is_ipaddrv6($if['ipaddrv6']) && is_numeric($if['subnetv6'])
                && ($_POST['network_subnet'] == $if['subnetv6'])
                && (gen_subnetv6($pconfig['network'], $pconfig['network_subnet']) == gen_subnetv6($if['ipaddrv6'], $if['subnetv6']))) {
                    $input_errors[] = sprintf(gettext("This network conflicts with address configured on interface %s."), $if['descr']);
            }
        }
    }

    if (count($input_errors) == 0){
        $route = array();
        $route['network'] = $osn;
        $route['gateway'] = $pconfig['gateway'];
        $route['descr'] = $pconfig['descr'];
        if (!empty($pconfig['disabled'])) {
            $route['disabled'] = true;
        } else {
            unset($route['disabled']);
        }

        if (file_exists('/tmp/.system_routes.apply')) {
            $toapplylist = unserialize(file_get_contents('/tmp/.system_routes.apply'));
        } else {
            $toapplylist = array();
        }
        $a_routes[$id] = $route;

        if (!empty($oroute)) {
            $delete_targets = array_diff($old_targets, $new_targets);
            if (count($delete_targets)) {
                foreach ($delete_targets as $dts) {
                    if (is_ipaddrv6($dts)) {
                        $family = '-inet6';
                    }
                    $toapplylist[] = "/sbin/route delete {$family} {$dts}";
                }
            }
        }
        file_put_contents('/tmp/.system_routes.apply', serialize($toapplylist));
        mark_subsystem_dirty('staticroutes');
        write_config();

        header("Location: system_routes.php");
        exit;
    }
}



$pgtitle = array(gettext('System'), gettext('Routes'), gettext('Edit'));
$shortcut_section = "routing";
legacy_html_escape_form_data($a_gateways);
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>


<body>
  <script type="text/javascript">
    $( document ).ready(function() {
        // hook in, ipv4/ipv6 selector events
        hook_ipv4v6('ipv4v6net', 'network-id');
    });
  </script>
  <?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php if (isset($input_errors) && count($input_errors) > 0) {
      print_input_errors($input_errors);
} ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form action="system_routes_edit.php" method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td width="22%"></td>
                    <td width="78%" align="right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_network" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination network"); ?></td>
                    <td>
                      <input name="network" type="text" id="network" value="<?=$pconfig['network'];?>" />
                      /
                      <select name="network_subnet" data-network-id="network" class="ipv4v6net" id="network_netbits">
  <?php               for ($i = 128; $i >= 0; $i--) :
  ?>
                        <option value="<?=$i;?>" <?= isset($pconfig['network_subnet']) && $i == $pconfig['network_subnet'] ? "selected=\"selected\"" : "";?>>
                          <?=$i;?>
                        </option>
  <?php
                      endfor; ?>
                      </select>
                      <div class="hidden" for="help_for_network">
                        <?=gettext("Destination network for this static route"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_gateway" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Gateway"); ?></td>
                    <td>
                      <select name="gateway" id="gateway" class="selectpicker">
<?php
                      foreach ($a_gateways as $gateway):?>
                        <option value="<?=$gateway['name'];?>" <?=$gateway['name'] == $pconfig['gateway'] ? "selected=\"selected\"" : "";?>>
                          <?=$gateway['name'] . " - " . $gateway['gateway'];?>
                        </option>
<?php
                      endforeach;?>
                      </select>
                      <div class="hidden" for="help_for_gateway">
                          <?=gettext("Choose which gateway this route applies to or");?>
                          <a href="/system_gateways_edit.php"><?=gettext("add a new one.");?></a>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled");?></td>
                    <td width="78%" class="vtable">
                      <input name="disabled" type="checkbox" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : "";?>/>
                      <div class="hidden" for="help_for_disabled">
                        <strong><?=gettext("Disable this static route");?></strong><br/>
                        <?=gettext("Set this option to disable this static route without removing it from the list.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <div for="help_for_descr" class="hidden">
                        <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input id="save" name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <input id="cancel" type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
<?php
                      if (isset($id) && $a_routes[$id]) :?>
                        <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php
                      endif; ?>
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
<?php include("foot.inc");
