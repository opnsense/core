<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

/**
 * return option array for valid translation networks
 */
function formTranslateAddresses() {
    global $config;
    $retval = array();

    // add this hosts ips
    foreach (legacy_config_get_interfaces(array('virtual' => false, "enable" => true)) as $intf => $intfdata) {
        $retval[$intf."ip"] = (!empty($intfdata['descr']) ? $intfdata['descr'] : $intf ) . " " . gettext("address");
    }

    // add VIPs's
    if (isset($config['virtualip']['vip'])) {
        foreach ($config['virtualip']['vip'] as $sn) {
            if (!isset($sn['noexpand'])) {
                if (in_array($sn['mode'], array("proxyarp", "other")) && $sn['type'] == "network") {
                    $start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
                    $end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
                    $len = $end - $start;
                    $retval[$sn['subnet'].'/'.$sn['subnet_bits']] = htmlspecialchars("Subnet: {$sn['subnet']}/{$sn['subnet_bits']} ({$sn['descr']})");
                    for ($i = 0; $i <= $len; $i++) {
                        $snip = long2ip32($start+$i);
                        $retval[$snip] = htmlspecialchars("{$snip} ({$sn['descr']})");
                    }
                } else {
                    $retval[$sn['subnet']] = htmlspecialchars("{$sn['subnet']} ({$sn['descr']})");
                }
            }
        }
    }

    // add Aliases
    foreach (legacy_list_aliases("network") as $alias) {
        if ($alias['type'] == "host") {
            $retval[$alias['name']] = $alias['name'];
        }
    }

    return $retval;
}

$a_out = &config_read_array('nat', 'outbound', 'rule');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // input record id, if valid
    if (isset($_GET['dup']) && isset($a_out[$_GET['dup']]))  {
        $configId = $_GET['dup'];
        $after = $configId;
    } elseif (isset($_GET['id']) && isset($a_out[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    }

    // init form data
    $pconfig = array();
    // set defaults
    $pconfig['source'] = 'any';
    $pconfig['source_subnet'] = 24;
    $pconfig['destination'] = "any";
    $pconfig['destination_subnet'] = 24;
    $pconfig['interface'] = "wan";

    if (isset($configId)) {
        // load data from config
        foreach (array('protocol','sourceport','dstport','natport','target','targetip'
                ,'targetip_subnet','poolopts','poolopts_sourcehashkey','interface','descr','nonat','log'
                ,'disabled','staticnatport','nosync','ipprotocol','tag','tagged', 'category') as $fieldname) {
              if (isset($a_out[$configId][$fieldname])) {
                  $pconfig[$fieldname] = $a_out[$configId][$fieldname];
              }
        }

        if (strpos($a_out[$configId]['source']['network'], "/") !== false) {
            list($pconfig['source'],$pconfig['source_subnet']) = explode('/', $a_out[$configId]['source']['network']);
        } else {
            $pconfig['source'] = $a_out[$configId]['source']['network'];
        }
        $pconfig['source_not'] = !empty($a_out[$configId]['source']['not']);

        if (!is_numeric($pconfig['source_subnet'])) {
              $pconfig['source_subnet'] = 32;
        }
        address_to_pconfig($a_out[$configId]['destination'], $pconfig['destination'],
          $pconfig['destination_subnet'], $pconfig['destination_not'],
          $none, $none);
    }

    // initialize unused elements
    foreach (array('protocol','sourceport','dstport','natport','target','targetip',
            'targetip_subnet','poolopts','poolopts_sourcehashkey','interface','descr','nonat','tag','tagged',
            'disabled','staticnatport','nosync','source','source_subnet','ipprotocol') as $fieldname) {
          if (!isset($pconfig[$fieldname])) {
              $pconfig[$fieldname] = null;
          }
    }
    if (empty($pconfig['ipprotocol'])) {
        if (strpos($pconfig['source'].$pconfig['destination'].$pconfig['targetip'], ":") !== false) {
            $pconfig['ipprotocol'] = 'inet6';
        } else {
            $pconfig['ipprotocol'] = 'inet';
        }
    }
    if (empty($pconfig['targetip'])) {
        $pconfig['targetip'] = $pconfig['target'];
    }
    $pconfig['category'] = !empty($pconfig['category']) ? explode(",", $pconfig['category']) : [];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    // input record id, if valid
    if (isset($pconfig['id']) && isset($a_out[$pconfig['id']])) {
        $id = $pconfig['id'];
    }
    if (isset($pconfig['after']) && isset($a_out[$pconfig['after']])) {
        $after = $pconfig['after'];
    }

    /* input validation */
    $reqdfields = explode(" ", "interface protocol source destination");
    $reqdfieldsn = array(gettext("Interface"),gettext("Protocol"),gettext("Source"),gettext("Destination"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (in_array($pconfig['protocol'], explode(" ", "any tcp udp tcp/udp"))) {
        if(!empty($pconfig['sourceport']) && !is_portrange($pconfig['sourceport']) && !is_portoralias($pconfig['sourceport'])) {
            $input_errors[] = gettext("You must supply either a valid port or port alias for the source port entry.");
        }
        if(!empty($pconfig['dstport']) && !is_portrange($pconfig['dstport']) && !is_portoralias($pconfig['dstport'])) {
            $input_errors[] = gettext("You must supply either a valid port or port alias for the destination port entry.");
        }
        if (!empty($pconfig['natport']) && !is_port($pconfig['natport']) && empty($pconfig['nonat'])) {
            $input_errors[] = gettext("You must supply a valid port for the NAT port entry.");
        }
    }

    if (!is_specialnet($pconfig['source']) && !is_ipaddroralias($pconfig['source'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $pconfig['source']);
    }

    if (!empty($pconfig['source_subnet']) && !is_numericint($pconfig['source_subnet'])) {
        $input_errors[] = gettext("A valid source bit count must be specified.");
    }
    if ($pconfig['source'] == "any" && !empty($pconfig['source_not'])) {
        $input_errors[] = gettext("Negating source address of \"any\" is invalid.");
    }
    if (!is_specialnet($pconfig['destination']) && !is_ipaddroralias($pconfig['destination'])) {
        $input_errors[] = gettext("A valid destination must be specified.");
    }
    if (!empty($pconfig['destination_subnet']) && !is_numericint($pconfig['destination_subnet'])) {
        $input_errors[] = gettext("A valid destination bit count must be specified.");
    }
    if ($pconfig['destination'] == "any" && !empty($pconfig['destination_not'])) {
        $input_errors[] = gettext("Negating destination address of \"any\" is invalid.");
    }

    if (!empty($pconfig['targetip']) && !is_ipaddr($pconfig['targetip']) && !is_subnet($pconfig['targetip'])
          && !is_specialnet($pconfig['targetip']) && !is_alias($pconfig['targetip']) && empty($pconfig['nonat'])) {
        $input_errors[] = gettext("A valid target IP address must be specified.");
    }
    /* Verify Pool Options */
    if (!empty($pconfig['targetip']) && is_alias($pconfig['targetip']) && !empty($pconfig['poolopts']) && substr($pconfig['poolopts'], 0, 11) != 'round-robin') {
        $input_errors[] = gettext("Only Round Robin pool options may be chosen when selecting an alias.");
    }
    /* Verify Source Hash Key if provided */
    if (!empty($pconfig['poolopts_sourcehashkey'])){
        if (empty($pconfig['poolopts']) || $pconfig['poolopts'] != 'source-hash') {
            $input_errors[] = gettext("Source Hash Key is only valid for Source Hash type");
        }
        if (substr($pconfig['poolopts_sourcehashkey'], 0, 2) != "0x" || !ctype_xdigit(substr($pconfig['poolopts_sourcehashkey'], 2, 32)) ){
            $input_errors[] = gettext("Source Hash Key must be 0x followed by 32 hexadecimal digits");
        }
    }
    // validate ipv4/v6, addresses should use selected address family
    foreach (array('source', 'destination', 'targetip') as $fieldname) {
        if (is_ipaddrv6($pconfig[$fieldname]) && $pconfig['ipprotocol'] != 'inet6') {
            $input_errors[] = sprintf(gettext("%s is not a valid IPv4 address."), $pconfig[$fieldname]);
        }
        if (is_ipaddrv4($pconfig[$fieldname]) && $pconfig['ipprotocol'] != 'inet') {
            $input_errors[] = sprintf(gettext("%s is not a valid IPv6 address."), $pconfig[$fieldname]);
        }
    }

    if (count($input_errors) == 0) {
        $natent = array();
        $natent['source'] = array();
        $natent['destination'] = array();
        $natent['descr'] = $pconfig['descr'];
        $natent['category'] = !empty($pconfig['category']) ? implode(",", $pconfig['category']) : null;
        $natent['interface'] = $pconfig['interface'];
        $natent['tag'] = $pconfig['tag'];
        $natent['tagged'] = $pconfig['tagged'];
        $natent['poolopts'] = $pconfig['poolopts'];
        $natent['poolopts_sourcehashkey'] = $pconfig['poolopts_sourcehashkey'];
        $natent['ipprotocol'] = $pconfig['ipprotocol'];

        if (isset($a_out[$id]['created']) && is_array($a_out[$id]['created']) ){
            $natent['created'] = $a_out[$id]['created'];
        }

        // target ip/net
        if (empty($pconfig['targetip'])) {
            // empty target "Interface address"
            $natent['target'] = $pconfig['targetip'];
            $natent['targetip_subnet'] = 0;
        } elseif (!array_key_exists($pconfig['targetip'], formTranslateAddresses())) {
            // a bit vague behaviour in "target" and "targetip", if a custom net is given
            // the backend code wants target to be filled with "other-subnet".
            // if any other known net is given, target is used to provide the actual address....
            // -- can't remove this behaviour now without breaking old config, so let's reimplement
            $natent['target'] = 'other-subnet';
            $natent['targetip'] = trim($pconfig['targetip']);
            $natent['targetip_subnet'] = $pconfig['targetip_subnet'];
        } else {
            $natent['target'] = $pconfig['targetip'];
        }

        // handle fields containing port numbers
        if (in_array($pconfig['protocol'], explode(" ", "any tcp udp tcp/udp"))) {
            if (isset($pconfig['staticnatport']) && empty($pconfig['nonat'])) {
                $natent['staticnatport'] = true;
            }
            $natent['sourceport'] = trim($pconfig['sourceport']);
            if (!empty($pconfig['natport']) && empty($pconfig['nonat'])) {
                $natent['natport'] = trim($pconfig['natport']);
            }
            if (!empty($pconfig['dstport'])) {
                $natent['dstport'] = trim($pconfig['dstport']);
            }
        } else {
            $natent['sourceport'] = "";
        }

        if (!empty($pconfig['protocol']) && $pconfig['protocol'] != "any") {
            $natent['protocol'] = $pconfig['protocol'];
        }

        /* parse source entry */
        if($pconfig['source'] == "any") {
            $natent['source']['network'] = "any";
        } else if($pconfig['source'] == "(self)") {
            $natent['source']['network'] = "(self)";
        } else if(is_alias($pconfig['source']) || is_specialnet($pconfig['source'])) {
            $natent['source']['network'] = trim($pconfig['source']);
        } else {
            if (is_ipaddrv6($pconfig['source'])) {
                $natent['source']['network'] = gen_subnetv6(trim($pconfig['source']), $pconfig['source_subnet']) . "/" . $pconfig['source_subnet'];
            } else {
                $natent['source']['network'] = gen_subnet(trim($pconfig['source']), $pconfig['source_subnet']) . "/" . $pconfig['source_subnet'];
            }
        }

        // destination address
        if ($pconfig['destination'] == "any") {
            $natent['destination']['any'] = true;
        } elseif (is_alias($pconfig['destination']) || is_specialnet($pconfig['destination'])){
            $natent['destination']['network'] = trim($pconfig['destination']) ;
        } else {
            if (is_ipaddrv6($pconfig['destination'])) {
                $natent['destination']['address'] = gen_subnetv6(trim($pconfig['destination']), $pconfig['destination_subnet']) . "/" . $pconfig['destination_subnet'];
            } else {
                $natent['destination']['address'] = gen_subnet(trim($pconfig['destination']), $pconfig['destination_subnet']) . "/" . $pconfig['destination_subnet'];
            }
        }

        // boolean fields
        if(!empty($pconfig['disabled'])) {
            $natent['disabled'] = true;
        }
        if(!empty($pconfig['nonat'])) {
            $natent['nonat'] = true;
        }
        if (!empty($pconfig['log'])) {
            $natent['log'] = true;
        }

        if(isset($pconfig['nosync'] ) && $pconfig['nosync'] == "yes") {
            $natent['nosync'] = true;
        }
        if (isset($pconfig['destination_not']) && $pconfig['destination'] != "any") {
            $natent['destination']['not'] = true;
        }
        if (isset($pconfig['source_not']) && $pconfig['source'] != "any") {
            $natent['source']['not'] = true;
        }

        $natent['updated'] = make_config_revision_entry();
        if (isset($id)) {
            $a_out[$id] = $natent;
        } else {
            $natent['created'] = make_config_revision_entry();
            if (isset($after)) {
                array_splice($a_out, $after+1, 0, array($natent));
            } else {
                $a_out[] = $natent;
            }
        }
        OPNsense\Core\Config::getInstance()->fromArray($config);
        $catmdl = new OPNsense\Firewall\Category();
        if ($catmdl->sync()) {
            $catmdl->serializeToConfig();
            $config = OPNsense\Core\Config::getInstance()->toArray(listtags());
        }
        write_config();
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat_out.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>
  <script src="<?= cache_safe('/ui/js/tokenize2.js') ?>"></script>
  <link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename('/css/tokenize2.css')) ?>">
  <script src="<?= cache_safe('/ui/js/opnsense_ui.js') ?>"></script>
  <script>
  $( document ).ready(function() {

    // select / input combination, link behaviour
    // when the data attribute "data-other" is selected, display related input item(s)
    // push changes from input back to selected option value
    $('[for!=""][for]').each(function(){
        var refObj = $("#"+$(this).attr("for"));
        if (refObj.is("select")) {
            // connect on change event to select box (show/hide)
            refObj.change(function(){
              if ($(this).find(":selected").attr("data-other") == "true") {
                  // show related controls
                  $('*[for="'+$(this).attr("id")+'"]').each(function(){
                    if ($(this).hasClass("selectpicker")) {
                      $(this).selectpicker('show');
                    } else {
                      $(this).removeClass("hidden");
                    }
                    $(this).prop('disabled', false);
                  });
              } else {
                  // hide related controls
                  $('*[for="'+$(this).attr("id")+'"]').each(function(){
                    if ($(this).hasClass("selectpicker")) {
                      $(this).selectpicker('hide');
                    } else {
                      $(this).addClass("hidden");
                    }
                    $(this).prop('disabled', true);
                  });
              }
            });
            // update initial
            refObj.change();

            // connect on change to input to save data to selector
            if ($(this).attr("name") == undefined) {
              $(this).change(function(){
                  var otherOpt = $('#'+$(this).attr('for')+' > option[data-other="true"]') ;
                  otherOpt.attr("value",$(this).val());
              });
            }
        }
    });

    // IPv4/IPv6 select
    hook_ipv4v6('ipv4v6net', 'network-id');
    formatTokenizersUI();
  });
  </script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped">
                <tr>
                  <td colspan="2">
                    <table>
                        <tr>
                            <td><?=gettext("Edit Advanced Outbound NAT entry");?></td>
                            <td colspan="2" style="text-align:right">
                                <small><?=gettext("full help"); ?> </small>
                                <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                            </td>
                        </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                  <td>
                    <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : ""; ?> />
                    <strong><?=gettext("Disable this rule"); ?></strong>
                    <div class="hidden" data-for="help_for_disabled">
                      <?=gettext("Set this option to disable this rule without removing it from the list."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_do_not_nat" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Do not NAT");?></td>
                  <td style="width:78%" class="vtable">
                    <input type="checkbox" name="nonat" <?=!empty($pconfig['nonat']) ? " checked=\"checked\"" : ""; ?> />
                    <div class="hidden" data-for="help_for_do_not_nat">
                      <?=gettext("Enabling this option will disable NAT for traffic matching this rule and stop processing Outbound NAT rules.");?><br />
                      <?=gettext("Hint: in most cases, you won't use this option.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
                  <td>
                    <div class="input-group">
                      <select name="interface" class="selectpicker" data-width="auto" data-live-search="true">
<?php
                        foreach (legacy_config_get_interfaces(array("enable" => true)) as $iface => $ifdetail): ?>
                        <option value="<?=$iface;?>" <?= $iface == $pconfig['interface'] ? "selected=\"selected\"" : ""; ?>>
                          <?=htmlspecialchars($ifdetail['descr']);?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="hidden" data-for="help_for_interface">
                      <?=gettext("Choose which interface this rule applies to"); ?>.<br />
                      <?=gettext("Hint: in most cases, you'll want to use WAN here"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_ipv46" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("TCP/IP Version");?></td>
                  <td>
                    <select name="ipprotocol" class="selectpicker" data-width="auto" data-live-search="true" data-size="5" >
<?php
                    foreach (array('inet' => 'IPv4','inet6' => 'IPv6') as $proto => $name): ?>
                    <option value="<?=$proto;?>" <?= $proto == $pconfig['ipprotocol'] ? "selected=\"selected\"" : "";?>>
                      <?=$name;?>
                    </option>
<?php
                    endforeach; ?>
                    </select>
                    <div class="hidden" data-for="help_for_ipv46">
                      <?=gettext("Select the Internet Protocol version this rule applies to");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_proto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol"); ?></td>
                  <td>
                    <div class="input-group">
                      <select id="proto" name="protocol" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
<?php                foreach (get_protocols() as $proto):
?>
              <option value="<?=strtolower($proto);?>" <?= strtolower($proto) == $pconfig['protocol'] ? "selected=\"selected\"" : ""; ?>><?=$proto;?></option>
<?php                endforeach; ?>
              </select>
                    </div>
                    <div class="hidden" data-for="help_for_proto">
                      <?=gettext("Choose which IP protocol " ."this rule should match."); ?><br/>
                      <?=gettext("Hint: in most cases, you should specify"); ?> <em><?=gettext("TCP"); ?></em> &nbsp;<?=gettext("here."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td> <a id="help_for_src_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Source invert') ?></td>
                  <td>
                    <input name="source_not" type="checkbox" value="yes" <?= !empty($pconfig['source_not']) ? 'checked="checked"' : '' ?> />
                    <div class="hidden" data-for="help_for_src_invert">
                      <?=gettext("Use this option to invert the sense of the match."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                    <td><a id="help_for_source" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Source address') ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="source" id="source" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['source'];?>" <?=!is_alias($pconfig['source']) && !in_array($pconfig['source'],array('(self)','any'))  ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                            foreach (legacy_list_aliases("network") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['source'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php                            endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Networks");?>">
<?php                             foreach (get_specialnets(true) as $ifent => $ifdesc):
?>
                                      <option value="<?=$ifent;?>" <?= $pconfig['source'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
<?php                              endforeach; ?>
                              </optgroup>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="input-group">
                          <!-- updates to "other" option in  source -->
                          <input type="text" for="source" id="src_address" value="<?=$pconfig['source'];?>" aria-label="<?=gettext("Source address");?>"/>
                          <select name="source_subnet"  data-network-id="src_address" class="selectpicker ipv4v6net input-group-btn" data-size="5" id="srcmask"  data-width="auto" for="source" >
                          <?php for ($i = 128; $i > 0; $i--): ?>
                            <option value="<?=$i;?>" <?= $i == $pconfig['source_subnet'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                          <?php endfor; ?>
                          </select>
                        </div>
                        </td>
                      </tr>
                    </table>
                    <div class="hidden" data-for="help_for_source">
                      <?=gettext("Enter the source network for the outbound NAT mapping.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_src_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Source port') ?></td>
                  <td>
                    <table class="table table-condensed">
                      <tbody>
                        <tr>
                          <td>
                            <select id="sourceport" name="sourceport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['sourceport'];?>">(<?=gettext("other"); ?>)</option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("port") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?= $pconfig['sourceport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Well-known ports");?>">
                                <option value="" <?= $pconfig['sourceport'] == "" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                <option value="<?=$wkport;?>" <?= $wkport == $pconfig['sourceport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <input type="text" value="<?=$pconfig['sourceport'];?>" for="sourceport"> <!-- updates to "other" option in  localbeginport -->
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="hidden" data-for="help_for_src_port">
                      <?=gettext("(leave blank for any)");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td> <a id="help_for_dst_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Destination invert') ?></td>
                  <td>
                    <input name="destination_not" type="checkbox" value="yes" <?= !empty($pconfig['destination_not']) ? 'checked="checked"' : '' ?> />
                    <div class="hidden" data-for="help_for_dst_invert">
                      <?=gettext("Use this option to invert the sense of the match."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                    <td><a id="help_for_destination" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Destination address') ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="destination" id="destination" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['destination'];?>" <?=!is_alias($pconfig['destination']) && $pconfig['destination'] != 'any' ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                             foreach (legacy_list_aliases("network") as $alias):
?>
                                      <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['destination'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php                              endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Networks");?>">
<?php                             foreach (get_specialnets(true) as $ifent => $ifdesc):
?>
                                      <option value="<?=$ifent;?>" <?= $pconfig['destination'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
<?php                              endforeach; ?>
                              </optgroup>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="input-group">
                          <!-- updates to "other" option in  source -->
                          <input type="text" id="dst_address" for="destination" value="<?=$pconfig['destination'];?>" aria-label="<?=gettext("Destination address");?>"/>
                          <select name="destination_subnet" data-network-id="dst_address" class="selectpicker ipv4v6net input-group-btn" id="dstmask" data-size="5" data-width="auto" for="destination" >
                          <?php for ($i = 128; $i > 0; $i--): ?>
                            <option value="<?=$i;?>" <?= $i == $pconfig['destination_subnet'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                          <?php endfor; ?>
                          </select>
                        </div>
                        </td>
                      </tr>
                    </table>
                    <div class="hidden" data-for="help_for_destination">
                      <?=gettext("Enter the destination network for the outbound NAT mapping.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_dstport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Destination port') ?></td>
                  <td>
                    <table class="table table-condensed">
                      <tbody>
                        <tr>
                          <td>
                            <select id="dstport" name="dstport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['dstport'];?>">(<?=gettext("other"); ?>)</option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("port") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?= $pconfig['dstport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Well-known ports");?>">
                                <option value="" <?= $pconfig['dstport'] == "" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                <option value="<?=$wkport;?>" <?= $wkport == $pconfig['dstport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <input type="text" value="<?=$pconfig['dstport'];?>" for="dstport"> <!-- updates to "other" option in  localbeginport -->
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="hidden" data-for="help_for_dstport">
                      <?=gettext("(leave blank for any)");?>
                    </div>
                  </td>
                </tr>
                <tr>
                    <td><a id="help_for_target" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Translation / target"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="targetip" id="targetip" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option value="" <?= empty($pconfig['targetip']) ? "selected=\"selected\"" : "";?> > <?=gettext("Interface address");?> </option>
                                <option data-other=true value="<?=$pconfig['targetip'];?>" <?= !empty($pconfig['targetip']) && !array_key_exists($pconfig['targetip'], formTranslateAddresses() ) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
<?php                              foreach (formTranslateAddresses() as $optKey => $optValue): ?>
                                    <option value="<?=$optKey;?>" <?= $pconfig['targetip'] == $optKey ? "selected=\"selected\"" : ""; ?>>
                                      <?=$optValue;?>
                                    </option>
<?php                              endforeach; ?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="input-group">
                              <!-- updates to "other" option in  source -->
                              <input type="text" id="targetip_text" for="targetip" value="<?=$pconfig['targetip'];?>" aria-label="<?=gettext("Translation address");?>"/>
                              <select name="targetip_subnet" data-network-id="targetip_text" class="selectpicker ipv4v6net input-group-btn" id="targetip_subnet" data-size="5" data-width="auto" for="targetip" >
                              <?php for ($i = 128; $i > 0; $i--): ?>
                                <option value="<?=$i;?>" <?= $i == $pconfig['targetip_subnet'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                              <?php endfor; ?>
                              </select>
                            </div>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" data-for="help_for_target">
                        <?=gettext("Packets matching this rule will be mapped to the IP address given here.");?><br />
                        <?=sprintf(gettext("If you want this rule to apply to another IP address rather than the IP address of the interface chosen above, ".
                                "select it here (you will need to define %sVirtual IP addresses%s on the interface first)."),'<a href="firewall_virtual_ip.php">','</a>')?>
                      </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_log" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Log");?></td>
                  <td>
                    <input name="log" type="checkbox" id="log" value="yes" <?= !empty($pconfig['log']) ? "checked=\"checked\"" : ""; ?> />
                    <strong><?=gettext("Log packets that are handled by this rule");?></strong>
                    <div class="hidden" data-for="help_for_log">
                      <?=sprintf(gettext("Hint: the firewall has limited local log space. Don't turn on logging for everything. If you want to do a lot of logging, consider using a %sremote syslog server%s."),'<a href="diag_logs_settings.php">','</a>') ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_natport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Translation") . " / " .gettext("port:");?></td>
                  <td>
                    <input name="natport" type="text" value="<?=$pconfig['natport'];?>" />
                    <div class="hidden" data-for="help_for_natport">
                      <?=gettext("Enter the source port for the outbound NAT mapping.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Static-port:");?></td>
                  <td>
                    <input name="staticnatport" type="checkbox" <?=!empty($pconfig['staticnatport']) ? " checked=\"checked\"" : "";?> >
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_poolopts" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Pool Options:");?></td>
                  <td>
                  <table class="table table-condensed">
                    <tbody>
                      <tr>
                        <td>
                        <select name="poolopts" id="poolopts" class="selectpicker">
                        <option value="" <?=empty($pconfig['poolopts']) ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("Default");?>
                        </option>
                        <option value="round-robin" <?=$pconfig['poolopts'] == "round-robin" ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("Round Robin");?>
                        </option>
                        <option value="round-robin sticky-address" <?=$pconfig['poolopts'] == "round-robin sticky-address" ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("Round Robin with Sticky Address");?>
                        </option>
                        <option value="random" <?=$pconfig['poolopts'] == "random" ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("Random");?>
                        </option>
                        <option value="random sticky-address" <?=$pconfig['poolopts'] == "random sticky-address" ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("Random with Sticky Address");?>
                        </option>
                        <option value="source-hash" data-other="true" <?=$pconfig['poolopts'] == "source-hash" ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("Source Hash");?>
                        </option>
                        <option value="bitmask" <?=$pconfig['poolopts'] == "bitmask" ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("Bitmask");?>
                        </option>
                        </select>
                        <div class="hidden" data-for="help_for_poolopts">
                          <?=gettext("Only Round Robin types work with Host Aliases. Any type can be used with a Subnet.");?><br />
                          <ul>
                            <li> <?=gettext("Round Robin: Loops through the translation addresses.");?></li>
                            <li> <?=gettext("Random: Selects an address from the translation address pool at random.");?></li>
                            <li> <?=gettext("Source Hash: Uses a hash of the source address to determine the translation address, ensuring that the redirection address is always the same for a given source. Optionally provide a Source Hash Key to make it persist when the ruleset is reloaded. Must be 0x followed by 32 hexadecimal digits.");?></li>
                            <li> <?=gettext("Bitmask: Applies the subnet mask and keeps the last portion identical; 10.0.1.50 -&gt; x.x.x.50.");?></li>
                            <li> <?=gettext("Sticky Address: The Sticky Address option can be used with the Random and Round Robin pool types to ensure that a particular source address is always mapped to the same translation address.");?></li>
                          </ul>
                        </div>
                        </td>
                        </tr>
                        <tr>
                        <td>
                          <input type="text" id="poolopts_sourcehashkey" name="poolopts_sourcehashkey" for="poolopts" placeholder="Source Hash Key" value="<?=$pconfig['poolopts_sourcehashkey']?>"/>
                        </td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
                <tr>
                    <td><a id="help_for_tag" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Set local tag"); ?></td>
                    <td>
                      <input name="tag" type="text" value="<?=$pconfig['tag'];?>" />
                      <div class="hidden" data-for="help_for_tag">
                        <?= gettext("You can mark a packet matching this rule and use this mark to match on other NAT/filter rules.") ?>
                      </div>
                    </td>
                </tr>
                <tr>
                    <td><a id="help_for_tagged" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Match local tag"); ?>   </td>
                    <td>
                      <input name="tagged" type="text" value="<?=$pconfig['tagged'];?>" />
                      <div class="hidden" data-for="help_for_tagged">
                        <?=gettext("You can match packet on a mark placed before on another rule.")?>
                      </div>
                    </td>
                </tr>
                <tr>
                  <td><a id="help_for_nosync" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("No XMLRPC Sync"); ?></td>
                  <td>
                    <input type="checkbox" value="yes" name="nosync" <?=!empty($pconfig['nosync']) ? "checked=\"checked\"" :"";?> />
                    <div class="hidden" data-for="help_for_nosync">
                      <?=gettext("Hint: This prevents the rule on Master from automatically syncing to other CARP members. This does NOT prevent the rule from being overwritten on Slave.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_category" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Category"); ?></td>
                  <td>
                    <select name="category[]" id="category" multiple="multiple" class="tokenize" data-allownew="true" data-width="334px" data-live-search="true">
<?php
                    foreach ((new OPNsense\Firewall\Category())->iterateCategories() as $category):
                      $catname = htmlspecialchars($category['name'], ENT_QUOTES | ENT_HTML401);?>
                      <option value="<?=$catname;?>" <?=!empty($pconfig['category']) && in_array($catname, $pconfig['category']) ? 'selected="selected"' : '';?> ><?=$catname;?></option>
<?php
                    endforeach;?>
                    </select>
                    <div class="hidden" data-for="help_for_category">
                      <?=gettext("You may enter or select a category here to group firewall rules (not parsed)."); ?>
                    </div>
                </tr>
                <tr>
                  <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                  <td>
                    <input name="descr" type="text" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" data-for="help_for_descr">
                      <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                    </div>
                </tr>
<?php
                $has_created_time = (isset($a_out[$id]['created']) && is_array($a_out[$id]['created']));
                $has_updated_time = (isset($a_out[$id]['updated']) && is_array($a_out[$id]['updated']));
                if ($has_created_time || $has_updated_time):
?>
                <tr>
                  <td colspan="2">&nbsp;</td>
                </tr>
                <tr>
                  <td colspan="2"><?=gettext("Rule Information");?></td>
                </tr>
<?php
                  if ($has_created_time):
?>
                <tr>
                  <td><?=gettext("Created");?></td>
                  <td>
                    <?= date(gettext('n/j/y H:i:s'), $a_out[$id]['created']['time']) ?> (<?= $a_out[$id]['created']['username'] ?>)
                  </td>
                </tr>
<?php
                  endif;
                  if ($has_updated_time):
?>
                <tr>
                  <td><?=gettext("Updated");?></td>
                  <td>
                    <?= date(gettext('n/j/y H:i:s'), $a_out[$id]['updated']['time']) ?> (<?= $a_out[$id]['updated']['username'] ?>)
                  </td>
                </tr>
<?php
                  endif;
                endif;
?>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                    <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/firewall_nat_out.php'" />
<?php
                    if (isset($id)):
?>
                    <input name="id" type="hidden" value="<?=$id;?>" />
<?php
                    endif;
?>
                    <input name="after" type="hidden" value="<?=isset($after) ? $after : "";?>" />
                  </td>
                </tr>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
