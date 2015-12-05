<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2008 Ermal Luçi
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

if (!isset($config['gres']['gre']) || !is_array($config['gres']['gre'])) {
	$config['gres']['gre'] = array();
}

$a_gres = &$config['gres']['gre'] ;

function gre_inuse($num) {
	global $config, $a_gres;

	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_gres[$num]['greif'])
			return true;
	}

	return false;
}

if ($_GET['act'] == "del") {
	if (!isset($_GET['id']))
                $input_errors[] = gettext("Wrong parameters supplied");
        else if (empty($a_gres[$_GET['id']]))
                $input_errors[] = gettext("Wrong index supplied");
	/* check if still in use */
	else if (gre_inuse($_GET['id'])) {
		$input_errors[] = gettext("This GRE tunnel cannot be deleted because it is still being used as an interface.");
	} else {
		mwexec("/sbin/ifconfig " . $a_gres[$_GET['id']]['greif'] . " destroy");
		unset($a_gres[$_GET['id']]);

		write_config();

		header("Location: interfaces_gre.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"),gettext("GRE"));

include("head.inc");

$main_buttons = array(
	array('href'=>'interfaces_gre_edit.php', 'label'=>gettext('Add')),
);

?>

<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

						<div class="tab-content content-box col-xs-12">

		                        <form action="interfaces_assign.php" method="post" name="iform" id="iform">

		                        <div class="table-responsive">
			                        <table class="table table-striped table-sort">

			                         <thead>
                                            <tr>
								<th width="20%" class="listtopic"><?=gettext("Interface");?></th>
								<th width="20%" class="listtopic"><?=gettext("Tunnel to...");?></th>
								<th width="50%" class="listtopic"><?=gettext("Description");?></th>
								<th width="10%" class="listtopic">&nbsp;</th>
                                            </tr>
                                        </thead>

									<tbody>

									  <?php $i = 0; foreach ($a_gres as $gre): ?>
						                <tr  ondblclick="document.location='interfaces_gre_edit.php?id=<?=$i;?>'">
						                  <td class="listlr">
											<?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($gre['if']));?>
						                  </td>
						                  <td class="listr">
											<?=htmlspecialchars($gre['remote-addr']);?>
						                  </td>
						                  <td class="listbg">
						                    <?=htmlspecialchars($gre['descr']);?>&nbsp;
						                  </td>
						                  <td valign="middle" class="list nowrap">


							                   <a href="interfaces_gre_edit.php?id=<?=$i;?>" class="btn btn-default" data-toggle="tooltip" data-placement="left" title="<?=gettext("edit group");?>"><span class="glyphicon glyphicon-edit"></span></a>

											   <a href="interfaces_gre.php?act=del&amp;id=<?=$i;?>" class="btn btn-default"  data-toggle="tooltip" data-placement="left" title="<?=gettext("delete group");?>" onclick="return confirm('<?=gettext("Do you really want to delete this GRE tunnel?");?>')"><span class="glyphicon glyphicon-remove"></span></a>
										</td>
										</tr>
									  <?php $i++; endforeach; ?>

									</tbody>
						              </table>
							      </div>
							      <div class="container-fluid">
							       <p><span class="text-danger"><strong><?=gettext("Note:");?><br /></strong></span><?=gettext("Here you can configure Generic Routing Encapsulation (GRE - RFC 2784) tunnels.");?></p>
							      </div>

		                        </form>

						</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
