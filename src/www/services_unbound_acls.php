<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2011 Warren Baker <warren@decoy.co.za>
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
require_once("unbound.inc");
require_once("system.inc");

function unbound_acl_id_used($id) {
    global $config;

    if (is_array($config['unbound']['acls']))
        foreach($config['unbound']['acls'] as & $acls)
            if ($id == $acls['aclid'])
                return true;

    return false;
}

function unbound_get_next_id() {
    $aclid = 0;
    while(unbound_acl_id_used($aclid))
        $aclid++;
    return $aclid;
}



$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/services_unbound_acls.php');

if (!is_array($config['unbound']['acls']))
	$config['unbound']['acls'] = array();

$a_acls = &$config['unbound']['acls'];

$id = $_GET['id'];
if (isset($_POST['aclid']))
	$id = $_POST['aclid'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if ($act == "del") {
	if (!$a_acls[$id]) {
		redirectHeader("services_unbound_acls.php");
		exit;
	}

	unset($a_acls[$id]);
	write_config();
	mark_subsystem_dirty('unbound');
}

if ($act == "new") {
	$id = unbound_get_next_id();
}

if ($act == "edit") {
	if (isset($id) && $a_acls[$id]) {
		$pconfig = $a_acls[$id];
		$networkacl = $a_acls[$id]['row'];
	}
}

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('unbound');
	} else {

		// input validation - only allow 50 entries in a single ACL
		for($x=0; $x<50; $x++) {
			if (isset($pconfig["acl_network{$x}"])) {
				$networkacl[$x] = array();
				$networkacl[$x]['acl_network'] = $pconfig["acl_network{$x}"];
				$networkacl[$x]['mask'] = $pconfig["mask{$x}"];
				$networkacl[$x]['description'] = $pconfig["description{$x}"];
				if (!is_ipaddr($networkacl[$x]['acl_network']))
					$input_errors[] = gettext("You must enter a valid network IP address for {$networkacl[$x]['acl_network']}.");

				if (is_ipaddr($networkacl[$x]['acl_network'])) {
					if (!is_subnet($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask']))
						$input_errors[] = gettext("You must enter a valid IPv4 netmask for {$networkacl[$x]['acl_network']}/{$networkacl[$x]['mask']}.");
				} else if (function_exists("is_ipaddrv6")) {
					if (!is_ipaddrv6($networkacl[$x]['acl_network']))
						$input_errors[] = gettext("You must enter a valid IPv6 address for {$networkacl[$x]['acl_network']}.");
					else if (!is_subnetv6($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask']))
						$input_errors[] = gettext("You must enter a valid IPv6 netmask for {$networkacl[$x]['acl_network']}/{$networkacl[$x]['mask']}.");
				} else
					$input_errors[] = gettext("You must enter a valid IPv4 address for {$networkacl[$x]['acl_network']}.");
			} else if (isset($networkacl[$x]))
				unset($networkacl[$x]);
		}

		if (!$input_errors) {
			if ($pconfig['Submit'] == gettext("Save")) {
				$acl_entry = array();
				$acl_entry['aclid'] = $pconfig['aclid'];
				$acl_entry['aclname'] = $pconfig['aclname'];
				$acl_entry['aclaction'] = $pconfig['aclaction'];
				$acl_entry['description'] = $pconfig['description'];
				$acl_entry['aclid'] = $pconfig['aclid'];
				$acl_entry['row'] = array();
				foreach ($networkacl as $acl)
					$acl_entry['row'][] = $acl;

				if (isset($id) && $a_acls[$id])
					$a_acls[$id] = $acl_entry;
				else
					$a_acls[] = $acl_entry;


				mark_subsystem_dirty("unbound");
				write_config();

				redirectHeader("/services_unbound_acls.php");
				exit;
			}

		}
	}
}

$closehead = false;
$pgtitle = "Services: DNS Resolver: Access Lists";
include("head.inc");

?>

<body>

<script type="text/javascript" src="/javascript/jquery.ipv4v6ify.js"></script>
<script type="text/javascript" src="/javascript/row_helper.js"></script>

<script type="text/javascript">
//<![CDATA[
	rowname[0] = "acl_network";
	rowtype[0] = "textbox,ipv4v6";
	rowsize[0] = "30";

	rowname[1] = "mask";
	rowtype[1] = "select,ipv4v6";
	rowsize[1] = "1";

	rowname[2] = "description";
	rowtype[2] = "textbox";
	rowsize[2] = "40";
//]]>
</script>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php
					if (!$savemsg)
						$savemsg = "";

					if (isset($input_errors) && count($input_errors) > 0)
						print_input_errors($input_errors);

					if (isset($savemsg))
						print_info_box($savemsg);

					if (is_subsystem_dirty("unbound"))
							print_info_box_np(gettext("The settings for the DNS Resolver have changed. You must apply the configuration to take affect."));
					?>



			    <section class="col-xs-12">

				<?php
						$tab_array = array();
						$tab_array[] = array(gettext("General Settings"), false, "/services_unbound.php");
						$tab_array[] = array(gettext("Advanced settings"), false, "services_unbound_advanced.php");
						$tab_array[] = array(gettext("Access Lists"), true, "/services_unbound_acls.php");
						display_top_tabs($tab_array, true);
					?>

					<div class="tab-content content-box col-xs-12">
					    <form action="services_unbound_acls.php" method="post" name="iform" id="iform">
							<?php if($act=="new" || $act=="edit"): ?>
								<input name="aclid" type="hidden" value="<?=$id;?>" />
								<input name="act" type="hidden" value="<?=$act;?>" />
								<table class="table table-striped">
									<tr>
										<td colspan="2"><?=ucwords(sprintf(gettext("%s Access List"),$act));?></td>
									</tr>
									<tr>
										<td width="22%"><?=gettext("Access List name");?></td>
										<td width="78%">
											<input name="aclname" type="text" class="formfld" id="aclname" size="30" maxlength="30" value="<?=htmlspecialchars($pconfig['aclname']);?>" />
											<br />
											<span><?=gettext("Provide an Access List name.");?></span>
										</td>
									</tr>
									<tr>
										<td width="22%"><?=gettext("Action");?></td>
										<td width="78%">
											<select name="aclaction" class="selectpicker">
												<?php $types = explode(",", "Allow,Deny,Refuse,Allow Snoop"); foreach ($types as $type): ?>
												<option value="<?=strtolower($type);?>" <?php if (strtolower($type) == strtolower($pconfig['aclaction'])) echo "selected=\"selected\""; ?>>
												<?=htmlspecialchars($type);?>
												</option>
												<?php endforeach; ?>
											</select>
											<br />
											<span class="text-default">
													<?=gettext("Choose what to do with DNS requests that match the criteria specified below.");?> <br />
													<?=gettext("<b>Deny:</b> This action stops queries from hosts within the netblock defined below.");?> <br />
													<?=gettext("<b>Refuse:</b> This action also stops queries from hosts within the netblock defined below, but sends a DNS rcode REFUSED error message back to the client.");?> <br />
													<?=gettext("<b>Allow:</b> This action allows queries from hosts within the netblock defined below.");?> <br />
													<?=gettext("<b>Allow Snoop:</b> This action allows recursive and nonrecursive access from hosts within the netblock defined below. Used for cache snooping and ideally should only be configured for your administrative host.");?> <br />
											</span>
										</td>
									</tr>
									<tr>
										<td width="22%"><?=gettext("Networks");?></td>
										<td width="78%">
											<table id="maintable" summary="networks" class="table table-striped">
													<tr>
														<td><div id="onecolumn"><?=gettext("Network");?></div></td>
														<td><div id="twocolumn"><?=gettext("CIDR");?></div></td>
														<td><div id="threecolumn"><?=gettext("Description");?></div></td>
														<td></td>
													</tr>
													<?php $counter = 0;
														if($networkacl)
															foreach($networkacl as $item):
																$network = $item['acl_network'];
																$cidr = $item['mask'];
																$description = $item['description'];
													?>
														<tr>
															<td>
																<input name="acl_network<?=$counter;?>" type="text" class="formfld unknown ipv4v6" id="acl_network<?=$counter;?>" size="30" value="<?=htmlspecialchars($network);?>" />
															</td>
															<td>
																<select name="mask<?=$counter;?>" class="formselect ipv4v6" id="mask<?=$counter;?>">
																<?php
																	for ($i = 128; $i > 0; $i--) {
																		echo "<option value=\"$i\" ";
																		if ($i == $cidr) echo "selected=\"selected\"";
																		echo ">" . $i . "</option>";
																	}
																?>
																</select>
															</td>
															<td>
																<input name="description<?=$counter;?>" type="text" class="formfld unknown" id="description<?=$counter;?>" size="40" value="<?=htmlspecialchars($description);?>" />
															</td>
															<td>
																<a onclick="removeRow(this); return false;" href="#" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
															</td>
														</tr>
													<?php $counter++; ?>
													<?php endforeach; ?>
											</table>
												<a onclick="javascript:addRowTo('maintable', 'formfldalias'); return false;" href="#" class="btn btn-default btn-xs">
													<span class="glyphicon glyphicon-plus"></span>
												</a>
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
										<td width="22%" valign="top"><?=gettext("Description");?></td>
										<td width="78%">
											<input name="description" type="text" class="formfld unknown" id="description" size="52" maxlength="52" value="<?=htmlspecialchars($pconfig['description']);?>" />
											<br />
											<span><?=gettext("You may enter a description here for your reference.");?></span>
										</td>
									</tr>
									<tr>
										<td width="22%">&nbsp;</td>
										<td width="78%">
												&nbsp;<br />&nbsp;
												<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
												<input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />
										</td>
									</tr>
								</table>

								<?php else: ?>

									<table class="table table-striped">

										<thead>
											<tr>
												<td width="25%" class="listhdrr"><?=gettext("Access List Name"); ?></td>
												<td width="25%" class="listhdrr"><?=gettext("Action"); ?></td>
												<td width="40%" class="listhdr"><?=gettext("Description"); ?></td>
												<td width="10%" class="list"></td>
											</tr>
										</thead>

										<tfoot>
											<tr>
												<td colspan="3"></td>
												<td>
													<a href="services_unbound_acls.php?act=new" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
												</td>
											</tr>
											<tr>
												<td colspan="4">
													<p>
														<?=gettext("Access Lists to control access to the DNS Resolver can be defined here.");?>
													</p>
												</td>
											</tr>
										</tfoot>
										<?php
											$i = 0;
											foreach($a_acls as $acl):
										?>
											<tr ondblclick="document.location='services_unbound_acls.php?act=edit&amp;id=<?=$i;?>'">
												<td>
													<?=htmlspecialchars($acl['aclname']);?>
												</td>
												<td>
													<?=htmlspecialchars($acl['aclaction']);?>
												</td>
												<td>
													<?=htmlspecialchars($acl['description']);?>
												</td>
												<td>
													<a href="services_unbound_acls.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
													<a href="services_unbound_acls.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this access list?"); ?>')" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
												</td>
											</tr>
										<?php
											$i++;
											endforeach;
										?>
										<tr style="display:none"><td></td></tr>
									</table>
								<?php endif; ?>
					    </form>
					</div>
			    </section>
			</div>
		</div>
	</section>


<?php include("foot.inc"); ?>
