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

$a_gateways = return_gateways_array(true, false, true);
$a_gateways_arr = array();
foreach ($a_gateways as $gw) {
    $a_gateways_arr[] = $gw;
}
$a_gateways = $a_gateways_arr;
$apinger_default = return_apinger_defaults();


// form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_gateways[$pconfig['id']])) {
        $id = $pconfig['id'];
    }
    $input_errors = array();

    /* input validation */
    $reqdfields = explode(" ", "name interface");
    $reqdfieldsn = array(gettext("Name"), gettext("Interface"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!isset($pconfig['name'])) {
        $input_errors[] = gettext("A valid gateway name must be specified.");
    }

    $valid = is_validaliasname($pconfig['name']);
    if ($valid === false) {
        $input_errors[] = sprintf(gettext('The name must be less than 32 characters long and may only consist of the following characters: %s'), 'a-z, A-Z, 0-9, _');
    } elseif ($valid === null) {
        $input_errors[] = sprintf(gettext('The name cannot be the internally reserved keyword "%s".'), $pconfig['name']);
    }

    /* skip system gateways which have been automatically added */
    if (!empty($pconfig['gateway']) && !is_ipaddr($pconfig['gateway']) &&
        $pconfig['attribute'] !== "system" && $pconfig['gateway'] != "dynamic"
        ) {
        $input_errors[] = gettext("A valid gateway IP address must be specified.");
    }

    if (!empty($pconfig['gateway']) && (is_ipaddr($pconfig['gateway'])) && !isset($_REQUEST['isAjax'])) {
        if (is_ipaddrv4($pconfig['gateway'])) {
            $parent_ip = get_interface_ip($pconfig['interface']);
            $parent_sn = get_interface_subnet($pconfig['interface']);
            if (empty($parent_ip) || empty($parent_sn)) {
                $input_errors[] = gettext("Cannot add IPv4 Gateway Address because no IPv4 address could be found on the interface.");
            } else {
                $subnets = array(gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn);
                $vips = link_interface_to_vips($_POST['interface']);
                if (is_array($vips)) {
                    foreach ($vips as $vip) {
                        if (!is_ipaddrv4($vip['subnet'])) {
                            continue;
                        }
                        $subnets[] = gen_subnet($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
                    }
                }

                $found = false;
                foreach ($subnets as $subnet) {
                    if (ip_in_subnet($pconfig['gateway'], $subnet)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found && !isset($pconfig['fargw'])) {
                    $input_errors[] = sprintf(gettext("The gateway address %1\$s does not lie within one of the chosen interface's subnets."), $pconfig['gateway']);
                }
            }
        } elseif (is_ipaddrv6($pconfig['gateway'])) {
            /* do not do a subnet match on a link local address, it's valid */
            if (!is_linklocal($pconfig['gateway'])) {
                $parent_ip = get_interface_ipv6($pconfig['interface']);
                $parent_sn = get_interface_subnetv6($pconfig['interface']);
                if (empty($parent_ip) || empty($parent_sn)) {
                    $input_errors[] = gettext("Cannot add IPv6 Gateway Address because no IPv6 address could be found on the interface.");
                } else {
                    $subnets = array(gen_subnetv6($parent_ip, $parent_sn) . "/" . $parent_sn);
                    $vips = link_interface_to_vips($pconfig['interface']);
                    if (is_array($vips)) {
                        foreach ($vips as $vip) {
                            if (!is_ipaddrv6($vip['subnet'])) {
                                continue;
                            }
                            $subnets[] = gen_subnetv6($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
                        }
                    }

                    $found = false;
                    foreach ($subnets as $subnet) {
                        if (ip_in_subnet($pconfig['gateway'], $subnet)) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found && !isset($pconfig['fargw'])) {
                        $input_errors[] = sprintf(gettext("The gateway address %1\$s does not lie within one of the chosen interface's subnets."), $pconfig['gateway']);
                    }
                }
            }
        }

        if (!empty($config['interfaces'][$pconfig['interface']]['ipaddr'])) {
            if (is_ipaddr($config['interfaces'][$pconfig['interface']]['ipaddr']) && (empty($pconfig['gateway']) || $pconfig['gateway'] == "dynamic")) {
                $input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv4 configuration.");
            }
        }
        if (!empty($config['interfaces'][$pconfig['interface']]['ipaddrv6'])) {
            if (is_ipaddr($config['interfaces'][$pconfig['interface']]['ipaddrv6']) && (empty($pconfig['gateway']) || $pconfig['gateway'] == "dynamic")) {
                $input_errors[] = gettext("Dynamic gateway values cannot be specified for interfaces with a static IPv6 configuration.");
            }
        }
    }
    if (($pconfig['monitor'] <> "") && !is_ipaddr($pconfig['monitor']) && $pconfig['monitor'] != "dynamic") {
        $input_errors[] = gettext("A valid monitor IP address must be specified.");
    }
    /* only allow correct IPv4 and IPv6 gateway addresses */
    if (!empty($pconfig['gateway']) && is_ipaddr($pconfig['gateway']) && $pconfig['gateway'] != "dynamic") {
        if (is_ipaddrv6($pconfig['gateway']) && ($pconfig['ipprotocol'] == "inet")) {
            $input_errors[] = gettext("The IPv6 gateway address '{$pconfig['gateway']}' can not be used as a IPv4 gateway'.");
        }
        if (is_ipaddrv4($pconfig['gateway']) && ($pconfig['ipprotocol'] == "inet6")) {
            $input_errors[] = gettext("The IPv4 gateway address '{$pconfig['gateway']}' can not be used as a IPv6 gateway'.");
        }
    }
    /* only allow correct IPv4 and IPv6 monitor addresses */
    if ( !empty($_POST['monitor']) && is_ipaddr($pconfig['monitor']) && $pconfig['monitor'] != "dynamic") {
        if (is_ipaddrv6($pconfig['monitor']) && ($pconfig['ipprotocol'] == "inet")) {
            $input_errors[] = gettext("The IPv6 monitor address '{$pconfig['monitor']}' can not be used on a IPv4 gateway'.");
        }
        if (is_ipaddrv4($pconfig['monitor']) && ($pconfig['ipprotocol'] == "inet6")) {
            $input_errors[] = gettext("The IPv4 monitor address '{$pconfig['monitor']}' can not be used on a IPv6 gateway'.");
        }
    }

    if (isset($pconfig['name'])) {
        /* check for overlaps */
        foreach ($a_gateways as $gateway) {
            if (isset($id) && $a_gateways[$id] === $gateway) {
                if ($gateway['name'] != $pconfig['name']) {
                    $input_errors[] = gettext("Changing name on a gateway is not allowed.");
                }
                continue;
            }
            if (!empty($pconfig['name'])) {
                if (!empty($gateway['name']) && $pconfig['name'] == $gateway['name'] && $gateway['attribute'] !== "system") {
                    $input_errors[] = sprintf(gettext('The gateway name "%s" already exists.'), $pconfig['name']);
                    break;
                }
            }
            if (is_ipaddr($pconfig['gateway'])) {
                if (!empty($gateway['name']) && $pconfig['gateway'] == $gateway['gateway'] && $gateway['attribute'] !== "system") {
                    $input_errors[] = sprintf(gettext('The gateway IP address "%s" already exists.'), $pconfig['gateway']);
                    break;
                }
            }
            if (is_ipaddr($pconfig['monitor'])) {
                if (!empty($gateway['monitor']) && $pconfig['monitor'] == $gateway['monitor'] && $gateway['attribute'] !== "system") {
                    $input_errors[] = sprintf(gettext('The monitor IP address "%s" is already in use. You must choose a different monitor IP.'), $pconfig['monitor']);
                    break;
                }
            }
        }
    }

    /* input validation of apinger advanced parameters */
    if (!empty($pconfig['latencylow'])) {
        if (!is_numeric($pconfig['latencylow'])) {
            $input_errors[] = gettext("The low latency threshold needs to be a numeric value.");
        } elseif ($pconfig['latencylow'] < 1) {
            $input_errors[] = gettext("The low latency threshold needs to be positive.");
        }
    }

    if (!empty($pconfig['latencyhigh'])) {
        if (!is_numeric($pconfig['latencyhigh'])) {
            $input_errors[] = gettext("The high latency threshold needs to be a numeric value.");
        } elseif ($_POST['latencyhigh'] < 1) {
            $input_errors[] = gettext("The high latency threshold needs to be positive.");
        }
    }

    if (!empty($pconfig['losslow'])) {
        if (!is_numeric($_POST['losslow'])) {
            $input_errors[] = gettext("The low Packet Loss threshold needs to be a numeric value.");
        } elseif ($pconfig['losslow'] < 1) {
            $input_errors[] = gettext("The low Packet Loss threshold needs to be positive.");
        } elseif ($pconfig['losslow'] >= 100) {
            $input_errors[] = gettext("The low Packet Loss threshold needs to be less than 100.");
        }
    }

    if (!empty($pconfig['losshigh'])) {
        if (!is_numeric($pconfig['losshigh'])) {
            $input_errors[] = gettext("The high Packet Loss threshold needs to be a numeric value.");
        } elseif ($pconfig['losshigh'] < 1) {
            $input_errors[] = gettext("The high Packet Loss threshold needs to be positive.");
        } elseif ($pconfig['losshigh'] > 100) {
            $input_errors[] = gettext("The high Packet Loss threshold needs to be 100 or less.");
        }
    }

    if (!empty($pconfig['latencylow']) && !empty($pconfig['latencyhigh'])) {
        if (is_numeric($pconfig['latencylow']) && is_numeric($pconfig['latencyhigh']) &&
            $pconfig['latencylow'] > $pconfig['latencyhigh']
           ) {
            $input_errors[] = gettext("The high latency threshold needs to be higher than the low latency threshold");
        }
    } elseif (!empty($pconfig['latencylow'])) {
        if (is_numeric($pconfig['latencylow']) && $pconfig['latencylow'] > $apinger_default['latencyhigh']) {
            $input_errors[] = sprintf(gettext('The low latency threshold needs to be less than the default high latency threshold (%d)'), $apinger_default['latencyhigh']);
        }
    } elseif (!empty($pconfig['latencyhigh'])) {
        if (is_numeric($_POST['latencyhigh']) && $_POST['latencyhigh'] < $apinger_default['latencylow']) {
            $input_errors[] = sprintf(gettext('The high latency threshold needs to be higher than the default low latency threshold (%d)'), $apinger_default['latencylow']);
        }
    }

    if (!empty($pconfig['losslow']) && !empty($pconfig['losshigh'])) {
        if (is_numeric($pconfig['losslow']) && is_numeric($pconfig['losshigh']) && $pconfig['losslow'] > $pconfig['losshigh']) {
            $input_errors[] = gettext("The high Packet Loss threshold needs to be higher than the low Packet Loss threshold");
        }
    } elseif (!empty($pconfig['losslow'])) {
        if (is_numeric($pconfig['losslow']) && $pconfig['losslow'] > $apinger_default['losshigh']) {
            $input_errors[] = sprintf(gettext('The low Packet Loss threshold needs to be less than the default high Packet Loss threshold (%d)'), $apinger_default['losshigh']);
        }
    } elseif (!empty($pconfig['losshigh'])) {
        if (is_numeric($pconfig['losshigh']) && $pconfig['losshigh'] < $apinger_default['losslow']) {
            $input_errors[] = sprintf(gettext('The high Packet Loss threshold needs to be higher than the default low Packet Loss threshold (%d)'), $apinger_default['losslow']);
        }
    }

    if (!empty($pconfig['interval'])) {
        if (!is_numeric($pconfig['interval'])) {
            $input_errors[] = gettext("The probe interval needs to be a numeric value.");
        } elseif ($pconfig['interval'] < 1) {
            $input_errors[] = gettext("The probe interval needs to be positive.");
        }
    }

    if (!empty($pconfig['down'])) {
        if (! is_numeric($pconfig['down'])) {
            $input_errors[] = gettext("The down time setting needs to be a numeric value.");
        } elseif ($pconfig['down'] < 1) {
            $input_errors[] = gettext("The down time setting needs to be positive.");
        }
    }

    if (!empty($pconfig['interval']) && !empty($pconfig['down'])) {
        if ((is_numeric($pconfig['interval'])) && (is_numeric($pconfig['down'])) && $pconfig['interval'] > $pconfig['down']) {
            $input_errors[] = gettext("The probe interval needs to be less than the down time setting.");
        }
    } elseif (!empty($pconfig['interval'])) {
        if (is_numeric($pconfig['interval']) && $pconfig['interval'] > $apinger_default['down']) {
            $input_errors[] = sprintf(gettext('The probe interval needs to be less than the default down time setting (%d)'), $apinger_default['down']);
        }
    } elseif (!empty($pconfig['down'])) {
        if (is_numeric($pconfig['down']) && $pconfig['down'] < $apinger_default['interval']) {
            $input_errors[] = sprintf(gettext('The down time setting needs to be higher than the default probe interval (%d)'), $apinger_default['interval']);
        }
    }

    if (!empty($pconfig['avg_delay_samples'])) {
        if (!is_numeric($pconfig['avg_delay_samples'])) {
            $input_errors[] = gettext("The average delay replies qty needs to be a numeric value.");
        } elseif ($pconfig['avg_delay_samples'] < 1) {
            $input_errors[] = gettext("The average delay replies qty needs to be positive.");
        }
    }

    if (!empty($pconfig['avg_loss_samples'])) {
        if (!is_numeric($_POST['avg_loss_samples'])) {
            $input_errors[] = gettext("The average packet loss probes qty needs to be a numeric value.");
        } elseif ($pconfig['avg_loss_samples'] < 1) {
            $input_errors[] = gettext("The average packet loss probes qty needs to be positive.");
        }
    }

    if (!empty($pconfig['avg_loss_delay_samples'])) {
        if (!is_numeric($pconfig['avg_loss_delay_samples'])) {
            $input_errors[] = gettext("The lost probe delay needs to be a numeric value.");
        } elseif ($pconfig['avg_loss_delay_samples'] < 1) {
            $input_errors[] = gettext("The lost probe delay needs to be positive.");
        }
    }

    if (count($input_errors) == 0) {
        // A result of obfuscating the list of gateways is that over here we need to map things back that should
        // be aligned with the configuration. Not going to fix this now.
        if (isset($a_gateways[$id]['attribute']) && is_numeric($a_gateways[$id]['attribute']) ) {
            $realid = $a_gateways[$id]['attribute'];
        }

        $a_gateway_item = &config_read_array('gateways', 'gateway_item');
        $reloadif = "";
        $gateway = array();

        if (empty($pconfig['interface'])) {
            $gateway['interface'] = $pconfig['friendlyiface'];
        } else {
            $gateway['interface'] = $pconfig['interface'];
        }
        if (is_ipaddr($pconfig['gateway'])) {
            $gateway['gateway'] = $pconfig['gateway'];
        } else {
            $gateway['gateway'] = "dynamic";
        }
        $gateway['name'] = $pconfig['name'];
        $gateway['weight'] = $pconfig['weight'];
        $gateway['ipprotocol'] = $pconfig['ipprotocol'];
        $gateway['interval'] = $pconfig['interval'];
        $gateway['descr'] = $pconfig['descr'];
        $gateway['avg_delay_samples'] = $pconfig['avg_delay_samples'];

        if ($pconfig['avg_delay_samples_calculated'] == "yes" || $pconfig['avg_delay_samples_calculated'] == "on") {
            $gateway['avg_delay_samples_calculated'] = true;
        }
        $gateway['avg_loss_samples'] = $pconfig['avg_loss_samples'];
        if ($pconfig['avg_loss_samples_calculated'] == "yes" || $pconfig['avg_loss_samples_calculated'] == "on") {
            $gateway['avg_loss_samples_calculated'] = true;
        }
        $gateway['avg_loss_delay_samples'] = $pconfig['avg_loss_delay_samples'];
        if ($pconfig['avg_loss_delay_samples_calculated'] == "yes" || $pconfig['avg_loss_delay_samples_calculated'] == "on") {
            $gateway['avg_loss_delay_samples_calculated'] = true;
        }

        if ($pconfig['monitor_disable'] == "yes") {
            $gateway['monitor_disable'] = true;
        }
        if ($pconfig['force_down'] == "yes") {
            $gateway['force_down'] = true;
        }
        if (is_ipaddr($pconfig['monitor'])) {
            $gateway['monitor'] = $pconfig['monitor'];
        }

        /* NOTE: If monitor ip is changed need to cleanup the old static route */
        if (isset($realid) && $pconfig['monitor'] != "dynamic" && !empty($a_gateway_item[$realid]) && is_ipaddr($a_gateway_item[$realid]['monitor']) &&
            $pconfig['monitor'] != $a_gateway_item[$realid]['monitor'] && $gateway['gateway'] != $a_gateway_item[$realid]['monitor']) {
            if (is_ipaddrv4($a_gateway_item[$realid]['monitor'])) {
                mwexec("/sbin/route delete " . escapeshellarg($a_gateway_item[$realid]['monitor']));
            } else {
                mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateway_item[$realid]['monitor']));
            }
        }

        if ($pconfig['defaultgw'] == "yes" || $pconfig['defaultgw'] == "on") {
            $i = 0;
            /* remove the default gateway bits for all gateways with the same address family */
            foreach ($a_gateway_item as $gw) {
                if ($gateway['ipprotocol'] == $gw['ipprotocol']) {
                    unset($config['gateways']['gateway_item'][$i]['defaultgw']);
                    if ($gw['interface'] != $pconfig['interface'] && $gw['defaultgw']) {
                        $reloadif = $gw['interface'];
                    }
                }
                $i++;
            }
            $gateway['defaultgw'] = true;
        }

        foreach (array('latencylow', 'latencyhigh', 'losslow', 'losshigh', 'down') as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $gateway[$fieldname] = $pconfig[$fieldname];
            }
        }

        if (isset($_POST['disabled'])) {
            $gateway['disabled'] = true;
        } elseif (isset($gateway['disabled'])) {
            unset($gateway['disabled']);
        }

        if (isset($_POST['fargw'])) {
            $gateway['fargw'] = true;
        } elseif (isset($gateway['fargw'])) {
            unset($gateway['fargw']);
        }

        /* when saving the manual gateway we use the attribute which has the corresponding id */
        if (isset($realid)) {
            $a_gateway_item[$realid] = $gateway;
        } else {
            $a_gateway_item[] = $gateway;
        }

        mark_subsystem_dirty('staticroutes');

        write_config();

        if (!empty($_REQUEST['isAjax'])) {
            echo $pconfig['name'];
            exit;
        } elseif (!empty($reloadif)) {
            configd_run("interface reconfigure {$reloadif}");
        }

        header(url_safe('Location: /system_gateways.php'));
        exit;
    } else {
        if (!empty($_REQUEST['isAjax'])) {
            header("HTTP/1.0 500 Internal Server Error");
            header("Content-type: text/plain");
            echo implode("\n\n", $input_errors);
            exit;
        }

        if (!empty($pconfig['interface'])) {
            $pconfig['friendlyiface'] = $_POST['interface'];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // retrieve form data
    if (isset($_GET['id']) && isset($a_gateways[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    } elseif (isset($_GET['dup']) && isset($a_gateways[$_GET['dup']])) {
        $configId = $_GET['dup'];
    }
    // set config details
    $pconfig = array();
    $pconfig['attribute'] = null;
    $pconfig['monitor_disable'] = true;

    // load data from config
    $copy_fields = array(
      'name', 'weight', 'interval', 'avg_delay_samples', 'avg_loss_samples', 'avg_loss_delay_samples',
      'interface', 'friendlyiface', 'ipprotocol', 'gateway', 'latencylow', 'latencyhigh', 'losslow', 'losshigh',
      'down', 'monitor', 'descr', 'avg_delay_samples_calculated', 'avg_loss_samples_calculated', 'fargw',
      'avg_loss_delay_samples_calculated', 'monitor_disable', 'dynamic', 'defaultgw', 'force_down', 'disabled'
    );
    foreach ($copy_fields as $fieldname) {
        if (isset($configId) && isset($a_gateways[$configId][$fieldname])) {
            $pconfig[$fieldname] = $a_gateways[$configId][$fieldname];
        } elseif (empty($pconfig[$fieldname]) || isset($configId)) {
            $pconfig[$fieldname] = null;
        }
    }
    if (isset($id) && isset($a_gateways[$configId]['attribute'])) {
        $pconfig['attribute'] = $a_gateways[$configId]['attribute'];
    }
}

legacy_html_escape_form_data($a_gateways);
legacy_html_escape_form_data($pconfig);

$service_hook = 'apinger';

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
function recalc_value(object, min, max) {
    if (object.val() != "") {
        object.val(Math.round(object.val()));     // Round to integer
        if (object.val() < min)  object.val(min); // Min Value
        if (object.val() > max)  object.val(max); // Max Value
        if (isNaN(object.val())) object.val('');  // Empty Value
    }
}

function calculated_change() {
  // How many replies should be used to compute average delay
  // for controlling "delay" alarms.
  // Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
  if ($('#avg_delay_samples_calculated').prop('checked') && ( $('#interval').val() > 0)) {
      $('#avg_delay_samples').val(60 * (1/6) / Math.pow($('#interval').val(), 0.333));  // Calculate
  }
  recalc_value($('#avg_delay_samples'), 1, 100);

  // How many probes should be used to compute average loss.
  // Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
  if ($('#avg_loss_samples_calculated').prop('checked') && ( $('#interval').val() > 0)) {
      $('#avg_loss_samples').val(60 / $('#interval').val());  // Calculate
  }
  recalc_value($('#avg_loss_samples'), 1, 1000);

  // The delay (in samples) after which loss is computed
  // without this delays larger than interval would be treated as loss.
  // Calculate a reasonable value based on gateway probe interval and RRD 1 minute average graph step size (60).
  if ($('#avg_loss_delay_samples_calculated').prop('checked') && ( $('#interval').val() > 0)) {
      $('#avg_loss_delay_samples').val(60 * (1/3) / $('#interval').val());  // Calculate
  }
  recalc_value($('#avg_loss_delay_samples'), 1, 200);
}


$( document ).ready(function() {
    // unhide advanced
    $("#btn_advanced").click(function(event){
        event.preventDefault();
        $(".advanced").toggleClass('hidden visible');
    });

    // (un)hide advanced on form load when any advanced setting is provided
<?php
  if ((!empty($pconfig['latencylow']) || !empty($pconfig['latencyhigh']) || !empty($pconfig['losslow']) || !empty($pconfig['losshigh']) || (isset($pconfig['weight']) && $pconfig['weight'] > 1) || (!empty($pconfig['interval']) && ($pconfig['interval'] > $apinger_default['interval'])) || (!empty($pconfig['down']) && !($pconfig['down'] == $apinger_default['down'])))): ?>
    $("#btn_advanced").click();
<?php
  endif;?>

});
//]]>
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php if (isset($input_errors) && count($input_errors) > 0) {
    print_input_errors($input_errors);
} ?>
      <section class="col-xs-12">
        <div class="content-box  table-responsive">
            <form method="post" name="iform" id="iform">
<?php
            if ($pconfig['attribute'] == "system" || is_numeric($pconfig['attribute'])):?>
              <input type='hidden' name='attribute' id='attribute' value="<?=$pconfig['attribute'];?>"/>
<?php
            endif;?>
              <input type='hidden' name='friendlyiface' id='friendlyiface' value="<?=$pconfig['friendlyiface'];?>"/>
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><?=gettext("Edit gateway");?></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                  <td>
                    <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : ""; ?> />
                    <div class="hidden" for="help_for_disabled">
                      <strong><?=gettext("Disable this gateway");?></strong><br />
                      <?=gettext("Set this option to disable this gateway without removing it from the list.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
                  <td>
                    <select name='interface' class="selectpicker" data-style="btn-default" data-live-search="true">
<?php
                    foreach (get_configured_interface_with_descr(false, true) as $iface => $ifacename):?>
                      <option value="<?=$iface;?>" <?=$iface == $pconfig['friendlyiface'] ? "selected='selected'" : "";?>>
                        <?=$ifacename;?>
                      </option>
<?php
                      endforeach;?>
                    </select>
                      <div class="hidden" for="help_for_interface">
                        <?=gettext("Choose which interface this gateway applies to."); ?>
                      </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_ipprotocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Address Family"); ?></td>
                  <td>
                    <select name='ipprotocol' class="selectpicker" data-style="btn-default" >
                      <option value="inet" <?=$pconfig['ipprotocol'] == 'inet' ? "selected='selected'" : "";?>>
                          <?=gettext("IPv4");?>
                      </option>
                      <option value="inet6" <?=$pconfig['ipprotocol'] == 'inet6'? "selected='selected'" : "";?>>
                          <?=gettext("IPv6");?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_ipprotocol">
                        <?=gettext("Choose the Internet Protocol this gateway uses."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Name"); ?></td>
                  <td>
                    <input name="name" type="text" size="20" value="<?=$pconfig['name'];?>" />
                    <div class="hidden" for="help_for_name">
                      <?=gettext("Gateway name"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_gateway" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Gateway"); ?></td>
                  <td>
                    <input name="gateway" type="text" size="28" value="<?=!empty($pconfig['dynamic']) ? "dynamic" : $pconfig['gateway'];?>"/>
                    <div class="hidden" for="help_for_gateway">
                      <?=gettext("Gateway IP address"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_defaultgw" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Default Gateway"); ?></td>
                  <td>
                    <input name="defaultgw" type="checkbox" value="yes" <?=!empty($pconfig['defaultgw']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_defaultgw">
                      <?=gettext("This will select the above gateway as the default gateway"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_fargw" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Far Gateway"); ?></td>
                  <td>
                    <input name="fargw" type="checkbox" value="yes" <?=!empty($pconfig['fargw']) ? 'checked="checked"' : '';?> />
                    <div class="hidden" for="help_for_fargw">
                      <?=gettext("This will allow the gateway to exist outside of the interface subnet."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_monitor_disable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable Gateway Monitoring"); ?></td>
                  <td>
                    <input name="monitor_disable" type="checkbox" value="yes" <?=!empty($pconfig['monitor_disable']) ? "checked=\"checked\"" : "";?>/>
                    <div class="hidden" for="help_for_monitor_disable">
                      <?=gettext("This will consider this gateway as always being up"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_monitor" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Monitor IP"); ?></td>
                  <td>
                      <input name="monitor" type="text" value="<?=$pconfig['gateway'] == $pconfig['monitor'] ? "" : $pconfig['monitor'] ;?>" size="28" />
                      <div class="hidden" for="help_for_monitor">
                        <strong><?=gettext("Alternative monitor IP"); ?></strong> <br />
                        <?=gettext("Enter an alternative address here to be used to monitor the link. This is used for the " .
                                                "quality RRD graphs as well as the load balancer entries. Use this if the gateway does not respond " .
                                                "to ICMP echo requests (pings)"); ?>.
                      </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_force_down" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Mark Gateway as Down"); ?></td>
                  <td>
                    <input name="force_down" type="checkbox" value="yes" <?=!empty($pconfig['force_down']) ? "checked=\"checked\"" : "";?>/>
                    <div class="hidden" for="help_for_force_down">
                      <strong><?=gettext("Mark Gateway as Down"); ?></strong><br />
                      <?=gettext("This will force this gateway to be considered Down"); ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced visible">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Advanced");?></td>
                  <td>
                    <input type="button" id="btn_advanced" value="Advanced" class="btn btn-default btn-xs"/><?=gettext(" - Show advanced option"); ?>
                  </td>
                </tr>
                <tr class="advanced hidden">
                    <td colspan="2"> <b><?=gettext("Advanced");?> </b> </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_weight" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Weight");?></td>
                  <td>
                    <select name="weight" class="selectpicker" data-width="auto">
<?php
                    for ($i = 1; $i < 6; $i++):?>
                      <option value="<?=$i;?>" <?=$pconfig['weight'] == $i ? "selected='selected'" : "";?> >
                        <?=$i;?>
                      </option>
<?php
                    endfor;?>
                    </select>
                    <div class="hidden" for="help_for_weight">
                      <?=gettext("Weight for this gateway when used in a Gateway Group.");?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_latency" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Latency thresholds");?></td>
                  <td>
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                              <th><?=gettext("From");?></th>
                              <th><?=gettext("To");?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                              <td>
                                <input name="latencylow" type="text" value="<?=$pconfig['latencylow'];?>" />
                              </td>
                              <td>
                                <input name="latencyhigh" type="text" value="<?=$pconfig['latencyhigh'];?>" />
                              </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="hidden" for="help_for_latency">
                        <?= sprintf(gettext('Low and high thresholds for latency in milliseconds. Default is %d/%d.'), $apinger_default['latencylow'], $apinger_default['latencyhigh']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_loss" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Packet Loss thresholds");?></td>
                  <td>
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                              <th><?=gettext("From");?></th>
                              <th><?=gettext("To");?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                              <td>
                                <input name="losslow" type="text" value="<?=$pconfig['losslow'];?>" />
                              </td>
                              <td>
                                <input name="losshigh" type="text" value="<?=$pconfig['losshigh'];?>" />
                              </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="hidden" for="help_for_loss">
                      <?= sprintf(gettext('Low and high thresholds for packet loss in %%. Default is %d/%d.'), $apinger_default['losslow'], $apinger_default['losshigh']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_interval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Probe Interval");?></td>
                  <td>
                    <input name="interval" id="interval" type="text" value="<?=$pconfig['interval'];?>" onclick="calculated_change()" />
                    <div class="hidden" for="help_for_interval">
                      <?= sprintf(gettext('How often that an ICMP probe will be sent in seconds. Default is %d.'), $apinger_default['interval']) ?><br /><br />
                      <?=gettext("NOTE: The quality graph is averaged over seconds, not intervals, so as the probe interval is increased the accuracy of the quality graph is decreased.");?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_down" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Down");?></td>
                  <td>
                    <input name="down" type="text" value="<?=$pconfig['down'];?>" />
                    <div class="hidden" for="help_for_down">
                      <?= sprintf(gettext('The number of seconds of failed probes before the alarm will fire. Default is %d.'), $apinger_default['down']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_avg_delay_samples" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Avg Delay Replies Qty");?></td>
                  <td>
                    <input name="avg_delay_samples" id="avg_delay_samples" type="text" value="<?=$pconfig['avg_delay_samples'];?>" onchange="calculated_change()"  />
                    <input name="avg_delay_samples_calculated" type="checkbox" id="avg_delay_samples_calculated" value="yes" <?=!empty($pconfig['avg_delay_samples_calculated']) ? "checked=\"checked\"" : "";?> onclick="calculated_change()" />
                    <?=gettext("Use calculated value."); ?>
                    <div class="hidden" for="help_for_avg_delay_samples">
                      <?= sprintf(gettext('How many replies should be used to compute average delay for controlling "delay" alarms? Default is %d.'), $apinger_default['avg_delay_samples']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_avg_loss_samples" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Avg Packet Loss Probes Qty");?></td>
                  <td>
                    <input name="avg_loss_samples" type="text" id="avg_loss_samples" value="<?=$pconfig['avg_loss_samples'];?>" onchange="calculated_change()" />
                    <input name="avg_loss_samples_calculated" type="checkbox" id="avg_loss_samples_calculated" value="yes" <?= !empty($pconfig['avg_loss_samples_calculated']) ? "checked=\"checked\"" : "";?> onclick="calculated_change()" />
                    <?=gettext("Use calculated value."); ?>

                    <div class="hidden" for="help_for_avg_loss_samples">
                      <?= sprintf(gettext('How many probes should be used to compute average packet loss? Default is %d.'), $apinger_default['avg_loss_samples']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_avg_loss_delay_samples" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Lost Probe Delay");?></td>
                  <td>
                    <input name="avg_loss_delay_samples" type="text" id="avg_loss_delay_samples" value="<?=$pconfig['avg_loss_delay_samples'];?>" onchange="calculated_change()"  />
                    <input name="avg_loss_delay_samples_calculated" type="checkbox" id="avg_loss_delay_samples_calculated" value="yes" <?= !empty($pconfig['avg_loss_delay_samples_calculated']) ? "checked=\"checked\"" : "";?> onclick="calculated_change()" />
                    <?=gettext("Use calculated value."); ?>

                    <div class="hidden" for="help_for_avg_loss_delay_samples">
                      <?= sprintf(gettext('The delay (in qty of probe samples) after which loss is computed. Without this, delays longer than the probe interval would be treated as packet loss. Default is %d.'), $apinger_default['avg_loss_delay_samples']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td></td>
                  <td>
                    <small>
                      <?= gettext("The probe interval must be less than the down time, otherwise the gateway will seem to go down then come up again at the next probe."); ?><br /><br />
                      <?= gettext("The down time defines the length of time before the gateway is marked as down, but the accuracy is controlled by the probe interval. For example, if your down time is 40 seconds but on a 30 second probe interval, only one probe would have to fail before the gateway is marked down at the 40 second mark. By default, the gateway is considered down after 10 seconds, and the probe interval is 1 second, so 10 probes would have to fail before the gateway is marked down."); ?><br />
                    </small>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                  <td>
                    <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" for="help_for_descr">
                      <?=gettext("You may enter a description here for your reference (not parsed)"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                    <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>"
                           onclick="window.location.href='<?=isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_gateways.php';?>'" />
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

<?php include("foot.inc");
