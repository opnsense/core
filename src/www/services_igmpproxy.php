<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2004 Scott Ullrich
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
require_once("interfaces.inc");

if (!is_array($config['igmpproxy']['igmpentry']))
	$config['igmpproxy']['igmpentry'] = array();

//igmpproxy_sort();
$a_igmpproxy = &$config['igmpproxy']['igmpentry'];

if ($_POST) {

	$pconfig = $_POST;

	$retval = 0;
	/* reload all components that use igmpproxy */
	$retval = services_igmpproxy_configure();

	if(stristr($retval, "error") <> true)
	    $savemsg = get_std_save_message($retval);
	else
	    $savemsg = $retval;

	clear_subsystem_dirty('igmpproxy');
}

if ($_GET['act'] == "del") {
	if ($a_igmpproxy[$_GET['id']]) {
		unset($a_igmpproxy[$_GET['id']]);
		write_config();
		mark_subsystem_dirty('igmpproxy');
		header("Location: services_igmpproxy.php");
		exit;
	}
}

$pgtitle = array(gettext("Services"),gettext("IGMP Proxy"));
include("head.inc");

$main_buttons = array(
	array('label'=>gettext("add a new igmpentry"), 'href'=>'services_igmpproxy_edit.php'),
);

?>

<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">

		<div class="container-fluid">

			<div class="row">
				<?php if (isset($savemsg)) print_info_box($savemsg); ?>
				<?php if (is_subsystem_dirty('igmpproxy')): ?><br/>
				<?php print_info_box_np(gettext("The IGMP entry list has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?>
				<?php endif; ?>

			    <section class="col-xs-12">

				<div class="content-box">

                        <form action="services_igmpproxy.php" method="post" name="iform" id="iform">

				<div class="table-responsive">
					<table class="table table-striped table-sort">
									<tr>
									  <td width="15%" class="listhdrr"><?=gettext("Name");?></td>
									  <td width="10%" class="listhdrr"><?=gettext("Type");?></td>
									  <td width="25%" class="listhdrr"><?=gettext("Values");?></td>
									  <td width="20%" class="listhdr"><?=gettext("Description");?></td>
									  <td width="10%" class="list">

									</tr>
										  <?php $i = 0; foreach ($a_igmpproxy as $igmpentry): ?>
									<tr>
									  <td class="listlr" ondblclick="document.location='services_igmpproxy_edit.php?id=<?=$i;?>';">
									    <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($igmpentry['ifname']));?>
									  </td>
									  <td class="listlr" ondblclick="document.location='services_igmpproxy_edit.php?id=<?=$i;?>';">
									    <?=htmlspecialchars($igmpentry['type']);?>
									  </td>
									  <td class="listr" ondblclick="document.location='services_igmpproxy_edit.php?id=<?=$i;?>';">
									      <?php
										$addresses = implode(", ", array_slice(explode(" ", $igmpentry['address']), 0, 10));
										echo $addresses;
										if(count($addresses) < 10) {
											echo " ";
										} else {
											echo "...";
										}
									    ?>
									  </td>
									  <td class="listbg" ondblclick="document.location='services_igmpproxy_edit.php?id=<?=$i;?>';">
									    <?=htmlspecialchars($igmpentry['descr']);?>&nbsp;
									  </td>
									  <td valign="middle" class="list nowrap">
									     <a href="services_igmpproxy_edit.php?id=<?=$i;?>" title="<?=gettext("edit igmpentry"); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
									     <a href="services_igmpproxy.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this igmp entry? All elements that still use it will become invalid (e.g. filter rules)!");?>')" title="<?=gettext("delete igmpentry");?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
									  </td>
									</tr>
										  <?php $i++; endforeach; ?>

									  <tr>
									    <td colspan="5" width="78%">
										<br />
									      <input id="submit" name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
										<br />
									    </td>
									  </tr>
									<tr>
									  <td class="tabcont" colspan="5">
									   <p><span class="vexpl"><span class="red"><strong><?=gettext("Note:");?><br /></strong></span><?=gettext("Please add the interface for upstream, the allowed subnets, and the downstream interfaces you would like the proxy to allow. Only one 'upstream' interface can be configured.");?></span></p>
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
