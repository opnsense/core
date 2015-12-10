<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
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
require_once("services.inc");
require_once("system.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");

if (!is_array($config['unbound']))
	$config['unbound'] = array();
$a_unboundcfg =& $config['unbound'];

if (!is_array($config['unbound']['hosts']))
	$config['unbound']['hosts'] = array();
$a_hosts =& $config['unbound']['hosts'];

/* Backwards compatibility for records created before introducing RR types. */
foreach ($a_hosts as $i => $hostent) {
    if (!isset($hostent['rr'])) {
        $a_hosts[$i]['rr'] = (is_ipaddrv6($hostent['ip'])) ? 'AAAA' : 'A';
    }
}

if (!is_array($config['unbound']['domainoverrides']))
	$config['unbound']['domainoverrides'] = array();
$a_domainOverrides = &$config['unbound']['domainoverrides'];

if ($_POST) {
	unset($input_errors);

	if ($_POST['apply']) {
		$retval = services_unbound_configure();
		$savemsg = get_std_save_message();
		if ($retval == 0) {
			clear_subsystem_dirty('unbound');
		}
		/* Update resolv.conf in case the interface bindings exclude localhost. */
		system_resolvconf_generate();
	}
}

if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'host') {
		if ($a_hosts[$_GET['id']]) {
			unset($a_hosts[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('unbound');
			header("Location: services_unbound_overrides.php");
			exit;
		}
	} elseif ($_GET['type'] == 'doverride') {
		if ($a_domainOverrides[$_GET['id']]) {
			unset($a_domainOverrides[$_GET['id']]);
			write_config();
			mark_subsystem_dirty('unbound');
			header("Location: services_unbound_overrides.php");
			exit;
		}
	}
}

$closehead = false;
$pgtitle = array(gettext('Services'), gettext('DNS Resolver'), gettext('Overrides'));
include_once("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
				<?php if (isset($savemsg)) print_info_box($savemsg); ?>
				<?php if (is_subsystem_dirty('unbound')): ?><br/>
				<?php print_info_box_apply(gettext("The configuration for the DNS Resolver, has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
				<?php endif; ?>

				<form action="services_unbound_overrides.php" method="post" name="iform" id="iform" onsubmit="presubmit()">

			    <section class="col-xs-12">

				    <div class="content-box">

					    <header class="content-box-head container-fluid">
						<h3><?=gettext("Host Overrides");?></h3>
					</header>

					<div class="content-box-main col-xs-12">
						<?=gettext("Entries in this section override individual results from the forwarders.");?>
	<?=gettext("Use these for changing DNS results or for adding custom DNS records.");?>
	<?=gettext("Keep in mind that all resource record types (i.e. A, AAAA, MX, etc. records) of a specified host below are being overwritten.");?>
					</div>
					    <div class="content-box-main col-xs-12">
					    <div class="table-responsive">
						    <table class="table table-striped table-sort">
								<thead>
								<tr>
									<td width="20%" class="listhdrr"><?=gettext("Host");?></td>
									<td width="20%" class="listhdrr"><?=gettext("Domain");?></td>
									<td width="5%" class="listhdrr"><?=gettext("Type");?></td>
									<td width="20%" class="listhdrr"><?=gettext("Value");?></td>
									<td width="30%" class="listhdr"><?=gettext("Description");?></td>
									<td width="5%" class="list">
										<table border="0" cellspacing="0" cellpadding="1" summary="add">
											<tr>
												<td width="17"></td>
												<td valign="middle"><a href="services_unbound_host_edit.php" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a></td>
											</tr>
										</table>
									</td>
								</tr>
								</thead>
								<tbody>
								<?php $i = 0; foreach ($a_hosts as $hostent): ?>
								<tr>
									<td class="listlr" ondblclick="document.location='services_unbound_host_edit.php?id=<?=$i;?>';">
										<?=strtolower($hostent['host']);?>&nbsp;
									</td>
									<td class="listr" ondblclick="document.location='services_unbound_host_edit.php?id=<?=$i;?>';">
										<?=strtolower($hostent['domain']);?>&nbsp;
									</td>
									<td class="listr" ondblclick="document.location='services_unbound_host_edit.php?id=<?=$i;?>';">
										<?=strtoupper($hostent['rr']);?>&nbsp;
									</td>
									<td class="listr" ondblclick="document.location='services_unbound_host_edit.php?id=<?=$i;?>';">
                                        <?php
                                            /* Presentation of DNS value differs between chosen RR type. */
                                            switch ($hostent['rr']) {
                                                case 'A':
                                                case 'AAAA':
                                                    print $hostent['ip'];
                                                    break;
                                                case 'MX':
                                                    print $hostent['mxprio'] . " " . $hostent['mx'];
                                                    break;
                                                default:
                                                    print '&nbsp;';
                                                    break;
                                            }
                                        ?>
									</td>
									<td class="listbg" ondblclick="document.location='services_unbound_host_edit.php?id=<?=$i;?>';">
										<?=htmlspecialchars($hostent['descr']);?>&nbsp;
									</td>
									<td valign="middle" class="list nowrap">
										<table border="0" cellspacing="0" cellpadding="1" summary="icons">
											<tr>
												<td valign="middle"><a href="services_unbound_host_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a></td>
												<td><a href="services_unbound_overrides.php?type=host&amp;act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this host?");?>')" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a></td>
											</tr>
										</table>
								</tr>
								<?php $i++; endforeach; ?>
								<tr style="display:none"><td></td></tr>
								</tbody>
							</table>

					    </div>
					    </div>
				    </div>

			    </section>

			   <section class="col-xs-12">

				    <div class="content-box">

					    <header class="content-box-head container-fluid">
						<h3><?=gettext("Domain Overrides");?></h3>
					</header>

					<div class="content-box-main col-xs-12">
						<p><?=gettext("Entries in this area override an entire domain by specifying an".
	" authoritative DNS server to be queried for that domain.");?></p>
					</div>

					    <div class="content-box-main col-xs-12">
					    <div class="table-responsive">
						    <table class="table table-striped table-sort"><thead>
								<tr>
									<td width="35%" class="listhdrr"><?=gettext("Domain");?></td>
									<td width="20%" class="listhdrr"><?=gettext("IP");?></td>
									<td width="40%" class="listhdr"><?=gettext("Description");?></td>
									<td width="5%" class="list">
										<table border="0" cellspacing="0" cellpadding="1" summary="add">
											<tr>
												<td width="17" height="17"></td>
												<td><a href="services_unbound_domainoverride_edit.php" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a></td>
											</tr>
										</table>
									</td>
								</tr>
								</thead>

								<tbody>
								<?php $i = 0; foreach ($a_domainOverrides as $doment): ?>
								<tr>
									<td class="listlr" ondblclick="document.location='services_unbound_domainoverride_edit.php?id=<?=$i;?>';">
										<?=strtolower($doment['domain']);?>&nbsp;
									</td>
									<td class="listr" ondblclick="document.location='services_unbound_domainoverride_edit.php?id=<?=$i;?>';">
										<?=$doment['ip'];?>&nbsp;
									</td>
									<td class="listbg" ondblclick="document.location='services_unbound_domainoverride_edit.php?id=<?=$i;?>';">
										<?=htmlspecialchars($doment['descr']);?>&nbsp;
									</td>
									<td valign="middle" class="list nowrap">
										<table border="0" cellspacing="0" cellpadding="1" summary="icons">
											<tr>
												<td valign="middle"><a href="services_unbound_domainoverride_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a></td>
												<td valign="middle"><a href="services_unbound_overrides.php?act=del&amp;type=doverride&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this domain override?");?>')" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a></td>
											</tr>
										</table>
									</td>
								</tr>
								<?php $i++; endforeach; ?>
								<tr style="display:none"><td></td></tr>
								</tbody>
							</table>
					    </div>
					    </div>
				    </div>
			   </section>
			   </form>

			</div>
		</div>
	</section>


<script type="text/javascript">
//<![CDATA[
enable_change(false);
//]]>
</script>
<?php include("foot.inc"); ?>
