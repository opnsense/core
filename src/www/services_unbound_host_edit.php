<?php

/*
	Copyright (C) 2015 Manuel Faux <mfaux@conf.at>
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2014 Warren Baker <warren@decoy.co.za>
	Copyright (C) 2003-2004 Bob Zoller <bob@kludgebox.com> and Manuel Kasper <mk@neon1.net>.
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

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/services_unbound_overrides.php');

function hostcmp($a, $b) {
	return strcasecmp($a['host'], $b['host']);
}

function hosts_sort() {
	global $g, $config;

	if (!is_array($config['unbound']['hosts']))
		return;

	usort($config['unbound']['hosts'], "hostcmp");
}

if (!is_array($config['unbound']['hosts']))
	$config['unbound']['hosts'] = array();

$a_hosts = &$config['unbound']['hosts'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_hosts[$id]) {
/* Backwards compatibility for records created before introducing different RR types. */
	if (!isset($a_hosts[$id]['rr'])) {
		$a_hosts[$id]['rr'] = 'A';
	}

	$pconfig['host'] = $a_hosts[$id]['host'];
	$pconfig['domain'] = $a_hosts[$id]['domain'];
	$pconfig['rr'] = $a_hosts[$id]['rr'];
	$pconfig['ip'] = $a_hosts[$id]['ip'];
	$pconfig['mxprio'] = $a_hosts[$id]['mxprio'];
	$pconfig['mx'] = $a_hosts[$id]['mx'];
	$pconfig['descr'] = $a_hosts[$id]['descr'];
	$pconfig['aliases'] = $a_hosts[$id]['aliases'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "domain rr");
	$reqdfieldsn = array(gettext("Domain"),gettext("Type"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['host'] && !is_hostname($_POST['host']))) {
		$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
	}

	if (($_POST['domain'] && !is_domain($_POST['domain']))) {
		$input_errors[] = gettext("A valid domain must be specified.");
	}

	switch ($_POST['rr']) {
		case 'A': /* also: AAAA */
			$reqdfields = explode(" ", "ip");
			$reqdfieldsn = array(gettext("IP address"));

			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

			if (($_POST['ip'] && !is_ipaddr($_POST['ip']))) {
				$input_errors[] = gettext("A valid IP address must be specified.");
			}
			break;
		case 'MX':
			$reqdfields = explode(" ", "mxprio mx");
			$reqdfieldsn = array(gettext("MX Priority"), gettext("MX Host"));

			do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

			if (($_POST['mxprio'] && !is_numericint($_POST['mxprio']))) {
				$input_errors[] = gettext("A valid MX priority must be specified.");
			}

			if (($_POST['mx'] && !is_domain($_POST['mx']))) {
				$input_errors[] = gettext("A valid MX host must be specified.");
			}
			break;
		default:
			$input_errors[] = gettext("A valid resource record type must be specified.");
			break;
	}

	/* collect aliases */
	$aliases = array();
	foreach ($_POST as $key => $value) {
		$entry = '';
		if (!substr_compare('aliashost', $key, 0, 9)) {
			$entry = substr($key, 9);
			$field = 'host';
		}
		elseif (!substr_compare('aliasdomain', $key, 0, 11)) {
			$entry = substr($key, 11);
			$field = 'domain';
		}
		elseif (!substr_compare('aliasdescription', $key, 0, 16)) {
			$entry = substr($key, 16);
			$field = 'description';
		}
		if (ctype_digit($entry)) {
			$aliases[$entry][$field] = $value;
		}
	}
	$pconfig['aliases']['item'] = $aliases;

	/* validate aliases */
	foreach ($aliases as $idx => $alias) {
		$aliasreqdfields = array('aliasdomain' . $idx);
		$aliasreqdfieldsn = array(gettext("Alias Domain"));

		var_dump(array('fields' => $aliasreqdfields, 'names' => $aliasreqdfieldsn, 'alias' => $alias));
		do_input_validation($_POST, $aliasreqdfields, $aliasreqdfieldsn, $input_errors);
		if (($alias['host'] && !is_hostname($alias['host'])))
			$input_errors[] = gettext("Hostnames in alias list can only contain the characters A-Z, 0-9 and '-'.");
		if (($alias['domain'] && !is_domain($alias['domain'])))
			$input_errors[] = gettext("A valid domain must be specified in alias list.");
	}

	/* check for overlaps */
	foreach ($a_hosts as $hostent) {
		if (isset($id) && ($a_hosts[$id]) && ($a_hosts[$id] === $hostent))
			continue;

		if (($hostent['host'] == $_POST['host']) && ($hostent['domain'] == $_POST['domain'])
			&& ((is_ipaddrv4($hostent['ip']) && is_ipaddrv4($_POST['ip'])) || (is_ipaddrv6($hostent['ip']) && is_ipaddrv6($_POST['ip'])))) {
			$input_errors[] = gettext("This host/domain already exists.");
			break;
		}
	}

	if (!$input_errors) {
		$hostent = array();
		$hostent['host'] = $_POST['host'];
		$hostent['domain'] = $_POST['domain'];
		$hostent['rr'] = $_POST['rr'];
		$hostent['ip'] = $_POST['ip'];
		$hostent['mxprio'] = $_POST['mxprio'];
		$hostent['mx'] = $_POST['mx'];
		$hostent['descr'] = $_POST['descr'];
		$hostent['aliases']['item'] = $aliases;

		/* Destinguish between A and AAAA by parsing the passed IP address */
		if ($_POST['rr'] == 'A') {
			if (is_ipaddrv6($_POST['ip'])) {
				$hostent['rr'] = 'AAAA';
			}
		}

		if (isset($id) && $a_hosts[$id])
			$a_hosts[$id] = $hostent;
		else
			$a_hosts[] = $hostent;
		hosts_sort();

		mark_subsystem_dirty('unbound');

		write_config();

		header("Location: services_unbound_overrides.php");
		exit;
	}
}

include("head.inc");

?>


<body>
	<script type="text/javascript" src="/javascript/row_helper.js"></script>

	<script type="text/javascript">
	//<![CDATA[
		rowname[0] = "aliashost";
		rowtype[0] = "textbox";
		rowsize[0] = "20";
		rowname[1] = "aliasdomain";
		rowtype[1] = "textbox";
		rowsize[1] = "20";
		rowname[2] = "aliasdescription";
		rowtype[2] = "textbox";
		rowsize[2] = "20";

		function type_change() {
			switch (jQuery('#rr').val()) {
				case 'A':
					jQuery('#ip').prop('disabled', false);
					jQuery('#mxprio').prop('disabled', true);
					jQuery('#mx').prop('disabled', true);
					break;
				case 'MX':
					jQuery('#ip').prop('disabled', true);
					jQuery('#mxprio').prop('disabled', false);
					jQuery('#mx').prop('disabled', false);
					break;
				default:
					jQuery('#ip').prop('disabled', false);
					jQuery('#mxprio').prop('disabled', false);
					jQuery('#mx').prop('disabled', false);
			}
		}
	//]]>
	</script>

	<?php include("fbegin.inc"); ?>

	<section class="page-content-main">

		<div class="container-fluid">

			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

				<div class="content-box">

                        <form action="services_unbound_host_edit.php" method="post" name="iform" id="iform">

				<div class="table-responsive">
					<table class="table table-striped table-sort">
									<tr>
										<td colspan="2" valign="top" class="listtopic"><?=gettext("Edit DNS Resolver entry");?></td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Host");?></td>
										<td width="78%" class="vtable">
											<input name="host" type="text" class="formfld" id="host" size="40" value="<?=htmlspecialchars($pconfig['host']);?>" /><br />
											<span class="vexpl"><?=gettext("Name of the host, without domain part"); ?><br />
											<?=gettext("e.g."); ?> <em><?=gettext("myhost"); ?></em></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Domain");?></td>
										<td width="78%" class="vtable">
											<input name="domain" type="text" class="formfld" id="domain" size="40" value="<?=htmlspecialchars($pconfig['domain']);?>" /><br />
											<span class="vexpl"><?=gettext("Domain of the host"); ?><br />
												<?=gettext("e.g."); ?> <em><?=gettext("example.com"); ?></em></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("Type");?></td>
										<td width="78%" class="vtable">
											<select name="rr" id="rr" class="formselect" onchange="type_change()">
											<?php
												 $rrs = array("A" => gettext("A or AAAA (IPv4 or IPv6 address)"), "MX" => gettext("MX (Mail server)"));
												 foreach ($rrs as $rr => $name) :
											?>
											<option value="<?=$rr;?>" <?=($rr == $pconfig['rr'] || ($rr == 'A' && $pconfig['rr'] == 'AAAA')) ? "selected=\"selected\"" : "";?> >
												<?=$name;?>
											</option>
											<?php endforeach; ?>
											</select>
											<span class="vexpl"><?=gettext("Type of resource record"); ?><br />
												<?=gettext("e.g."); ?> <em>A</em> <?=gettext("or"); ?> <em>AAAA</em> <?=gettext("for IPv4 or IPv6 addresses"); ?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("IP");?></td>
										<td width="78%" class="vtable">
											<input name="ip" type="text" class="formfld" id="ip" size="40" value="<?=htmlspecialchars($pconfig['ip']);?>" /><br />
											<span class="vexpl"><?=gettext("IP address of the host"); ?><br />
												<?=gettext("e.g."); ?> <em>192.168.100.100</em> <?=gettext("or"); ?> <em>fd00:abcd::1</em></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("MX Priority");?></td>
										<td width="78%" class="vtable">
											<input name="mxprio" type="text" class="formfld" id="mxprio" size="6" value="<?=htmlspecialchars($pconfig['mxprio']);?>" /><br />
											<span class="vexpl"><?=gettext("Priority of MX record"); ?><br />
												<?=gettext("e.g."); ?> <em>10</em></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncellreq"><?=gettext("MX Host");?></td>
										<td width="78%" class="vtable">
											<input name="mx" type="text" class="formfld" id="mx" size="6" value="<?=htmlspecialchars($pconfig['mx']);?>" /><br />
											<span class="vexpl"><?=gettext("Host name of MX host"); ?><br />
                                                <?=gettext("e.g."); ?> <em>mail.example.com</em></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
										<td width="78%" class="vtable">
											<input name="descr" type="text" class="formfld" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>" /><br />
											<span class="vexpl"><?=gettext("You may enter a description here for your reference (not parsed).");?></span>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top" class="vncell"><div id="addressnetworkport"><?=gettext("Aliases"); ?></div></td>
										<td width="78%" class="vtable">
											<table id="maintable" summary="aliases">
												<tbody>
													<tr>
														<td colspan="4">
															<div style="padding:5px; margin-top: 16px; margin-bottom: 16px; border:1px dashed #000066; background-color: #ffffff; color: #000000; font-size: 8pt;" id="itemhelp">
																<?=gettext("Enter additional names for this host."); ?>
															</div>
														</td>
													</tr>
													<tr>
														<td><div id="onecolumn"><?=gettext("Host");?></div></td>
														<td><div id="twocolumn"><?=gettext("Domain");?></div></td>
														<td><div id="threecolumn"><?=gettext("Description");?></div></td>
													</tr>
													<?php
														$counter = 0;
														if (isset($pconfig['aliases']['item'])):
															foreach($pconfig['aliases']['item'] as $item):
																$host = $item['host'];
																$domain = $item['domain'];
																$description = $item['description'];
													?>
													<tr>
														<td>
															<input autocomplete="off" name="aliashost<?php echo $counter; ?>" type="text" class="formfld unknown" id="aliashost<?php echo $counter; ?>" size="20" value="<?=htmlspecialchars($host);?>" />
														</td>
														<td>
															<input autocomplete="off" name="aliasdomain<?php echo $counter; ?>" type="text" class="formfld unknown" id="aliasdomain<?php echo $counter; ?>" size="20" value="<?=htmlspecialchars($domain);?>" />
														</td>
														<td>
															<input name="aliasdescription<?php echo $counter; ?>" type="text" class="formfld unknown" id="aliasdescription<?php echo $counter; ?>" size="20" value="<?=htmlspecialchars($description);?>" />
														</td>
														<td>
															<a onclick="removeRow(this); return false;" href="#" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
														</td>
													</tr>
													<?php
																$counter++;
															endforeach;
														endif;
													?>
												</tbody>
											</table>
											<a onclick="javascript:addRowTo('maintable', 'formfldalias'); return false;" href="#" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
											<script type="text/javascript">
											//<![CDATA[
												field_counter_js = 3;
												rows = 1;
												totalrows = <?php echo $counter; ?>;
												loaded = <?php echo $counter; ?>;
											//]]>
											</script>
										</td>
									</tr>
									<tr>
										<td width="22%" valign="top">&nbsp;</td>
										<td width="78%">
											<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
											<input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
											<?php if (isset($id) && $a_hosts[$id]): ?>
											<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
											<?php endif; ?>
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

<script type="text/javascript">
//<![CDATA[
type_change();
//]]>
</script>
<?php include("foot.inc"); ?>
