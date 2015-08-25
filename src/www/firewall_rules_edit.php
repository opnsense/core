<?php

/*
  Copyright (C) 2014 Deciso B.V.
  Copyright (C) 2005 Scott Ullrich (sullrich@gmail.com)
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
require_once("interfaces.inc");

/* TCP flags */
$tcpflags = array("syn", "ack", "fin", "rst", "psh", "urg", "ece", "cwr");

/* OS types, request from backend */
$ostypes = json_decode(configd_run('filter list osfp'));

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
 * fetch list of selectable networks to use in form
 */
function formNetworks() {

  $networks = array();
  $networks["any"] = gettext("any");
  $networks["pptp"] = gettext("PPTP clients");
  $networks["pppoe"] = gettext("PPPoE clients");
  $networks["l2tp"] = gettext("L2TP clients");
  foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
      $networks[$ifent] = htmlspecialchars($ifdesc) . " " . gettext("net");
      $networks[$ifent."ip"] = htmlspecialchars($ifdesc). " ". gettext("address");
  }
  return $networks;
}

/**
 * check if advanced options are set on selected element
 */
function FormSetAdvancedOptions(&$item) {
  foreach (array("max", "max-src-nodes", "max-src-conn", "max-src-states","nopfsync", "statetimeout"
                ,"max-src-conn-rate","max-src-conn-rates", "tag", "tagged", "allowopts", "disablereplyto","tcpflags1"
                ,"tcpflags2") as $fieldname) {

      if (!empty($item[$fieldname])) {
          return true;
      }
  }
  if (!empty($item["statetype"]) && $item["statetype"] != 'keep state') {
      return true;
  }
  return false;
}


function is_posnumericint($arg) {
  // Note that to be safe we do not allow any leading zero - "01", "007"
  return (is_numericint($arg) && $arg[0] != '0' && $arg > 0);
}



/**
 * obscured by clouds, is_specialnet uses this.. so let's hide it in here.
 * let's kill this another day.
 */
$specialsrcdst = explode(" ", "any (self) pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
  $specialsrcdst[] = "{$kif}";
  $specialsrcdst[] = "{$kif}ip";
}

if (!isset($config['filter']['rule'])) {
  $config['filter']['rule'] = array();
}
$a_filter = &$config['filter']['rule'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // input record id, if valid
    if (isset($_GET['dup']) && isset($a_filter[$_GET['dup']]))  {
        $configId = $_GET['dup'];
        $after = $configId;
    } elseif (isset($_GET['id']) && isset($a_filter[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    }

    // define form fields
    $config_fields = array('interface','type','direction','ipprotocol','protocol','icmptype','os','dscp','disabled','log'
                          ,'descr','tcpflags_any','tcpflags1','tcpflags2','tag','tagged','quick','allowopts'
                          ,'disablereplyto','max','max-src-nodes','max-src-conn','max-src-states','statetype'
                          ,'statetimeout','nopfsync','nosync','max-src-conn-rate','max-src-conn-rates','gateway','sched'
                          ,'associated-rule-id','floating'
                        );

    $pconfig = array();
    $pconfig['type'] = "pass";
    $pconfig['protocol'] = "any";
    if (isset($configId)) {
        // 1-on-1 copy of config data
        foreach ($config_fields as $fieldname) {
            if (isset($a_filter[$configId][$fieldname])) {
                $pconfig[$fieldname] = $a_filter[$configId][$fieldname];
            }
        }

        // process fields with some kind of logic
        address_to_pconfig($a_filter[$configId]['source'], $pconfig['src'],
          $pconfig['srcmask'], $pconfig['srcnot'],
          $pconfig['srcbeginport'], $pconfig['srcendport']);
        address_to_pconfig($a_filter[$configId]['destination'], $pconfig['dst'],
          $pconfig['dstmask'], $pconfig['dstnot'],
          $pconfig['dstbeginport'], $pconfig['dstendport']);
        if (isset($id) && isset($a_filter[$configId]['associated-rule-id'])) {
            // do not link on rule copy.
            $pconfig['associated-rule-id'] = $a_filter[$configId]['associated-rule-id'];
        }
    } else {
        /* defaults */
        if (isset($_GET['if'])) {
            if ($_GET['if'] == "FloatingRules" ) {
                $pconfig['floating'] = true;
            } else {
                $pconfig['interface'] = $_GET['if'];
            }
        }
        $pconfig['src'] = "any";
        $pconfig['dst'] = "any";
    }

    // initialize empty fields
    foreach ($config_fields as $fieldname) {
        if (!isset($pconfig[$fieldname])) {
            $pconfig[$fieldname] = null;
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    // input record id, if valid
    if (isset($pconfig['id']) && isset($a_filter[$pconfig['id']])) {
        $id = $pconfig['id'];
    }
    if (isset($pconfig['after']) && isset($a_filter[$pconfig['after']])) {
        $after = $pconfig['after'];
    }

    // preprocessing form fields which differ in presentation / actual storage
    if (empty($pconfig['tcpflags_any'])) {
        $settcpflags = array();
        $outoftcpflags = array();
        foreach ($tcpflags as $tcpflag) {
          if (isset($pconfig['tcpflags1_' . $tcpflag]) && $pconfig['tcpflags1_' . $tcpflag] == "on")
            $settcpflags[] = $tcpflag;
          if (isset($pconfig['tcpflags2_' . $tcpflag]) && $pconfig['tcpflags2_' . $tcpflag] == "on")
            $outoftcpflags[] = $tcpflag;
        }
        // flags should be set within
        if (!empty($outoftcpflags)) {
            $pconfig['tcpflags2'] = join(",", $outoftcpflags);
        }
        if (!empty($settcpflags)) {
            $pconfig['tcpflags1'] = join(",", $settcpflags);
        }
    }

    // validate form input
    $reqdfields = array("ipprotocol","type","protocol","src","dst");
    $reqdfieldsn = array(gettext("TCP/IP Version"),gettext("Type")
                        ,gettext("Protocol"),gettext("Source"),gettext("Destination"));
    if (!is_specialnet($pconfig['src'])) {
      $reqdfields[] = "srcmask";
      $reqdfieldsn[] = gettext("Source bit count");
    }
    if (!is_specialnet($pconfig['dst'])) {
      $reqdfields[] = "dstmask";
      $reqdfieldsn[] = gettext("Destination bit count");
    }

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if(($pconfig['ipprotocol'] == "inet46") && !empty($pconfig['gateway'])) {
        $input_errors[] = gettext("You can not assign a gateway to a rule that applies to IPv4 and IPv6");
    }
    if (!empty($pconfig['gateway']) && isset($config['gateways']['gateway_group'])) {
        foreach($config['gateways']['gateway_group'] as $gw_group) {
            if($gw_group['name'] == $pconfig['gateway']) {
                $a_gatewaygroups = return_gateway_groups_array();
                $family = $a_gatewaygroups[$pconfig['gateway']]['ipprotocol'];
                if(($pconfig['ipprotocol'] == "inet6") && ($pconfig['ipprotocol'] != $family)) {
                    $input_errors[] = gettext("You can not assign a IPv4 gateway group on IPv6 Address Family rule");
                }
                if(($pconfig['ipprotocol'] == "inet") && ($pconfig['ipprotocol'] != $family)) {
                    $input_errors[] = gettext("You can not assign a IPv6 gateway group on IPv4 Address Family rule");
                }
            }
        }
    }
    if (!empty($pconfig['gateway']) && is_ipaddr(lookup_gateway_ip_by_name($pconfig['gateway']))) {
        if( $pconfig['ipprotocol'] == "inet6" && !is_ipaddrv6(lookup_gateway_ip_by_name($pconfig['gateway']))) {
            $input_errors[] = gettext("You can not assign the IPv4 Gateway to a IPv6 Filter rule");
        }
        if( $pconfig['ipprotocol'] == "inet" && !is_ipaddrv4(lookup_gateway_ip_by_name($pconfig['gateway']))) {
            $input_errors[] = gettext("You can not assign the IPv6 Gateway to a IPv4 Filter rule");
        }
    }
    if ($pconfig['protocol'] == "icmp" && !empty($pconfig['icmptype']) && $pconfig['ipprotocol'] == "inet46") {
        $input_errors[] =  gettext("You can not assign a ICMP type to a rule that applies to IPv4 and IPv6");
    }
    if($pconfig['statetype'] == "synproxy state" ) {
        if ($pconfig['protocol'] != "tcp") {
            $input_errors[] = sprintf(gettext("%s is only valid with protocol tcp."),$pconfig['statetype']);
        }
        if($pconfig['gateway'] != "") {
            $input_errors[] = sprintf(gettext("%s is only valid if the gateway is set to 'default'."),$pconfig['statetype']);
        }
    }
    if ( !empty($pconfig['srcbeginport']) && !is_portoralias($pconfig['srcbeginport']) && $pconfig['srcbeginport'] != 'any')
        $input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."),$pconfig['srcbeginport']);
    if ( !empty($pconfig['srcendport']) && !is_portoralias($pconfig['srcendport']) && $pconfig['srcendport'] != 'any')
        $input_errors[] = sprintf(gettext("%s is not a valid end source port. It must be a port alias or integer between 1 and 65535."),$pconfig['srcendport']);
    if ( !empty($pconfig['dstbeginport']) && !is_portoralias($pconfig['dstbeginport']) && $pconfig['dstbeginport'] != 'any')
        $input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."),$pconfig['dstbeginport']);
    if ( !empty($pconfig['dstendport']) && !is_portoralias($pconfig['dstendport']) && $pconfig['dstendport'] != 'any')
        $input_errors[] = sprintf(gettext("%s is not a valid end destination port. It must be a port alias or integer between 1 and 65535."),$pconfig['dstendport']);

    if ( (is_alias($pconfig['srcbeginport']) || is_alias($pconfig['srcendport']))  && $pconfig['srcbeginport'] != $pconfig['srcendport']) {
        $input_errors[] = gettext('When selecting aliases for source ports, both from and to fields must be the same');
    }
    if ( (is_alias($pconfig['dstbeginport']) || is_alias($pconfig['dstendport']))  && $pconfig['dstbeginport'] != $pconfig['dstendport']) {
        $input_errors[] = gettext('When selecting aliases for destination ports, both from and to fields must be the same');
    }
    if (!is_specialnet($pconfig['src'])) {
        if (!is_ipaddroralias($pconfig['src'])) {
            $input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."),$pconfig['src']);
        }
        if (!is_numericint($pconfig['srcmask'])) {
            $input_errors[] = gettext("A valid source bit count must be specified.");
        }
    }
    if (!is_specialnet($pconfig['dst'])) {
        if (!is_ipaddroralias($pconfig['dst'])) {
            $input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."),$pconfig['dst']);
        }
        if (!is_numericint($pconfig['dstmask'])) {
            $input_errors[] = gettext("A valid destination bit count must be specified.");
        }
    }
    if((is_ipaddr($pconfig['src']) && is_ipaddr($pconfig['dst']))) {
      if(!validate_address_family($pconfig['src'], $pconfig['dst']))
        $input_errors[] = sprintf(gettext("The Source IP address %s Address Family differs from the destination %s."), $pconfig['src'], $pconfig['dst']);
      if((is_ipaddrv6($pconfig['src']) || is_ipaddrv6($pconfig['dst'])) && ($pconfig['ipprotocol'] == "inet"))
        $input_errors[] = gettext("You can not use IPv6 addresses in IPv4 rules.");
      if((is_ipaddrv4($pconfig['src']) || is_ipaddrv4($pconfig['dst'])) && ($pconfig['ipprotocol'] == "inet6"))
        $input_errors[] = gettext("You can not use IPv4 addresses in IPv6 rules.");
    }

    if (is_ipaddrv4($pconfig['src']) && $pconfig['srcmask'] > 32) {
        $input_errors[] = gettext("Invalid subnet mask on IPv4 source");
    }
    if (is_ipaddrv4($pconfig['dst']) && $pconfig['dstmask'] > 32) {
        $input_errors[] = gettext("Invalid subnet mask on IPv4 destination");
    }

    if((is_ipaddr($pconfig['src']) || is_ipaddr($pconfig['dst'])) && ($pconfig['ipprotocol'] == "inet46")) {
        $input_errors[] = gettext("You can not use a IPv4 or IPv6 address in combined IPv4 + IPv6 rules.");
    }
    if (!empty($pconfig['os'])) {
        if ($pconfig['protocol'] != "tcp") {
            $input_errors[] = gettext("OS detection is only valid with protocol tcp.");
        }
        if (!in_array($pconfig['os'], $ostypes)) {
            $input_errors[] = gettext("Invalid OS detection selection. Please select a valid OS.");
        }
    }

    if (!empty($pconfig['floating']) && !empty($pconfig['gateway']) && (empty($pconfig['direction']) || $pconfig['direction'] == "any")) {
        $input_errors[] = gettext("You can not use gateways in Floating rules without choosing a direction.");
    }

    if (!in_array($pconfig['protocol'], array("tcp","tcp/udp"))) {
      if (!empty($pconfig['max-src-conn']))
        $input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for TCP protocol.");
      if (!empty($pconfig['max-src-conn-rate']) || !empty($pconfig['max-src-conn-rates']))
        $input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for TCP protocol.");
      if (!empty($pconfig['statetimeout']))
        $input_errors[] = gettext("You can only specify the state timeout (advanced option) for TCP protocol.");
    }
    if ($pconfig['type'] <> "pass") {
      if (!empty($pconfig['max']))
        $input_errors[] = gettext("You can only specify the maximum state entries (advanced option) for Pass type rules.");
      if (!empty($pconfig['max-src-nodes']))
        $input_errors[] = gettext("You can only specify the maximum number of unique source hosts (advanced option) for Pass type rules.");
      if (!empty($pconfig['max-src-conn']))
        $input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for Pass type rules.");
      if (!empty($pconfig['max-src-states']))
        $input_errors[] = gettext("You can only specify the maximum state entries per host (advanced option) for Pass type rules.");
      if (!empty($pconfig['max-src-conn-rate']) || !empty($pconfig['max-src-conn-rates']))
        $input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for Pass type rules.");
      if (!empty($pconfig['statetimeout']))
        $input_errors[] = gettext("You can only specify the state timeout (advanced option) for Pass type rules.");
    }
    if ($pconfig['statetype'] == "none") {
      if (!empty($pconfig['max']))
        $input_errors[] = gettext("You cannot specify the maximum state entries (advanced option) if statetype is none.");
      if (!empty($pconfig['max-src-nodes']))
        $input_errors[] = gettext("You cannot specify the maximum number of unique source hosts (advanced option) if statetype is none.");
      if (!empty($pconfig['max-src-conn']))
        $input_errors[] = gettext("You cannot specify the maximum number of established connections per host (advanced option) if statetype is none.");
      if (!empty($pconfig['max-src-states']))
        $input_errors[] = gettext("You cannot specify the maximum state entries per host (advanced option) if statetype is none.");
      if (!empty($pconfig['max-src-conn-rate']) || !empty($pconfig['max-src-conn-rates']))
        $input_errors[] = gettext("You cannot specify the maximum new connections per host / per second(s) (advanced option) if statetype is none.");
      if (!empty($pconfig['statetimeout']))
        $input_errors[] = gettext("You cannot specify the state timeout (advanced option) if statetype is none.");
    }

    if (!empty($pconfig['max']) && !is_posnumericint($pconfig['max']))
      $input_errors[] = gettext("Maximum state entries (advanced option) must be a positive integer");

    if (!empty($pconfig['max-src-nodes']) && !is_posnumericint($pconfig['max-src-nodes']))
      $input_errors[] = gettext("Maximum number of unique source hosts (advanced option) must be a positive integer");

    if (!empty($pconfig['max-src-conn']) && !is_posnumericint($pconfig['max-src-conn']))
      $input_errors[] = gettext("Maximum number of established connections per host (advanced option) must be a positive integer");

    if (!empty($pconfig['max-src-states']) && !is_posnumericint($pconfig['max-src-states']))
      $input_errors[] = gettext("Maximum state entries per host (advanced option) must be a positive integer");

    if (!empty($pconfig['max-src-conn-rate']) && !is_posnumericint($pconfig['max-src-conn-rate']))
      $input_errors[] = gettext("Maximum new connections per host / per second(s) (advanced option) must be a positive integer");

    if (!empty($pconfig['statetimeout']) && !is_posnumericint($pconfig['statetimeout']))
      $input_errors[] = gettext("State timeout (advanced option) must be a positive integer");

    if ( (empty($pconfig['max-src-conn-rate']) && !empty($pconfig['max-src-conn-rates'])) ||
         (!empty($pconfig['max-src-conn-rate']) && empty($pconfig['max-src-conn-rates']))
        ) {
        $input_errors[] = gettext("Both maximum new connections per host and the interval (per second(s)) must be specified");
    }

    if (empty($pconfig['tcpflags2']) && !empty($pconfig['tcpflags1']))
      $input_errors[] = gettext("If you specify TCP flags that should be set you should specify out of which flags as well.");


    if (count($input_errors)  == 0) {
        $filterent = array();
        // 1-on-1 copy of form values
        $copy_fields = array('type', 'interface', 'ipprotocol', 'tag', 'tagged', 'max', 'max-src-nodes'
                            , 'max-src-conn', 'max-src-states', 'statetimeout', 'statetype', 'os', 'dscp', 'descr', 'gateway'
                            , 'sched', 'associated-rule-id', 'direction', 'quick'
                            , 'max-src-conn-rate', 'max-src-conn-rates') ;

        foreach ($copy_fields as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                if (is_array($pconfig[$fieldname])) {
                     $filterent[$fieldname] = implode(",", $pconfig[$fieldname]);
                } else  {
                    $filterent[$fieldname] = trim($pconfig[$fieldname]);
                }
            }
        }

        // attributes with some kind of logic
        if (!empty($pconfig['floating'])) {
            $filterent['floating'] = "yes";
        }

        if (!empty($pconfig['tcpflags_any'])) {
            $filterent['tcpflags_any'] = true;
        } else {
            $settcpflags = array();
            $outoftcpflags = array();
            foreach ($tcpflags as $tcpflag) {
                if (isset($pconfig['tcpflags1_' . $tcpflag]) && $pconfig['tcpflags1_' . $tcpflag] == "on") {
                    $settcpflags[] = $tcpflag;
                }
                if (isset($pconfig['tcpflags2_' . $tcpflag]) && $pconfig['tcpflags2_' . $tcpflag] == "on") {
                    $outoftcpflags[] = $tcpflag;
                }
            }
            if (!empty($outoftcpflags)) {
                $filterent['tcpflags2'] = join(",", $outoftcpflags);
                if (!empty($settcpflags)) {
                    $filterent['tcpflags1'] = join(",", $settcpflags);
                }
            }
        }
        if (!empty($pconfig['allowopts'])) {
            $filterent['allowopts'] = true;
        }
        if (!empty($pconfig['disablereplyto'])) {
            $filterent['disablereplyto'] = true;
        }
        if(!empty($pconfig['nopfsync'])) {
            $filterent['nopfsync'] = true;
        }
        if(!empty($pconfig['nosync'])) {
            $filterent['nosync'] = true;
        }
        if (!empty($pconfig['disabled'])) {
            $filterent['disabled'] = true;
        }


        if ($pconfig['protocol'] != "any") {
            $filterent['protocol'] = $pconfig['protocol'];
        }

        if ($pconfig['protocol'] == "icmp" && !empty($pconfig['icmptype'])) {
            $filterent['icmptype'] = $pconfig['icmptype'];
        }

        // reset port values for non tcp/udp traffic
        if (($pconfig['protocol'] != "tcp") && ($pconfig['protocol'] != "udp") && ($pconfig['protocol'] != "tcp/udp")) {
          $pconfig['srcbeginport'] = 0;
          $pconfig['srcendport'] = 0;
          $pconfig['dstbeginport'] = 0;
          $pconfig['dstendport'] = 0;
        }

        pconfig_to_address($filterent['source'], $pconfig['src'],
          $pconfig['srcmask'], !empty($pconfig['srcnot']),
          $pconfig['srcbeginport'], $pconfig['srcendport']);

        pconfig_to_address($filterent['destination'], $pconfig['dst'],
          $pconfig['dstmask'], !empty($pconfig['dstnot']),
          $pconfig['dstbeginport'], $pconfig['dstendport']);


        $filterent['updated'] = make_config_revision_entry();

        // update or insert item
        if (isset($id)) {
            if ( isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created']) ) {
                $filterent['created'] = $a_filter[$id]['created'];
            }
            $a_filter[$id] = $filterent;
        } else {
            $filterent['created'] = make_config_revision_entry();
            if (isset($after)) {
                array_splice($a_filter, $after+1, 0, array($filterent));
            } else {
                $a_filter[] = $filterent;
            }
        }
        // sort filter items per interface, not really necessary but leaves a bit nicer sorted config.xml behind.
        filter_rules_sort();
        // write to config
        if (write_config()) {
            mark_subsystem_dirty('filter');
        }

        if (!empty($pconfig['floating'])) {
            header("Location: firewall_rules.php?if=FloatingRules");
        } else {
            header("Location: firewall_rules.php?if=" . htmlspecialchars($pconfig['interface']));
        }
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
$pgtitle = array(gettext("Firewall"),gettext("Rules"),gettext("Edit"));
$shortcut_section = "firewall";

$closehead = false;

$page_filename = "firewall_rules_edit.php";
include("head.inc");
?>
</head>

<body>
  <script type="text/javascript">
  $( document ).ready(function() {
      // show source fields (advanced)
      $("#showadvancedboxsrc").click(function(){
          $(".advanced_opt_src").toggleClass("hidden visible");
      });

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

      $("#proto").change(function() {
          if ( $("#proto").val() == 'icmp' ) {
              $("#icmpbox").removeClass("hidden");
          } else {
              $("#icmpbox").addClass("hidden");
          }
      });

      // IPv4 address, fix dstmask
      $("#dst_address").change(function(){
        if ( $(this).val().indexOf('.') > -1 && $("#dstmask").val() > 32) {
            $("#dstmask").val("32");
            $('#dstmask').selectpicker('refresh');
        }
      });

      // IPv4 address, fix srcmask
      $("#src_address").change(function(){
          if ( $(this).val().indexOf('.') > -1 && $("#srcmask").val() > 32) {
              $("#srcmask").val("32");
              $('#srcmask').selectpicker('refresh');
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
      });

      $(".input_tcpflags_any").click(function(){
          $(".input_flags").prop( "checked", false );
      });
      $(".input_flags").click(function(){
          $(".input_tcpflags_any").prop( "checked", false );
      });

      // show / hide advanced Options
      $("#toggleAdvanced").click(function(){
          $(".opt_advanced").toggleClass("visible hidden");
      });

      // init
      $("#proto").change();
      <?php if ( (!empty($pconfig['srcbeginport']) && $pconfig['srcbeginport'] != "any") || (!empty($pconfig['srcendport']) && $pconfig['srcendport'] != "any") ): ?>
        $(".advanced_opt_src").toggleClass("hidden visible");
      <?php endif; ?>

      // toggle advanced
      <?php if (FormSetAdvancedOptions($pconfig)) :?>
      $("#toggleAdvanced").click();
      <?php endif;?>

  });

  </script>
  <?php include("fbegin.inc"); ?>
    <section class="page-content-main">
      <div class="container-fluid">
        <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
          <section class="col-xs-12">
            <div class="content-box">
              <form action="firewall_rules_edit.php" method="post" name="iform" id="iform">
                <input type='hidden' name="id" value="<?=isset($id) ? $id:''?>" />
                <input name="after" type="hidden" value="<?=isset($after) ? $after :'';?>" />
                <input type="hidden" name="floating" value="<?=$pconfig['floating'];?>" />
                <div class="table-responsive">
                  <table class="table table-striped">
                  <tr>
                    <th colspan="2"><?=gettext("Edit Firewall rule");?></th>
                  </tr>
                  <tr>
                    <td width="22%"><a id="help_for_action" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Action");?></td>
                    <td width="78%">
                      <select name="type" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                        $type_options = array('Pass', 'Block', 'Reject');
                        if (!empty($pconfig['floating'])) {
                            $type_options[] = 'Match';
                        }
                        foreach ($type_options as $type): ?>
                        <option value="<?=strtolower($type);?>" <?= strtolower($type) == strtolower($pconfig['type']) ? "selected=\"selected\"" :""; ?>>
                          <?=$type;?>
                        </option>
<?php
                        endforeach; ?>
                      </select>
                      <div class="hidden" for="help_for_action">
                        <?=gettext("Choose what to do with packets that match the criteria specified below.");?> <br />
                        <?=gettext("Hint: the difference between block and reject is that with reject, a packet (TCP RST or ICMP port unreachable for UDP) is returned to the sender, whereas with block the packet is dropped silently. In either case, the original packet is discarded.");?>
                      </div>
                      <br />
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
<?php             if (!empty($pconfig['floating'])):
?>
                  <tr>
                    <td><a id="help_for_quick" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Quick");?>
                    </td>
                    <td>
                      <input name="quick" type="checkbox" id="quick" value="yes" <?php if ($pconfig['quick']) echo "checked=\"checked\""; ?> />
                      <div class="hidden" for="help_for_quick">
                        <strong><?=gettext("Apply the action immediately on match.");?></strong><br />
                        <?=gettext("Set this option if you need to apply this action to traffic that matches this rule immediately.");?>
                      </div>
                    </td>
                  </tr>
<?php
                  endif; ?>
<?php
                  if( !empty($pconfig['associated-rule-id']) ): ?>
                  <tr>
                    <td><?=gettext("Associated filter rule");?></td>
                    <td>
                      <input name='associated-rule-id' id='associated-rule-id' type='hidden' value='<?=$pconfig['associated-rule-id'];?>' />
                      <span class="text-danger"><strong><?=gettext("Note: ");?></strong></span><?=gettext("This is associated to a NAT rule.");?><br />
                      <?=gettext("You cannot edit the interface, protocol, source, or destination of associated filter rules.");?>
                      <br />
<?php
                        if (isset($config['nat']['rule'])):
                          foreach( $config['nat']['rule'] as $index => $nat_rule ):
                            if( isset($nat_rule['associated-rule-id']) && $nat_rule['associated-rule-id']==$pconfig['associated-rule-id'] ) :
?>
                              <a href="firewall_nat_edit.php?id=<?=$index;?>"> <?=gettext("View the NAT rule");?> </a>
<?php
                              break;
                            endif;
                          endforeach;
                        endif;
?>
                    </td>
                  </tr>
<?php
                  endif; ?>
                  <tr>
                    <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface");?></td>
                    <td>
<?php
                    if (!empty($pconfig['floating'])): ?>
                      <select name="interface[]" title="Select interfaces..." multiple="multiple" class="selectpicker" data-live-search="true" data-size="5" tabindex="2" <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>>
<?php
                    else: ?>
                      <select name="interface" class="selectpicker" data-live-search="true" data-size="5" <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>>
<?php
                    endif;
                    foreach (formInterfaces() as $iface => $ifacename): ?>
                        <option value="<?=$iface;?>" <?= !empty($pconfig['interface']) && ($iface == $pconfig['interface'] || in_array($iface, explode(",", $pconfig['interface']))) ? "selected=\"selected\"" : ""; ?>>
                          <?=htmlspecialchars($ifacename);?>
                        </option>
<?php
                    endforeach; ?>
                        </select>
                        <div class="hidden" for="help_for_interface">
                          <?=gettext("Choose on which interface packets must come in to match this rule.");?>
                        </div>
                    </td>
                  </tr>
<?php
                  if (!empty($pconfig['floating'])): ?>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Direction");?></td>
                    <td>
                      <select name="direction" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                      foreach (array('any','in','out') as $direction): ?>
                      <option value="<?=$direction;?>" <?= $direction == $pconfig['direction'] ? "selected=\"selected\"" : "" ?>>
                          <?=$direction;?>
                      </option>
<?php
                      endforeach; ?>
                      </select>
                    </td>
                  <tr>
<?php
                  endif; ?>
                  <tr>
                    <td><a id="help_for_ipv46" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("TCP/IP Version");?></td>
                    <td>
                      <select name="ipprotocol" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                      foreach (array('inet' => 'IPv4','inet6' => 'IPv6', 'inet46' => 'IPv4+IPv6' ) as $proto => $name): ?>
                      <option value="<?=$proto;?>" <?= $proto == $pconfig['ipprotocol'] ? "selected=\"selected\"" : "";?>>
                        <?=$name;?>
                      </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" for="help_for_ipv46">
                        <?=gettext("Select the Internet Protocol version this rule applies to");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_protocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol");?></td>
                    <td>
                      <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="protocol" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                      $protocols = explode(" ", "TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP PIM OSPF any carp pfsync");
                      foreach ($protocols as $proto): ?>
                        <option value="<?=strtolower($proto);?>" <?= strtolower($proto) == $pconfig['protocol'] ? "selected=\"selected\"" :""; ?>>
                          <?=$proto;?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" for="help_for_protocol">
                        <?=gettext("Choose which IP protocol this rule should match.");?> <br />
                        <?=gettext("Hint: in most cases, you should specify ");?><em>TCP</em> &nbsp;<?=gettext("here.");?>
                      </div>
                    </td>
                  </tr>
                  <tr id="icmpbox">
                    <td><a id="help_for_icmptype" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ICMP type");?></td>
                    <td>
                      <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="icmptype" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                      $icmptypes = array(
                      "" => gettext("any"),
                      "echoreq" => gettext("Echo request"),
                      "echorep" => gettext("Echo reply"),
                      "unreach" => gettext("Destination unreachable"),
                      "squench" => gettext("Source quench"),
                      "redir" => gettext("Redirect"),
                      "althost" => gettext("Alternate Host"),
                      "routeradv" => gettext("Router advertisement"),
                      "routersol" => gettext("Router solicitation"),
                      "timex" => gettext("Time exceeded"),
                      "paramprob" => gettext("Invalid IP header"),
                      "timereq" => gettext("Timestamp"),
                      "timerep" => gettext("Timestamp reply"),
                      "inforeq" => gettext("Information request"),
                      "inforep" => gettext("Information reply"),
                      "maskreq" => gettext("Address mask request"),
                      "maskrep" => gettext("Address mask reply")
                      );

                      foreach ($icmptypes as $icmptype => $descr): ?>
                        <option value="<?=$icmptype;?>" <?= $icmptype == $pconfig['icmptype'] ? "selected=\"selected\"" : ""; ?>>
                          <?=$descr;?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <br />
                      <div class="hidden" for="help_for_icmptype">
                        <?=gettext("If you selected ICMP for the protocol above, you may specify an ICMP type here.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td> <a id="help_for_src_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ".gettext("Invert");?> </td>
                    <td>
                      <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  name="srcnot" type="checkbox" value="yes" <?= !empty($pconfig['srcnot']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" for="help_for_src_invert">
                        <?=gettext("Use this option to invert the sense of the match."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Source"); ?></td>
                      <td>
                        <table class="table table-condensed">
                          <tr>
                            <td>
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="src" id="src" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['src'];?>" <?=!is_specialnet($pconfig['src']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                                <optgroup label="<?=gettext("aliasses");?>">
  <?php                        foreach (legacy_list_aliasses("network") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['src'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("net");?>">
  <?php                          foreach (formNetworks() as $ifent => $ifdesc):
  ?>
                                  <option value="<?=$ifent;?>" <?= $pconfig['src'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
  <?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div>
                              <table border="0" cellpadding="0" cellspacing="0">
                                <tbody>
                                  <tr>
                                      <td width="348px">
                                        <!-- updates to "other" option in  src -->
                                        <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  type="text" id="src_address" for="src" value="<?=$pconfig['src'];?>" aria-label="<?=gettext("Source address");?>"/>
                                      </td>
                                      <td>
                                        <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="srcmask" class="selectpicker" data-size="5" id="srcmask"  data-width="auto" for="src" >
                                        <?php for ($i = 128; $i > 0; $i--): ?>
                                          <option value="<?=$i;?>" <?= $i == $pconfig['srcmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                                        <?php endfor; ?>
                                        </select>
                                      </td>
                                  </tr>
                                </tbody>
                              </table>
                          </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr class="advanced_opt_src visible">
                    <td><?=gettext("Source"); ?></td>
                    <td>
                      <input type="button" class="btn btn-default" value="<?=gettext("Advanced"); ?>" id="showadvancedboxsrc" />
                      <div class="hidden" for="help_for_source">
                        <?=gettext("Show source address and port range"); ?>
                      </div>
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
                            <td >
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  id="srcbeginport" name="srcbeginport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['srcbeginport'];?>">(<?=gettext("other"); ?>)</option>
                                <optgroup label="<?=gettext("aliasses");?>">
  <?php                        foreach (legacy_list_aliasses("port") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?= $pconfig['srcbeginport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("well known ports");?>">
                                  <option value="any" <?= $pconfig['srcbeginport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
  <?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                  <option value="<?=$wkport;?>" <?= $wkport == $pconfig['srcbeginport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
  <?php                            endforeach; ?>
                                </optgroup>
                              </select>
                            </td>
                            <td>
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  id="srcendport" name="srcendport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['srcendport'];?>">(<?=gettext("other"); ?>)</option>
                                <optgroup label="<?=gettext("aliasses");?>">
  <?php                        foreach (legacy_list_aliasses("port") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?= $pconfig['srcendport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("well known ports");?>">
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
                              <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  type="text" value="<?=$pconfig['srcbeginport'];?>" for="srcbeginport"> <!-- updates to "other" option in  srcbeginport -->
                            </td>
                            <td>
                              <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  type="text" value="<?=$pconfig['srcendport'];?>" for="srcendport"> <!-- updates to "other" option in  srcendport -->
                            </td>
                          </tr>
                        </tbody>
                      </table>
                      <div class="hidden" for="help_for_srcport">
                        <?=gettext("Specify the source port or port range for this rule"); ?>.
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
                      <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="dstnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['dstnot']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" for="help_for_dst_invert">
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
                            <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="dst" id="dst" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['dst'];?>" <?=!is_specialnet($pconfig['dst']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <optgroup label="<?=gettext("aliasses");?>">
  <?php                        foreach (legacy_list_aliasses("network") as $alias):
  ?>
                                <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['dst'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
  <?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("net");?>">
  <?php                          foreach (formNetworks() as $ifent => $ifdesc):
  ?>
                                <option value="<?=$ifent;?>" <?= $pconfig['dst'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
  <?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table border="0" cellpadding="0" cellspacing="0">
                              <tbody>
                                <tr>
                                    <td width="348px">
                                      <!-- updates to "other" option in  src -->
                                      <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  type="text" id="dst_address" for="dst" value="<?=$pconfig['dst'];?>" aria-label="<?=gettext("Destination address");?>"/>
                                    </td>
                                    <td>
                                      <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="dstmask" class="selectpicker" data-size="5" id="srcmask"  data-width="auto" for="dst" >
                                      <?php for ($i = 128; $i > 0; $i--): ?>
                                        <option value="<?=$i;?>" <?= $i == $pconfig['dstmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                                      <?php endfor; ?>
                                      </select>
                                    </td>
                                </tr>
                              </tbody>
                            </table>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr class="act_port_select">
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
                            <td >
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> id="dstbeginport" name="dstbeginport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['dstbeginport'];?>">(<?=gettext("other"); ?>)</option>
                                <optgroup label="<?=gettext("aliasses");?>">
  <?php                        foreach (legacy_list_aliasses("port") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?= $pconfig['dstbeginport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("well known ports");?>">
                                  <option value="any" <?= $pconfig['dstbeginport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
  <?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                  <option value="<?=$wkport;?>" <?= $wkport == $pconfig['dstbeginport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
  <?php                            endforeach; ?>
                                </optgroup>
                              </select>
                            </td>
                            <td>
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> id="dstendport" name="dstendport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['dstendport'];?>">(<?=gettext("other"); ?>)</option>
                                <optgroup label="<?=gettext("aliasses");?>">
  <?php                        foreach (legacy_list_aliasses("port") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?= $pconfig['dstendport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("well known ports");?>">
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
                              <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  type="text" value="<?=$pconfig['dstbeginport'];?>" for="dstbeginport"> <!-- updates to "other" option in  dstbeginport -->
                            </td>
                            <td>
                              <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  type="text" value="<?=$pconfig['dstendport'];?>" for="dstendport"> <!-- updates to "other" option in  dstendport -->
                            </td>
                          </tr>
                        </tbody>
                      </table>
                      <div class="hidden" for="help_for_dstport">
                        <?=gettext("Specify the port or port range for the destination of the packet for this mapping."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_log" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Log");?></td>
                    <td>
                      <input name="log" type="checkbox" id="log" value="yes" <?= !empty($pconfig['log']) ? "checked=\"checked\"" : ""; ?> />
                      <div class="hidden" for="help_for_log">
                        <strong><?=gettext("Log packets that are handled by this rule");?></strong>
                        <br />
                        <?=gettext("Hint: the firewall has limited local log space. Don't turn on logging for everything. If you want to do a lot of logging, consider using a remote syslog server"); ?> (<?=gettext("see the"); ?> <a href="diag_logs_settings.php"><?=gettext("Diagnostics: System logs: Settings"); ?></a> <?=gettext("page"); ?>).
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
                  <tr>
                    <th colspan="2"><?=gettext("Advanced features");?></th>
                  </tr>
                  <tr>
                    <td><a id="help_for_sourceos" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source OS");?></td>
                    <td>
                        <select name="os" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                          <option value="" <?= empty($pconfig['os']) ? "selected=\"selected\"" : ""; ?>>Any</option>
<?php
                          foreach ($ostypes as $ostype): ?>
                            <option value="<?=$ostype;?>" <?= $ostype == $pconfig['os'] ? "selected=\"selected\"" : ""; ?>>
                              <?=htmlspecialchars($ostype);?>
                            </option>
<?php
                        endforeach;?>
                        </select>
                        <div class="hidden" for="help_for_sourceos">
                          <strong><?=gettext("OS Type:");?></strong><br/>
                          <?=gettext("Note: this only works for TCP rules. General OS choice matches all subtypes.");?>
                        </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i>  <?=gettext("Diffserv Code Point");?></td>
                    <td>
                        <select name="dscp" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                          <option value=""><?=gettext("none");?></option>
<?php
                          $firewall_rules_dscp_types = array("af11","af12","af13","af21","af22","af23","af31","af32","af33","af41"
                                  ,"af42","af43","VA","EF","cs1","cs2","cs3","cs4","cs5","cs6","cs7","0x01","0x02","0x04");
                          foreach($firewall_rules_dscp_types as $frdt):?>
                            <option value="<?=$frdt?>"<?= $pconfig['dscp'] == $frdt ? " selected=\"selected\"" :""; ?>>
                              <?=$frdt?>
                            </option>
<?php
                        endforeach; ?>
                        </select>
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
                    <td><a id="help_for_schedule" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Schedule");?></td>
                    <td>
                        <select name='sched' class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                          <option value="" <?= empty($pconfig['sched']) ? " selected=\"selected\"" : "";?> >
                            <?=gettext("none");?>
                          </option>
<?php
                        if (isset($config['schedules']['schedule']) && count($config['schedules']['schedule']) > 0) :
                        foreach($config['schedules']['schedule'] as $schedule):
?>
                          <option value="<?=htmlspecialchars($schedule['name']);?>" <?= $pconfig['sched'] == $schedule['name'] ? " selected=\"selected\"" : "";?> >
                            <?=htmlspecialchars($schedule['name']);?>
                          </option>
<?php
                        endforeach;
                        endif;?>
                        </select>
                        <div class="hidden" for="help_for_schedule">
                            <p><?=gettext("Leave as 'none' to leave the rule enabled all the time.");?></p>
                        </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_gateway" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Gateway");?></td>
                    <td>
                        <select name='gateway' class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                        <option value="" ><?=gettext("default");?></option>
<?php
                        foreach(return_gateways_array() as $gwname => $gw):
?>
                          <option value="<?=$gwname;?>" <?=$gwname == $pconfig['gateway'] ? " selected=\"selected\"" : "";?>>
                            <?=$gw['name'];?>
                            <?=empty($gw['gateway']) ? "" : " - " . $gw['gateway'];?>
                          </option>
<?php
                        endforeach;
                        $a_gatewaygroups = return_gateway_groups_array();
                        foreach($a_gatewaygroups as $gwg_name => $gwg_data):?>
                          <option value="<?=$gwg_name;?>" <?=$gwg_name == $pconfig['gateway'] ? " selected=\"selected\"" : "";?>>
                            <?=$gwg_name;?>
                          </option>

<?php
                        endforeach;?>
                        </select>
                        <div class="hidden" for="help_for_gateway">
                          <?=gettext("Leave as 'default' to use the system routing table.  Or choose a gateway to utilize policy based routing.");?>
                        </div>
                    </td>
                  </tr>
                  <tr>
                    <td><?=gettext("Advanced Options");?></td>
                    <td>
                      <input id="toggleAdvanced" type="button" class="btn btn-default" value="<?=gettext("Show/Hide"); ?>" />
                    </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                    <td></td>
                    <td><strong><?=gettext("Note: Leave fields blank to disable the feature.");?></strong></td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_allowopts" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("allow options");?> </td>
                      <td>
                        <input type="checkbox" value="yes" name="allowopts"<?= !empty($pconfig['allowopts']) ? " checked=\"checked\"" : ""; ?> />
                        <div class="hidden" for="help_for_allowopts">
                          <?=gettext("This allows packets with IP options to pass. Otherwise they are blocked by default. This is usually only seen with multicast traffic.");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_disable_replyto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("disable reply-to");?> </td>
                      <td>
                        <input type="checkbox" value="yes" name="disablereplyto"<?= !empty($pconfig['disablereplyto']) ? " checked=\"checked\"" :""; ?> />
                        <div class="hidden" for="help_for_disable_replyto">
                          <?=gettext("This will disable auto generated reply-to for this rule.");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_tag" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Set local tag"); ?></td>
                      <td>
                        <input name="tag" type="text" value="<?=$pconfig['tag'];?>" />
                        <div class="hidden" for="help_for_tag">
                          <?=gettext("You can mark a packet matching this rule and use this mark to match on other NAT/filter rules. It is called"); ?> <b><?=gettext("Policy filtering"); ?></b>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_tagged" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Match local tag"); ?>   </td>
                      <td>
                        <input name="tagged" type="text" value="<?=$pconfig['tagged'];?>" />
                        <div class="hidden" for="help_for_tagged">
                          <?=gettext("You can match packet on a mark placed before on another rule.")?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max states");?> </td>
                      <td>
                        <input name="max" type="text" value="<?=$pconfig['max'];?>" />
                        <div class="hidden" for="help_for_max">
                          <?=gettext(" Maximum state entries this rule can create");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max-src-nodes" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max source nodes");?> </td>
                      <td>
                        <input name="max-src-nodes" type="text" value="<?=$pconfig['max-src-nodes'];?>"/>
                        <div class="hidden" for="help_for_max-src-nodes">
                          <?=gettext(" Maximum number of unique source hosts");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max-src-conn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max established");?> </td>
                      <td>
                        <input name="max-src-conn" type="text" value="<?= $pconfig['max-src-conn'];?>" />
                        <div class="hidden" for="help_for_max-src-conn">
                            <?=gettext(" Maximum number of established connections per host (TCP only)");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max-src-states" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max source states");?> </td>
                      <td>
                        <input name="max-src-states" type="text" value="<?=$pconfig['max-src-states'];?>" />
                        <div class="hidden" for="help_for_max-src-states">
                            <?=gettext(" Maximum state entries per host");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max-src-conn-rate" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max new connections");?> </td>
                      <td>
                        <table border="0" cellspacing="0" cellpadding="0">
                          <tbody>
                            <tr>
                              <td>
                                <input name="max-src-conn-rate" type="text" value="<?=$pconfig['max-src-conn-rate'];?>" />
                              </td>
                              <td> / </td>
                              <td>
                                <select name="max-src-conn-rates" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                  <option value="" <?=intval($pconfig['max-src-conn-rates']) < 1 ? "selected=\"selected\"" : "";?>><?=gettext("none");?></option>
<?php
                                  for($x=1; $x<255; $x++):?>
                                  <option value="<?=$x;?>" <?=$pconfig['max-src-conn-rates'] == $x ? "selected=\"selected\"" :"";?> >
                                    <?=$x;?>
                                  </option>
<?php
                                 endfor;?>
                                </select>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                        <div class="hidden" for="help_for_max-src-conn-rate">
                            <?=gettext("Maximum new connections per host / per second(s) (TCP only)");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_statetimeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("State timeout");?></td>
                      <td>
                        <input name="statetimeout" type="text" value="<?=$pconfig['statetimeout'];?>" /><br />
                        <div class="hidden" for="help_for_statetimeout">
                          <?=gettext("State Timeout in seconds (TCP only)");?><br/>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_tcpflags" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("TCP flags");?></td>
                      <td>
                        <table class="table table-condensed">
<?php
                          $setflags = explode(",", $pconfig['tcpflags1']);
                          $outofflags = explode(",", $pconfig['tcpflags2']);
                          $header = "<td></td>";
                          $tcpflags1 = "<td>set</td>";
                          $tcpflags2 = "<td>out of</td>";
                          foreach ($tcpflags as $tcpflag) {
                            $header .= "<td><strong>" . strtoupper($tcpflag) . "</strong></td>\n";
                            $tcpflags1 .= "<td> <input class='input_flags' type='checkbox' name='tcpflags1_{$tcpflag}' value='on' ";
                            if (array_search($tcpflag, $setflags) !== false)
                              $tcpflags1 .= "checked=\"checked\"";
                            $tcpflags1 .= " /></td>\n";
                            $tcpflags2 .= "<td> <input class='input_flags' type='checkbox' name='tcpflags2_{$tcpflag}' value='on' ";
                            if (array_search($tcpflag, $outofflags) !== false)
                              $tcpflags2 .= "checked=\"checked\"";
                            $tcpflags2 .= " /></td>\n";
                          }
                          echo "<tr>{$header}</tr>\n";
                          echo "<tr>{$tcpflags1}</tr>\n";
                          echo "<tr>{$tcpflags2}</tr>\n";
?>
                        <tr>
                          <td></td>
                          <td colspan="10">
                            <input type='checkbox' class="input_tcpflags_any" name='tcpflags_any' value='on' <?= !empty($pconfig['tcpflags_any']) ? "checked=\"checked\"" :""; ?> />
                            <strong><?=gettext("Any flags.");?></strong>
                          </td>
                        <tr>
                        </table>
                        <div class="hidden" for="help_for_tcpflags">
                            <?=gettext("Use this to choose TCP flags that must "."be set or cleared for this rule to match.");?>
                        </div>
                      </td>
                  </tr>
                    <tr class="opt_advanced hidden">
                        <td><a id="help_for_nopfsync" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("State Type");?> / <?=gettext("NO pfsync");?> </td>
                        <td>
                          <input name="nopfsync" type="checkbox" value="yes" <?= !empty($pconfig['nopfsync']) ? "checked=\"checked\"" : "";?> />
                          <div class="hidden" for="help_for_nopfsync">
                            <?=gettext("Hint: This prevents states created by this rule to be sync'ed over pfsync.");?><br />
                          </div>
                        </td>
                    </tr>
                    <tr class="opt_advanced hidden">
                        <td><a id="help_for_statetype" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("State Type");?></td>
                        <td>
                          <select name="statetype">
                            <option value="keep state" <?= empty($pconfig['statetype']) || $pconfig['statetype'] == "keep state" ? "selected=\"selected\"" : ""; ?>>
                              <?=gettext("keep state");?>
                            </option>
                            <option value="sloppy state" <?=$pconfig['statetype'] == "sloppy state" ? "selected=\"selected\"" :""; ?>>
                              <?=gettext("sloppy state");?>
                            </option>
                            <option value="synproxy state"<?=$pconfig['statetype'] == "synproxy state" ?  "selected=\"selected\"" :""; ?>>
                              <?=gettext("synproxy state");?>
                            </option>
                            <option value="none"<?=$pconfig['statetype'] == "none" ? "selected=\"selected\"" :""; ?>>
                              <?=gettext("none");?>
                            </option>
                          </select>
                          <div class="hidden" for="help_for_statetype">
                            <span>
                              <?=gettext("Hint: Select which type of state tracking mechanism you would like to use.  If in doubt, use keep state.");?>
                            </span>
                              <ul>
                                <li><strong><?=gettext("keep state");?> </strong><?=gettext("Works with all IP protocols.");?></li>
                                <li><strong><?=gettext("sloppy state");?> </strong><?=gettext("Works with all IP protocols.");?></li>
                                <li><strong><?=gettext("synproxy state");?> </strong><?=gettext("Proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods. This option includes the functionality of keep state and modulate state combined.");?></li>
                                <li><strong><?=gettext("none");?> </strong><?=gettext("Do not use state mechanisms to keep track.  This is only useful if you're doing advanced queueing in certain situations.  Please check the documentation.");?> </li>
                              </ul>
                          </div>
                        </td>
                    </tr>
<?php
                    $has_created_time = (isset($a_filter[$id]['created']) && is_array($a_filter[$id]['created']));
                    $has_updated_time = (isset($a_filter[$id]['updated']) && is_array($a_filter[$id]['updated']));
                    if ($has_created_time || $has_updated_time):
?>
                    <tr>
                      <td colspan="2"><?=gettext("Rule Information");?></td>
                    </tr>
<?php
                    if ($has_created_time): ?>
                    <tr>
                      <td><?=gettext("Created");?></td>
                      <td>
                        <?= date(gettext("n/j/y H:i:s"), $a_filter[$id]['created']['time']) ?> <?= gettext("by") ?> <strong><?= $a_filter[$id]['created']['username'] ?></strong>
                      </td>
                    </tr>
<?php
                    endif;
                    if ($has_updated_time):?>
                    <tr>
                      <td><?=gettext("Updated");?></td>
                      <td>
                        <?= date(gettext("n/j/y H:i:s"), $a_filter[$id]['updated']['time']) ?> <?= gettext("by") ?> <strong><?= $a_filter[$id]['updated']['username'] ?></strong>
                      </td>
                    </tr>
<?php
                    endif;
                    endif; ?>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                        &nbsp;<br />&nbsp;
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                        <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_rules.php');?>'" />
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
