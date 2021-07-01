<?php

/*
 * Copyright (C) 2014-2021 Deciso B.V.
 * Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>
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
require_once("interfaces.inc");
require_once("plugins.inc.d/dpinger.inc");

$gateways = new \OPNsense\Routing\Gateways(legacy_interfaces_details());
$a_gateways = array_values($gateways->gatewaysIndexedByName(true, false, true));
$dpinger_default = dpinger_defaults();

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

    if (empty($pconfig['name'])) {
        $input_errors[] = gettext("A valid gateway name must be specified.");
    } elseif (!isset($id) && !preg_match('/^[a-zA-Z0-9_\-]{1,32}$/', $pconfig['name'])) {
        $input_errors[] = sprintf(gettext('The name must be less than 32 characters long and may only consist of the following characters: %s'), 'a-z, A-Z, 0-9, _');
    }

    /* skip system gateways which have been automatically added */
    if (!empty($pconfig['gateway']) && !is_ipaddr($pconfig['gateway']) &&
        $pconfig['attribute'] !== "system" && $pconfig['gateway'] != "dynamic") {
        $input_errors[] = gettext("A valid gateway IP address must be specified.");
    }

    $vips = [];

    foreach (config_read_array('virtualip', 'vip') as $vip) {
        if ($pconfig['interface'] == $vip['interface']) {
            $vips[] = $vip;
        }
    }

    if (!empty($pconfig['gateway']) && is_ipaddr($pconfig['gateway'])) {
        if (is_ipaddrv4($pconfig['gateway'])) {
            list ($parent_ip, $parent_sn) = explode('/', find_interface_network(get_real_interface($pconfig['interface']), false));
            $parent_ip = empty($pconfig['ajaxip']) ? $parent_ip : $pconfig['ajaxip'];
            $parent_sn = empty($pconfig['ajaxnet']) ? $parent_sn : $pconfig['ajaxnet'];
            if (empty($parent_ip) || empty($parent_sn)) {
                $input_errors[] = gettext("Cannot add IPv4 Gateway Address because no IPv4 address could be found on the interface.");
            } else {
                $subnets = array(gen_subnet($parent_ip, $parent_sn) . "/" . $parent_sn);
                foreach ($vips as $vip) {
                    if (!is_ipaddrv4($vip['subnet'])) {
                        continue;
                    }
                    $subnets[] = gen_subnet($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
                }

                $found = false;

                foreach ($subnets as $subnet) {
                    if (ip_in_subnet($pconfig['gateway'], $subnet)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found && !isset($pconfig['fargw'])) {
                    $input_errors[] = sprintf(gettext('The gateway address "%s" does not lie within one of the chosen interface\'s IPv4 subnets.'), $pconfig['gateway']);
                }
            }
        } elseif (is_ipaddrv6($pconfig['gateway'])) {
            /* do not do a subnet match on a link local address, it's valid */
            if (!is_linklocal($pconfig['gateway'])) {
                list ($parent_ip, $parent_sn) = explode('/', find_interface_networkv6(get_real_interface($pconfig['interface'], 'inet6'), false));
                $parent_ip = empty($pconfig['ajaxip']) ? $parent_ip : $pconfig['ajaxip'];
                $parent_sn = empty($pconfig['ajaxnet']) ? $parent_sn : $pconfig['ajaxnet'];
                if (empty($parent_ip) || empty($parent_sn)) {
                    $input_errors[] = gettext("Cannot add IPv6 Gateway Address because no IPv6 address could be found on the interface.");
                } else {
                    $subnets = array(gen_subnetv6($parent_ip, $parent_sn) . "/" . $parent_sn);
                    foreach ($vips as $vip) {
                        if (!is_ipaddrv6($vip['subnet'])) {
                            continue;
                        }
                        $subnets[] = gen_subnetv6($vip['subnet'], $vip['subnet_bits']) . "/" . $vip['subnet_bits'];
                    }

                    $found = false;

                    foreach ($subnets as $subnet) {
                        if (ip_in_subnet($pconfig['gateway'], $subnet)) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $input_errors[] = sprintf(gettext('The gateway address "%s" does not lie within one of the chosen interface\'s IPv6 subnets.'), $pconfig['gateway']);
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
    if (($pconfig['monitor'] != '') && !is_ipaddr($pconfig['monitor']) && $pconfig['monitor'] != 'dynamic') {
        $input_errors[] = gettext("A valid monitor IP address must be specified.");
    }
    /* only allow correct IPv4 and IPv6 gateway addresses */
    if (!empty($pconfig['gateway']) && is_ipaddr($pconfig['gateway']) && $pconfig['gateway'] != "dynamic") {
        if (is_ipaddrv6($pconfig['gateway']) && ($pconfig['ipprotocol'] == "inet")) {
            $input_errors[] = sprintf(gettext('The IPv6 gateway address "%s" cannot be used as an IPv4 gateway.'), $pconfig['gateway']);
        }
        if (is_ipaddrv4($pconfig['gateway']) && ($pconfig['ipprotocol'] == "inet6")) {
            $input_errors[] = sprintf(gettext('The IPv4 gateway address "%s" can not be used as an IPv6 gateway.'), $pconfig['gateway']);
        }
    }
    /* only allow correct IPv4 and IPv6 monitor addresses */
    if (!empty($pconfig['monitor']) && is_ipaddr($pconfig['monitor']) && $pconfig['monitor'] != "dynamic") {
        if (is_ipaddrv6($pconfig['monitor']) && ($pconfig['ipprotocol'] == "inet")) {
            $input_errors[] = sprintf(gettext('The IPv6 monitor address "%s" can not be used on an IPv4 gateway.'), $pconfig['monitor']);
        }
        if (is_ipaddrv4($pconfig['monitor']) && ($pconfig['ipprotocol'] == "inet6")) {
            $input_errors[] = sprintf(gettext('The IPv4 monitor address "%s" can not be used on an IPv6 gateway.'), $pconfig['monitor']);
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

    if (!empty($pconfig['priority']) && !is_numeric($pconfig['priority'])) {
        $input_errors[] = gettext("Priority needs to be a numeric value.");
    }

    /****
    /* XXX: dpinger needs to take defaults under consideration
    /****/
    $dpinger_config = dpinger_defaults();
    foreach ($dpinger_config as $prop => $value) {
        $dpinger_config[$prop] = !empty($pconfig[$prop]) ? $pconfig[$prop] : $value;
    }

    if (!is_numeric($dpinger_config['latencylow'])) {
        $input_errors[] = gettext("The low latency threshold needs to be a numeric value.");
    } elseif ($dpinger_config['latencylow'] < 1) {
        $input_errors[] = gettext("The low latency threshold needs to be positive.");
    }

    if (!is_numeric($dpinger_config['latencyhigh'])) {
        $input_errors[] = gettext("The high latency threshold needs to be a numeric value.");
    } elseif ($dpinger_config['latencyhigh'] < 1) {
        $input_errors[] = gettext("The high latency threshold needs to be positive.");
    }

    if (!is_numeric($dpinger_config['losslow'])) {
        $input_errors[] = gettext("The low Packet Loss threshold needs to be a numeric value.");
    } elseif ($dpinger_config['losslow'] < 1) {
        $input_errors[] = gettext("The low Packet Loss threshold needs to be positive.");
    } elseif ($dpinger_config['losslow'] >= 100) {
        $input_errors[] = gettext("The low Packet Loss threshold needs to be less than 100.");
    }

    if (!is_numeric($dpinger_config['losshigh'])) {
        $input_errors[] = gettext("The high Packet Loss threshold needs to be a numeric value.");
    } elseif ($dpinger_config['losshigh'] < 1) {
        $input_errors[] = gettext("The high Packet Loss threshold needs to be positive.");
    } elseif ($dpinger_config['losshigh'] > 100) {
        $input_errors[] = gettext("The high Packet Loss threshold needs to be 100 or less.");
    }

    if (is_numeric($dpinger_config['latencylow']) && is_numeric($dpinger_config['latencyhigh']) &&
        $pconfig['latencylow'] > $pconfig['latencyhigh']
       ) {
        $input_errors[] = gettext("The high latency threshold needs to be higher than the low latency threshold");
    }

    if (is_numeric($dpinger_config['losslow']) && is_numeric($dpinger_config['losshigh']) && $dpinger_config['losslow'] > $dpinger_config['losshigh']) {
        $input_errors[] = gettext("The high Packet Loss threshold needs to be higher than the low Packet Loss threshold");
    }

    if (!is_numeric($dpinger_config['interval'])) {
        $input_errors[] = gettext("The probe interval needs to be a numeric value.");
    } elseif ($dpinger_config['interval'] < 1) {
        $input_errors[] = gettext("The probe interval needs to be positive.");
    }

    if (!is_numeric($dpinger_config['alert_interval'])) {
        $input_errors[] = gettext("The alert interval needs to be a numeric value.");
    } elseif ($dpinger_config['alert_interval'] < 1) {
        $input_errors[] = gettext("The alert interval needs to be positive.");
    }

    if (!is_numeric($dpinger_config['data_length'])) {
        $input_errors[] = gettext("The data length needs to be a numeric value.");
    } elseif ($dpinger_config['data_length'] < 0) {
        $input_errors[] = gettext("The data length needs to be positive.");
    }

    if (!is_numeric($dpinger_config['time_period'])) {
        $input_errors[] = gettext("The time period needs to be a numeric value.");
    } elseif ($dpinger_config['time_period'] < 1) {
        $input_errors[] = gettext("The time period needs to be positive.");
    } elseif (is_numeric($dpinger_config['interval']) && $dpinger_config['time_period'] < (2.1*$dpinger_config['interval'])) {
        $input_errors[] = gettext("The time period needs at least 2.1 times that of the probe interval.");
    }

    if (!is_numeric($dpinger_config['loss_interval'])) {
        $input_errors[] = gettext("The loss interval needs to be a numeric value.");
    } elseif ($dpinger_config['loss_interval'] < 1) {
        $input_errors[] = gettext("The loss interval needs to be positive.");
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

        $gateway['interface'] = $pconfig['interface'];
        if (is_ipaddr($pconfig['gateway'])) {
            $gateway['gateway'] = $pconfig['gateway'];
        } else {
            $gateway['gateway'] = "dynamic";
        }
        $gateway['name'] = $pconfig['name'];
        $gateway['priority'] = $pconfig['priority'];
        $gateway['weight'] = $pconfig['weight'];
        $gateway['ipprotocol'] = $pconfig['ipprotocol'];
        $gateway['interval'] = $pconfig['interval'];
        $gateway['descr'] = $pconfig['descr'];

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

        $gateway['defaultgw'] = ($pconfig['defaultgw'] == "yes" || $pconfig['defaultgw'] == "on");

        foreach (array('alert_interval', 'latencylow', 'latencyhigh', 'loss_interval', 'losslow', 'losshigh', 'time_period', 'data_length') as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $gateway[$fieldname] = $pconfig[$fieldname];
            }
        }

        if (isset($pconfig['disabled'])) {
            $gateway['disabled'] = true;
        } elseif (isset($gateway['disabled'])) {
            unset($gateway['disabled']);
        }

        if (isset($pconfig['fargw'])) {
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

        if (!empty($pconfig['isAjax'])) {
            echo $pconfig['name'];
            exit;
        } elseif (!empty($reloadif)) {
            configdp_run('interface reconfigure', array($reloadif));
        }

        header(url_safe('Location: /system_gateways.php'));
        exit;
    } else {
        if (!empty($pconfig['isAjax'])) {
            header("HTTP/1.0 500 Internal Server Error");
            header("Content-type: text/plain");
            echo implode("\n\n", $input_errors);
            exit;
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
        'defaultgw',
        'descr',
        'disabled',
        'dynamic',
        'fargw',
        'force_down',
        'gateway',
        'interface',
        'interval',
        'ipprotocol',
        'latencyhigh',
        'latencylow',
        'losshigh',
        'losslow',
        'monitor',
        'monitor_disable',
        'name',
        'weight',
        'alert_interval',
        'data_length',
        'time_period',
        'loss_interval',
        'priority'
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

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<script>
//<![CDATA[
$( document ).ready(function() {
    $("#ipprotocol").change(function () {
        if ($("#ipprotocol").val() == 'inet6') {
            $("#fargw_opts").hide();
        } else {
            $("#fargw_opts").show();
        }
    });
    $("#ipprotocol").change();

    // unhide advanced
    $("#btn_advanced").click(function(event){
        event.preventDefault();
        $(".advanced").toggleClass('hidden visible');
    });

    // (un)hide advanced on form load when any advanced setting is provided
<?php
  if ((!empty($pconfig['latencylow']) || !empty($pconfig['latencyhigh']) || !empty($pconfig['data_length']) || !empty($pconfig['losslow']) || !empty($pconfig['losshigh']) || (isset($pconfig['weight']) && $pconfig['weight'] > 1) || (!empty($pconfig['interval']) && ($pconfig['interval'] > $dpinger_default['interval'])) || (!empty($pconfig['alert_interval']) && ($pconfig['alert_interval'] > $dpinger_default['alert_interval'])) || (!empty($pconfig['time_period']) && ($pconfig['time_period'] > $dpinger_default['time_period'])) || (!empty($pconfig['loss_interval']) && ($pconfig['loss_interval'] > $dpinger_default['loss_interval'])))): ?>
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
                    <div class="hidden" data-for="help_for_disabled">
                      <?=gettext("Set this option to disable this gateway without removing it from the list.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Name') ?></td>
                  <td>
                    <input name="name" type="text" size="20" value="<?=$pconfig['name'];?>" />
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Description') ?></td>
                  <td>
                    <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
                  <td>
                    <select name='interface' class="selectpicker" data-style="btn-default" data-live-search="true">
<?php
                    foreach (legacy_config_get_interfaces(array('virtual' => false, "enable" => true)) as $iface => $ifcfg):?>
                      <option value="<?=$iface;?>" <?=$iface == $pconfig['interface'] ? "selected='selected'" : "";?>>
                        <?= $ifcfg['descr'] ?>
                      </option>
<?php
                      endforeach;?>
                    </select>
                      <div class="hidden" data-for="help_for_interface">
                        <?=gettext("Choose which interface this gateway applies to."); ?>
                      </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_ipprotocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Address Family"); ?></td>
                  <td>
                    <select id="ipprotocol" name="ipprotocol" class="selectpicker" data-style="btn-default" >
                      <option value="inet" <?=$pconfig['ipprotocol'] == 'inet' ? "selected='selected'" : "";?>>
                          <?=gettext("IPv4");?>
                      </option>
                      <option value="inet6" <?=$pconfig['ipprotocol'] == 'inet6'? "selected='selected'" : "";?>>
                          <?=gettext("IPv6");?>
                      </option>
                    </select>
                    <div class="hidden" data-for="help_for_ipprotocol">
                        <?=gettext("Choose the Internet Protocol this gateway uses."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('IP address') ?></td>
                  <td>
                    <input name="gateway" type="text" size="28" value="<?=!empty($pconfig['dynamic']) ? "dynamic" : $pconfig['gateway'];?>"/>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_defaultgw" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Upstream Gateway"); ?></td>
                  <td>
                    <input name="defaultgw" type="checkbox" value="yes" <?=!empty($pconfig['defaultgw']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" data-for="help_for_defaultgw">
                      <?= gettext('This will select the above gateway as a default gateway candidate.') ?>
                    </div>
                  </td>
                </tr>
                <tr id="fargw_opts">
                  <td><a id="help_for_fargw" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Far Gateway"); ?></td>
                  <td>
                    <input name="fargw" type="checkbox" value="yes" <?=!empty($pconfig['fargw']) ? 'checked="checked"' : '';?> />
                    <div class="hidden" data-for="help_for_fargw">
                      <?=gettext("This will allow the gateway to exist outside of the interface subnet."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_monitor_disable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disable Gateway Monitoring"); ?></td>
                  <td>
                    <input name="monitor_disable" type="checkbox" value="yes" <?=!empty($pconfig['monitor_disable']) ? "checked=\"checked\"" : "";?>/>
                    <div class="hidden" data-for="help_for_monitor_disable">
                      <?= gettext('This will consider this gateway as always being "up".') ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_monitor" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Monitor IP"); ?></td>
                  <td>
                      <input name="monitor" type="text" value="<?=$pconfig['gateway'] == $pconfig['monitor'] ? "" : $pconfig['monitor'] ;?>" size="28" />
                      <div class="hidden" data-for="help_for_monitor">
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
                    <div class="hidden" data-for="help_for_force_down">
                      <?= gettext('This will force this gateway to be considered "down".') ?>
                    </div>
                  </td>
                </tr>


                <tr>
                  <td><a id="help_for_priority" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Priority"); ?></td>
                  <td>
                    <select id="priority" name="priority" class="selectpicker"  data-live-search="true" data-size="5">
<?php
                    for ($prio=255; $prio >= 1; --$prio):?>
                        <option value="<?=$prio;?>" <?=$pconfig['priority'] == $prio ? "selected=selected" : "";?> >
                            <?=$prio;?>
                        </option>
<?php
                    endfor;?>
                    </select>
                    <div class="hidden" data-for="help_for_priority">
                      <?= gettext('Influences sort order when selecting a (default) gateway, lower means more important.') ?>
                    </div>
                  </td>
                </tr>

                <tr class="advanced visible">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Advanced");?></td>
                  <td>
                    <input type="button" id="btn_advanced" value="<?= html_safe(gettext('Advanced')) ?>" class="btn btn-default btn-xs"/><?=gettext(" - Show advanced option"); ?>
                  </td>
                </tr>
                <tr class="advanced hidden">
                    <td colspan="2"> <b><?=gettext("Advanced");?> </b> </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_weight" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Weight");?></td>
                  <td>
                    <select name="weight" class="selectpicker">
<?php
                    for ($i = 1; $i < 6; $i++):?>
                      <option value="<?=$i;?>" <?=$pconfig['weight'] == $i ? "selected='selected'" : "";?> >
                        <?=$i;?>
                      </option>
<?php
                    endfor;?>
                    </select>
                    <div class="hidden" data-for="help_for_weight">
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
                    <div class="hidden" data-for="help_for_latency">
                        <?= sprintf(gettext('Low and high thresholds for latency in milliseconds. Default is %d/%d.'), $dpinger_default['latencylow'], $dpinger_default['latencyhigh']) ?>
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
                    <div class="hidden" data-for="help_for_loss">
                      <?= sprintf(gettext('Low and high thresholds for packet loss in %%. Default is %d/%d.'), $dpinger_default['losslow'], $dpinger_default['losshigh']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_interval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Probe Interval");?></td>
                  <td>
                    <input name="interval" id="interval" type="text" value="<?=$pconfig['interval'];?>" />
                    <div class="hidden" data-for="help_for_interval">
                      <?= sprintf(gettext('How often that an ICMP probe will be sent in seconds. Default is %d.'), $dpinger_default['interval']) ?>
                    </div>
                  </td>
                </tr>
                 <tr class="advanced hidden">
                  <td><a id="help_for_alert_interval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Alert Interval");?></td>
                  <td>
                    <input name="alert_interval" id="alert_interval" type="text" value="<?=$pconfig['alert_interval'];?>" />
                    <div class="hidden" data-for="help_for_alert_interval">
                      <?= sprintf(gettext('Time interval between alerts. Default is %d.'), $dpinger_default['alert_interval']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_time_period" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Time Period");?></td>
                  <td>
                    <input name="time_period" id="interval" type="text" value="<?=$pconfig['time_period'];?>" />
                    <div class="hidden" data-for="help_for_time_period">
                      <?= sprintf(gettext('The time period over which results are averaged. Default is %d.'), $dpinger_default['time_period']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_loss_interval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Loss Interval");?></td>
                  <td>
                    <input name="loss_interval" id="loss_interval" type="text" value="<?=$pconfig['loss_interval'];?>" />
                    <div class="hidden" data-for="help_for_loss_interval">
                      <?= sprintf(gettext('Time interval before packets are treated as lost. Default is %d.'), $dpinger_default['loss_interval']) ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced hidden">
                  <td><a id="help_for_data_length" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Data Length");?></td>
                  <td>
                    <input name="data_length" id="data_length" type="text" value="<?=$pconfig['data_length'];?>" />
                    <div class="hidden" data-for="help_for_data_length">
                      <?= sprintf(gettext('Specify the number of data bytes to be sent. Default is %d.'), $dpinger_default['data_length']) ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Save')) ?>" />
                    <input type="button" class="btn btn-default" value="<?= html_safe(gettext('Cancel')) ?>"
                           onclick="window.location.href = '/system_gateways.php';" />
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
