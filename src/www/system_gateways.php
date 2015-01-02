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

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

$a_gateways = return_gateways_array(true, false, true);
$a_gateways_arr = array();
foreach ($a_gateways as $gw)
	$a_gateways_arr[] = $gw;
$a_gateways = $a_gateways_arr;

if (!is_array($config['gateways']['gateway_item']))
	$config['gateways']['gateway_item'] = array();

$a_gateway_item = &$config['gateways']['gateway_item'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {

		$retval = 0;

		$retval = system_routing_configure();
		$retval |= filter_configure();
		/* reconfigure our gateway monitor */
		setup_gateways_monitor();

		$savemsg = get_std_save_message($retval);
		if ($retval == 0)
			clear_subsystem_dirty('staticroutes');
	}
}

function can_delete_gateway_item($id) {
	global $config, $input_errors, $a_gateways;

	if (!isset($a_gateways[$id]))
		return false;

	if (is_array($config['gateways']['gateway_group'])) {
		foreach ($config['gateways']['gateway_group'] as $group) {
			foreach ($group['item'] as $item) {
				$items = explode("|", $item);
				if ($items[0] == $a_gateways[$id]['name']) {
					$input_errors[] = sprintf(gettext("Gateway '%s' cannot be deleted because it is in use on Gateway Group '%s'"), $a_gateways[$id]['name'], $group['name']);
					break;
				}
			}
		}
	}

	if (is_array($config['staticroutes']['route'])) {
		foreach ($config['staticroutes']['route'] as $route) {
			if ($route['gateway'] == $a_gateways[$id]['name']) {
				$input_errors[] = sprintf(gettext("Gateway '%s' cannot be deleted because it is in use on Static Route '%s'"), $a_gateways[$id]['name'], $route['network']);
				break;
			}
		}
	}

	if (isset($input_errors))
		return false;

	return true;
}

function delete_gateway_item($id) {
	global $config, $a_gateways;

	if (!isset($a_gateways[$id]))
		return;

	/* NOTE: Cleanup static routes for the monitor ip if any */
	if (!empty($a_gateways[$id]['monitor']) &&
	    $a_gateways[$id]['monitor'] != "dynamic" &&
	    is_ipaddr($a_gateways[$id]['monitor']) &&
	    $a_gateways[$id]['gateway'] != $a_gateways[$id]['monitor']) {
		if (is_ipaddrv4($a_gateways[$id]['monitor']))
			mwexec("/sbin/route delete " . escapeshellarg($a_gateways[$id]['monitor']));
		else
			mwexec("/sbin/route delete -inet6 " . escapeshellarg($a_gateways[$id]['monitor']));
	}

	if ($config['interfaces'][$a_gateways[$id]['friendlyiface']]['gateway'] == $a_gateways[$id]['name'])
		unset($config['interfaces'][$a_gateways[$id]['friendlyiface']]['gateway']);
	unset($config['gateways']['gateway_item'][$a_gateways[$id]['attribute']]);
}

unset($input_errors);
if ($_GET['act'] == "del") {
	if (can_delete_gateway_item($_GET['id'])) {
		$realid = $a_gateways[$_GET['id']]['attribute'];
		delete_gateway_item($_GET['id']);
		write_config("Gateways: removed gateway {$realid}");
		mark_subsystem_dirty('staticroutes');
		header("Location: system_gateways.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {
	/* delete selected items */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei)
			if(!can_delete_gateway_item($rulei))
				break;

		if (!isset($input_errors)) {
			$items_deleted = "";
			foreach ($_POST['rule'] as $rulei) {
				delete_gateway_item($rulei);
				$items_deleted .= "{$rulei} ";
			}
			if (!empty($items_deleted)) {
				write_config("Gateways: removed gateways {$items_deleted}");
				mark_subsystem_dirty('staticroutes');
			}
			header("Location: system_gateways.php");
			exit;
		}
	}

} else if ($_GET['act'] == "toggle" && $a_gateways[$_GET['id']]) {
	$realid = $a_gateways[$_GET['id']]['attribute'];

	if(isset($a_gateway_item[$realid]['disabled']))
		unset($a_gateway_item[$realid]['disabled']);
	else
		$a_gateway_item[$realid]['disabled'] = true;

	if (write_config("Gateways: enable/disable"))
		mark_subsystem_dirty('staticroutes');

	header("Location: system_gateways.php");
	exit;
}

$pgtitle = array(gettext("System"),gettext("Gateways"));
$shortcut_section = "gateways";

include("head.inc");

$main_buttons = array(
	array('label'=>'Add Gateway', 'href'=>'system_gateways_edit.php'),
);

?>

<body>
    <?php include("fbegin.inc"); ?>


<!-- row -->

<section class="page-content-main">
	<div class="container-fluid">

        <div class="row">

            <?php
		if ($input_errors) print_input_errors($input_errors);
		if ($savemsg) print_info_box($savemsg);
		if (is_subsystem_dirty('staticroutes')) print_info_box_np(gettext("The gateway configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));
            ?>

            <section class="col-xs-12">

                <? include('system_gateways_tabs.php'); ?>

                <div class="content-box">

                    <div class="table-responsive">

                        <form action="system_gateways.php" method="post">

                            <table class="table table-striped table-sort sortable" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
				<thead>
					<tr id="frheader">
						<th width="4%" colspan="2" class="list">&nbsp;</th>
						<th width="15%" class="listhdrr"><?=gettext("Name"); ?></th>
						<th width="10%" class="listhdrr"><?=gettext("Interface"); ?></th>
						<th width="15%" class="listhdrr"><?=gettext("Gateway"); ?></th>
						<th width="15%" class="listhdrr"><?=gettext("Monitor IP"); ?></th>
						<th width="31%" class="listhdr"><?=gettext("Description"); ?></th>
						<th width="10%" class="list">

						</th>
					</tr>
				</thead>

                                <tbody>
                                <?php
                                $textse = "</span>";
                                $i = 0;
                                foreach ($a_gateways as $gateway):
					if (isset($gateway['disabled']) || isset($gateway['inactive'])) {
						$textss = "<span class=\"text-muted\">";
						$iconfn = "glyphicon glyphicon-play text-muted";
					} else {
						$textss = "<span>";
						$iconfn = "glyphicon glyphicon-play text-success";
					}
                                ?>
				<tr valign="top" id="fr<?=$i;?>">
					<td class="listt">

                                    <?php
					if (is_numeric($gateway['attribute'])):
                                    ?>
						<input type="checkbox" id="frc<?=$i;?>" name="rule[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$i;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" />
                                    <?php
					else:
                                    ?>
						&nbsp;
                                    <?php
					endif;
                                    ?>
					</td>
					<td class="listt" align="center">
                                    <?php
					if (isset($gateway['inactive'])):
                                    ?>
						<span class="glyphicon glyphicon-remove text-muted" title="<?=gettext("This gateway is inactive because interface is missing");?>"></span>

                                    <?php
					elseif (is_numeric($gateway['attribute'])):
                                    ?>
						<a href="?act=toggle&amp;id=<?=$i;?>" title="<?=gettext("click to toggle enabled/disabled status");?>" >
							<span class="glyphicon <?php echo $iconfn;?>"></span>

						</a>
                                    <?php
					else:
                                    ?>
					<span class="glyphicon <?php echo $iconfn;?>"  title="<?=gettext("click to toggle enabled/disabled status");?>"></span>
                                    <?php
					endif;
                                    ?>
					</td>
					<td class="listlr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
                                    <?php
						echo $textss;
						echo $gateway['name'];
						if(isset($gateway['defaultgw']))
							echo " <strong>(default)</strong>";
						echo $textse;
                                    ?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
                                    <?php
						echo $textss;
						echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($gateway['friendlyiface']));
						echo $textse;
                                    ?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
                                    <?php
						echo $textss;
						echo $gateway['gateway'] . " ";
						echo $textse;
                                    ?>
					</td>
					<td class="listr" onclick="fr_toggle(<?=$i;?>)" id="frd<?=$i;?>" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
                                    <?php
						echo $textss;
						echo htmlspecialchars($gateway['monitor']) . " ";
						echo $textse;
                                    ?>
					</td>
                                    <?php
                                    if (is_numeric($gateway['attribute'])):
                                    ?>
					<td class="listbg" onclick="fr_toggle(<?=$i;?>)" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
                                    <?php
                                    else:
                                    ?>
					<td class="listbgns" onclick="fr_toggle(<?=$i;?>)" ondblclick="document.location='system_gateways_edit.php?id=<?=$i;?>';">
                                    <?php
                                    endif;
						echo $textss;
						echo htmlspecialchars($gateway['descr']) . "&nbsp;";
						echo $textse;
                                    ?>
					</td>
					<td valign="middle" class="list nowrap">

									<a href="system_gateways_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs">
										<span class="glyphicon glyphicon-pencil"></span>
									</a>

                                            <?php
							if (is_numeric($gateway['attribute'])):
                                            ?>

									<a href="system_gateways.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this gateway?"); ?>')" class="btn btn-default btn-xs">
										<span class="glyphicon glyphicon-remove"></span>
									</a>

                                            <?php

							endif;
                                            ?>
							<a href="system_gateways_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs">
										<span class="glyphicon glyphicon-plus"></span>
									</a>
					</td>
				</tr>
                                <?php
                                $i++;
                                endforeach;
                                ?>
				<tr>
					<td class="list" colspan="7"></td>
					<td class="list">
						<table border="0" cellspacing="0" cellpadding="1" summary="edit">
							<tr>
								<td>
                                                <?php
								if ($i > 0):

                                                ?>
									<button type="submit" name="del_x" class="btn btn-default btn-xs"
										 title="<?=gettext("delete selected items");?>"
										onclick="return confirm('<?=gettext("Do you really want to delete the selected gateway items?");?>')">
										<span class="glyphicon glyphicon-remove"></span>
									</button>
                                                <?php
								endif;
								?>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				</tbody>
                            </table>

                        </form>

                    </div>
                </div>
            </section>
        </div>
	</div>
</section>

<?php include("foot.inc"); ?>
