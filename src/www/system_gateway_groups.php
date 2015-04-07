<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("openvpn.inc");

if (!is_array($config['gateways'])) {
	$config['gateways'] = array();
}

if (!is_array($config['gateways']['gateway_item'])) {
	$config['gateways']['gateway_item'] = array();
}

if (!is_array($config['gateways']['gateway_group'])) {
	$config['gateways']['gateway_group'] = array();
}

$a_gateway_groups = &$config['gateways']['gateway_group'];
$a_gateways = &$config['gateways']['gateway_item'];
$changedesc = gettext("Gateway Groups") . ": ";

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		$retval = system_routing_configure();

		configd_run('service reload dyndnsall');
		configd_run('service reload ipsecdns');
		configd_run('filter reload');

		/* reconfigure our gateway monitor */
		setup_gateways_monitor();

		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('staticroutes');

		foreach ($a_gateway_groups as $gateway_group) {
			$gw_subsystem = 'gwgroup.' . $gateway_group['name'];
			if (is_subsystem_dirty($gw_subsystem)) {
				openvpn_resync_gwgroup($gateway_group['name']);
				clear_subsystem_dirty($gw_subsystem);
			}
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_gateway_groups[$_GET['id']]) {
		$changedesc .= gettext("removed gateway group") . " {$_GET['id']}";
		foreach ($config['filter']['rule'] as $idx => $rule) {
			if ($rule['gateway'] == $a_gateway_groups[$_GET['id']]['name'])
				unset($config['filter']['rule'][$idx]['gateway']);
		}
		unset($a_gateway_groups[$_GET['id']]);
		write_config($changedesc);
		mark_subsystem_dirty('staticroutes');
		header("Location: system_gateway_groups.php");
		exit;
	}
}

$pgtitle = array(gettext("System"),gettext("Gateway Groups"));
$shortcut_section = "gateway-groups";

include("head.inc");

$main_buttons = array(
	array('label'=>'Add group', 'href'=>'system_gateway_groups_edit.php'),
);

?>

<body>
<?php include("fbegin.inc"); ?>


	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if ($savemsg) print_info_box($savemsg); ?>
				<?php if (is_subsystem_dirty('staticroutes')): ?><br/>
				<?php print_info_box_np(sprintf(gettext("The gateway configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br />"));?><br /><br />
				<?php endif; ?>

			    <section class="col-xs-12">


					<?php
								$tab_array = array();
								$tab_array[0] = array(gettext("Gateways"), false, "system_gateways.php");
								$tab_array[1] = array(gettext("Routes"), false, "system_routes.php");
								$tab_array[2] = array(gettext("Groups"), true, "system_gateway_groups.php");
								display_top_tabs($tab_array);
					?>

					<div class="tab-content content-box col-xs-12">

				    <div class="container-fluid">

	                        <form action="system_gateway_groups.php" method="post" name="iform" id="iform">
								<input type="hidden" name="y1" value="1" />

	                        <div class="table-responsive">
		                        <table class="table table-striped table-sort">
									<thead>
						                <tr>
						                  <td width="15%" class="listhdrr"><?=gettext("Group Name");?></td>
						                  <td width="15%" class="listhdrr"><?=gettext("Gateways");?></td>
						                  <td width="20%" class="listhdrr"><?=gettext("Priority");?></td>
						                  <td width="30%" class="listhdr"><?=gettext("Description");?></td>
						                  <td width="10%" class="list">

											</td>
								</tr>
								</thead>
								<tbody>
									  <?php $i = 0; foreach ($a_gateway_groups as $gateway_group): ?>
						                <tr>
						                  <td class="listlr" ondblclick="document.location='system_gateway_groups_edit.php?id=<?=$i;?>';">
						                    <?php
									echo $gateway_group['name'];
								?>
						                  </td>
						                  <td class="listr" ondblclick="document.location='system_gateway_groups_edit.php?id=<?=$i;?>';">
						                    <?php
									foreach($gateway_group['item'] as $item) {
										$itemsplit = explode("|", $item);
										echo htmlspecialchars(strtoupper($itemsplit[0])) . "<br />\n";
									}
								    ?>
						                  </td>
						                  <td class="listr" ondblclick="document.location='system_gateway_groups_edit.php?id=<?=$i;?>';">
								    <?php
									foreach($gateway_group['item'] as $item) {
										$itemsplit = explode("|", $item);
										echo "Tier ". htmlspecialchars($itemsplit[1]) . "<br />\n";
									}
								    ?>
						                  </td>
						                  <td class="listbg" ondblclick="document.location='system_gateway_groups_edit.php?id=<?=$i;?>';">
										<?=htmlspecialchars($gateway_group['descr']);?>&nbsp;
						                  </td>
						                  <td valign="middle" class="list nowrap">
									<table border="0" cellspacing="0" cellpadding="1" summary="edit">
									   <tr>
										<td><a href="system_gateway_groups_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a></td>
										<td><a href="system_gateway_groups.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this gateway group?");?>')" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a></td>
									   </tr>
									   <tr>
										<td width="17"></td>
										<td><a href="system_gateway_groups_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a></td>
									   </tr>
									</table>
						                  </td>
								</tr>
									  <?php $i++; endforeach; ?>
						                <tr style="display:none;"><td></td></tr>
								</tbody>
									</table>
									</div>
									<p><b><?=gettext("Note:");?></b>  <?=gettext("Remember to use these Gateway Groups in firewall rules in order to enable load balancing, failover, or policy-based routing. Without rules directing traffic into the Gateway Groups, they will not be used.");?></p>
							</form>

							</div>
							</div>
							</section>
							</div>
							</div>
							</section>


<?php include("foot.inc"); ?>
