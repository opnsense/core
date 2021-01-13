<?php

/*
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2005 Scott Ullrich <sullrich@gmail.com>
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
require_once("interfaces.inc");
require_once("filter.inc");

/* TCP flags */
$tcpflags = array("syn", "ack", "fin", "rst", "psh", "urg", "ece", "cwr");

/* OS types, request from backend */
$ostypes = json_decode(configd_run('filter list osfp json'));
if ($ostypes == null) {
    $ostypes = array();
}
$gateways = new \OPNsense\Routing\Gateways(legacy_interfaces_details());


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
    // check these fields for anything being set except a blank string
    foreach (array('set-prio', 'set-prio-low', 'prio') as $fieldname) {
        if (isset($item[$fieldname]) && $item[$fieldname] !== '') {
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


$a_filter = &config_read_array('filter', 'rule');


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
    $config_fields = array(
        'allowopts',
        'associated-rule-id',
        'category',
        'descr',
        'direction',
        'disabled',
        'disablereplyto',
        'floating',
        'gateway',
        'icmptype',
        'icmp6-type',
        'interface',
        'ipprotocol',
        'log',
        'max',
        'max-src-conn',
        'max-src-conn-rate',
        'max-src-conn-rates',
        'max-src-nodes',
        'max-src-states',
        'nopfsync',
        'nosync',
        'os',
        'prio',
        'protocol',
        'quick',
        'sched',
        'set-prio',
        'set-prio-low',
        'statetimeout',
        'statetype',
        'tag',
        'tagged',
        'tcpflags1',
        'tcpflags2',
        'tcpflags_any',
        'type',
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
        $pconfig['category'] = !empty($pconfig['category']) ? explode(",", $pconfig['category']) : [];

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
                $pconfig['quick'] = true;
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

    if ($pconfig['ipprotocol'] == "inet46" && !empty($pconfig['gateway'])) {
        $input_errors[] = gettext("You can not assign a gateway to a rule that applies to IPv4 and IPv6");
    }
    if (!empty($pconfig['gateway']) && isset($config['gateways']['gateway_group'])) {
        $family = $gateways->getGroupIPProto($pconfig['gateway']);
        if ($family !== null && $pconfig['ipprotocol'] == "inet6" && $pconfig['ipprotocol'] != $family) {
            $input_errors[] = gettext('You can not assign an IPv4 gateway group on an IPv6 rule.');
        }
        if ($family !== null && $pconfig['ipprotocol'] == "inet" && $pconfig['ipprotocol'] != $family) {
            $input_errors[] = gettext('You can not assign an IPv6 gateway group on an IPv4 rule.');
        }
    }
    if (!empty($pconfig['gateway']) && is_ipaddr($gateways->getAddress($pconfig['gateway']))) {
        if ($pconfig['ipprotocol'] == "inet6" && !is_ipaddrv6($gateways->getAddress($pconfig['gateway']))) {
            $input_errors[] = gettext('You can not assign the IPv4 Gateway to an IPv6 filter rule.');
        }
        if ($pconfig['ipprotocol'] == "inet" && !is_ipaddrv4($gateways->getAddress($pconfig['gateway']))) {
            $input_errors[] = gettext('You can not assign the IPv6 Gateway to an IPv4 filter rule.');
        }
    }
    if ($pconfig['protocol'] == "icmp" && !empty($pconfig['icmptype']) && $pconfig['ipprotocol'] == "inet46") {
        $input_errors[] =  gettext('You can not assign an ICMP type to a rule that applies to IPv4 and IPv6.');
    } elseif ($pconfig['protocol'] == "ipv6-icmp" && !empty($pconfig['icmp6-type']) && $pconfig['ipprotocol'] == "inet46") {
        $input_errors[] =  gettext('You can not assign an ICMP type to a rule that applies to IPv4 and IPv6.');
    } elseif ($pconfig['protocol'] == "ipv6-icmp" && $pconfig['ipprotocol'] != "inet6") {
        $input_errors[] =  gettext('You can not assign an ICMP type to a rule that applies to IPv4 and IPv6.');
    }
    if ($pconfig['statetype'] == "synproxy state" || $pconfig['statetype'] == "modulate state") {
        if ($pconfig['protocol'] != "tcp") {
            $input_errors[] = sprintf(gettext("%s is only valid with protocol tcp."),$pconfig['statetype']);
        }
        if($pconfig['gateway'] != "") {
            $input_errors[] = sprintf(gettext("%s is only valid if the gateway is set to 'default'."),$pconfig['statetype']);
        }
    }
    if (!empty($pconfig['srcbeginport']) && !is_portoralias($pconfig['srcbeginport']) && $pconfig['srcbeginport'] != 'any')
        $input_errors[] = sprintf(gettext("%s is not a valid start source port. It must be a port alias or integer between 1 and 65535."),$pconfig['srcbeginport']);
    if (!empty($pconfig['srcendport']) && !is_portoralias($pconfig['srcendport']) && $pconfig['srcendport'] != 'any')
        $input_errors[] = sprintf(gettext("%s is not a valid end source port. It must be a port alias or integer between 1 and 65535."),$pconfig['srcendport']);
    if (!empty($pconfig['dstbeginport']) && !is_portoralias($pconfig['dstbeginport']) && $pconfig['dstbeginport'] != 'any')
        $input_errors[] = sprintf(gettext("%s is not a valid start destination port. It must be a port alias or integer between 1 and 65535."),$pconfig['dstbeginport']);
    if (!empty($pconfig['dstendport']) && !is_portoralias($pconfig['dstendport']) && $pconfig['dstendport'] != 'any')
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

    if (is_ipaddr($pconfig['src']) && is_ipaddr($pconfig['dst'])) {
        if ((is_ipaddrv4($pconfig['src']) && is_ipaddrv6($pconfig['dst'])) || (is_ipaddrv6($pconfig['src']) && is_ipaddrv4($pconfig['dst']))) {
            $input_errors[] = sprintf(gettext("The Source IP address %s Address Family differs from the destination %s."), $pconfig['src'], $pconfig['dst']);
        }
    }
    foreach (array('src', 'dst') as $fam) {
        if (is_ipaddr($pconfig[$fam])) {
            if (is_ipaddrv6($pconfig[$fam]) && $pconfig['ipprotocol'] == "inet") {
                $input_errors[] = gettext("You can not use IPv6 addresses in IPv4 rules.");
            } elseif (is_ipaddrv4($pconfig[$fam]) && $pconfig['ipprotocol'] == "inet6") {
                $input_errors[] = gettext("You can not use IPv4 addresses in IPv6 rules.");
            }
        }
    }

    if (is_ipaddrv4($pconfig['src']) && $pconfig['srcmask'] > 32) {
        $input_errors[] = gettext("Invalid subnet mask on IPv4 source");
    }
    if (is_ipaddrv4($pconfig['dst']) && $pconfig['dstmask'] > 32) {
        $input_errors[] = gettext("Invalid subnet mask on IPv4 destination");
    }

    if ((is_ipaddr($pconfig['src']) || is_ipaddr($pconfig['dst'])) && ($pconfig['ipprotocol'] == "inet46")) {
        $input_errors[] = gettext('You can not use an IPv4 or IPv6 address in combined IPv4 + IPv6 rules.');
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
    } elseif (empty($pconfig['floating']) && $pconfig['direction'] != "in" && !empty($pconfig['gateway'])) {
        // XXX: Technically this is not completely true, but since you can only send to other destinations reachable
        //      from the selected interface in this case, it will likely be confusing for our users.
        //      Policy based routing rules on inbound traffic can use the correct outbound interface, which is the
        //      scenario that is most commonly used .
        //      For compatibilty reasons, we only apply this on non-floating rules.
        $input_errors[] = gettext("Policy based routing (gateway setting) is only supported on inbound rules.");
    }

    if (!in_array($pconfig['protocol'], array("tcp","tcp/udp"))) {
      if (!empty($pconfig['max-src-conn']))
          $input_errors[] = gettext("You can only specify the maximum number of established connections per host (advanced option) for TCP protocol.");
      if (!empty($pconfig['max-src-conn-rate']) || !empty($pconfig['max-src-conn-rates']))
          $input_errors[] = gettext("You can only specify the maximum new connections per host / per second(s) (advanced option) for TCP protocol.");
      if (!empty($pconfig['statetimeout']))
          $input_errors[] = gettext("You can only specify the state timeout (advanced option) for TCP protocol.");
    }
    if ($pconfig['type'] != 'pass') {
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


    if (isset($pconfig['set-prio']) && $pconfig['set-prio'] !== '' && (!is_numericint($pconfig['set-prio']) || $pconfig['set-prio'] < 0 || $pconfig['set-prio'] > 7)) {
        $input_errors[] = gettext('Set priority must be an integer between 0 and 7.');
    }

    if (isset($pconfig['set-prio-low']) && $pconfig['set-prio-low'] !== '' && (!is_numericint($pconfig['set-prio-low']) || $pconfig['set-prio-low'] < 0 || $pconfig['set-prio-low'] > 7)) {
        $input_errors[] = gettext('Set priority for low latency and acknowledgements must be an integer between 0 and 7.');
    }

    if (isset($pconfig['set-prio-low']) && $pconfig['set-prio-low'] !== '' && (!isset($pconfig['set-prio']) || $pconfig['set-prio'] === '')) {
        $input_errors[] = gettext('Set priority for low latency and acknowledgements requires a set priority for normal packets.');
    }

    if (!empty($pconfig['prio']) && (!is_numericint($pconfig['prio']) || $pconfig['prio'] < 0 || $pconfig['prio'] > 7)) {
        $input_errors[] = gettext('Priority match must be an integer between 0 and 7.');
    }

    if (count($input_errors) == 0) {
        $filterent = array();
        // 1-on-1 copy of form values
        $copy_fields = array('type', 'interface', 'ipprotocol', 'tag', 'tagged', 'max', 'max-src-nodes'
                            , 'max-src-conn', 'max-src-states', 'statetimeout', 'statetype', 'os', 'descr', 'gateway'
                            , 'sched', 'associated-rule-id', 'direction'
                            , 'max-src-conn-rate', 'max-src-conn-rates', 'category') ;

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

        if (!empty($pconfig['log'])) {
            $filterent['log'] = true;
        }

        if (isset($pconfig['set-prio']) && $pconfig['set-prio'] !== '') {
            $filterent['set-prio'] = $pconfig['set-prio'];
        }

        if (isset($pconfig['set-prio-low']) && $pconfig['set-prio-low'] !== '') {
            $filterent['set-prio-low'] = $pconfig['set-prio-low'];
        }

        if (isset($pconfig['prio']) && $pconfig['prio'] !== '') {
            $filterent['prio'] = $pconfig['prio'];
        }
        // XXX: Always store quick, so none existent can have a different functional meaning than an empty value.
        //      Not existent means previous defaults (empty + floating --> non quick, empty + non floating --> quick)
        $filterent['quick'] = !empty($pconfig['quick']) ? 1 : 0;

        if ($pconfig['protocol'] != "any") {
            $filterent['protocol'] = $pconfig['protocol'];
        }

        if ($pconfig['protocol'] == "icmp" && !empty($pconfig['icmptype'])) {
            $filterent['icmptype'] = $pconfig['icmptype'];
        } elseif ($pconfig['protocol'] == 'ipv6-icmp' && !empty($pconfig['icmp6-type'])) {
            $filterent['icmp6-type'] = $pconfig['icmp6-type'];
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
        OPNsense\Core\Config::getInstance()->fromArray($config);
        $catmdl = new OPNsense\Firewall\Category();
        if ($catmdl->sync()) {
            $catmdl->serializeToConfig();
            $config = OPNsense\Core\Config::getInstance()->toArray(listtags());
        }
        write_config();
        mark_subsystem_dirty('filter');

        header(url_safe('Location: /firewall_rules.php?if=%s', array(
            !empty($pconfig['floating']) ? 'FloatingRules' : $pconfig['interface']
        )));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($a_filter);

$priorities = interfaces_vlan_priorities();

include("head.inc");

?>
<script src="<?= cache_safe('/ui/js/tokenize2.js') ?>"></script>
<link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename('/css/tokenize2.css')) ?>">
<script src="<?= cache_safe('/ui/js/opnsense_ui.js') ?>"></script>
<body>
  <script>
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
          $("#icmpbox").addClass("hidden");
          $("#icmp6box").addClass("hidden");
          if ( $("#proto").val() == 'icmp' ) {
              $("#icmpbox").removeClass("hidden");
          } else if ( $("#proto").val() == 'ipv6-icmp' ) {
              $("#icmp6box").removeClass("hidden");
          }
          let port_disabled = true;
          // lock src/dst ports on other then tcp/udp
          if ($("#proto").val() == 'tcp' || $("#proto").val() == 'udp' || $("#proto").val() == 'tcp/udp') {
              port_disabled = false;
          } else {
              port_disabled = true;
          }
          var port_fields = ['srcbeginport', 'srcendport', 'dstbeginport', 'dstendport'];
          port_fields.forEach(function(field){
            if (port_disabled) {
                $("#"+field+" optgroup:last option:first").prop('selected', true);
            }
            $("#"+field).prop('disabled', port_disabled);
            $("#"+field).selectpicker('refresh');
            $("#"+field).change();
          });
          if ($("#proto").val() == 'tcp') {
              $(".input_tcpflags_any,.input_flags").prop('disabled', false);
          } else {
              $(".input_tcpflags_any,.input_flags").prop('disabled', true);
          }

      });

      // IPv4/IPv6 select
      hook_ipv4v6('ipv4v6net', 'network-id');

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
                <input type='hidden' name="id" value="<?=isset($id) ? $id:''?>" />
                <input name="after" type="hidden" value="<?=isset($after) ? $after :'';?>" />
                <input type="hidden" name="floating" value="<?=$pconfig['floating'];?>" />
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%"><strong><?=gettext("Edit Firewall rule");?></strong></td>
                    <td style="width:78%;text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td style="width:22%"><a id="help_for_action" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Action");?></td>
                    <td style="width:78%">
                      <select name="type" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                        $type_options = array('Pass' => gettext('Pass'), 'Block' => gettext('Block'), 'Reject' => gettext('Reject'));
                        foreach ($type_options as $type => $type_translated): ?>
                        <option value="<?=strtolower($type);?>" <?= strtolower($type) == strtolower($pconfig['type']) ? "selected=\"selected\"" :""; ?>>
                          <?=$type_translated;?>
                        </option>
<?php
                        endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_action">
                        <?=gettext("Choose what to do with packets that match the criteria specified below.");?> <br />
                        <?=gettext("Hint: the difference between block and reject is that with reject, a packet (TCP RST or ICMP port unreachable for UDP) is returned to the sender, whereas with block the packet is dropped silently. In either case, the original packet is discarded.");?>
                      </div>
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
<?php
                  // XXX: either use quick setting from the config, or fallback to the defaults
                  //      floating and not set --> deselected, interface rule and not set --> selected
                  if (empty($pconfig['floating']) && $pconfig['quick'] == null){
                      $is_quick = true;
                  } elseif (!empty($pconfig['floating']) && $pconfig['quick'] == null) {
                      $is_quick = false;
                  } else {
                      $is_quick = $pconfig['quick'];
                  }
?>
                  <tr>
                    <td><a id="help_for_quick" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Quick");?>
                    </td>
                    <td>
                      <input name="quick" type="checkbox" id="quick" value="yes" <?= !empty($is_quick) ? "checked=\"checked\"" : "";?> />
                      <strong><?=gettext("Apply the action immediately on match.");?></strong>
                      <div class="hidden" data-for="help_for_quick">
                        <?=gettext("If a packet matches a rule specifying quick, ".
                                   "then that rule is considered the last matching rule and the specified action is taken. ".
                                   "When a rule does not have quick enabled, the last matching rule wins.");?>
                      </div>
                    </td>
                  </tr>
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

                    foreach (legacy_config_get_interfaces(array("enable" => true)) as $iface => $ifdetail): ?>
                        <option value="<?=$iface;?>"
                            <?= !empty($pconfig['interface']) && (
                                  $iface == $pconfig['interface'] ||
                                  // match floating / multiple interfaces
                                  (!is_array($pconfig['interface']) && in_array($iface, explode(',', $pconfig['interface']))) ||
                                  (is_array($pconfig['interface']) && in_array($iface, $pconfig['interface']))
                                ) ? 'selected="selected"' : ''; ?>>
                          <?= htmlspecialchars($ifdetail['descr']) ?>
                        </option>
<?php
                    endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_interface">
                          <?=gettext("Choose on which interface packets must come in to match this rule.");?>
                        </div>
                    </td>
                  </tr>
<?php
                  // XXX: for legacy compatibility we keep supporting "any" on floating rules, regular rules should choose
                  $direction_options = !empty($pconfig['floating']) ? array('in','out', 'any') : array('in','out');?>
                  <tr>
                    <td><a id="help_for_direction" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Direction");?></td>
                    <td>
                      <select name="direction" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                      foreach ($direction_options as $direction): ?>
                      <option value="<?=$direction;?>" <?= $direction == $pconfig['direction'] ? "selected=\"selected\"" : "" ?>>
                          <?=$direction;?>
                      </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_direction">
                        <?=gettext("Direction of the traffic. The default policy is to filter inbound traffic, ".
                                   "which sets the policy to the interface originally receiving the traffic.") ?>
                      </div>
                    </td>
                  <tr>
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
                      <div class="hidden" data-for="help_for_ipv46">
                        <?=gettext("Select the Internet Protocol version this rule applies to");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_protocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol");?></td>
                    <td>
                      <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="protocol" id="proto" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                      foreach (get_protocols() as $proto): ?>
                        <option value="<?=strtolower($proto);?>" <?= strtolower($proto) == $pconfig['protocol'] ? "selected=\"selected\"" :""; ?>>
                          <?=$proto;?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_protocol">
                        <?=gettext("Choose which IP protocol this rule should match.");?> <br />
                        <?= gettext("Hint: in most cases, you should specify TCP here.") ?>
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
                      "echoreq" => gettext("Echo Request"),
                      "echorep" => gettext("Echo Reply"),
                      "unreach" => gettext("Destination Unreachable"),
                      "squench" => gettext("Source Quench (Deprecated)"),
                      "redir" => gettext("Redirect"),
                      "althost" => gettext("Alternate Host Address (Deprecated)"),
                      "routeradv" => gettext("Router Advertisement"),
                      "routersol" => gettext("Router Solicitation"),
                      "timex" => gettext("Time Exceeded"),
                      "paramprob" => gettext("Parameter Problem"),
                      "timereq" => gettext("Timestamp"),
                      "timerep" => gettext("Timestamp Reply"),
                      "inforeq" => gettext("Information Request (Deprecated)"),
                      "inforep" => gettext("Information Reply (Deprecated)"),
                      "maskreq" => gettext("Address Mask Request (Deprecated)"),
                      "maskrep" => gettext("Address Mask Reply (Deprecated)")
                      );

                      foreach ($icmptypes as $icmptype => $descr): ?>
                        <option value="<?=$icmptype;?>" <?= $icmptype == $pconfig['icmptype'] ? "selected=\"selected\"" : ""; ?>>
                          <?=$descr;?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_icmptype">
                        <?=gettext("If you selected ICMP for the protocol above, you may specify an ICMP type here.");?>
                      </div>
                    </td>
                  </tr>
                  <tr id="icmp6box">
                    <td><a id="help_for_icmp6-type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ICMP6 type");?></td>
                    <td>
                      <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="icmp6-type" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                      $icmp6types = array(
                          "" => gettext("any"),
                          "unreach" => gettext("Destination unreachable"),
                          "toobig" => gettext("Packet too big"),
                          "timex" => gettext("Time exceeded"),
                          "paramprob" => gettext("Invalid IPv6 header"),
                          "echoreq" => gettext("Echo service request"),
                          "echorep" => gettext("Echo service reply"),
                          "groupqry" => gettext("Group membership query"),
                          "listqry" => gettext("Multicast listener query"),
                          "grouprep" => gettext("Group membership report"),
                          "listenrep" => gettext("Multicast listener report"),
                          "groupterm" => gettext("Group membership termination"),
                          "listendone" => gettext("Multicast listener done"),
                          "routersol" => gettext("Router solicitation"),
                          "routeradv" => gettext("Router advertisement"),
                          "neighbrsol" => gettext("Neighbor solicitation"),
                          "neighbradv" => gettext("Neighbor advertisement"),
                          "redir" => gettext("Shorter route exists"),
                          "routrrenum" => gettext("Route renumbering"),
                          "fqdnreq" => gettext("FQDN query"),
                          "niqry" => gettext("Node information query"),
                          "wrureq" => gettext("Who-are-you request"),
                          "fqdnrep" => gettext("FQDN reply"),
                          "nirep" => gettext("Node information reply"),
                          "wrurep" => gettext("Who-are-you reply"),
                          "mtraceresp" => gettext("mtrace response"),
                          "mtrace" => gettext("mtrace messages")
                      );

                      foreach ($icmp6types as $icmp6type => $descr): ?>
                        <option value="<?=$icmp6type;?>" <?= $icmp6type == $pconfig['icmp6-type'] ? "selected=\"selected\"" : ""; ?>>
                          <?=$descr;?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_icmp6-type">
                        <?=gettext("If you selected ICMP6 for the protocol above, you may specify an ICMP6 type here.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td> <a id="help_for_src_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source") . " / ".gettext("Invert");?> </td>
                    <td>
                      <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  name="srcnot" type="checkbox" value="yes" <?= !empty($pconfig['srcnot']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_src_invert">
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
                            <div>
                              <table style="border:0;">
                                <tbody>
                                  <tr>
                                      <td style="width:348px">
                                        <!-- updates to "other" option in  src -->
                                        <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  type="text" id="src_address" for="src" value="<?=$pconfig['src'];?>" aria-label="<?=gettext("Source address");?>"/>
                                      </td>
                                      <td>
                                        <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="srcmask" data-network-id="src_address" class="selectpicker ipv4v6net" data-size="5" id="srcmask"  data-width="auto" for="src" >
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
                      <input type="button" class="btn btn-default" value="<?= html_safe(gettext('Advanced')) ?>" id="showadvancedboxsrc" />
                      <div class="hidden" data-for="help_for_source">
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
                            <td>
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  id="srcbeginport" name="srcbeginport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
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
                                  <option value="<?=$wkport;?>" <?= $wkport == $pconfig['srcbeginport'] && $wkport == $pconfig['srcendport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
  <?php                            endforeach; ?>
                                </optgroup>
                              </select>
                            </td>
                            <td>
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  id="srcendport" name="srcendport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
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
                                  <option value="<?=$wkport;?>" <?= $wkport == $pconfig['srcbeginport'] && $wkport == $pconfig['srcendport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
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
                      <div class="hidden" data-for="help_for_srcport">
                        <?=gettext("Specify the source port or port range for this rule."); ?>
                        <b><?= gettext("This is usually random and almost never equal to the destination port range (and should usually be 'any').") ?></b>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td> <a id="help_for_dst_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ".gettext("Invert");?> </td>
                    <td>
                      <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="dstnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['dstnot']) ? "checked=\"checked\"" : "";?> />
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
                            <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="dst" id="dst" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['dst'];?>" <?=!is_specialnet($pconfig['dst']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <optgroup label="<?=gettext("Aliases");?>">
  <?php                        foreach (legacy_list_aliases("network") as $alias):
  ?>
                                <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['dst'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
  <?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Networks");?>">
  <?php                          foreach (get_specialnets(true) as $ifent => $ifdesc):
  ?>
                                <option value="<?=$ifent;?>" <?= $pconfig['dst'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
  <?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table style="border:0;">
                              <tbody>
                                <tr>
                                    <td style="width:348px">
                                      <!-- updates to "other" option in  src -->
                                      <input <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>  type="text" id="dst_address" for="dst" value="<?=$pconfig['dst'];?>" aria-label="<?=gettext("Destination address");?>"/>
                                    </td>
                                    <td>
                                      <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> name="dstmask" class="selectpicker ipv4v6net" data-network-id="dst_address" data-size="5" id="dstmask"  data-width="auto" for="dst" >
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
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> id="dstbeginport" name="dstbeginport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
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
                                  <option value="<?=$wkport;?>" <?= $wkport == $pconfig['dstbeginport'] && $wkport == $pconfig['dstendport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
  <?php                            endforeach; ?>
                                </optgroup>
                              </select>
                            </td>
                            <td>
                              <select <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?> id="dstendport" name="dstendport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
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
                                  <option value="<?=$wkport;?>" <?= $wkport == $pconfig['dstbeginport'] && $wkport == $pconfig['dstendport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
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
                      <div class="hidden" data-for="help_for_dstport">
                        <?=gettext("Specify the port or port range for the destination of the packet for this mapping."); ?>
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
                    <td><a id="help_for_category" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Category"); ?></td>
                    <td>
                      <select name="category[]" id="category" multiple="multiple" class="tokenize" data-allownew="true" data-sortable="false" data-width="334px" data-live-search="true">
<?php
                      foreach ((new OPNsense\Firewall\Category())->iterateCategories() as $category):?>
                        <option value="<?=$category['name'];?>"  <?=in_array($category['name'], $pconfig['category']) ? 'selected="selected"' : '';?> ><?=$category['name'];?></option>
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
                        <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                      </div>
                  </tr>
                  <tr>
                    <th colspan="2"><?=gettext("Advanced features");?></th>
                  </tr>
                  <tr>
                    <td><a id="help_for_sourceos" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source OS");?></td>
                    <td>
                        <select name="os" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                        <option value="" <?= empty($pconfig['os']) ? "selected=\"selected\"" : ""; ?>><?= gettext('Any') ?></option>
<?php
                          foreach ($ostypes as $ostype): ?>
                            <option value="<?=$ostype;?>" <?= $ostype == $pconfig['os'] ? "selected=\"selected\"" : ""; ?>>
                              <?=htmlspecialchars($ostype);?>
                            </option>
<?php
                        endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_sourceos">
                          <strong><?=gettext("OS Type:");?></strong><br/>
                          <?=gettext("Note: this only works for TCP rules. General OS choice matches all subtypes.");?>
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
                        <div class="hidden" data-for="help_for_schedule">
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
                        foreach($gateways->gatewaysIndexedByName(true, true, true) as $gwname => $gw):
?>
                          <option value="<?=$gwname;?>" <?=$gwname == $pconfig['gateway'] ? " selected=\"selected\"" : "";?>>
                            <?=$gw['name'];?>
                            <?=empty($gw['gateway']) ? "" : " - " . $gw['gateway'];?>
                          </option>
<?php
                        endforeach;
                        foreach ($gateways->getGroupNames() as $gwg_name):?>
                          <option value="<?=$gwg_name;?>" <?=$gwg_name == $pconfig['gateway'] ? " selected=\"selected\"" : "";?>>
                            <?=$gwg_name;?>
                          </option>
<?php
                        endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_gateway">
                          <?=gettext("Leave as 'default' to use the system routing table. Or choose a gateway to utilize policy based routing.");?>
                        </div>
                    </td>
                  </tr>
                  <tr>
                    <td><?=gettext("Advanced Options");?></td>
                    <td>
                      <input id="toggleAdvanced" type="button" class="btn btn-default" value="<?= html_safe(gettext('Show/Hide')) ?>" />
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
                        <div class="hidden" data-for="help_for_allowopts">
                          <?=gettext("This allows packets with IP options to pass. Otherwise they are blocked by default. This is usually only seen with multicast traffic.");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_disable_replyto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("disable reply-to");?> </td>
                      <td>
                        <input type="checkbox" value="yes" name="disablereplyto"<?= !empty($pconfig['disablereplyto']) ? " checked=\"checked\"" :""; ?> />
                        <div class="hidden" data-for="help_for_disable_replyto">
                          <?=gettext("This will disable auto generated reply-to for this rule.");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_set-prio" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Set priority') ?></td>
                      <td>
                          <table class="table table-condensed">
                              <tr>
                                  <th><?= gettext('All packets') ?></th>
                                  <th><?= gettext('Low Delay/TCP ACK') ?></th>
                              </tr>
                              <tr>
                                  <td>
                                    <select name="set-prio">
                                        <option value=""<?=(!isset($pconfig['set-prio']) || $pconfig['set-prio'] === '' ? ' selected="selected"' : '');?>><?=htmlspecialchars(gettext('Keep current priority'));?></option>
<?php foreach ($priorities as $prio => $priority): ?>
                                        <option value="<?=$prio;?>"<?=(isset($pconfig['set-prio']) && $pconfig['set-prio'] !== '' && $pconfig['set-prio'] == $prio ? ' selected="selected"' : '');?>><?=htmlspecialchars($priority);?></option>
<?php endforeach ?>
                                    </select>
                                  </td>
                                  <td>
                                    <select name="set-prio-low">
                                        <option value=""<?=(!isset($pconfig['set-prio-low']) || $pconfig['set-prio-low'] === '' ? ' selected="selected"' : '');?>><?=htmlspecialchars(gettext('Use main priority'));?></option>
<?php foreach ($priorities as $prio => $priority): ?>
                                        <option value="<?=$prio;?>"<?=(isset($pconfig['set-prio-low']) && $pconfig['set-prio-low'] !== '' && $pconfig['set-prio-low'] == $prio ? ' selected="selected"' : '');?>><?=htmlspecialchars($priority);?></option>
<?php endforeach ?>
                                    </select>
                                  </td>
                              </tr>
                          </table>
                          <div class="hidden" data-for="help_for_set-prio">
                              <?= gettext('Set the priority of packets matching this rule. If both priorities are set here, packets with a TOS of "lowdelay" or TCP ACKs with no data payload will be assigned the latter. If the packets are transmitted on a VLAN interface, the queueing priority will be written as the priority code point in the 802.1Q VLAN header.') ?>
                          </div>
                    </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_prio" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext('Match priority'); ?></td>
                      <td>
                        <select name="prio">
                            <option value=""<?=(!isset($pconfig['prio']) || $pconfig['prio'] === '' ? ' selected="selected"' : '');?>><?=htmlspecialchars(gettext('Any priority'));?></option>
<?php foreach ($priorities as $prio => $priority): ?>
                            <option value="<?=$prio;?>"<?=(isset($pconfig['prio']) && $pconfig['prio'] !== '' && $pconfig['prio'] == $prio ? ' selected="selected"' : '');?>><?=htmlspecialchars($priority);?></option>
<?php endforeach ?>
                        </select>
                        <div class="hidden" data-for="help_for_prio">
                          <?=gettext('Match on the priority of packets.');?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_tag" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Set local tag"); ?></td>
                      <td>
                        <input name="tag" type="text" value="<?=$pconfig['tag'];?>" />
                        <div class="hidden" data-for="help_for_tag">
                          <?= gettext("You can mark a packet matching this rule and use this mark to match on other NAT/filter rules.") ?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_tagged" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Match local tag"); ?>   </td>
                      <td>
                        <input name="tagged" type="text" value="<?=$pconfig['tagged'];?>" />
                        <div class="hidden" data-for="help_for_tagged">
                          <?=gettext("You can match packet on a mark placed before on another rule.")?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max states");?> </td>
                      <td>
                        <input name="max" type="text" value="<?=$pconfig['max'];?>" />
                        <div class="hidden" data-for="help_for_max">
                          <?=gettext("Maximum state entries this rule can create");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max-src-nodes" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max source nodes");?> </td>
                      <td>
                        <input name="max-src-nodes" type="text" value="<?=$pconfig['max-src-nodes'];?>"/>
                        <div class="hidden" data-for="help_for_max-src-nodes">
                          <?=gettext("Maximum number of unique source hosts");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max-src-conn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max established");?> </td>
                      <td>
                        <input name="max-src-conn" type="text" value="<?= $pconfig['max-src-conn'];?>" />
                        <div class="hidden" data-for="help_for_max-src-conn">
                            <?=gettext("Maximum number of established connections per host (TCP only)");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max-src-states" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max source states");?> </td>
                      <td>
                        <input name="max-src-states" type="text" value="<?=$pconfig['max-src-states'];?>" />
                        <div class="hidden" data-for="help_for_max-src-states">
                            <?=gettext("Maximum state entries per host");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_max-src-conn-rate" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max new connections");?> </td>
                      <td>
                        <table style="border:0;">
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
                        <div class="hidden" data-for="help_for_max-src-conn-rate">
                            <?=gettext("Maximum new connections per host / per second(s) (TCP only)");?>
                        </div>
                      </td>
                  </tr>
                  <tr class="opt_advanced hidden">
                      <td><a id="help_for_statetimeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("State timeout");?></td>
                      <td>
                        <input name="statetimeout" type="text" value="<?=$pconfig['statetimeout'];?>" />
                        <div class="hidden" data-for="help_for_statetimeout">
                          <?=gettext("State Timeout in seconds (TCP only)");?>
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
                          $tcpflags1 = "<td>" . gettext('set') . "</td>";
                          $tcpflags2 = "<td>" . gettext('out of') . "</td>";
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
                        <div class="hidden" data-for="help_for_tcpflags">
                            <?=gettext("Use this to choose TCP flags that must be set or cleared for this rule to match.");?>
                        </div>
                      </td>
                  </tr>
                    <tr class="opt_advanced hidden">
                        <td><a id="help_for_nopfsync" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("State Type");?> / <?=gettext("NO pfsync");?> </td>
                        <td>
                          <input name="nopfsync" type="checkbox" value="yes" <?= !empty($pconfig['nopfsync']) ? "checked=\"checked\"" : "";?> />
                          <div class="hidden" data-for="help_for_nopfsync">
                            <?=gettext("Hint: This prevents states created by this rule to be sync'ed over pfsync.");?>
                          </div>
                        </td>
                    </tr>
                    <tr class="opt_advanced hidden">
                        <td><a id="help_for_statetype" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("State Type");?></td>
                        <td>
                          <select name="statetype" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                            <option value="keep state" <?= empty($pconfig['statetype']) || $pconfig['statetype'] == "keep state" ? "selected=\"selected\"" : ""; ?>>
                              <?=gettext("keep state");?>
                            </option>
                            <option value="sloppy state" <?=$pconfig['statetype'] == "sloppy state" ? "selected=\"selected\"" :""; ?>>
                              <?=gettext("sloppy state");?>
                            </option>
                            <option value="modulate state"<?=$pconfig['statetype'] == "modulate state" ?  "selected=\"selected\"" :""; ?>>
                              <?=gettext("modulate state");?>
                            </option>
                            <option value="synproxy state"<?=$pconfig['statetype'] == "synproxy state" ?  "selected=\"selected\"" :""; ?>>
                              <?=gettext("synproxy state");?>
                            </option>
                            <option value="none"<?=$pconfig['statetype'] == "none" ? "selected=\"selected\"" :""; ?>>
                              <?=gettext("none");?>
                            </option>
                          </select>
                          <div class="hidden" data-for="help_for_statetype">
                            <span>
                              <?=gettext("Hint: Select which type of state tracking mechanism you would like to use. If in doubt, use keep state.");?>
                            </span>
                              <ul>
                                <li><?= sprintf(gettext('%sKeep state%s is used for stateful connection tracking.'),'<strong>', '</strong>') ?></li>
                                <li><?= sprintf(gettext('%sSloppy state%s works like keep state, but it does not check sequence numbers. Use it when the firewall does not see all packets.'),'<strong>', '</strong>') ?></li>
                                <li><?= sprintf(gettext('%sSynproxy state%s proxies incoming TCP connections to help protect servers from spoofed TCP SYN floods. This option includes the functionality of keep state and modulate state combined.'),'<strong>', '</strong>') ?></li>
                                <li><?= sprintf(gettext("%sNone%s: Do not use state mechanisms to keep track. This is only useful if you're doing advanced queueing in certain situations. Please check the documentation."),'<strong>', '</strong>') ?></li>
                              </ul>
                              <p><?= sprintf(gettext('Source and more information can be found %shere%s.'),'<a href="https://www.freebsd.org/cgi/man.cgi?query=pf.conf&amp;sektion=5">','</a>') ?></p>
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
                        <?= date(gettext('n/j/y H:i:s'), $a_filter[$id]['created']['time']) ?> (<?= $a_filter[$id]['created']['username'] ?>)
                      </td>
                    </tr>
<?php
                    endif;
                    if ($has_updated_time):?>
                    <tr>
                      <td><?=gettext("Updated");?></td>
                      <td>
                        <?= date(gettext('n/j/y H:i:s'), $a_filter[$id]['updated']['time']) ?> (<?= $a_filter[$id]['updated']['username'] ?>)
                      </td>
                    </tr>
<?php
                    endif;
                    endif; ?>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                        <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/firewall_rules.php?if=<?= !empty($pconfig['floating']) ? 'FloatingRules' : $pconfig['interface'] ?>'" />
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
