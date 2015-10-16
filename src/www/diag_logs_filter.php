<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) Jim Pingle jim@pingle.org
	Copyright (C) 2004-2009 Scott Ullrich
	Copyright (C) 2003-2009 Manuel Kasper <mk@neon1.net>,
	(origin easyrule.inc/php) Copyright (C) 2009-2010 Jim Pingle (jpingle@gmail.com)
	(origin easyrule.inc/php) Originally Sponsored By Anathematic @ pfSense Forums
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
require_once("filter_log.inc");
require_once("system.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");

/********************************************************************************************************************
 * imported from easyrule.inc/php
 ********************************************************************************************************************/

function easyrule_find_rule_interface($int) {
	global $config;
	/* Borrowed from firewall_rules.php */
	$iflist = get_configured_interface_with_descr(false, true);

	if (isset($config['pptpd']['mode']) && $config['pptpd']['mode'] == "server")
		$iflist['pptp'] = "PPTP VPN";

	if (isset($config['pppoe']['mode']) && $config['pppoe']['mode'] == "server")
		$iflist['pppoe'] = "PPPoE VPN";

	if (isset($config['l2tp']['mode']) && $config['l2tp']['mode'] == "server")
                $iflist['l2tp'] = "L2TP VPN";

	/* add ipsec interfaces */
	if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])){
		$iflist["enc0"] = "IPSEC";
	}

	if (isset($iflist[$int]))
		return $int;

	foreach ($iflist as $if => $ifd) {
		if (strtolower($int) == strtolower($ifd))
			return $if;
	}

	if (substr($int, 0, 4) == "ovpn")
		return "openvpn";

	return false;
}

function easyrule_block_rule_exists($int = 'wan', $ipproto = "inet") {
	global $config;
	$blockaliasname = 'EasyRuleBlockHosts';
	/* No rules, we we know it doesn't exist */
	if (!is_array($config['filter']['rule'])) {
		return false;
	}

	/* Search through the rules for one referencing our alias */
	foreach ($config['filter']['rule'] as $rule) {
		if (!is_array($rule) || !is_array($rule['source']))
			continue;
		$checkproto = isset($rule['ipprotocol']) ? $rule['ipprotocol'] : "inet";
		if ($rule['source']['address'] == $blockaliasname . strtoupper($int) && ($rule['interface'] == $int) && ($checkproto == $ipproto))
			return true;
	}
	return false;
}

function easyrule_block_rule_create($int = 'wan', $ipproto = "inet") {
	global $config;
	$blockaliasname = 'EasyRuleBlockHosts';
	/* If the alias doesn't exist, exit.
	 * Can't create an empty alias, and we don't know a host */
	if (easyrule_block_alias_getid($int) === false)
		return false;

	/* If the rule already exists, no need to do it again */
	if (easyrule_block_rule_exists($int, $ipproto))
		return true;

	/* No rules, start a new array */
	if (!is_array($config['filter']['rule'])) {
		$config['filter']['rule'] = array();
	}

	filter_rules_sort();
	$a_filter = &$config['filter']['rule'];

	/* Make up a new rule */
	$filterent = array();
	$filterent['type'] = 'block';
	$filterent['interface'] = $int;
	$filterent['ipprotocol'] = $ipproto;
	$filterent['source']['address'] = $blockaliasname . strtoupper($int);
	$filterent['destination']['any'] = '';
	$filterent['descr'] = gettext("Easy Rule: Blocked from Firewall Log View");
	$filterent['created'] = make_config_revision_entry(null, gettext("Easy Rule"));

	array_splice($a_filter, 0, 0, array($filterent));

	return true;
}

function easyrule_block_alias_getid($int = 'wan')
{
	global $config;

	$blockaliasname = 'EasyRuleBlockHosts';

	if (!isset($config['aliases']) || !is_array($config['aliases'])) {
		return false;
	}

	/* Hunt down an alias with the name we want, return its id */
	foreach ($config['aliases']['alias'] as $aliasid => $alias) {
		if ($alias['name'] == $blockaliasname . strtoupper($int)) {
			return $aliasid;
		}
	}

	return false;
}

function easyrule_block_alias_add($host, $int = 'wan') {
	global $config;
	$blockaliasname = 'EasyRuleBlockHosts';
	/* If the host isn't a valid IP address, bail */
	$host = trim($host, "[]");
	if (!is_ipaddr($host) && !is_subnet($host))
		return false;

	/* If there are no aliases, start an array */
	if (!isset($config['aliases']) || !is_array($config['aliases'])) {
		$config['aliases'] = array();
	}
	if (!isset($config['aliases']['alias'])) {
		$config['aliases']['alias'] = array();
	}
	$a_aliases = &$config['aliases']['alias'];

	/* Try to get the ID if the alias already exists */
	$id = easyrule_block_alias_getid($int);
	if ($id === false)
	  unset($id);

	$alias = array();
	if (is_subnet($host)) {
		list($host, $mask) = explode("/", $host);
	} elseif (is_specialnet($host)) {
		$mask = 0;
	} elseif (strpos($host,':') !== false && is_ipaddrv6($host)) {
		$mask = 128;
	} else {
		$mask = 32;
	}

	if (isset($id) && $a_aliases[$id]) {
		/* Make sure this IP isn't already in the list. */
		if (in_array($host.'/'.$mask, explode(" ", $a_aliases[$id]['address'])))
			return true;
		/* Since the alias already exists, just add to it. */
		$alias['name']    = $a_aliases[$id]['name'];
		$alias['type']    = $a_aliases[$id]['type'];
		$alias['descr']   = $a_aliases[$id]['descr'];

		$alias['address'] = $a_aliases[$id]['address'] . ' ' . $host . '/' . $mask;
		$alias['detail']  = $a_aliases[$id]['detail'] . gettext('Entry added') . ' ' . date('r') . '||';
	} else {
		/* Create a new alias with all the proper information */
		$alias['name']    = $blockaliasname . strtoupper($int);
		$alias['type']    = 'network';
		$alias['descr']   = gettext("Hosts blocked from Firewall Log view");

		$alias['address'] = $host . '/' . $mask;
		$alias['detail']  = gettext('Entry added') . ' ' . date('r') . '||';
	}

	/* Replace the old alias if needed, otherwise tack it on the end */
	if (isset($id) && $a_aliases[$id])
		$a_aliases[$id] = $alias;
	else
		$a_aliases[] = $alias;

	// Sort list
	$a_aliases = msort($a_aliases, "name");

	return true;
}

function easyrule_block_host_add($host, $int = 'wan', $ipproto = "inet") {
	global $retval;
	/* Bail if the supplied host is not a valid IP address */
	$host = trim($host, "[]");
	if (!is_ipaddr($host) && !is_subnet($host))
		return false;

	/* Flag whether or not we need to reload the filter */
	$dirty = false;

	/* Attempt to add this host to the alias */
	if (easyrule_block_alias_add($host, $int)) {
		$dirty = true;
	} else {
		/* Couldn't add the alias, or adding the host failed. */
		return false;
	}

	/* Attempt to add the firewall rule if it doesn't exist.
	 * Failing to add the rule isn't necessarily an error, it may
	 * have been modified by the user in some way. Adding to the
	 * Alias is what's important.
	 */
	if (!easyrule_block_rule_exists($int, $ipproto)) {
		if (easyrule_block_rule_create($int, $ipproto)) {
			$dirty = true;
		} else {
			return false;
		}
	}

	/* If needed, write the config and reload the filter */
	if ($dirty) {
		write_config();
		$retval = filter_configure();
		return true;
	} else {
		return false;
	}
}

function easyrule_pass_rule_add($int, $proto, $srchost, $dsthost, $dstport, $ipproto) {
	global $config;

	/* No rules, start a new array */
	if (!is_array($config['filter']['rule'])) {
		$config['filter']['rule'] = array();
	}

	filter_rules_sort();
	$a_filter = &$config['filter']['rule'];

	/* Make up a new rule */
	$filterent = array();
	$filterent['type'] = 'pass';
	$filterent['interface'] = $int;
	$filterent['ipprotocol'] = $ipproto;
	$filterent['descr'] = gettext("Easy Rule: Passed from Firewall Log View");

	if ($proto != "any")
		$filterent['protocol'] = $proto;
	else
		unset($filterent['protocol']);

	/* Default to only allow echo requests, since that's what most people want and
	 *  it should be a safe choice. */
	if ($proto == "icmp")
		$filterent['icmptype'] = 'echoreq';

	if ((strtolower($proto) == "icmp6") || (strtolower($proto) == "icmpv6"))
		$filterent['protocol'] = "icmp";

	if (is_subnet($srchost)) {
		list($srchost, $srcmask) = explode("/", $srchost);
	} elseif (is_specialnet($srchost)) {
		$srcmask = 0;
	} elseif (is_ipaddrv6($srchost)) {
		$srcmask = 128;
	} else {
		$srcmask = 32;
	}

	if (is_subnet($dsthost)) {
		list($dsthost, $dstmask) = explode("/", $dsthost);
	} elseif (is_specialnet($dsthost)) {
		$dstmask = 0;
	} elseif (is_ipaddrv6($dsthost)) {
		$dstmask = 128;
	} else {
		$dstmask = 32;
	}

	pconfig_to_address($filterent['source'], $srchost, $srcmask);
	pconfig_to_address($filterent['destination'], $dsthost, $dstmask, '', $dstport, $dstport);

	$filterent['created'] = make_config_revision_entry(null, gettext("Easy Rule"));
	$a_filter[] = $filterent;

	write_config($filterent['descr']);
	$retval = filter_configure();
	return true;
}

function easyrule_parse_block($int, $src, $ipproto = "inet") {
	if (!empty($src) && !empty($int)) {
		$src = trim($src, "[]");
		if (!is_ipaddr($src) && !is_subnet($src)) {
			return gettext("Tried to block invalid IP:") . ' ' . htmlspecialchars($src);
		}
		$int = easyrule_find_rule_interface($int);
		if ($int === false) {
			return gettext("Invalid interface for block rule:") . ' ' . htmlspecialchars($int);
		}
		if (easyrule_block_host_add($src, $int, $ipproto)) {
			return gettext("Host added successfully");
		} else {
			return gettext("Failed to create block rule, alias, or add host.");
		}
	} else {
		return gettext("Tried to block but had no host IP or interface");
	}
	return gettext("Unknown block error.");
}
function easyrule_parse_pass($int, $proto, $src, $dst, $dstport = 0, $ipproto = "inet") {
	/* Check for valid int, srchost, dsthost, dstport, and proto */
	$protocols_with_ports = array('tcp', 'udp');
	$src = trim($src, "[]");
	$dst = trim($dst, "[]");

	if (!empty($int) && !empty($proto) && !empty($src) && !empty($dst)) {
		$int = easyrule_find_rule_interface($int);
		if ($int === false) {
			return gettext("Invalid interface for pass rule:") . ' ' . htmlspecialchars($int);
		}
		if (getprotobyname($proto) == -1) {
			return gettext("Invalid protocol for pass rule:") . ' ' . htmlspecialchars($proto);
		}
		if (!is_ipaddr($src) && !is_subnet($src) && !is_ipaddroralias($src) && !is_specialnet($src)) {
			return gettext("Tried to pass invalid source IP:") . ' ' . htmlspecialchars($src);
		}
		if (!is_ipaddr($dst) && !is_subnet($dst) && !is_ipaddroralias($dst) && !is_specialnet($dst)) {
			return gettext("Tried to pass invalid destination IP:") . ' ' . htmlspecialchars($dst);
		}
		if (in_array($proto, $protocols_with_ports)) {
			if (empty($dstport)) {
				return gettext("Missing destination port:") . ' ' . htmlspecialchars($dstport);
			}
			if (!is_port($dstport) && ($dstport != "any")) {
				return gettext("Tried to pass invalid destination port:") . ' ' . htmlspecialchars($dstport);
			}
		} else {
			$dstport = 0;
		}
		/* Should have valid input... */
		if (easyrule_pass_rule_add($int, $proto, $src, $dst, $dstport, $ipproto)) {
			return gettext("Successfully added pass rule!");
		} else {
			return gettext("Failed to add pass rule.");
		}
	} else {
		return gettext("Missing parameters for pass rule.");
	}
	return gettext("Unknown pass error.");
}

/********************************************************************************************************************
 * other imported
 ********************************************************************************************************************/

function get_port_with_service($port, $proto) {
	if (!$port)
		return '';

	$service = getservbyport($port, $proto);
	$portstr = "";
	if ($service) {
		$portstr = sprintf('<span title="' . gettext('Service %1$s/%2$s: %3$s') . '">' . htmlspecialchars($port) . '</span>', $port, $proto, $service);
	} else {
		$portstr = htmlspecialchars($port);
	}
	return ':' . $portstr;
}

function find_rule_by_number($rulenum, $type = 'block')
{
	/* Passing arbitrary input to grep could be a Very Bad Thing(tm) */
	if (!is_numeric($rulenum) || !in_array($type, array('pass', 'block', 'match', 'rdr')))
		return;

	$lookup_pattern = "^@{$rulenum}[[:space:]]{$type}[[:space:]].*[[:space:]]log[[:space:]]";
	/* At the moment, miniupnpd is the only thing I know of that
	   generates logging rdr rules */
	unset($buffer);
	if ($type == "rdr")
		$_gb = exec("/sbin/pfctl -vvPsn -a \"miniupnpd\" | /usr/bin/egrep " . escapeshellarg("^@{$rulenum}"), $buffer);
	else {
		if (file_exists('/tmp/rules.debug')) {
			$_gb = exec('/sbin/pfctl -vvPnf /tmp/rules.debug 2>/dev/null | /usr/bin/egrep ' . escapeshellarg($lookup_pattern), $buffer);
		} else {
			$_gb = exec('/sbin/pfctl -vvPsr | /usr/bin/egrep ' . escapeshellarg($lookup_pattern), $buffer);
		}
	}
	if (is_array($buffer))
		return $buffer[0];

	return "";
}

function buffer_rules_load()
{
	global $buffer_rules_rdr, $buffer_rules_normal;
	unset($buffer, $buffer_rules_rdr, $buffer_rules_normal);
	/* Redeclare globals after unset to work around PHP */
	global $buffer_rules_rdr, $buffer_rules_normal;
	$buffer_rules_rdr = array();
	$buffer_rules_normal = array();

	$_gb = exec("/sbin/pfctl -vvPsn -a \"miniupnpd\" | grep '^@'", $buffer);
	if (is_array($buffer)) {
		foreach ($buffer as $line) {
			list($key, $value) = explode (" ", $line, 2);
			$buffer_rules_rdr[$key] = $value;
		}
	}
	unset($buffer, $_gb);
	if (file_exists('/tmp/rules.debug')) {
		$_gb = exec("/sbin/pfctl -vvPnf /tmp/rules.debug 2>/dev/null | /usr/bin/egrep '^@[0-9]+\([0-9]+\)[[:space:]].*[[:space:]]log[[:space:]]' | /usr/bin/egrep -v '^@[0-9]+\([0-9]+\)[[:space:]](nat|rdr|binat|no|scrub)'", $buffer);
	} else {
		$_gb = exec("/sbin/pfctl -vvPsr | /usr/bin/egrep '^@[0-9]+\([0-9]+\)[[:space:]].*[[:space:]]log[[:space:]]'", $buffer);
	}

	if (is_array($buffer)) {
		foreach ($buffer as $line) {
			list($key, $value) = explode (" ", $line, 2);
			$matches = array();
			if (preg_match('/\@(?P<rulenum>\d+)\)/', $key, $matches) == 1) {
				$key = "@{$matches['rulenum']}";
			}
			$buffer_rules_normal[$key] = $value;
		}
	}
	unset($_gb, $buffer);
}

function buffer_rules_clear()
{
	unset($GLOBALS['buffer_rules_normal']);
	unset($GLOBALS['buffer_rules_rdr']);
}

function find_rule_by_number_buffer($rulenum, $type)
{
	global $buffer_rules_rdr, $buffer_rules_normal;

	$lookup_key = "@{$rulenum}";

	if ($type == "rdr")	{
		$ruleString = $buffer_rules_rdr[$lookup_key];
		//TODO: get the correct 'description' part of a RDR log line. currently just first 30 characters..
		$rulename = substr($ruleString,0,30);
	} else {
		$ruleString = $buffer_rules_normal[$lookup_key];
		list(,$rulename,) = explode("\"",$ruleString);
		$rulename = str_replace("USER_RULE: ",'<span class="glyphicon glyphicon-user" title="USER_RULE" alt="USER_RULE"></span>',$rulename);
	}
	return "{$rulename} ({$lookup_key})";
}


/**********************************************************************************************************************************
 * End of imported code
 *********************************************************************************************************************************/


# --- AJAX RESOLVE ---
if (isset($_POST['resolve'])) {
	$ip = strtolower($_POST['resolve']);
	$res = (is_ipaddr($ip) ? gethostbyaddr($ip) : '');

	if ($res && $res != $ip)
		$response = array('resolve_ip' => $ip, 'resolve_text' => $res);
	else
		$response = array('resolve_ip' => $ip, 'resolve_text' => gettext("Cannot resolve"));

	echo json_encode(str_replace("\\","\\\\", $response)); // single escape chars can break JSON decode
	exit;
}

if (isset($_POST['easyrule'])) {

	$response = array("status"=>"unknown") ;
	switch ($_POST['easyrule']) {
		case 'block':
			easyrule_parse_block($_POST['intf'], $_POST['srcip'], $_POST['ipproto']);
			$response["status"] = "block" ;
			break;
		case 'pass':
			easyrule_parse_pass($_POST['intf'], $_POST['proto'], $_POST['srcip'], $_POST['dstip'], $_POST['dstport'], $_POST['ipproto']);
			$response["status"] = "pass" ;
			break;
	}


	echo json_encode(str_replace("\\","\\\\", $response));
	exit;
}

function getGETPOSTsettingvalue($settingname, $default)
{
	$settingvalue = $default;
	if(isset($_GET[$settingname]))
		$settingvalue = $_GET[$settingname];
	if(isset($_POST[$settingname]))
		$settingvalue = $_POST[$settingname];
	return $settingvalue;
}

$rulenum = getGETPOSTsettingvalue('getrulenum', null);
if($rulenum) {
	list($rulenum, $type) = explode(',', $rulenum);
	$rule = find_rule_by_number($rulenum,  $type);
	echo gettext("The rule that triggered this action is") . ":\n\n{$rule}";
	exit;
}

$filterfieldsarray = array();
$filtersubmit = getGETPOSTsettingvalue('filtersubmit', null);
if ($filtersubmit) {
	$interfacefilter = getGETPOSTsettingvalue('interface', null);
	$filtertext = getGETPOSTsettingvalue('filtertext', "");
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);


	$actpass = getGETPOSTsettingvalue('actpass', null);
	$actblock = getGETPOSTsettingvalue('actblock', null);

	$filterfieldsarray['act'] = str_replace("  ", " ", trim($actpass . " " . $actblock));
	$filterfieldsarray['act'] = $filterfieldsarray['act'] != "" ? $filterfieldsarray['act'] : 'All';
	$filterfieldsarray['time'] = getGETPOSTsettingvalue('filterlogentries_time', null);
	$filterfieldsarray['interface'] = getGETPOSTsettingvalue('filterlogentries_interfaces', null);
	$filterfieldsarray['srcip'] = getGETPOSTsettingvalue('filterlogentries_sourceipaddress', null);
	$filterfieldsarray['srcport'] = getGETPOSTsettingvalue('filterlogentries_sourceport', null);
	$filterfieldsarray['dstip'] = getGETPOSTsettingvalue('filterlogentries_destinationipaddress', null);
	$filterfieldsarray['dstport'] = getGETPOSTsettingvalue('filterlogentries_destinationport', null);
	$filterfieldsarray['proto'] = getGETPOSTsettingvalue('filterlogentries_protocol', null);
	$filterfieldsarray['tcpflags'] = getGETPOSTsettingvalue('filterlogentries_protocolflags', null);
	$filterfieldsarray['version'] = getGETPOSTsettingvalue('filterlogentries_version', null);
	$filterlogentries_qty = getGETPOSTsettingvalue('filterlogentries_qty', null);
} else {
	$interfacefilter = null;
	$filterlogentries_qty = null ;
	$filtertext = null;
	foreach (array('act','time','interface','srcip','srcport','dstip','dstport','proto','tcpflags', 'version') as $tag) {
		$filterfieldsarray[$tag] = null;
	}
}

$filter_logfile = '/var/log/filter.log';

if (isset($config['syslog']['nentries'])) {
	$nentries = $config['syslog']['nentries'];
}  else {
	$nentries = 50;
}


# Override Display Quantity
if (isset($filterlogentries_qty) && $filterlogentries_qty != null) {
	$nentries = $filterlogentries_qty;
}

if (isset($_POST['clear'])) {
	clear_clog($filter_logfile);
}

$pgtitle = array(gettext('Firewall'), gettext('Log Files'), gettext('Normal View'));
$shortcut_section = "firewall";
include("head.inc");

?>

<script src="/javascript/filter_log.js" type="text/javascript"></script>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">
					<div class="tab-content content-box col-xs-12">
							<form id="filterlogentries" name="filterlogentries" action="diag_logs_filter.php" method="post">
							<?php
								$Include_Act = explode(",", str_replace(" ", ",", $filterfieldsarray['act']));
								if ($filterfieldsarray['interface'] == "All") $interface = "";
							?>
							<div class="table-responsive widgetconfigdiv" id="filterlogentries_show"  style="<?=(!isset($config['syslog']['rawfilter']))?"":"display:none"?>">
                                <table class="table table-striped">
					      <thead>
					        <tr>
					          <th><?= gettext('Action') ?></th>
					          <th><?= gettext('Time and interface') ?></th>
					          <th><?= gettext('Source and destination IP Address') ?></th>
					          <th><?= gettext('Source and destination port') ?></th>
					          <th><?= gettext('Protocol') ?></th>
					          <th><?= gettext('Protocol') ?></th>
					        </tr>
					      </thead>
					      <tbody>
					        <tr>
					          <td>
						          <label class="__nowrap">
                                            <input id="actpass"   name="actpass"   type="checkbox" value="Pass"   <?php if (in_arrayi('Pass',   $Include_Act)) echo "checked=\"checked\""; ?> />&nbsp;&nbsp;Pass
                                          </label>
                                      </td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Time') ?>" id="filterlogentries_time" name="filterlogentries_time" value="<?= $filterfieldsarray['time'] ?>"></td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Source IP Address') ?>" id="filterlogentries_sourceipaddress" name="filterlogentries_sourceipaddress" value="<?= $filterfieldsarray['srcip'] ?>"></td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Source Port') ?>" id="filterlogentries_sourceport" name="filterlogentries_sourceport" value="<?= $filterfieldsarray['srcport'] ?>"></td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Protocol') ?>" id="filterlogentries_protocol" name="filterlogentries_protocol" value="<?= $filterfieldsarray['proto'] ?>"></td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Quantity') ?>" id="filterlogentries_qty" name="filterlogentries_qty" value="<?= $filterlogentries_qty ?>"></td>
					        </tr>
					        <tr>
					          <td>
						          <label class="__nowrap">
                                            <input id="actblock"  name="actblock"  type="checkbox" value="Block"  <?php if (in_arrayi('Block',  $Include_Act)) echo "checked=\"checked\""; ?> /> &nbsp;&nbsp;Block
                                          </label>
                                      </td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Interface') ?>" id="filterlogentries_interfaces" name="filterlogentries_interfaces" value="<?= $filterfieldsarray['interface'] ?>"></td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Destination IP Address') ?>" id="filterlogentries_destinationipaddress" name="filterlogentries_destinationipaddress" value="<?= $filterfieldsarray['dstip'] ?>"></td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Destination Port') ?>" id="filterlogentries_destinationport" name="filterlogentries_destinationport" value="<?= $filterfieldsarray['dstport'] ?>"></td>
					          <td><input type="text" class="form-control" placeholder="<?= gettext('Protocol Flags') ?>" id="filterlogentries_protocolflags" name="filterlogentries_protocolflags" value="<?= $filterfieldsarray['tcpflags'] ?>"></td>
                    <td>
                      <select class="form-control" id="filterlogentries_version" name="filterlogentries_version">
                        <?php
                          $versionlist = array("All" => gettext("Any"), "4" => gettext('IPv4'), "6" => gettext('IPv6'));
                          foreach ($versionlist as $version => $versionname)
                          {
                            echo '<option value="' . htmlspecialchars($version) . '"' . ((isset ($filterfieldsarray['version']) && $filterfieldsarray['version'] == $version ) ? ' selected="selected"' : '') . '>' . htmlspecialchars($versionname) . '</option>' ;
                          }
                        ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="6">
						<span class="vexpl"><a href="http://en.wikipedia.org/wiki/Transmission_Control_Protocol">TCP Flags</a>: F - FIN, S - SYN, A or . - ACK, R - RST, P - PSH, U - URG, E - ECE, W - CWR</span>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="6">
                      <input id="filtersubmit" name="filtersubmit" type="submit" class="btn btn-primary" style="vertical-align:top;" value="<?=gettext("Filter");?>" />
                    </td>
					        </tr>
					      </tbody>
					    </table>
                            </div>

							</form>
					</div>
			    </section>
			     <section class="col-xs-12">

					<div class="tab-content content-box col-xs-12">

							<div class="table-responsive">
								<table class="table table-striped table-sort">


						<?php if (!isset($config['syslog']['rawfilter'])):
							$iflist = get_configured_interface_with_descr(false, true);
							if (isset($iflist[$interfacefilter]))
								$interfacefilter = $iflist[$interfacefilter];
							if (isset($filtersubmit))
								$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filterfieldsarray);
							else
								$filterlog = conv_log_filter($filter_logfile, $nentries, $nentries + 100, $filtertext, $interfacefilter);

						?>
									<tr>
									  <td colspan="<?=isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions']==="1"?7:6?>" class="listtopic">
										<strong>
										<?php if ( (!$filtertext) && (!$filterfieldsarray) )
											printf(gettext("Last %s firewall log entries."),count($filterlog));
										else
											echo sprintf(gettext('Showing %s matching log entries (maximum is %s).'), count($filterlog), $nentries);?>
										</strong>
									  </td>
									</tr>
									<tr class="sortableHeaderRowIdentifier">
									  <td width="50" class="listhdrr"><?=gettext("Act");?></td>
									  <td class="listhdrr"><?=gettext("Time");?></td>
									  <td class="listhdrr"><?=gettext("If");?></td>
									  <?php if (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "1"):?>
										<td width="10%" class="listhdrr"><?=gettext("Rule");?></td>
									  <?php endif;?>
									  <td class="listhdrr"><?=gettext("Source");?></td>
									  <td class="listhdrr"><?=gettext("Destination");?></td>
									  <td class="listhdrr"><?=gettext("Proto");?></td>
									</tr>
									<?php
									if (isset($config['syslog']['filterdescriptions']))
										buffer_rules_load();
									$rowIndex = 0;
									foreach ($filterlog as $filterent):
									$evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
									$rowIndex++;?>
									<tr class="<?=$evenRowClass?>">
									  <td class="listMRlr nowrap" align="center" sorttable_customkey="<?=$filterent['act']?>">
									  <a onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?php echo "{$filterent['rulenum']},{$filterent['act']}"; ?>', outputrule);" title="<?php echo $filterent['act'];?>"><span class="glyphicon glyphicon-<?php switch ($filterent['act']) {
									    case 'pass':
                                                                                echo "play";  /* icon triangle */
                                                                                break;
                                                                            case 'match':
                                                                                echo "random";
                                                                                break;
                                                                            case 'reject':
									    case 'block':
									    default:
                                                                            echo 'remove'; /* a x*/
                                                                            break;
									  }
									  ?>"></span></a></td>
									  <?php if (isset($filterent['count'])) echo $filterent['count'];?></a></center></td>
									  <td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['time']);?></td>
									  <td class="listMRr nowrap">
										<?php if ($filterent['direction'] == "out"): ?>
										<span class="glyphicon glyphicon-cloud-download" alt="Direction=OUT" title="Direction=OUT"></span>
										<?php endif; ?>
										<?php echo htmlspecialchars($filterent['interface']);?></td>
									  <?php
									  if (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "1")
										echo("<td class=\"listMRr nowrap\">".find_rule_by_number_buffer($filterent['rulenum'],$filterent['act'])."</td>");

									  $int = strtolower($filterent['interface']);
									  $proto = strtolower($filterent['proto']);
									  if($filterent['version'] == '6') {
										$ipproto = "inet6";
										$filterent['srcip'] = "[{$filterent['srcip']}]";
										$filterent['dstip'] = "[{$filterent['dstip']}]";
									  } else {
									        $ipproto = "inet";
									  }
									  if (!isset($filterent['srcport'])) $filterent['srcport'] = null ;
									  $srcstr = $filterent['srcip'] . get_port_with_service($filterent['srcport'], $proto);
									  $src_htmlclass = str_replace(array('.', ':'), '-', $filterent['srcip']);
									  if (!isset($filterent['dstport'])) $filterent['dstport'] = null ;
									  $dststr = $filterent['dstip'] . get_port_with_service($filterent['dstport'], $proto);
									  $dst_htmlclass = str_replace(array('.', ':'), '-', $filterent['dstip']);
									  ?>
									  <td class="listMRr nowrap">
										<span onclick="javascript:resolve_with_ajax('<?php echo "{$filterent['srcip']}"; ?>');" title="<?=gettext("Click to resolve");?>" class="ICON-<?= $src_htmlclass; ?>" alt="Icon Reverse Resolve with DNS"><span class="btn btn-default btn-xs glyphicon glyphicon-info-sign"></span></span>

										<a title="<?=gettext("Easy Rule: Add to Block List");?>" href="#blockEasy" class="btn btn-danger btn-xs easy_block">
											<input type="hidden" value="<?= $filterent['srcip']; ?>" id="srcip"/>
											<input type="hidden" value="<?= $int;?>" id="intf"/>
											<input type="hidden" value="<?= $ipproto;?>" id="ipproto"/>
										<span class="glyphicon glyphicon-remove" alt="Icon Easy Rule: Add to Block List"></span></a>
										<?php echo $srcstr . '<span class="RESOLVE-' . $src_htmlclass . '"></span>';?>
									  </td>
									  <td class="listMRr nowrap">
										<span onclick="javascript:resolve_with_ajax('<?php echo "{$filterent['dstip']}"; ?>');" title="<?=gettext("Click to resolve");?>" class="ICON-<?= $dst_htmlclass; ?>" alt="Icon Reverse Resolve with DNS"><span class="btn btn-default btn-xs  glyphicon glyphicon-info-sign"></span></span>
										<a title="<?=gettext("Easy Rule: Pass this traffic");?>" href="#blockEasy" class="btn btn-success btn-xs easy_pass">
											<input type="hidden" value="<?= $filterent['srcip']; ?>" id="srcip"/>
											<input type="hidden" value="<?= $filterent['dstip']; ?>" id="dstip"/>
											<input type="hidden" value="<?= $filterent['dstport']; ?>" id="dstport"/>
											<input type="hidden" value="<?= $int;?>" id="intf"/>
											<input type="hidden" value="<?= $proto;?>" id="proto"/>
											<input type="hidden" value="<?= $ipproto;?>" id="ipproto"/>
										<span  class="glyphicon glyphicon-play" alt="Icon Easy Rule: Pass this traffic"></span></a>
										<?php echo $dststr . '<span class="RESOLVE-' . $dst_htmlclass . '"></span>';?>
									  </td>
									  <?php
										if ($filterent['proto'] == "TCP")
											$filterent['proto'] .= ":{$filterent['tcpflags']}";
									  ?>
									  <td class="listMRr nowrap"><?php echo htmlspecialchars($filterent['proto']);?></td>
									</tr>
									<?php if (isset($config['syslog']['filterdescriptions']) && $config['syslog']['filterdescriptions'] === "2"):?>
									<tr class="<?=$evenRowClass?>">
									  <td colspan="2" class="listMRDescriptionL listMRlr" />
									  <td colspan="4" class="listMRDescriptionR listMRr nowrap"><?=find_rule_by_number_buffer($filterent['rulenum'],$filterent['act']);?></td>
									</tr>
									<?php endif;
									endforeach;
									buffer_rules_clear(); ?>
						<?php else: ?>
								  <tr>
									<td colspan="2" class="listtopic">
									  <strong><?php printf(gettext("Last %s firewall log entries"),$nentries);?></strong></td>
								  </tr>
								  <?php
									if($filtertext)
										dump_clog($filter_logfile, $nentries, true, array("$filtertext"));
									else
										dump_clog($filter_logfile, $nentries);
								  ?>
								<tr><td colspan="2">
								<form id="clearform" name="clearform" action="diag_logs_filter.php" method="post" style="margin-top: 14px;">
									<input id="submit" name="clear" type="submit" class="btn btn-primary" value="<?=gettext("Clear log");?>" />
								</form>
								</td></tr>
						<?php endif; ?>

								</table>
								</div>
							</td>
						  </tr>
						</table>
				    </div>
			</section>
			</div>
		</div>
	</section>


<!-- AJAXY STUFF -->
<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
	$(".easy_block").click(function(){
		$.ajax(
			"/diag_logs_filter.php",
			{
				type: 'post',
				dataType: 'json',
				data: {
					easyrule:'block',
					srcip:$(this).find('#srcip').val(),
					ipproto:$(this).find('#ipproto').val(),
					intf:$(this).find('#intf').val()
				},
				complete: function(data,status) {
					alert("added block rule");
				},
			});

	});

	$(".easy_pass").click(function(){
		$.ajax(
			"/diag_logs_filter.php",
			{
				type: 'post',
				dataType: 'json',
				data: {
					easyrule:'pass',
					srcip:$(this).find('#srcip').val(),
					dstip:$(this).find('#dstip').val(),
					dstport:$(this).find('#dstport').val(),
					proto:$(this).find('#proto').val(),
					ipproto:$(this).find('#ipproto').val(),
					intf:$(this).find('#intf').val()
				},
				complete: function(data,status) {
					alert("added pass rule");
				},
			});

	});
});

function resolve_with_ajax(ip_to_resolve) {
	var url = "/diag_logs_filter.php";

	jQuery.ajax(
		url,
		{
			type: 'post',
			dataType: 'json',
			data: {
				resolve: ip_to_resolve,
				},
			complete: resolve_ip_callback
		});

}

function resolve_ip_callback(transport) {
	var response = jQuery.parseJSON(transport.responseText);
	var resolve_class = htmlspecialchars(response.resolve_ip.replace(/[.:]/g, '-'));
	var resolve_text = '<small><br />' + htmlspecialchars(response.resolve_text) + '<\/small>';

	jQuery('span.RESOLVE-' + resolve_class).html(resolve_text);
	jQuery('img.ICON-' + resolve_class).removeAttr('title');
	jQuery('img.ICON-' + resolve_class).removeAttr('alt');
	jQuery('img.ICON-' + resolve_class).attr('src', '/themes/<?= $g['theme']; ?>/images/icons/icon_log_d.gif');
	jQuery('img.ICON-' + resolve_class).prop('onclick', null);
	  // jQuery cautions that "removeAttr('onclick')" fails in some versions of IE
}

// From http://stackoverflow.com/questions/5499078/fastest-method-to-escape-html-tags-as-html-entities
function htmlspecialchars(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
}
//]]>
</script>

<?php include("foot.inc"); ?>
