<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004 Scott Ullrich
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2010 Jim Pingle
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

if (!isset($config['aliases'])) {
        $config['aliases'] = array();
}
if (!isset($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}

$a_aliases = &$config['aliases']['alias'];

$pconfig = array();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		if (isset($_GET['id']) && is_numericint($_GET['id']) && isset($a_aliases[$_GET['id']])) {
				$id = $_GET['id'];
				foreach (array("name","detail","address","type","descr","updatefreq","aliasurl","url") as $fieldname) {
					if (isset($a_aliases[$id][$fieldname])) {
						$pconfig[$fieldname] = $a_aliases[$id][$fieldname];
					} else {
						$pconfig[$fieldname] = null;
					}
				}
				// convert to array if only one is provided
				if (!empty($pconfig['aliasurl']) && !is_array($pconfig['aliasurl'])) {
					$pconfig['aliasurl'] = array($pconfig['aliasurl']);
				}
		} else {
				// init empty
				$init_fields = array("name","detail","address","type","descr","updatefreq","url");
				foreach ($init_fields as $fieldname) {
					$pconfig[$fieldname] = null;
				}
		}
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$pconfig = $_POST;
		if (isset($_POST['id']) && is_numericint($_POST['id']) && isset($a_aliases[$_POST['id']])) {
				$id = $_POST['id'];
		}

		// fix form type conversions ( list to string, as saved in config )
		// -- fill in default row description and make sure separators are removed
		if (strpos($pconfig['type'],'urltable') !== false) {
				$pconfig['url'] = $pconfig['host_url'][0];
		} elseif (strpos($pconfig['type'],'url') !== false) {
				$pconfig['aliasurl'] = $pconfig['host_url'];
		} else {
				$pconfig['address'] = implode(' ',$pconfig['host_url']);
		}
		unset($pconfig['host_url']);
		foreach ($pconfig['detail'] as &$detailDescr) {
				if (empty($detailDescr)) {
						$detailDescr = trim(str_replace('|',' ' , sprintf(gettext("Entry added %s"), date('r')) ));
				}
		}
		$pconfig['detail'] = implode('||',$pconfig['detail']);


		if (isset($pconfig['submit'])) {
				$input_errors = array();
				// validate data

				/* Check for reserved keyword names */
				// Keywords not allowed in names
				$reserved_keywords = array("all", "pass", "block", "out", "queue", "max", "min", "pptp", "pppoe", "L2TP", "OpenVPN", "IPsec");

				// Add all Load balance names to reserved_keywords
				if (is_array($config['load_balancer']['lbpool']))
					foreach ($config['load_balancer']['lbpool'] as $lbpool)
						$reserved_keywords[] = $lbpool['name'];

				$reserved_ifs = get_configured_interface_list(false, true);
				$reserved_keywords = array_merge($reserved_keywords, $reserved_ifs, $reserved_table_names);
				foreach($reserved_keywords as $rk)
					if($rk == $pconfig['name'])
						$input_errors[] = sprintf(gettext("Cannot use a reserved keyword as alias name %s"), $rk);

				/* check for name interface description conflicts */
				foreach($config['interfaces'] as $interface) {
					if($interface['descr'] == $pconfig['name']) {
						$input_errors[] = gettext("An interface description with this name already exists.");
						break;
					}
				}
				if ( is_validaliasname($pconfig['name']) !== true) {
						$input_errors[] = gettext("The alias name must be less than 32 characters long and may only consist of the characters") . " a-z, A-Z, 0-9, _.";
				}

				if (!empty($pconfig['updatefreq']) && !is_numericint($pconfig['updatefreq'])) {
						$input_errors[] = gettext("Update Frequency should by a number");
				}

				/* check for name conflicts */
				if (empty($a_aliases[$id])) {
					foreach ($a_aliases as $alias) {
						if ($alias['name'] == $_POST['name']) {
							$input_errors[] = gettext("An alias with this name already exists.");
							break;
						}
					}
				}

				/* user may not change type */
				if (isset($id) && $pconfig['type'] != $a_aliases[$id]['type']) {
						$input_errors[] = gettext("Alias type may not be changed for an existing alias.");
				}

				if ($pconfig['type'] == 'urltable') {
						if (empty($pconfig['url']) || !is_URL($pconfig['url'])) {
								$input_errors[] = gettext("You must provide a valid URL.");
						}
				}

				if (count($input_errors) == 0) {
						// save to config
						$copy_fields = array("name","detail","address","type","descr","updatefreq","aliasurl","url");
						$confItem = array();
						foreach ($copy_fields as $fieldname) {
								if (!empty($pconfig[$fieldname])) {
										$confItem[$fieldname] = $pconfig[$fieldname];
								}
						}

						/*   Check to see if alias name needs to be
						 *   renamed on referenced rules and such
						 */
						if (isset($id) && $pconfig['name'] <> $pconfig['origname']) {
							// Firewall rules
							$origname = $pconfig['origname'];
							update_alias_names_upon_change(array('filter', 'rule'), array('source', 'address'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'address'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('filter', 'rule'), array('source', 'port'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'port'), $pconfig['name'], $origname);
							// NAT Rules
							update_alias_names_upon_change(array('nat', 'rule'), array('source', 'address'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'rule'), array('source', 'port'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'address'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'port'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'rule'), array('target'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'rule'), array('local-port'), $pconfig['name'], $origname);
							// NAT 1:1 Rules
							update_alias_names_upon_change(array('nat', 'onetoone'), array('destination', 'address'), $pconfig['name'], $origname);
							// NAT Outbound Rules
							update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('source', 'network'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('sourceport'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('destination', 'address'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('dstport'), $pconfig['name'], $origname);
							update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('target'), $pconfig['name'], $origname);
							// Alias in an alias
							update_alias_names_upon_change(array('aliases', 'alias'), array('address'), $pconfig['name'], $origname);
						}


						// save to config
						if (isset($id)) {
								$a_aliases[$id] = $confItem;
						} else {
								$a_aliases[] = $confItem;
						}
						// Sort list
						$a_aliases = msort($a_aliases, "name");

						if (write_config()) {
							// post save actions
							mark_subsystem_dirty('aliases');
							if (strpos($pconfig['type'],'url') !== false) {
									// update URL Table Aliases
									configd_run('filter refresh_url_alias', true);
							}
						}

						if(!empty($pconfig['type']) == 'host') {
								header("Location: firewall_aliases.php?tab=ip");
						} elseif (strpos($pconfig['type'],'url') !== false) {
								header("Location: firewall_aliases.php?tab=url");
						} else {
								header("Location: firewall_aliases.php?tab=".$pconfig['type']);
						}
						exit;

				}
		}
}

$pgtitle = array(gettext("Firewall"),gettext("Aliases"),gettext("Edit"));
$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_aliases.php');

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>
<body>
<?php
	include("fbegin.inc");
?>
<script type="text/javascript">
	$( document ).ready(function() {
		// add new detail record
		$("#addNew").click(function(){
				// copy last row and reset values
				$('#detailTable > tbody').append('<tr>'+$('#detailTable > tbody > tr:last').html()+'</tr>');
				$('#detailTable > tbody > tr:last > td > input').each(function(){
					$(this).val("");
				});
		});

		function toggleType() {
			if ($("#typeSelect").val() == 'urltable' || $("#typeSelect").val() == 'urltable_ports'  ) {
				$("#updatefreq").removeClass('show');
				$("#updatefreq").removeClass('hidden');
				$("#updatefreqHeader").removeClass('show');
				$("#updatefreqHeader").removeClass('hidden');
				$("#addNew").addClass('hidden');
				$("#addNew").addClass('show');
				$('#detailTable > tbody > tr:gt(0)').remove();
			} else {
				$("#updatefreq").addClass('hidden');
				$("#updatefreq").addClass('show');
				$("#updatefreqHeader").addClass('hidden');
				$("#updatefreqHeader").addClass('show');
				$("#addNew").removeClass('show');
				$("#addNew").removeClass('hidden');
			}
			switch($("#typeSelect").val()) {
			    case 'urltable':
			        $("#detailsHeading1").html("<?=gettext("IP or FQDN");?>");
			        break;
					case 'urltable_ports':
			        $("#detailsHeading1").html("<?=gettext("IP or FQDN");?>");
			        break;
					case 'url':
			        $("#detailsHeading1").html("<?=gettext("IP or FQDN");?>");
			        break;
					case 'url_ports':
			        $("#detailsHeading1").html("<?=gettext("IP or FQDN");?>");
			        break;
					case 'host':
			        $("#detailsHeading1").html("<?=gettext("Host(s)");?>");
			        break;
					case 'network':
			        $("#detailsHeading1").html("<?=gettext("Network(s)");?>");
			        break;
					case 'port':
			        $("#detailsHeading1").html("<?=gettext("Port(s)");?>");
			        break;
			}
		}

		$("#typeSelect").change(function(){
			toggleType();
		});

		toggleType();
	});
</script>


	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">
<?php 	if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
				<div id="inputerrors"></div>
					<section class="col-xs-12">
						<div class="content-box">
					 		<header class="content-box-head container-fluid">
				        <h3><?=gettext("Alias Edit");?></h3>
							</header>
				    	<div class="content-box-main">
								<form action="firewall_aliases_edit.php" method="post" name="iform" id="iform">
	            		<div class="table-responsive">
		                <table class="table table-striped">
											<tr>
												<td colspan="2" align="right">
													<small><?=gettext("full help"); ?> </small>
													<i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_opnvpn_server" type="button"></i></a>
												</td>
											</tr>
											<tr>
												<td width="22%"><a id="help_for_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Name"); ?></td>
												<td width="78%">
													<input name="origname" type="hidden" id="origname" class="form-control unknown" size="40" value="<?=$pconfig['name'];?>" />
													<?php if (isset($id)): ?>
														<input name="id" type="hidden" value="<?=$id;?>" />
													<?php endif; ?>
													<input name="name" type="text" id="name" class="form-control unknown" size="40" maxlength="31" value="<?=$pconfig['name'];?>" />
													<div class="hidden" for="help_for_name">
														<?=gettext("The name of the alias may only consist of the characters \"a-z, A-Z, 0-9 and _\"."); ?>
													</div>
												</td>
											</tr>
											<tr>
												<td><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
												<td>
													<input name="descr" type="text" class="form-control unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
													<div class="hidden" for="help_for_description">
														<?=gettext("You may enter a description here for your reference (not parsed)."); ?>
													</div>
												</td>
											</tr>
											<tr>
												<td><a id="help_for_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type"); ?></td>
												<td>
													<select name="type" class="form-control" id="typeSelect">
														<option value="host" <?=$pconfig['type'] == "host" ? "selected=\"selected\"" : ""; ?>><?=gettext("Host(s)"); ?></option>
														<option value="network" <?=$pconfig['type'] == "network" ? "selected=\"selected\"" : ""; ?>><?=gettext("Network(s)"); ?></option>
														<option value="port" <?=$pconfig['type'] == "port" ? "selected=\"selected\"" : ""; ?>><?=gettext("Port(s)"); ?></option>
														<option value="url" <?=$pconfig['type'] == "url" ? "selected=\"selected\"" : ""; ?>><?=gettext("URL (IPs)");?></option>
														<option value="url_ports" <?=$pconfig['type'] == "url_ports" ? "selected=\"selected\"" : ""; ?>><?=gettext("URL (Ports)");?></option>
														<option value="urltable" <?=$pconfig['type'] == "urltable" ? "selected=\"selected\"" : ""; ?>><?=gettext("URL Table (IPs)"); ?></option>
														<option value="urltable_ports" <?=$pconfig['type'] == "urltable_ports" ? "selected=\"selected\"" : ""; ?>><?=gettext("URL Table (Ports)"); ?></option>
													</select>
												</td>
										</tr>
										<tr>
											<td><div id="addressnetworkport"><a id="help_for_hosts" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Host(s)"); ?></div></td>
											<td>
												<table class="table table-striped table-condensed" id="detailTable">
													<thead>
														<tr>
															<th id="detailsHeading1"><?=gettext("Network"); ?></th>
															<th id="detailsHeading3"><?=gettext("Description"); ?></th>
															<th id="updatefreqHeader" ><?=gettext("Update Freq. (days)");?></th>
														</tr>
													</thead>
													<tbody>
<?php											if (is_array($pconfig['aliasurl'])):
														$detail_desc = explode("||", $pconfig['detail']);
														foreach ($pconfig['aliasurl'] as $aliasid => $aliasurl):
?>
														<tr>
															<td>
																<input type="text" class="form-control" name="host_url[]" value="<?=$aliasurl;?>"/>
															</td>
															<td>
																<input type="text" class="form-control" name="detail[]" value="<?= isset($detail_desc[$aliasid])?$detail_desc[$aliasid]:"";?>"?>
															</td>
															<td>
<?php 													if ($aliasid ==0):
?>
																<input type="text" class="form-control input-sm" id="updatefreq"  name="updatefreq" value="<?=$pconfig['updatefreq'];?>" >
<?php 													endif;
?>
															</td>
														</tr>
<?php												endforeach;
													else:
														$detail_desc = explode("||", $pconfig['detail']);
														if (empty($pconfig['address']) && isset($pconfig['url'])) {
															$addresslst = array($pconfig['url']);
														} else {
															$addresslst = explode(' ', $pconfig['address']);
														}
														foreach ($addresslst as $addressid => $address):
?>
														<tr>
															<td>
																<input type="text" class="form-control"  name="host_url[]" value="<?=$address;?>"/>
															</td>
															<td>
																<input type="text" class="form-control" name="detail[]" value="<?= isset($detail_desc[$addressid])?$detail_desc[$addressid]:"";?>"?>
															</td>
															<td>
<?php 													if ($addressid ==0):
?>
																<input type="text" class="form-control  input-sm" id="updatefreq" name="updatefreq" value="<?=$pconfig['updatefreq'];?>" >
<?php 													endif;
?>
															</td>
														</tr>

<?php 											endforeach;
													endif;
?>
													</tbody>
													<tfoot>
														<tr>
															<td colspan="3">
																<div id="addNew" style="cursor:pointer;" class="btn btn-default btn-xs" alt="add"><span class="glyphicon glyphicon-plus"></span></div>
															</td>
														</tr>
													</tfoot>
												</table>
												<div class="hidden" for="help_for_hosts">
													<span class="text-info">
														<?=gettext("Networks")?><br/>
													</span>
													<small>
														<?=gettext("Networks are specified in CIDR format.  Select the CIDR mask that pertains to each entry. /32 specifies a single IPv4 host, /128 specifies a single IPv6 host, /24 specifies 255.255.255.0, /64 specifies a normal IPv6 network, etc. Hostnames (FQDNs) may also be specified, using a /32 mask for IPv4 or /128 for IPv6. You may also enter an IP range such as 192.168.1.1-192.168.1.254 and a list of CIDR networks will be derived to fill the range.");?>
														<br/>
													</small>
													<span class="text-info">
														<?=gettext("Hosts")?><br/>
													</span>
													<small>
														<?=gettext("Enter as many hosts as you would like.  Hosts must be specified by their IP address or fully qualified domain name (FQDN). FQDN hostnames are periodically re-resolved and updated. If multiple IPs are returned by a DNS query, all are used.");?>
														<br/>
													</small>
													<span class="text-info">
														<?=gettext("Ports")?><br/>
													</span>
													<small>
														<?=gettext("Enter as many ports as you wish.  Port ranges can be expressed by separating with a colon.");?>
														<br/>
													</small>
													<span class="text-info">
														<?=gettext("URL's")?><br/>
													</span>
													<small>
														<?=gettext("Enter a URL containing a large number of IPs,ports and/or Subnets. After saving the lists will be downloaded and scheduled for automatic updates when a frequency is provided.");?>
													</small>
												</div>
											</td>
										<tr>
											<td>&nbsp;</td>
											<td>
												<input id="submit" name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
												<input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
											</td>
										</tr>
									</table>
		                        </div>
						</form>
				    </div>
				</div>
			    </section>
			</div>
		</div>
	</section>
<?php include("foot.inc"); ?>
