<?php

/*
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2009 Janne Enberg <janne.enberg@lietu.net>
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

$a_nat = &config_read_array('nat', 'rule');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // load form data from config
    if (isset($_GET['id']) && is_numericint($_GET['id']) && isset($a_nat[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id; // load form data from id
    } elseif (isset($_GET['dup']) && isset($a_nat[$_GET['dup']])){
        $after = $_GET['dup'];
        $configId = $_GET['dup']; // load form data from id
    }
    if (isset($_GET['after']) && isset($a_nat[$_GET['after']])) {
        $after = $_GET['after'];
    }

    // initialize form and set defaults
    $pconfig = array();
    $pconfig['protocol'] = "tcp";
    $pconfig['srcbeginport'] = "any";
    $pconfig['srcendport'] = "any";
    $pconfig['interface'] = ["wan"];
    $pconfig['dstbeginport'] = 80 ;
    $pconfig['dstendport'] = 80 ;
    $pconfig['local-port'] = 80;
    if (isset($configId)) {
        // copy 1-on-1
        foreach (array('protocol','target','local-port','descr','interface','associated-rule-id','nosync','log',
                      'natreflection','created','updated','ipprotocol','tag','tagged','poolopts', 'category') as $fieldname) {
            if (isset($a_nat[$configId][$fieldname])) {
                $pconfig[$fieldname] = $a_nat[$configId][$fieldname];
            } else {
                $pconfig[$fieldname] = null;
            }
        }
        // fields with some kind of logic.
        $pconfig['disabled'] = isset($a_nat[$configId]['disabled']);
        $pconfig['nordr'] = isset($a_nat[$configId]['nordr']);
        $pconfig['interface'] = explode(",", $pconfig['interface']);
        address_to_pconfig($a_nat[$configId]['source'], $pconfig['src'],
          $pconfig['srcmask'], $pconfig['srcnot'],
          $pconfig['srcbeginport'], $pconfig['srcendport']);

        address_to_pconfig($a_nat[$configId]['destination'], $pconfig['dst'],
          $pconfig['dstmask'], $pconfig['dstnot'],
          $pconfig['dstbeginport'], $pconfig['dstendport']);
          if (empty($pconfig['ipprotocol'])) {
              if (strpos($pconfig['src'].$pconfig['dst'].$pconfig['target'], ":") !== false) {
                  $pconfig['ipprotocol'] = 'inet6';
              } else {
                  $pconfig['ipprotocol'] = 'inet';
              }
          }
    } elseif (isset($_GET['template']) && $_GET['template'] == 'transparent_proxy') {
        // new rule for transparent proxy reflection, to use as sample
        $pconfig['interface'] = ["lan"];
        $pconfig['src'] = "lan";
        $pconfig['dst'] = "any";
        $pconfig['ipprotocol'] = "inet";
        if (isset($_GET['https'])){
            $pconfig['dstbeginport'] = 443;
            $pconfig['dstendport'] = 443;
            if (isset($config['OPNsense']['proxy']['forward']['sslbumpport'])) {
                $pconfig['local-port'] = $config['OPNsense']['proxy']['forward']['sslbumpport'];
            } else {
                $pconfig['local-port'] = 3129;
            }
        } else {
            $pconfig['dstbeginport'] = 80;
            $pconfig['dstendport'] = 80;
            // try to read the proxy configuration to determine the current port
            // this has some disadvantages in case of dependencies, but there isn't
            // a much better solution available at the moment.
            if (isset($config['OPNsense']['proxy']['forward']['port'])) {
                $pconfig['local-port'] = $config['OPNsense']['proxy']['forward']['port'];
            } else {
                $pconfig['local-port'] = 3128;
            }
        }
        $pconfig['target'] = '127.0.0.1';

        $pconfig['natreflection'] = 'enable';
        $pconfig['descr'] = gettext("redirect traffic to proxy");
    } else {
        $pconfig['src'] = "any";
    }
    // init empty fields
    foreach (array('dst','dstmask','srcmask','dstbeginport','dstendport','target',
        'local-port','natreflection','descr','disabled','nosync','ipprotocol',
        'tag','tagged','poolopts') as $fieldname) {
        if (!isset($pconfig[$fieldname])) {
            $pconfig[$fieldname] = null;
        }
    }
    $pconfig['category'] = !empty($pconfig['category']) ? explode(",", $pconfig['category']) : [];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();
    // validate id and store if usable
    if (isset($pconfig['id']) && is_numericint($pconfig['id']) && isset($a_nat[$pconfig['id']])) {
        $id = $_POST['id'];
    }
    if (isset($pconfig['after']) && isset($a_nat[$pconfig['after']])) {
        // place record after provided sequence number
        $after = $pconfig['after'];
    }

    /* Validate input data  */
    if ($pconfig['protocol'] == 'tcp'  || $pconfig['protocol'] == 'udp' || $pconfig['protocol'] == 'tcp/udp') {
        $reqdfields = explode(" ", "interface protocol dstbeginport dstendport");
        $reqdfieldsn = array(gettext("Interface"),gettext("Protocol"),gettext("Destination port from"),gettext("Destination port to"));
    } else {
        $reqdfields = explode(" ", "interface protocol");
        $reqdfieldsn = array(gettext("Interface"),gettext("Protocol"));
    }

    $reqdfields[] = "src";
    $reqdfieldsn[] = gettext("Source address");
    $reqdfields[] = "dst";
    $reqdfieldsn[] = gettext("Destination address");

    if (empty($pconfig['nordr'])) {
        $reqdfields[] = "target";
        $reqdfieldsn[] = gettext("Redirect target IP");
    }

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!isset($pconfig['nordr']) && ($pconfig['target'] && !is_ipaddroralias($pconfig['target']) && !is_subnet($pconfig['target']))) {
        $input_errors[] = sprintf(gettext("\"%s\" is not a valid redirect target IP address, network or host alias."), $pconfig['target']);
    }
    if (!empty($pconfig['srcbeginport']) && $pconfig['srcbeginport'] != 'any' && !is_portoralias($pconfig['srcbeginport']))
        $input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."), $pconfig['srcbeginport']);
    if (!empty($pconfig['srcendport']) && $pconfig['srcendport'] != 'any' && !is_portoralias($pconfig['srcendport']))
        $input_errors[] = sprintf(gettext("%s is not a valid end source port. It must be a port alias or integer between 1 and 65535."), $pconfig['srcendport']);
    if (!empty($pconfig['dstbeginport']) && $pconfig['dstbeginport'] != 'any' && !is_portoralias($pconfig['dstbeginport']))
        $input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."), $pconfig['dstbeginport']);
    if (!empty($pconfig['dstendport']) && $pconfig['dstendport'] != 'any' && !is_portoralias($pconfig['dstendport']))
        $input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."), $pconfig['dstendport']);

    if (($pconfig['protocol'] == "tcp" || $pconfig['protocol'] == "udp" || $_POST['protocol'] == "tcp/udp") && (!isset($pconfig['nordr']) && !is_portoralias($pconfig['local-port']))) {
        $input_errors[] = sprintf(gettext("A valid redirect target port must be specified. It must be a port alias or integer between 1 and 65535."), $pconfig['local-port']);
    }

    if (!is_specialnet($pconfig['src']) && !is_ipaddroralias($pconfig['src'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $pconfig['src']);
    }

    // validate ipv4/v6, addresses should use selected address family
    foreach (array('src', 'dst', 'target') as $fieldname) {
        if (is_ipaddrv6($pconfig[$fieldname]) && $pconfig['ipprotocol'] != 'inet6') {
            $input_errors[] = sprintf(gettext("%s is not a valid IPv4 address."), $pconfig[$fieldname]);
        }
        if (is_ipaddrv4($pconfig[$fieldname]) && $pconfig['ipprotocol'] != 'inet') {
            $input_errors[] = sprintf(gettext("%s is not a valid IPv6 address."), $pconfig[$fieldname]);
        }
    }

    if (!empty($pconfig['srcmask']) && !is_numericint($pconfig['srcmask'])) {
        $input_errors[] = gettext("A valid source bit count must be specified.");
    }

    if (!is_specialnet($pconfig['dst']) && !is_ipaddroralias($pconfig['dst'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $pconfig['dst']);
    }

    if (!empty($pconfig['dstmask']) && !is_numericint($pconfig['dstmask'])) {
      $input_errors[] = gettext("A valid destination bit count must be specified.");
    }
    if (!isset($_POST['nordr'])
      && is_numericint($pconfig['dstbeginport']) && is_numericint($pconfig['dstendport']) && is_numericint($pconfig['local-port'])
      &&
      (max($pconfig['dstendport'],$pconfig['dstbeginport']) - min($pconfig['dstendport'],$pconfig['dstbeginport']) + $pconfig['local-port']) > 65535) {
        $input_errors[] = gettext("The target port range must be an integer between 1 and 65535.");
    }

    if (count($input_errors) == 0) {
        $natent = array();

        if ($pconfig['protocol'] != 'any') {
            $natent['protocol'] = $pconfig['protocol'];
        }
        $natent['interface'] = implode(",", $pconfig['interface']);
        $natent['category'] = implode(",", $pconfig['category']);
        $natent['ipprotocol'] = $pconfig['ipprotocol'];
        $natent['descr'] = $pconfig['descr'];
        $natent['tag'] = $pconfig['tag'];
        $natent['tagged'] = $pconfig['tagged'];
        $natent['poolopts'] = $pconfig['poolopts'];

        if (!empty($natent['nordr'])) {
            $natent['associated-rule-id'] = '';
        } elseif (!empty($pconfig['filter-rule-association']) && $pconfig['filter-rule-association'] == "pass") {
            $natent['associated-rule-id'] = "pass";
        } elseif (!empty($pconfig['associated-rule-id'])) {
            $natent['associated-rule-id'] = $pconfig['associated-rule-id'];
        } else {
            $natent['associated-rule-id'] = null;
        }

        $natent['disabled'] = !empty($pconfig['disabled']);
        $natent['nordr'] = !empty($pconfig['nordr']);
        $natent['nosync'] = !empty($pconfig['nosync']);
        $natent['log'] = !empty($pconfig['log']);

        if (empty($natent['nordr'])) {
            $natent['target'] = $pconfig['target'];
            $natent['local-port'] = $pconfig['local-port'];
        }

        pconfig_to_address($natent['source'], $pconfig['src'],
          $pconfig['srcmask'], !empty($pconfig['srcnot']),
          $pconfig['srcbeginport'], $pconfig['srcendport']);

        pconfig_to_address($natent['destination'], $pconfig['dst'],
          $pconfig['dstmask'], !empty($pconfig['dstnot']),
          $pconfig['dstbeginport'], $pconfig['dstendport']);

        if ($pconfig['natreflection'] == "purenat" || $pconfig['natreflection'] == "disable") {
            $natent['natreflection'] = $pconfig['natreflection'];
        }

        // If we used to have an associated filter rule, but no-longer should have one
        if (isset($id) && !empty($a_nat[$id]['associated-rule-id']) && ( empty($natent['associated-rule-id']) || $natent['associated-rule-id'] != $a_nat[$id]['associated-rule-id'] ) ) {
            // Delete the previous rule
            foreach ($config['filter']['rule'] as $key => $item){
                if (isset($item['associated-rule-id']) && $item['associated-rule-id']==$a_nat[$id]['associated-rule-id'] ){
                    unset($config['filter']['rule'][$key]);
                    break;
                }
            }
            mark_subsystem_dirty('filter');
        }

        // Updating a rule with a filter rule associated
        if (!empty($natent['associated-rule-id']) || !empty($pconfig['filter-rule-association'])) {
            /* auto-generate a matching firewall rule */
            $filterent = array();
            // If a rule already exists, load it
            if (!empty($natent['associated-rule-id'])) {
                // search rule by associated-rule-id
                $filterentid = false;
                foreach ($config['filter']['rule'] as $key => $item){
                    if (isset($item['associated-rule-id']) && $item['associated-rule-id']==$natent['associated-rule-id']) {
                        $filterentid = $key;
                        break;
                    }
                }
                if ($filterentid === false) {
                    $filterent['associated-rule-id'] = $natent['associated-rule-id'];
                } else {
                    $filterent = &config_read_array('filter', 'rule', $filterentid);
                }
            }
            pconfig_to_address($filterent['source'], $pconfig['src'],
              $pconfig['srcmask'], !empty($pconfig['srcnot']),
              $pconfig['srcbeginport'], $pconfig['srcendport']);

            // Update interface, protocol and destination
            $filterent['interface'] = $natent['interface'];
            $filterent['statetype'] = "keep state";
            if (!empty($natent['protocol'])) {
                $filterent['protocol'] = $natent['protocol'];
            } elseif (isset($filterent['protocol'])) {
                unset($filterent['protocol']);
            }
            $filterent['ipprotocol'] = $natent['ipprotocol'];
            if (!isset($filterent['destination'])) {
                $filterent['destination'] = array();
            }
            $filterent['destination']['address'] = $pconfig['target'];
            if (count($pconfig['interface']) > 1) {
                $filterent['floating'] = true;
                $filterent['quick'] = "yes";
            } else {
                unset($filterent['floating']);
                unset($filterent['quick']);
            }

            if (!empty($pconfig['log'])) {
                $filterent['log'] = true;
            } elseif (isset($filterent['log'])) {
                unset($filterent['log']);
            }

            if (is_numericint($pconfig['local-port']) && is_numericint($pconfig['dstendport']) && is_numericint($pconfig['dstbeginport'])) {
                $dstpfrom = $pconfig['local-port'];
                $dstpto = $dstpfrom + max($pconfig['dstendport'], $pconfig['dstbeginport']) - min($pconfig['dstbeginport'],$pconfig['dstendport']) ;
                if ($dstpfrom == $dstpto) {
                    $filterent['destination']['port'] = $dstpfrom;
                } else {
                    $filterent['destination']['port'] = $dstpfrom . "-" . $dstpto;
                }
            } else {
                // if any of the ports is an alias, copy contents of local-port
                $filterent['destination']['port'] = $pconfig['local-port'];
            }

            $filterent['descr'] = $pconfig['descr'];
            $filterent['category'] = $natent['category'];

            // If this is a new rule, create an ID and add the rule
            if (!empty($pconfig['filter-rule-association']) && $pconfig['filter-rule-association'] != 'pass') {
                if ($pconfig['filter-rule-association'] == 'add-associated') {
                    $filterent['associated-rule-id'] = $natent['associated-rule-id'] = uniqid("nat_", true);
                }
                $filterent['created'] = make_config_revision_entry();
                $config['filter']['rule'][] = $filterent;
            }

            mark_subsystem_dirty('filter');
        }

        // Update the NAT entry now
        $natent['updated'] = make_config_revision_entry();
        if (isset($id)) {
            if (isset($a_nat[$id]['created'])) {
                $natent['created'] = $a_nat[$id]['created'];
            }
            $a_nat[$id] = $natent;
        } else {
            $natent['created'] = make_config_revision_entry();
            if (isset($after)) {
                array_splice($a_nat, $after+1, 0, array($natent));
            } else {
                $a_nat[] = $natent;
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

        header(url_safe('Location: /firewall_nat.php'));
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
    // show source fields (advanced)
    $("#showadvancedboxsrc").click(function(){
        $(".advanced_opt_src").toggleClass("hidden visible");
    });

    // on change event protocol change
    $("#proto").change(function(){
        let port_disabled = true;
        if ($("#proto").val() == "tcp" ||  $("#proto").val() == "udp" || $("#proto").val() == "tcp/udp") {
            port_disabled = false;
        } else {
            $("#dstbeginport optgroup:last option:first").prop('selected', true);
            $("#dstendport optgroup:last option:first").prop('selected', true);
            $("#srcbeginport optgroup:last option:first").prop('selected', true);
            $("#srcendport optgroup:last option:first").prop('selected', true);
            port_disabled = true;
        }
        $("#srcbeginport").prop('disabled', port_disabled);
        $("#srcendport").prop('disabled', port_disabled);
        $("#dstbeginport").prop('disabled', port_disabled);
        $("#dstendport").prop('disabled', port_disabled);
        $("#localbeginport").prop('disabled', port_disabled);
        $("input[for='localbeginport']").prop('disabled', port_disabled);
        $("#srcbeginport").selectpicker('refresh');
        $("#srcendport").selectpicker('refresh');
        $("#dstbeginport").selectpicker('refresh');
        $("#dstendport").selectpicker('refresh');
        $("#localbeginport").selectpicker('refresh');
        $("input[for='localbeginport']").prop('disabled', port_disabled);
    });

    // on change event for "No RDR" checkbox
    $("#nordr").change(function(){
        if ($("#nordr").prop('checked')) {
          $(".act_no_rdr").addClass("hidden");
          $(".act_no_rdr :input").prop( "disabled", true );
        } else {
          $(".act_no_rdr").removeClass("hidden");
          $(".act_no_rdr :input").prop( "disabled", false );
        }
        $(".act_no_rdr .selectpicker").selectpicker('refresh');
    });

    // trigger initial form change
    $("#nordr").change(); // no-rdr
    $("#proto").change(); // protocol

    // show source address when selected
    <?php if (!empty($pconfig['srcnot']) || $pconfig['src'] != "any" || $pconfig['srcbeginport'] != "any" || $pconfig['srcendport'] != "any"): ?>
    $(".advanced_opt_src").toggleClass("hidden visible");
    <?php endif; ?>

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

    // align dropdown source from/to port
    $("#srcbeginport").change(function(){
        $('#srcendport').prop('selectedIndex', $("#srcbeginport").prop('selectedIndex') );
        $('#srcendport').selectpicker('refresh');
        $('#srcendport').change();
    });
    // align dropdown destination from/to port
    $("#dstbeginport").change(function(){
        $('#dstendport').prop('selectedIndex', $("#dstbeginport").prop('selectedIndex') );
        $('#dstendport').selectpicker('refresh');
        $('#dstendport').change();
        // on new entry, align redirect target port to dst target
        if ($("#entryid").length == 0) {
            $('#localbeginport').prop('selectedIndex', $("#dstbeginport").prop('selectedIndex') );
            $('#localbeginport').change();
        }
    });

    $("input[for='dstbeginport']").change(function(){
        // on new entry, align redirect target port to dst target
        if ($("#entryid").length == 0) {
            $("input[for='localbeginport']").val($(this).val());
            $("input[for='localbeginport']").change();
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
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><?=gettext("Edit Redirect entry"); ?></td>
                  <td  style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                  <td>
                    <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : ""; ?> />
                    <?=gettext("Disable this rule"); ?>
                    <div class="hidden" data-for="help_for_disabled">
                      <?=gettext("Set this option to disable this rule without removing it from the list."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_nordr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("No RDR (NOT)"); ?></td>
                  <td>
                    <input type="checkbox" name="nordr" id="nordr" <?= !empty($pconfig['nordr']) ? "checked=\"checked\"" : ""; ?> />
                    <div class="hidden" data-for="help_for_nordr">
                      <?=gettext("Enabling this option will disable redirection for traffic matching this rule."); ?>
                      <br /><?=gettext("Hint: this option is rarely needed, don't use this unless you know what you're doing."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
                  <td>
                    <div class="input-group">
                      <select name="interface[]" class="selectpicker" data-width="auto" data-live-search="true" multiple="multiple">
<?php
                        foreach (legacy_config_get_interfaces(array("enable" => true)) as $iface => $ifdetail): ?>
                        <option value="<?=$iface;?>" <?= in_array($iface, $pconfig['interface'] ?? []) ? "selected=\"selected\"" : ""; ?>>
                          <?=htmlspecialchars($ifdetail['descr']);?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="hidden" data-for="help_for_interface">
                      <?=gettext("Choose which interface this rule applies to."); ?><br />
                      <?=gettext("Hint: in most cases, you'll want to use WAN here."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_ipv46" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("TCP/IP Version");?></td>
                  <td>
                    <select name="ipprotocol" class="selectpicker" data-width="auto" data-live-search="true" data-size="5" >
<?php
                    foreach (array('inet' => 'IPv4','inet6' => 'IPv6', 'inet46' => 'IPv4+IPv6') as $proto => $name): ?>
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
<?php foreach (get_protocols() as $proto): ?>
                        <option value="<?=strtolower($proto);?>" <?= strtolower($proto) == $pconfig['protocol'] ? "selected=\"selected\"" : ""; ?>>
                          <?= $proto ?>
                        </option>
<?php endforeach ?>
              </select>
                    </div>
                    <div class="hidden" data-for="help_for_proto">
                      <?=gettext("Choose which IP protocol " ."this rule should match."); ?><br/>
                      <?=gettext("Hint: in most cases, you should specify"); ?> <em><?=gettext("TCP"); ?></em> &nbsp;<?=gettext("here."); ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced_opt_src visible">
                  <td><?=gettext("Source"); ?></td>
                  <td>
                    <input type="button" class="btn btn-default" value="<?= html_safe(gettext('Advanced')) ?>" id="showadvancedboxsrc" />
                    <div class="hidden" data-for="help_for_source">
                      <?=gettext("Show source address and port range"); ?>
                    </div>
                  </td>
                </tr>
                <tr class="advanced_opt_src hidden">
                    <td> <a id="help_for_src_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source") . " / ".gettext("Invert");?> </td>
                    <td>
                      <input name="srcnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['srcnot']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_src_invert">
                        <?=gettext("Use this option to invert the sense of the match."); ?>
                      </div>
                    </td>
                </tr>
                <tr class="advanced_opt_src hidden">
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Source"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="src" id="src" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['src'];?>" <?=!is_specialnet($pconfig['src']) && !is_alias($pconfig['src']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("network") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['src'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Networks");?>">
<?php                          foreach (get_specialnets(true) as $ifent => $ifdesc):
?>
                                <option value="<?=$ifent;?>" <?= $pconfig['src'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
<?php                            endforeach; ?>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="input-group">
                          <!-- updates to "other" option in  src -->
                          <input type="text" id="src_address" for="src" value="<?=$pconfig['src'];?>" aria-label="<?=gettext("Source address");?>"/>
                          <select name="srcmask" data-network-id="src_address" class="selectpicker ipv4v6net input-group-btn" data-size="5" id="srcmask"  data-width="auto" for="src" >
                          <?php for ($i = 128; $i > 0; $i--): ?>
                            <option value="<?=$i;?>" <?= $i == $pconfig['srcmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                          <?php endfor; ?>
                          </select>
                        </div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr class="hidden advanced_opt_src">
                  <td><a id="help_for_srcport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source port range"); ?></td>
                  <td>
                    <table class="table table-condensed">
                      <thead>
                        <tr>
                          <th><?=gettext("from:"); ?></th>
                          <th><?=gettext("to:"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>
                            <select id="srcbeginport" name="srcbeginport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['srcbeginport'];?>">(<?=gettext("other"); ?>)</option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("port") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?= $pconfig['srcbeginport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Well-known ports");?>">
                                <option value="any" <?= $pconfig['srcbeginport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                <option value="<?=$wkport;?>" <?= $wkport == $pconfig['srcbeginport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                          <td>
                            <select id="srcendport" name="srcendport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['srcendport'];?>">(<?=gettext("other"); ?>)</option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("port") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?= $pconfig['srcendport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Well-known ports");?>">
                                <option value="any" <?= $pconfig['srcendport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php                          foreach ($wkports as $wkport => $wkportdesc): ?>
                                <option value="<?=$wkport;?>" <?= $wkport == $pconfig['srcendport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php                          endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <input type="text" value="<?=$pconfig['srcbeginport'];?>" for="srcbeginport"> <!-- updates to "other" option in  srcbeginport -->
                          </td>
                          <td>
                            <input type="text" value="<?=$pconfig['srcendport'];?>" for="srcendport"> <!-- updates to "other" option in  srcendport -->
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="hidden" data-for="help_for_srcport">
                      <?=gettext("When using the TCP or UDP protocols, specify the source port or port range for this rule"); ?>.
                      <b><?=gettext("This is usually"); ?>
                        <em><?=gettext("random"); ?></em>
                         <?=gettext("and almost never equal to the destination port range (and should usually be 'any')"); ?>.
                       </b>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td> <a id="help_for_dst_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ".gettext("Invert");?> </td>
                  <td>
                    <input name="dstnot" type="checkbox" id="dstnot" value="yes" <?= !empty($pconfig['dstnot']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" data-for="help_for_dst_invert">
                      <?=gettext("Use this option to invert the sense of the match."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Destination"); ?></td>
                  <td>
                    <table class="table table-condensed">
                      <tr>
                        <td>
                          <select name="dst" id="dst" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                            <option data-other=true value="<?=$pconfig['dst'];?>" <?=!is_specialnet($pconfig['dst']) && !is_alias($pconfig['dst']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                            <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("network") as $alias):
?>
                              <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['dst'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php                          endforeach; ?>
                            </optgroup>
                            <optgroup label="<?=gettext("Networks");?>">
<?php                         foreach (get_specialnets(true) as $ifent => $ifdesc):
?>
                              <option value="<?=$ifent;?>" <?= $pconfig['dst'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
<?php                         endforeach;
?>
                            </optgroup>
                            <optgroup label="<?=gettext("Virtual IPs");?>">
<?php
                              if (isset($config['virtualip']['vip'])):
                                foreach ($config['virtualip']['vip'] as $sn):
                                  if (isset($sn['noexpand']))
                                    continue;
                                  if (in_array($sn['mode'], array("proxyarp", "other")) && $sn['type'] == "network"):
                                    $start = ip2long32(gen_subnet($sn['subnet'], $sn['subnet_bits']));
                                    $end = ip2long32(gen_subnet_max($sn['subnet'], $sn['subnet_bits']));
                                    $len = $end - $start;
                                    for ($i = 0; $i <= $len; $i++):
                                      $snip = long2ip32($start+$i);
?>
                              <option value="<?=$snip;?>" <?=$snip == $pconfig['dst'] ? "selected=\"selected\"" : "";?>>
                                <?=htmlspecialchars("{$snip} ({$sn['descr']})");?>
                              </option>
<?php
                                    endfor;
                                  else:
?>
                              <option value="<?=$sn['subnet'];?>" <?= $sn['subnet'] == $pconfig['dst'] ? "selected=\"selected\"" : ""; ?>>
                                <?=htmlspecialchars("{$sn['subnet']} ({$sn['descr']})");?>
                              </option>
<?php
                                  endif;
                                endforeach;
                              endif;
?>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="input-group">
                          <!-- updates to "other" option in dst -->
                          <input type="text" id="dst_address" for="dst" value="<?= !is_specialnet($pconfig['dst']) ? $pconfig['dst'] : "";?>" aria-label="<?=gettext("Destination address");?>"/>
                          <select name="dstmask" data-network-id="dst_address" class="selectpicker ipv4v6net input-group-btn" data-size="5" id="dstmask"  data-width="auto" for="dst" >
                          <?php for ($i = 128; $i > 0; $i--): ?>
                            <option value="<?=$i;?>" <?= $i == $pconfig['dstmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                          <?php endfor; ?>
                          </select>
                        </div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_dstport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination port range"); ?></td>
                  <td>
                    <table class="table table-condensed">
                      <thead>
                        <tr>
                          <th><?=gettext("from:"); ?></th>
                          <th><?=gettext("to:"); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>
                            <select id="dstbeginport" name="dstbeginport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['dstbeginport'];?>">(<?=gettext("other"); ?>)</option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("port") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?= $pconfig['dstbeginport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Well-known ports");?>">
                                <option value="any" <?= $pconfig['dstbeginport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                <option value="<?=$wkport;?>" <?= $wkport == $pconfig['dstbeginport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                          <td>
                            <select id="dstendport" name="dstendport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['dstendport'];?>">(<?=gettext("other"); ?>)</option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("port") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?= $pconfig['dstendport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Well-known ports");?>">
                                <option value="any" <?= $pconfig['dstendport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php                          foreach ($wkports as $wkport => $wkportdesc): ?>
                                <option value="<?=$wkport;?>" <?= $wkport == $pconfig['dstendport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php                          endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <input type="text" value="<?=$pconfig['dstbeginport'];?>" for="dstbeginport"> <!-- updates to "other" option in  dstbeginport -->
                          </td>
                          <td>
                            <input type="text" value="<?=$pconfig['dstendport'];?>" for="dstendport"> <!-- updates to "other" option in  dstendport -->
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="hidden" data-for="help_for_dstport">
                      <?=gettext("When using the TCP or UDP protocols, specify the port or port range for the destination of the packet for this mapping."); ?>
                    </div>
                  </td>
                </tr>
                <tr class="act_no_rdr">
                  <td><a id="help_for_localip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Redirect target IP"); ?></td>
                  <td>
                    <table class="table table-condensed">
                      <tr>
                        <td>
                          <select name="target" id="target" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                            <option data-other=true value="<?=$pconfig['target'];?>" <?=!is_alias($pconfig['target']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                            <optgroup label="<?=gettext("Aliases");?>">
<?php
                              foreach (legacy_list_aliases("network") as $alias):?>
                              <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['target'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php
                              endforeach; ?>
                            </optgroup>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <div class="input-group">
                          <!-- updates to "other" option in target -->
                          <input type="text" id="target_address" for="target" value="<?=$pconfig['target'];?>" aria-label="<?=gettext("Redirect target IP");?>"/>
                        </div>
                        </td>
                      </tr>
                    </table>
                    <div class="hidden" data-for="help_for_localip">
                      <?=gettext("Enter the internal IP address of " .
                      "the server on which you want to map the ports."); ?><br/>
                      <?=gettext("e.g."); ?> <em>192.168.1.12</em>
                    </div>
                </tr>
                <tr class="act_no_rdr">
                  <td><a id="help_for_localbeginport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Redirect target port"); ?></td>
                  <td>
                    <table class="table table-condensed">
                      <tbody>
                        <tr>
                          <td>
                            <select id="localbeginport" name="local-port" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['local-port'];?>">(<?=gettext("other"); ?>)</option>
                              <optgroup label="<?=gettext("Aliases");?>">
<?php                        foreach (legacy_list_aliases("port") as $alias):
?>
                                <option value="<?=$alias['name'];?>" <?= $pconfig['local-port'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Well-known ports");?>">
                                <option value="any" <?= $pconfig['local-port'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                <option value="<?=$wkport;?>" <?= $wkport == $pconfig['local-port'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <input type="text" value="<?=$pconfig['local-port'];?>" for="localbeginport"> <!-- updates to "other" option in  localbeginport -->
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="hidden" data-for="help_for_localbeginport">
                      <?=gettext("Specify the port on the machine with the " .
                      "IP address entered above. In case of a port range, specify " .
                      "the beginning port of the range (the end port will be calculated " .
                      "automatically)."); ?><br />
                      <?=gettext("Hint: this is usually identical to the 'from' port above"); ?>
                    </div>
                  </td>
                </tr>
                <tr class="act_no_rdr">
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
                    <div class="hidden" data-for="help_for_poolopts">
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
                  <td><a id="help_for_log" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Log') ?></td>
                  <td>
                    <input name="log" type="checkbox" id="log" value="yes" <?= !empty($pconfig['log']) ? 'checked="checked"' : '' ?>/>
                    <div class="hidden" data-for="help_for_log">
                      <?=gettext("Log packets that are handled by this rule");?><br/>
                      <?=sprintf(gettext("Hint: the firewall has limited local log space. Don't turn on logging for everything. If you want to do a lot of logging, consider using a %sremote syslog server%s."),'<a href="diag_logs_settings.php">','</a>') ?>
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
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" data-for="help_for_descr">
                      <?=gettext("You may enter a description here " ."for your reference (not parsed)."); ?>
                    </div>
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
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("NAT reflection"); ?></td>
                  <td>
                    <select name="natreflection" class="selectpicker">
                    <option value="default" <?=$pconfig['natreflection'] != "enable" && $pconfig['natreflection'] != "purenat" && $pconfig['natreflection'] != "disable" ? "selected=\"selected\"" : ""; ?>><?=gettext("Use system default"); ?></option>
                    <option value="purenat" <?=$pconfig['natreflection'] == "purenat" ? "selected=\"selected\"" : ""; ?>><?=gettext("Enable"); ?></option>
                    <option value="disable" <?=$pconfig['natreflection'] == "disable" ? "selected=\"selected\"" : ""; ?>><?=gettext("Disable"); ?></option>
                    </select>
                  </td>
                </tr>
<?php            if (isset($id) && (!isset($_GET['dup']) || !is_numericint($_GET['dup']))): ?>
                <tr class="act_no_rdr">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Filter rule association"); ?></td>
                  <td>
                    <select name="associated-rule-id" class="selectpicker" >
                      <option value=""><?=gettext("None"); ?></option>
                      <!-- maybe we should remove this in the future, multi purpose id field might not be the best thing in the world -->
                      <option value="pass" <?= $pconfig['associated-rule-id'] == "pass" ? " selected=\"selected\"" : ""; ?>><?=gettext("Pass"); ?></option>
                      <?php
                      $linkedrule = "";
                      if (isset($config['filter']['rule'])):
                        filter_rules_sort();
                        foreach ($config['filter']['rule'] as $filter_id => $filter_rule):
                          if (isset($filter_rule['associated-rule-id'])):
                            $is_selected = $filter_rule['associated-rule-id']==$pconfig['associated-rule-id'];
                            if ($is_selected) $linkedrule = $filter_id;
?>
                            <option value="<?=$filter_rule['associated-rule-id']?>" <?= $is_selected ?  " selected=\"selected\"" : "";?> >
                                <?=htmlspecialchars('Rule ' . $filter_rule['descr']);?>
                            </option>

<?php
                          endif;
                        endforeach;
                      endif;
?>
                    </select>
                  </td>
                </tr>
<?php         elseif (!isset($id) || (isset($_GET['dup']) && is_numericint($_GET['dup']))) :
?>
                <tr class="act_no_rdr">
                  <td><a id="help_for_fra" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Filter rule association"); ?></td>
                  <td>
                    <select name="filter-rule-association">
                      <option value=""><?=gettext("None"); ?></option>
                      <option value="add-associated" selected="selected"><?=gettext("Add associated filter rule"); ?></option>
                      <option value="add-unassociated"><?=gettext("Add unassociated filter rule"); ?></option>
                      <option value="pass"><?=gettext("Pass"); ?></option>
                    </select>
                    <div class="hidden" data-for="help_for_fra">
                      <?=gettext("NOTE: The \"pass\" selection does not work properly with Multi-WAN. It will only work on an interface containing the default gateway.")?>
                    </div>
                  </td>
                </tr>
<?php          endif;

                $has_created_time = (isset($pconfig['created']) && is_array($pconfig['created']));
                $has_updated_time = (isset($pconfig['updated']) && is_array($pconfig['updated']));

                if ($has_created_time || $has_updated_time):
?>
                <tr>
                  <td colspan="2">&nbsp;</td>
                </tr>
                <tr>
                  <td colspan="2"><?=gettext("Rule Information");?></td>
                </tr>
<?php          if ($has_created_time): ?>
                <tr>
                  <td><?=gettext("Created");?></td>
                  <td>
                    <?= date(gettext('n/j/y H:i:s'), $pconfig['created']['time']) ?> (<?= $pconfig['created']['username'] ?>)
                  </td>
                </tr>
<?php          endif;
                if ($has_updated_time):
?>
                <tr>
                  <td><?=gettext("Updated");?></td>
                  <td>
                    <?= date(gettext('n/j/y H:i:s'), $pconfig['updated']['time']) ?> (<?= $pconfig['updated']['username'] ?>)
                  </td>
                </tr>
<?php          endif;
                endif;
?>
                <tr>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                    <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/firewall_nat.php'" />
                    <?php if (isset($id)): ?>
                    <input id="entryid" name="id" type="hidden" value="<?=$id;?>" />
                    <?php endif; ?>
                    <?php if (isset($after)) : ?>
                    <input name="after" type="hidden" value="<?=$after;?>" />
                    <?php endif; ?>
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
