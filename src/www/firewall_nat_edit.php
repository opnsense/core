<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2009 Janne Enberg <janne.enberg@lietu.net>
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
 * obscured by clouds, is_specialnet uses this.. so let's hide it in here.
 * let's kill this another day.
 */
$specialsrcdst = explode(" ", "any (self) pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
	$specialsrcdst[] = "{$kif}";
	$specialsrcdst[] = "{$kif}ip";
}


// init config and get reference
if (!isset($config['nat']['rule']) || !is_array($config['nat']['rule'])) {
	$config['nat']['rule'] = array();
}
$a_nat = &$config['nat']['rule'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		// load form data from config
		if (isset($_GET['id']) && is_numericint($_GET['id']) && isset($a_nat[$_GET['id']])) {
			$id = $_GET['id'];
			$configId = $id; // load form data from id
		} else if (isset($_GET['dup']) && is_numericint($_GET['dup']) && isset($a_nat[$_GET['id']])){
			$after = $_GET['dup'];
			$configId = $_GET['dup']; // load form data from id
		}
		if (isset($_GET['after']) && (is_numericint($_GET['after']) || $_GET['after'] == "-1")) {
		 $after = $_GET['after'];
		}

		// initialize form and set defaults
		$pconfig = array();
		$pconfig['protocol'] = "tcp";
		$pconfig['srcbeginport'] = "any";
		$pconfig['srcendport'] = "any";
		$pconfig['interface'] = "wan";
		$pconfig['dstbeginport'] = 80 ;
		$pconfig['dstendport'] = 80 ;
		$pconfig['local-port'] = 80;
		if (isset($configId)) {
				// copy 1-on-1
				foreach (array('protocol','target','local-port','descr','interface','associated-rule-id','nosync'
											,'natreflection','created','updated') as $fieldname) {
						if (isset($a_nat[$configId][$fieldname])) {
								$pconfig[$fieldname] = $a_nat[$configId][$fieldname];
						}
				}
				// fields with some kind of logic.
				$pconfig['disabled'] = isset($a_nat[$configId]['disabled']);
				$pconfig['nordr'] = isset($a_nat[$configId]['nordr']);
				address_to_pconfig($a_nat[$configId]['source'], $pconfig['src'],
					$pconfig['srcmask'], $pconfig['srcnot'],
					$pconfig['srcbeginport'], $pconfig['srcendport']);

				address_to_pconfig($a_nat[$configId]['destination'], $pconfig['dst'],
					$pconfig['dstmask'], $pconfig['dstnot'],
					$pconfig['dstbeginport'], $pconfig['dstendport']);
		} else if (isset($_GET['template']) && $_GET['template'] == 'transparant_proxy') {
				// new rule for transparant proxy reflection, to use as sample
				$pconfig['interface'] = "lan";
				$pconfig['src'] = "lan";
				$pconfig['dst'] = "any";
				$pconfig['dstbeginport'] = 80 ;
				$pconfig['dstendport'] = 80 ;
				$pconfig['target'] = '127.0.0.1';
				// try to read the proxy configuration to determine the current port
				// this has some disadvantages in case of dependencies, but there isn't
				// a much better solution available at the moment.
				if (isset($config['OPNsense']['proxy']['forward']['port'])) {
						$pconfig['local-port'] = $config['OPNsense']['proxy']['forward']['port'];
				} else {
						$pconfig['local-port'] = 3128;
				}
				$pconfig['natreflection'] = 'enable';
				$pconfig['descr'] = "redirect traffic to proxy";
		} else {
				$pconfig['src'] = "any";
		}
		// init empty fields
		foreach (array("dst","dstmask","srcmask","dstbeginport","dstendport","target","local-port","natreflection","descr","disabled","nosync") as $fieldname) {
				if (!isset($pconfig[$fieldname])) {
						$pconfig[$fieldname] = null;
				}
		}
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$pconfig = $_POST;
		$input_errors = array();
		// validate id and store if usable
		if (isset($_POST['id']) && is_numericint($_POST['id']) && isset($a_nat[$_POST['id']])) {
				$id = $_POST['id'];
		}
		if (isset($_POST['after']) && (is_numericint($_POST['after']) || $_POST['after'] == "-1")) {
				$after = $_POST['after'];
		}

		/* Validate input data  */
		foreach ($pconfig as $key => $value) {
				if(htmlentities($value) <> $value) {
						$input_errors[] = sprintf(gettext("Invalid characters detected %s. Please remove invalid characters and save again."), $value);
				}
		}

		if( $pconfig['protocol'] == "tcp"  || $pconfig['protocol'] == "udp" || $pconfig['protocol'] == "tcp/udp") {
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

		if (!empty($pconfig['nordr'])) {
				$reqdfields[] = "target";
				$reqdfieldsn[] = gettext("Redirect target IP");
		}

		do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

		if (!isset($pconfig['nordr']) && ($pconfig['target'] && !is_ipaddroralias($pconfig['target']))) {
				$input_errors[] = sprintf(gettext("\"%s\" is not a valid redirect target IP address or host alias."), $pconfig['target']);
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

		// save data if valid
		if (count($input_errors) == 0) {
				$natent = array();

				// 1-on-1 copy
				$natent['protocol'] = $pconfig['protocol'];
				$natent['interface'] = $pconfig['interface'];
				$natent['descr'] = $pconfig['descr'];
				if (!empty($pconfig['associated-rule-id'])) {
						$natent['associated-rule-id'] = $pconfig['associated-rule-id'];
				} else {
						$natent['associated-rule-id'] = null;
				}


				// form processing logic
				$natent['disabled'] = !empty($pconfig['disabled']) ? true:false;
				$natent['nordr'] = !empty($pconfig['nordr']) ? true:false;
				$natent['nosync'] = !empty($pconfig['nosync']) ? true:false;

				if ($natent['nordr']) {
						$natent['associated-rule-id'] = '';
				} else {
						$natent['target'] = $pconfig['target'];
						$natent['local-port'] = $pconfig['local-port'];
				}
				pconfig_to_address($natent['source'], $pconfig['src'],
					$pconfig['srcmask'], !empty($pconfig['srcnot']),
					$pconfig['srcbeginport'], $pconfig['srcendport']);

				pconfig_to_address($natent['destination'], $pconfig['dst'],
					$pconfig['dstmask'], !empty($pconfig['dstnot']),
					$pconfig['dstbeginport'], $pconfig['dstendport']);

				if(!empty($pconfig['filter-rule-association']) && $pconfig['filter-rule-association'] == "pass") {
						$natent['associated-rule-id'] = "pass";
				}

				if ($pconfig['natreflection'] == "enable" || $pconfig['natreflection'] == "purenat" || $pconfig['natreflection'] == "disable") {
						$natent['natreflection'] = $pconfig['natreflection'];
				}

				// If we used to have an associated filter rule, but no-longer should have one
				if (isset($id) && !empty($a_nat[$id]['associated-rule-id']) && ( empty($natent['associated-rule-id']) || $natent['associated-rule-id'] != $a_nat[$id]['associated-rule-id'] ) ) {
						// Delete the previous rule
						foreach ($config['filter']['rule'] as $key => $item){
								if(isset($item['associated-rule-id']) && $item['associated-rule-id']==$a_nat[$id]['associated-rule-id'] ){
										unset($config['filter']['rule'][$key]);
										break;
								}
						}
						mark_subsystem_dirty('filter');
				}

				$need_filter_rule = false;
				// Updating a rule with a filter rule associated
				if (!empty($natent['associated-rule-id']))
						$need_filter_rule = true;
				// Create a rule or if we want to create a new one
				if( $natent['associated-rule-id']=='new' ) {
						$need_filter_rule = true;
						unset( $natent['associated-rule-id'] );
						$pconfig['filter-rule-association']='add-associated';
				}
				// If creating a new rule, where we want to add the filter rule, associated or not
				else if( isset($pconfig['filter-rule-association']) &&
					($pconfig['filter-rule-association']=='add-associated' ||
					$pconfig['filter-rule-association']=='add-unassociated') )
						$need_filter_rule = true;

				if ($need_filter_rule) {
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
										$filterent =& $config['filter']['rule'][$filterentid];
								}
						}
						pconfig_to_address($filterent['source'], $pconfig['src'],
							$pconfig['srcmask'], !empty($pconfig['srcnot']),
							$pconfig['srcbeginport'], $pconfig['srcendport']);

						// Update interface, protocol and destination
						$filterent['interface'] = $pconfig['interface'];
						$filterent['protocol'] = $pconfig['protocol'];
						if (!isset($filterent['destination'])) {
								$filterent['destination'] = array();
						}
						$filterent['destination']['address'] = $pconfig['target'];

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

						/*
						 * Our firewall filter description may be no longer than
						 * 63 characters, so don't let it be.
						 */
						$filterent['descr'] = substr("NAT " . $pconfig['descr'], 0, 62);

						// If this is a new rule, create an ID and add the rule
						if( isset($pconfig['filter-rule-association']) && $pconfig['filter-rule-association']=='add-associated' ) {
								$filterent['associated-rule-id'] = $natent['associated-rule-id'] = uniqid("nat_", true);
								$filterent['created'] = make_config_revision_entry(null, gettext("NAT Port Forward"));
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
						if (is_numeric($after)) {
								array_splice($a_nat, $after+1, 0, array($natent));
						} else {
								$a_nat[] = $natent;
						}
				}

				if (write_config()) {
						mark_subsystem_dirty('natconf');
				}

				header("Location: firewall_nat.php");
				exit;
		}
}

legacy_html_escape_form_data($pconfig);

$closehead = false;
$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("Port Forward"),gettext("Edit"));
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

		// on change event protocol change
		$("#proto").change(function(){
				if ($("#proto").val() == "tcp" ||  $("#proto").val() == "udp" || $("#proto").val() == "tcp/udp") {
						$(".act_port_select").removeClass("hidden");
				} else {
						$(".act_port_select").addClass("hidden");
				}
		});

		// on change event for "No RDR" checkbox
		$("#nordr").change(function(){
				if ($("#nordr").prop('checked')) {
					$(".act_no_rdr").addClass("hidden");
				} else {
					$(".act_no_rdr").removeClass("hidden");
				}
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
						<form action="firewall_nat_edit.php" method="post" name="iform" id="iform">
							<table class="table table-striped">
								<tr>
									<td colspan="2" align="right">
										<small><?=gettext("full help"); ?> </small>
										<i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_opnvpn_server" type="button"></i></a>
									</td>
								</tr>
								<tr>
									<td colspan="2"><?=gettext("Edit Redirect entry"); ?></td>
								</tr>
								<tr>
									<td width="22%"><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
									<td width="78%">
										<input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : ""; ?> />
										<div class="hidden" for="help_for_disabled">
											<strong><?=gettext("Disable this rule"); ?></strong><br />
											<?=gettext("Set this option to disable this rule without removing it from the list."); ?>
										</div>
									</td>
								</tr>
								<tr>
									<td><a id="help_for_nordr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("No RDR (NOT)"); ?></td>
									<td>
										<input type="checkbox" name="nordr" id="nordr" <?= !empty($pconfig['nordr']) ? "checked=\"checked\"" : ""; ?> />
										<div class="hidden" for="help_for_nordr">
											<?=gettext("Enabling this option will disable redirection for traffic matching this rule."); ?>
											<br /><?=gettext("Hint: this option is rarely needed, don't use this unless you know what you're doing."); ?>
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
											<?=gettext("Choose which interface this rule applies to."); ?><br />
											<?=gettext("Hint: in most cases, you'll want to use WAN here."); ?>
										</div>
									</td>
								</tr>
								<tr>
									<td><a id="help_for_proto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol"); ?></td>
									<td>
										<div class="input-group">
											<select id="proto" name="protocol" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
<?php								foreach (explode(" ", "TCP UDP TCP/UDP ICMP ESP AH GRE IPV6 IGMP PIM OSPF") as $proto):
?>
							<option value="<?=strtolower($proto);?>" <?= strtolower($proto) == $pconfig['protocol'] ? "selected=\"selected\"" : ""; ?>>
													<?=$proto;?>
												</option>
<?php								endforeach; ?>
							</select>
										</div>
										<div class="hidden" for="help_for_proto">
											<?=gettext("Choose which IP protocol " ."this rule should match."); ?><br/>
											<?=gettext("Hint: in most cases, you should specify"); ?> <em><?=gettext("TCP"); ?></em> &nbsp;<?=gettext("here."); ?>
										</div>
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
								<tr class="advanced_opt_src hidden">
										<td> <a id="help_for_src_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source") . " / ".gettext("Invert");?> </td>
										<td>
											<input name="srcnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['srcnot']) ? "checked=\"checked\"" : "";?> />
											<div class="hidden" for="help_for_src_invert">
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
															<option data-other=true value="<?=$pconfig['src'];?>" <?=!is_specialnet($pconfig['src']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
															<optgroup label="<?=gettext("aliasses");?>">
<?php												foreach (legacy_list_aliasses("network") as $alias):
?>
																<option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['src'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php													endforeach; ?>
															</optgroup>
															<optgroup label="<?=gettext("net");?>">
<?php													foreach (formNetworks() as $ifent => $ifdesc):
?>
																<option value="<?=$ifent;?>" <?= $pconfig['src'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
<?php														endforeach; ?>
														</optgroup>
													</select>
												</td>
											</tr>
											<tr>
												<td>
													<div class="input-group">
													<!-- updates to "other" option in  src -->
													<input type="text" for="src" value="<?=$pconfig['src'];?>" aria-label="<?=gettext("Source address");?>"/>
													<select name="srcmask" class="selectpicker" data-size="5" id="srcmask"  data-width="auto" for="src" >
													<?php for ($i = 32; $i > 0; $i--): ?>
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
													<td >
														<select id="srcbeginport" name="srcbeginport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
															<option data-other=true value="<?=$pconfig['srcbeginport'];?>">(<?=gettext("other"); ?>)</option>
															<optgroup label="<?=gettext("aliasses");?>">
<?php												foreach (legacy_list_aliasses("port") as $alias):
?>
																<option value="<?=$alias['name'];?>" <?= $pconfig['srcbeginport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php													endforeach; ?>
															</optgroup>
															<optgroup label="<?=gettext("well known ports");?>">
																<option value="any" <?= $pconfig['srcbeginport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php														foreach ($wkports as $wkport => $wkportdesc): ?>
																<option value="<?=$wkport;?>" <?= $wkport == $pconfig['srcbeginport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php														endforeach; ?>
															</optgroup>
														</select>
													</td>
													<td>
														<select id="srcendport" name="srcendport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
															<option data-other=true value="<?=$pconfig['srcendport'];?>">(<?=gettext("other"); ?>)</option>
															<optgroup label="<?=gettext("aliasses");?>">
<?php												foreach (legacy_list_aliasses("port") as $alias):
?>
																<option value="<?=$alias['name'];?>" <?= $pconfig['srcendport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php													endforeach; ?>
															</optgroup>
															<optgroup label="<?=gettext("well known ports");?>">
																<option value="any" <?= $pconfig['srcendport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php													foreach ($wkports as $wkport => $wkportdesc): ?>
																<option value="<?=$wkport;?>" <?= $wkport == $pconfig['srcendport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php													endforeach; ?>
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
										<input name="dstnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['dstnot']) ? "checked=\"checked\"" : "";?> />
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
													<select name="dst" id="dst" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
														<option data-other=true value="<?=$pconfig['dst'];?>" <?=!is_specialnet($pconfig['dst']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
														<optgroup label="<?=gettext("aliasses");?>">
<?php												foreach (legacy_list_aliasses("network") as $alias):
?>
															<option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['dst'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
<?php													endforeach; ?>
														</optgroup>
														<optgroup label="<?=gettext("net");?>">
<?php													foreach (formNetworks() as $ifent => $ifdesc):
?>
															<option value="<?=$ifent;?>" <?= $pconfig['dst'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
<?php														endforeach; ?>
														</optgroup>
													</select>
												</td>
											</tr>
											<tr>
												<td>
													<div class="input-group">
													<!-- updates to "other" option in  src -->
													<input type="text" for="dst" value="<?= !is_specialnet($pconfig['dst']) ? $pconfig['dst'] : "";?>" aria-label="<?=gettext("Destination address");?>"/>
													<select name="dstmask" class="selectpicker" data-size="5" id="dstmask"  data-width="auto" for="dst" >
													<?php for ($i = 32; $i > 0; $i--): ?>
														<option value="<?=$i;?>" <?= $i == $pconfig['dstmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
													<?php endfor; ?>
													</select>
												</div>
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
														<select id="dstbeginport" name="dstbeginport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
															<option data-other=true value="<?=$pconfig['dstbeginport'];?>">(<?=gettext("other"); ?>)</option>
															<optgroup label="<?=gettext("aliasses");?>">
<?php												foreach (legacy_list_aliasses("port") as $alias):
?>
																<option value="<?=$alias['name'];?>" <?= $pconfig['dstbeginport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php													endforeach; ?>
															</optgroup>
															<optgroup label="<?=gettext("well known ports");?>">
																<option value="any" <?= $pconfig['dstbeginport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php														foreach ($wkports as $wkport => $wkportdesc): ?>
																<option value="<?=$wkport;?>" <?= $wkport == $pconfig['dstbeginport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php														endforeach; ?>
															</optgroup>
														</select>
													</td>
													<td>
														<select id="dstendport" name="dstendport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
															<option data-other=true value="<?=$pconfig['dstendport'];?>">(<?=gettext("other"); ?>)</option>
															<optgroup label="<?=gettext("aliasses");?>">
<?php												foreach (legacy_list_aliasses("port") as $alias):
?>
																<option value="<?=$alias['name'];?>" <?= $pconfig['dstendport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php													endforeach; ?>
															</optgroup>
															<optgroup label="<?=gettext("well known ports");?>">
																<option value="any" <?= $pconfig['dstendport'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php													foreach ($wkports as $wkport => $wkportdesc): ?>
																<option value="<?=$wkport;?>" <?= $wkport == $pconfig['dstendport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php													endforeach; ?>
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
										<div class="hidden" for="help_for_dstport">
											<?=gettext("Specify the port or port range for the destination of the packet for this mapping."); ?>
										</div>
									</td>
								</tr>
								<tr>
									<tr class="act_no_rdr">
									<td><a id="help_for_localip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Redirect target IP"); ?></td>
									<td>
										<input name="target" type="text" class="formfldalias" size="20" value="<?=$pconfig['target'];?>" />
										<div class="hidden" for="help_for_localip">
											<?=gettext("Enter the internal IP address of " .
											"the server on which you want to map the ports."); ?><br/>
											<?=gettext("e.g."); ?> <em>192.168.1.12</em>
										</div>
								</tr>
								<tr class="act_port_select act_no_rdr">
									<td><a id="help_for_localbeginport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Redirect target port"); ?></td>
									<td>
										<table class="table table-condensed">
											<tbody>
												<tr>
													<td>
														<select id="localbeginport" name="local-port" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
															<option data-other=true value="<?=$pconfig['local-port'];?>">(<?=gettext("other"); ?>)</option>
															<optgroup label="<?=gettext("aliasses");?>">
<?php												foreach (legacy_list_aliasses("port") as $alias):
?>
																<option value="<?=$alias['name'];?>" <?= $pconfig['local-port'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
<?php													endforeach; ?>
															</optgroup>
															<optgroup label="<?=gettext("well known ports");?>">
																<option value="any" <?= $pconfig['local-port'] == "any" ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
<?php														foreach ($wkports as $wkport => $wkportdesc): ?>
																<option value="<?=$wkport;?>" <?= $wkport == $pconfig['local-port'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
<?php														endforeach; ?>
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
										<div class="hidden" for="help_for_localbeginport">
											<?=gettext("Specify the port on the machine with the " .
											"IP address entered above. In case of a port range, specify " .
											"the beginning port of the range (the end port will be calculated " .
											"automatically)."); ?><br />
											<?=gettext("Hint: this is usually identical to the 'from' port above"); ?>
										</div>
									</td>
								</tr>
								<tr>
									<td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
									<td>
										<input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" />
										<div class="hidden" for="help_for_descr">
			<?=gettext("You may enter a description here " ."for your reference (not parsed)."); ?>
										</div>
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
									<td><i class="fa fa-info-circle text-muted"></i> <?=gettext("NAT reflection"); ?></td>
									<td>
										<select name="natreflection" class="selectpicker">
										<option value="default" <?=$pconfig['natreflection'] != "enable" && $pconfig['natreflection'] != "purenat" && $pconfig['natreflection'] != "disable" ? "selected=\"selected\"" : ""; ?>><?=gettext("Use system default"); ?></option>
										<option value="enable" <?=$pconfig['natreflection'] == "enable" ? "selected=\"selected\"" : ""; ?>><?=gettext("Enable (NAT + Proxy)"); ?></option>
										<option value="purenat" <?=$pconfig['natreflection'] == "purenat" ? "selected=\"selected\"" : ""; ?>><?=gettext("Enable (Pure NAT)"); ?></option>
										<option value="disable" <?=$pconfig['natreflection'] == "disable" ? "selected=\"selected\"" : ""; ?>><?=gettext("Disable"); ?></option>
										</select>
									</td>
								</tr>
<?php						if (isset($id) && (!isset($_GET['dup']) || !is_numericint($_GET['dup']))): ?>
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
										<br/>
										<a href="firewall_rules_edit.php?id=<?=$linkedrule;?>"> <?=gettext("View the filter rule");?></a>
									</td>
								</tr>
<?php				 elseif (!isset($id) || (isset($_GET['dup']) && is_numericint($_GET['dup']))) :
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
										<div class="hidden" for="help_for_fra">
											<?=gettext("NOTE: The \"pass\" selection does not work properly with Multi-WAN. It will only work on an interface containing the default gateway.")?>
										</div>
									</td>
								</tr>
<?php					endif;

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
<?php					if ($has_created_time): ?>
								<tr>
									<td><?=gettext("Created");?></td>
									<td>
										<?= date(gettext("n/j/y H:i:s"), $pconfig['created']['time']) ?> <?= gettext("by") ?> <strong><?=$pconfig['created']['username'];?></strong>
									</td>
								</tr>
<?php					endif;
								if ($has_updated_time):
?>
								<tr>
									<td><?=gettext("Updated");?></td>
									<td>
										<?= date(gettext("n/j/y H:i:s"), $pconfig['updated']['time']) ?> <?= gettext("by") ?> <strong><?=$pconfig['updated']['username'];?></strong>
									</td>
								</tr>
<?php					endif;
								endif;
?>
								<tr>
									<td>&nbsp;</td>
									<td>&nbsp;</td>
								</tr>
								<tr>
									<td>&nbsp;</td>
									<td>
										<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
										<input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat.php';?>'" />
										<?php if (isset($id)): ?>
										<input name="id" type="hidden" value="<?=$id;?>" />
										<?php endif; ?>
										<?php if (isset($after)) : ?>
										<input name="after" type="hidden" value="<?=htmlspecialchars($after);?>" />
										<?php endif; ?>
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
<?php include("foot.inc"); ?>
