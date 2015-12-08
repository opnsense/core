<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2009 Ermal Luçi
	Copyright (C) 2004 Scott Ullrich
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

if (!isset($config['ifgroups']['ifgroupentry'])) {
	$config['ifgroups']['ifgroupentry'] = array();
}

$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

if ($_GET['act'] == "del") {
	if ($a_ifgroups[$_GET['id']]) {
		$members = explode(" ", $a_ifgroups[$_GET['id']]['members']);
		foreach ($members as $ifs) {
			$realif = get_real_interface($ifs);
			if ($realif)
				mwexec("/sbin/ifconfig  {$realif} -group " . $a_ifgroups[$_GET['id']]['ifname']);
		}
		unset($a_ifgroups[$_GET['id']]);
		write_config();
		header("Location: interfaces_groups.php");
		exit;
	}
}

include("head.inc");

$main_buttons = array(
	array('href'=>'interfaces_groups_edit.php', 'label'=>gettext("Add a new group")),
);


?>

<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">


			    <section class="col-xs-12">

						<div class="tab-content content-box col-xs-12">



		                        <form action="interfaces_assign.php" method="post" name="iform" id="iform">
			                        <table class="table table-striped table-sort">

                                        <thead>
                                            <tr>
								<th width="15%" class="listtopic"><?=gettext("Name");?></th>
								<th width="30%" class="listtopic"><?=gettext("Members");?></th>
								<th width="25%" class="listtopic"><?=gettext("Description");?></th>
								<th width="10%" class="listtopic">&nbsp;</th>
                                            </tr>
                                        </thead>

									<tbody>

										<?php if (count ($a_ifgroups)):
												$i = 0; foreach ($a_ifgroups as $ifgroupentry): ?>
										<tr>
										  <td class="listlr" ondblclick="document.location='interfaces_groups_edit.php?id=<?=$i;?>';">
											<a href="/firewall_rules.php?if=<?=htmlspecialchars($ifgroupentry['ifname']);?>"><?=htmlspecialchars($ifgroupentry['ifname']);?></a>
										  </td>
										  <td class="listr" ondblclick="document.location='interfaces_groups_edit.php?id=<?=$i;?>';">
										      <?php
											$members_arr = explode(" ", $ifgroupentry['members']);
											$iflist = get_configured_interface_with_descr(false, true);
											$memberses_arr = array();
											foreach ($members_arr as $memb)
												$memberses_arr[] = $iflist[$memb] ? $iflist[$memb] : $memb;
											unset($iflist);
											$memberses = implode(", ", $memberses_arr);
											echo $memberses;
											if(count($members_arr) < 10) {
												echo " ";
											} else {
												echo "...";
											}
										    ?>
										  </td>
										  <td class="listbg" ondblclick="document.location='interfaces_groups_edit.php?id=<?=$i;?>';">
										    <?=htmlspecialchars($ifgroupentry['descr']);?>&nbsp;
										  </td>
										  <td valign="middle" class="list nowrap">
										    <a href="interfaces_groups_edit.php?id=<?=$i;?>" class="btn btn-default"><span class="glyphicon glyphicon-edit" data-toggle="tooltip" data-placement="left"  title="<?=gettext("edit group");?>"></span></a>

                        <a href="interfaces_groups.php?act=del&amp;id=<?=$i;?>" class="btn btn-default"  onclick="return confirm('<?=gettext("Do you really want to delete this group? All elements that still use it will become invalid (e.g. filter rules)!");?>')" data-toggle="tooltip" data-placement="left"  title="<?=gettext("delete ifgroupentry");?>"><span class="glyphicon glyphicon-remove"></span></a>

										  </td>
										</tr>
											  <?php $i++; endforeach; endif;?>
									</tbody>
									</table>

									<div class="container-fluid">
									<p><span class="vexpl"><span class="text-danger"><strong><?=gettext("Note:");?><br /></strong></span><?=gettext("Interface Groups allow you to create rules that apply to multiple interfaces without duplicating the rules. If you remove members from an interface group, the group rules no longer apply to that interface.");?></span></p>
									</div>

		                        </form>

						</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
