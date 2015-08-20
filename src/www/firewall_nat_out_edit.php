<?php
/*
  Copyright (C) 2014-2015 Deciso B.V.
  Copyright (C) 2004 Scott Ullrich
  Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("pfsense-utils.inc");


/**
 * build array with interface options for this form
 */
function formInterfaces() {
    global $config;
    $interfaces = array();
    foreach ( get_configured_interface_with_descr(false, true) as $if => $ifdesc)
        $interfaces[$if] = $ifdesc;

    if (isset($config['l2tp']['mode']) && $config['l2tp']['mode'] == "server")
        $interfaces['l2tp'] = "L2TP VPN";

    if (isset($config['pptpd']['mode']) && $config['pptpd']['mode'] == "server")
        $interfaces['pptp'] = "PPTP VPN";

    if (is_pppoe_server_enabled())
      $interfaces['pppoe'] = "PPPoE VPN";

    /* add ipsec interfaces */
    if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
        $interfaces["enc0"] = "IPsec";

    /* add openvpn/tun interfaces */
    if (isset($config['openvpn']['openvpn-server']) || isset($config['openvpn']['openvpn-client'])) {
      $interfaces['openvpn'] = 'OpenVPN';
    }
    return $interfaces;
}

/**
 * return option array for valid translation networks
 */
function formTranslateAddresses() {
    global $config;
    $retval = array();

    // add this hosts ips
    foreach ($config['interfaces'] as $intf => $intfdata) {
        if (isset($intfdata['ipaddr']) && $intfdata['ipaddr'] != 'dhcp') {
            $retval[$intfdata['ipaddr']] = (!empty($intfdata['descr']) ? $intfdata['descr'] : $intf ) . " " . gettext("address");
        }
    }

    // add VIPs's
    if (isset($config['virtualip']['vip'])) {
        foreach ($config['virtualip']['vip'] as $sn) {
            if (!isset($sn['noexpand'])) {
                if ($sn['mode'] == "proxyarp" && $sn['type'] == "network") {
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
    foreach (legacy_list_aliasses("network") as $alias) {
        if ($alias['type'] == "host") {
            $retval[$alias['name']] = $alias['name'];;
        }
    }

    return $retval;
}

if (!isset($config['nat']['outbound']['rule'])) {
    if (!isset($config['nat']['outbound'])) {
        $config['nat']['outbound'] = array();
    }
    $config['nat']['outbound']['rule'] = array();
}
$a_out = &$config['nat']['outbound']['rule'];

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
                ,'targetip_subnet','poolopts','interface','descr','nonat'
                ,'disabled','staticnatport','nosync') as $fieldname) {
              if (isset($a_out[$configId][$fieldname])) {
                  $pconfig[$fieldname] = $a_out[$configId][$fieldname];
              }
        }

        if (strpos($a_out[$configId]['source']['network'], "/") !== false) {
            list($pconfig['source'],$pconfig['source_subnet']) = explode('/', $a_out[$configId]['source']['network']);
        } else {
            $pconfig['source_subnet'] = $a_out[$configId]['source']['network'];
        }

        if (!is_numeric($pconfig['source_subnet']))
          $pconfig['source_subnet'] = 32;
        address_to_pconfig($a_out[$configId]['destination'], $pconfig['destination'],
          $pconfig['destination_subnet'], $pconfig['destination_not'],
          $none, $none);
    }

    // initialize unused elements
    foreach (array('protocol','sourceport','dstport','natport','target','targetip'
            ,'targetip_subnet','poolopts','interface','descr','nonat'
            ,'disabled','staticnatport','nosync','source','source_subnet') as $fieldname) {
          if (!isset($pconfig[$fieldname])) {
              $pconfig[$fieldname] = null;
          }
    }


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
    foreach ($pconfig as $key => $value) {
        if(htmlentities($value) <> $value) {
            $input_errors[] = sprintf(gettext("Invalid characters detected %s. Please remove invalid characters and save again."), htmlentities($value));
        }
    }
    $reqdfields = explode(" ", "interface protocol source source_subnet destination destination_subnet");
    $reqdfieldsn = array(gettext("Interface"),gettext("Protocol"),gettext("Source"),gettext("Source bit count"),gettext("Destination"),gettext("Destination bit count"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (in_array($pconfig['protocol'], explode(" ", "any tcp udp tcp/udp"))) {
        if(!empty($pconfig['sourceport']) && !is_portoralias($pconfig['sourceport']))
          $input_errors[] = gettext("You must supply either a valid port or port alias for the source port entry.");

        if(!empty($pconfig['dstport']) && !is_portoralias($pconfig['dstport']))
          $input_errors[] = gettext("You must supply either a valid port or port alias for the destination port entry.");

        if(!empty($pconfig['natport']) && !is_port($pconfig['natport']) && empty($pconfig['nonat']))
          $input_errors[] = gettext("You must supply a valid port for the NAT port entry.");
    }

    if (!(in_array($pconfig['source'], array("any","self")) || is_ipaddroralias($pconfig['source']))) {
        $input_errors[] = gettext("A valid source must be specified.");
    }
    if (!empty($pconfig['source_subnet']) && !is_numericint($pconfig['source_subnet'])) {
      $input_errors[] = gettext("A valid source bit count must be specified.");
    }
    if (!(in_array($pconfig['destination'], array("any","self")) || is_ipaddroralias($pconfig['destination']))) {
        $input_errors[] = gettext("A valid destination must be specified.");
    }
    if (!empty($pconfig['destination_subnet']) && !is_numericint($pconfig['destination_subnet'])) {
      $input_errors[] = gettext("A valid destination bit count must be specified.");
    }
    if ($pconfig['destination'] == "any" && !empty($pconfig['destination_not'])) {
      $input_errors[] = gettext("Negating destination address of \"any\" is invalid.");
    }

    if (!is_ipaddr($pconfig['targetip']) && !is_subnet($pconfig['targetip']) && !is_alias($pconfig['targetip']) && empty($pconfig['nonat'])) {
      $input_errors[] = gettext("A valid target IP address must be specified.");
    }
    /* Verify Pool Options */
    if (!is_alias($pconfig['targetip']) && substr($pconfig['poolopts'], 0, 11) == "round-robin") {
        $input_errors[] = gettext("Only Round Robin pool options may be chosen when selecting an alias.");
    }

    if (count($input_errors) == 0) {
        $natent = array();
        $natent['source'] = array();
        $natent['destination'] = array();
        $natent['descr'] = $pconfig['descr'];
        $natent['interface'] = $pconfig['interface'];
        $natent['poolopts'] = $pconfig['poolopts'];

        if ( isset($a_out[$id]['created']) && is_array($a_out[$id]['created']) ){
            $natent['created'] = $a_out[$id]['created'];
        }

        // target ip/net
        if (!array_key_exists($pconfig['targetip'], formTranslateAddresses())) {
            // a bit vague behaviour in "target" and "targetip", if a custom net is given
            // the backend code wants target to be filled with "other-subnet".
            // if any other known net is given, target is used to provide the actual address....
            // -- can't remove this behaviour now without breaking old confid, so let's reimplement
            $natent['target'] = 'other-subnet';
            $natent['targetip'] = trim($pconfig['targetip']) ;
            $natent['targetip_subnet'] = $pconfig['targetip_subnet'] ;
        } else {
            $natent['target'] = $pconfig['targetip'] ;
        }


        // handle fields containing portnumbers
        if (in_array($pconfig['protocol'], explode(" ", "any tcp udp tcp/udp"))) {
            if (isset($pconfig['staticnatport']) && !empty($pconfig['nonat'])) {
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
        } else if(is_alias($pconfig['source'])) {
            $natent['source']['network'] = trim($pconfig['source']);
        } else {
            $natent['source']['network'] = gen_subnet(trim($pconfig['source']), $pconfig['source_subnet']) . "/" . $pconfig['source_subnet'];
        }

        // destination address
        if ($pconfig['destination'] == "any") {
            $natent['destination']['any'] = true;
        } elseif (is_alias($pconfig['destination'])){
            $natent['destination']['address'] = trim($pconfig['destination']) ;
        } else {
            $natent['destination']['address'] = gen_subnet(trim($pconfig['destination']), $pconfig['destination_subnet']) . "/" . $pconfig['destination_subnet'];;
        }

        // boolean fields
        if(!empty($pconfig['disabled'])) {
          $natent['disabled'] = true;
        }
        if(!empty($pconfig['nonat'])) {
            $natent['nonat'] = true;
        }

        if(isset($pconfig['nosync'] ) && $pconfig['nosync'] == "yes") {
            $natent['nosync'] = true;
        }
        if (isset($pconfig['destination_not']) && $pconfig['destination'] != "any") {
            $natent['destination']['not'] = true;
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
        if (write_config()) {
            mark_subsystem_dirty('natconf');
        }
        header("Location: firewall_nat_out.php");
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Outbound"),gettext("Edit"));
$closehead = false;
include("head.inc");
?>
</head>
<body>
  <script type="text/javascript">
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
                  });
              } else {
                  // hide related controls
                  $('*[for="'+$(this).attr("id")+'"]').each(function(){
                    if ($(this).hasClass("selectpicker")) {
                      $(this).selectpicker('hide');
                    } else {
                      $(this).addClass("hidden");
                    }
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

  });
  </script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form action="firewall_nat_out_edit.php" method="post" name="iform" id="iform">
              <table class="table table-striped">
                <tr>
                  <td colspan="2">
                    <table>
                        <tr>
                            <td><?=gettext("Edit Advanced Outbound NAT entry");?></td>
                            <td colspan="2" align="right">
                                <small><?=gettext("full help"); ?> </small>
                                <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_opnvpn_server" type="button"></i></a>
                            </td>
                        </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                  <td>
                    <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : ""; ?> />
                    <div class="hidden" for="help_for_disabled">
                      <strong><?=gettext("Disable this rule"); ?></strong><br />
                      <?=gettext("Set this option to disable this rule without removing it from the list."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_do_not_nat" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Do not NAT");?></td>
                  <td width="78%" class="vtable">
                    <input type="checkbox" name="nonat" <?=!empty($pconfig['nonat']) ? " checked=\"checked\"" : ""; ?> />
                    <div class="hidden" for="help_for_do_not_nat">
                      <?=gettext("Enabling this option will disable NAT for traffic matching this rule and stop processing Outbound NAT rules.");?><br />
                      <?=gettext("Hint: in most cases, you won't use this option.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
                  <td>
                    <div class="input-group">
                      <select name="interface" class="selectpicker" data-width="auto" data-live-search="true" onchange="dst_change(this.value,iface_old,document.iform.dsttype.value);iface_old = document.iform.interface.value;typesel_change();">
<?php
                        foreach (formInterfaces() as $iface => $ifacename): ?>
                        <option value="<?=$iface;?>" <?= $iface == $pconfig['interface'] ? "selected=\"selected\"" : ""; ?>>
                          <?=htmlspecialchars($ifacename);?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="hidden" for="help_for_interface">
                      <?=gettext("Choose which interface this rule applies to"); ?>.<br />
                      <?=gettext("Hint: in most cases, you'll want to use WAN here"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_proto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol"); ?></td>
                  <td>
                    <div class="input-group">
                      <select id="proto" name="protocol" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
<?php                foreach (explode(" ", "TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP PIM OSPF") as $proto):
?>
              <option value="<?=strtolower($proto);?>" <?= strtolower($proto) == $pconfig['protocol'] ? "selected=\"selected\"" : ""; ?>>
                          <?=$proto;?>
                        </option>
<?php                endforeach; ?>
              </select>
                    </div>
                    <div class="hidden" for="help_for_proto">
                      <?=gettext("Choose which IP protocol " ."this rule should match."); ?><br/>
                      <?=gettext("Hint: in most cases, you should specify"); ?> <em><?=gettext("TCP"); ?></em> &nbsp;<?=gettext("here."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                    <td><a id="help_for_source" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="source" id="source" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['source'];?>" <?=!is_alias($pconfig['source']) && !in_array($pconfig['source'],array('(self)','any'))  ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <option value="any" <?=$pconfig['source'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any");?></option>
                              <option value="(self)" <?=$pconfig['source'] == "(self)" ? "selected=\"selected\"" : ""; ?>><?=gettext("This Firewall (self)");?></option>
                              <optgroup label="<?=gettext("aliasses");?>">
<?php                            foreach (legacy_list_aliasses("network") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['source'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php                            endforeach; ?>
                              </optgroup>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="input-group">
                          <!-- updates to "other" option in  source -->
                          <input type="text" for="source" value="<?=$pconfig['source'];?>" aria-label="<?=gettext("Source address");?>"/>
                          <select name="source_subnet" class="selectpicker" data-size="5" id="srcmask"  data-width="auto" for="source" >
                          <?php for ($i = 32; $i > 0; $i--): ?>
                            <option value="<?=$i;?>" <?= $i == $pconfig['source_subnet'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                          <?php endfor; ?>
                          </select>
                        </div>
                        </td>
                      </tr>
                    </table>
                    <div class="hidden" for="help_for_source">
                      <?=gettext("Enter the source network for the outbound NAT mapping.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_src_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source port:");?></td>
                  <td>
                    <input name="sourceport" type="text" value="<?=$pconfig['sourceport'];?>" />
                    <div class="hidden" for="help_for_src_port">
                      <?=gettext("(leave blank for any)");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td> <a id="help_for_dst_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ".gettext("Invert");?> </td>
                  <td>
                    <input name="destination_not" type="checkbox" value="yes" <?= !empty($pconfig['destination_not']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" for="help_for_dst_invert">
                      <?=gettext("Use this option to invert the sense of the match."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                    <td><a id="help_for_destination" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="destination" id="destination" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['destination'];?>" <?=!is_alias($pconfig['destination']) && $pconfig['destination'] != 'any' ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <option value="any" <?=$pconfig['destination'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any");?></option>
                              <optgroup label="<?=gettext("aliasses");?>">
<?php                        foreach (legacy_list_aliasses("network") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['destination'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php                          endforeach; ?>
                              </optgroup>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="input-group">
                          <!-- updates to "other" option in  source -->
                          <input type="text" for="destination" value="<?=$pconfig['destination'];?>" aria-label="<?=gettext("Destination address");?>"/>
                          <select name="destination_subnet" class="selectpicker" data-size="5" data-width="auto" for="destination" >
                          <?php for ($i = 32; $i > 0; $i--): ?>
                            <option value="<?=$i;?>" <?= $i == $pconfig['destination_subnet'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                          <?php endfor; ?>
                          </select>
                        </div>
                        </td>
                      </tr>
                    </table>
                    <div class="hidden" for="help_for_destination">
                      <?=gettext("Enter the source network for the outbound NAT mapping.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_dstport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination port:");?></td>
                  <td>
                    <input name="dstport" type="text" value="<?=$pconfig['dstport'];?>" />
                    <div class="hidden" for="help_for_dstport">
                      <?=gettext("(leave blank for any)");?>
                    </div>
                  </td>
                </tr>
                <tr>
                    <td><a id="help_for_target" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Translation"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="targetip" id="targetip" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['targetip'];?>" <?= !empty($pconfig['target']) && !array_key_exists($pconfig['targetip'], formTranslateAddresses() ) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
<?                              foreach (formTranslateAddresses() as $optKey => $optValue):
?>
                                    <option value="<?=$optKey;?>" <?= $pconfig['target'] == $optKey ? "selected=\"selected\"" : ""; ?>>
                                      <?=$optValue;?>
                                    </option>
<?                              endforeach;
?>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="input-group">
                              <!-- updates to "other" option in  source -->
                              <input type="text" for="targetip" value="<?=$pconfig['targetip'];?>" aria-label="<?=gettext("Translation address");?>"/>
                              <select name="targetip_subnet" class="selectpicker" data-size="5" data-width="auto" for="destination" >
                              <?php for ($i = 32; $i > 0; $i--): ?>
                                <option value="<?=$i;?>" <?= $i == $pconfig['targetip_subnet'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                              <?php endfor; ?>
                              </select>
                            </div>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" for="help_for_target">
                        <?=gettext("Packets matching this rule will be mapped to the IP address given here.");?><br />
                        <?=gettext("If you want this rule to apply to another IP address rather than the IP address of the interface chosen above, ".
                                "select it here (you will need to define ");?>
                                <a href="firewall_virtual_ip.php"><?=gettext("Virtual IP");?></a>
                                <?=gettext("addresses on the interface first).");?>
                      </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_poolopts" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Pool Options:");?></td>
                  <td>
                    <select name="poolopts" class="selectpicker">
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
                      <option value="source-hash" <?=$pconfig['poolopts'] == "source-hash" ? "selected=\"selected\"" : ""; ?>>
                        <?=gettext("Source Hash");?>
                      </option>
                      <option value="bitmask" <?=$pconfig['poolopts'] == "bitmask" ? "selected=\"selected\"" : ""; ?>>
                        <?=gettext("Bitmask");?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_poolopts">
                      <?=gettext("Only Round Robin types work with Host Aliases. Any type can be used with a Subnet.");?><br />
                      * <?=gettext("Round Robin: Loops through the translation addresses.");?><br />
                      * <?=gettext("Random: Selects an address from the translation address pool at random.");?><br />
                      * <?=gettext("Source Hash: Uses a hash of the source address to determine the translation address, ensuring that the redirection address is always the same for a given source.");?><br />
                      * <?=gettext("Bitmask: Applies the subnet mask and keeps the last portion identical; 10.0.1.50 -&gt; x.x.x.50.");?><br />
                      * <?=gettext("Sticky Address: The Sticky Address option can be used with the Random and Round Robin pool types to ensure that a particular source address is always mapped to the same translation address.");?><br />
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_natport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Translation") . " / " .gettext("port:");?></td>
                  <td>
                    <input name="natport" type="text" value="<?=$pconfig['natport'];?>" />
                    <div class="hidden" for="help_for_natport">
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
                  <td><a id="help_for_nosync" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("No XMLRPC Sync"); ?></td>
                  <td>
                    <input type="checkbox" value="yes" name="nosync" <?=!empty($pconfig['nosync']) ? "checked=\"checked\"" :"";?> />
                    <div class="hidden" for="help_for_nosync">
                      <?=gettext("Hint: This prevents the rule on Master from automatically syncing to other CARP members. This does NOT prevent the rule from being overwritten on Slave.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                  <td>
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" for="help_for_descr">
                      <?=gettext("You may enter a description here " ."for your reference (not parsed)."); ?>
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
                    <?= date(gettext("n/j/y H:i:s"), $a_out[$id]['created']['time']) ?> <?= gettext("by") ?> <strong><?= $a_out[$id]['created']['username'] ?></strong>
                  </td>
                </tr>
<?php
                  endif;
                  if ($has_updated_time):
?>
                <tr>
                  <td><?=gettext("Updated");?></td>
                  <td>
                    <?= date(gettext("n/j/y H:i:s"), $a_out[$id]['updated']['time']) ?> <?= gettext("by") ?> <strong><?= $a_out[$id]['updated']['username'] ?></strong>
                  </td>
                </tr>
<?php
                  endif;
                endif;
?>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                    <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat_out.php');?>'" />
<?php
                    if (isset($id) && $a_out[$id]):
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
