<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2005-2007 Scott Ullrich
	Copyright (C) 2008 Shrew Soft Inc
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

require("guiconfig.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_advanced_sysctl.php');

if (!is_array($config['sysctl']['item']))
	$config['sysctl']['item'] = array();

$a_tunable = &$config['sysctl']['item'];

if (is_numericint($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if ($act == "edit") {
	if ($a_tunable[$id]) {
		$pconfig['tunable'] = $a_tunable[$id]['tunable'];
		$pconfig['value'] = $a_tunable[$id]['value'];
		$pconfig['descr'] = $a_tunable[$id]['descr'];
	}
}

if ($act == "del") {
	if ($a_tunable[$id]) {
		/* if this is an AJAX caller then handle via JSON */
		if(isAjax() && is_array($input_errors)) {
			input_errors2Ajax($input_errors);
			exit;
		}
		if (!$input_errors) {
			unset($a_tunable[$id]);
			write_config();
			mark_subsystem_dirty('sysctl');
			pfSenseHeader("system_advanced_sysctl.php");
			exit;
		}
	}
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* if this is an AJAX caller then handle via JSON */
	if (isAjax() && is_array($input_errors)) {
		input_errors2Ajax($input_errors);
		exit;
	}

	if ($_POST['apply']) {
		$retval = 0;
		system_setup_sysctl();
		$savemsg = get_std_save_message($retval);
		clear_subsystem_dirty('sysctl');
	}

	if ($_POST['Submit'] == gettext("Save")) {
		$tunableent = array();

		$tunableent['tunable'] = $_POST['tunable'];
		$tunableent['value'] = $_POST['value'];
		$tunableent['descr'] = $_POST['descr'];

		if (isset($id) && $a_tunable[$id])
			$a_tunable[$id] = $tunableent;
		else
			$a_tunable[] = $tunableent;

		mark_subsystem_dirty('sysctl');

		write_config();

		pfSenseHeader("system_advanced_sysctl.php");
		exit;
    }
}

$pgtitle = array(gettext("System"),gettext("Advanced: System Tunables"));
include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>


<!-- row -->
<section class="page-content-main">
	<div class="container-fluid">

        <div class="row">

            <form action="system_advanced_sysctl.php" method="post">
			<?php
				if ($input_errors) print_input_errors($input_errors);
				if ($savemsg) print_info_box($savemsg);
				if (is_subsystem_dirty('sysctl') && ($act != "edit" ))
					print_info_box_np(gettext("The firewall tunables have changed.  You must apply the configuration to take affect."));
			?>
		</form>

            <section class="col-xs-12">

                <? include('system_advanced_tabs.php'); ?>

                <div class="content-box tab-content">

			<div class="table-responsive">

                        <?php if ($act != "edit" ): ?>

			<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area" class="table table-striped">

				<thead>
				<tr>
					<th width="20%"><?=gettext("Tunable Name"); ?></th>
					<th width="60%"><?=gettext("Description"); ?></th>
					<th width="20%" colspan="2"><?=gettext("Value"); ?></th>
				</tr>
				</thead>

				<tbody>
				<?php $i = 0; foreach ($config['sysctl']['item'] as $tunable): ?>

				<tr>
					<td class="listlr" ondblclick="document.location='system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>';">
						<?php echo $tunable['tunable']; ?>
					</td>
					<td class="listr" align="left" ondblclick="document.location='system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>';">
						<?php echo $tunable['descr']; ?>
					</td>
					<td class="listr" align="left" ondblclick="document.location='system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>';">
						<?php echo $tunable['value']; ?>
						<?php
							if($tunable['value'] == "default")
								echo "(" . get_default_sysctl_value($tunable['tunable']) . ")";
						?>
					</td>
					<td class="list nowrap">
						<table border="0" cellspacing="0" cellpadding="1" summary="edit delete">
							<tr>
								<td valign="middle">
									<a href="system_advanced_sysctl.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs">
									    <span class="glyphicon glyphicon-pencil"></span>
									</a>
								</td>
								<td valign="middle">
									<a href="system_advanced_sysctl.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?"); ?>')" class="btn btn-default btn-xs">
										<span class="glyphicon glyphicon-remove"></span>
									</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<?php $i++; endforeach; ?>
				<tr>
					<td colspan="4">
						<a href="system_advanced_sysctl.php?act=edit" class="btn btn-primary pull-right">
								<span class="glyphicon glyphicon-plus"></span>
								</a>
					</td>
				</tr>

				</tbody>
			</table>

                        <?php else: ?>

			<form action="system_advanced_sysctl.php" method="post" name="iform" id="iform">

				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="edit system tunable" class="table table-striped">
					<thead>
						<tr>
							<th colspan="2" valign="top" class="listtopic"><?=gettext("Edit system tunable"); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Tunable"); ?></td>
							<td width="78%" class="vtable">
								<input size="65" name="tunable" value="<?php echo $pconfig['tunable']; ?>" />
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Description"); ?></td>
							<td width="78%" class="vtable">
								<textarea name="descr"><?php echo $pconfig['descr']; ?></textarea>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext("Value"); ?></td>
							<td width="78%" class="vtable">
								<input size="65" name="value" value="<?php echo $pconfig['value']; ?>" />
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<input id="submit" name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
								<input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=$referer;?>'" />

								<?php if (isset($id) && $a_tunable[$id]): ?>
								<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			</form>

			<?php endif; ?>
			</div>

                </div>
            </section>
        </div>
    </div>
</section>

<?php include("foot.inc"); ?>
