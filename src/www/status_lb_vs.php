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
require_once("services.inc");
require_once("vslb.inc");

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
if (!is_array($config['load_balancer']['virtual_server'])) {
	$config['load_balancer']['virtual_server'] = array();
}
$a_vs = &$config['load_balancer']['virtual_server'];
$a_pool = &$config['load_balancer']['lbpool'];
$rdr_a = get_lb_redirects();

$pgtitle = array(gettext('Services'), gettext('Load Balancer'), gettext('Virtual Server Status'));
$shortcut_section = 'relayd';

include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (is_subsystem_dirty('loadbalancer')): ?><br/>
				<?php print_info_box_apply(sprintf(gettext("The load balancer configuration has been changed%sYou must apply the changes in order for them to take effect."), "<br />"));?>
				<?php endif; ?>

			    <section class="col-xs-12">

					<div class="tab-content content-box col-xs-12">
				    <div class="container-fluid">
							<form action="status_lb_pool.php" method="post">
							<div class="table-responsive">


				              <table class="table table-striped table-sort __nomb">
				                <thead>
				                <tr>
				                  <td width="10%" class="listhdrr"><?=gettext("Name"); ?></td>
						  <td width="20%" class="listhdrr"><?=gettext("Address"); ?></td>
				                  <td width="10%" class="listhdrr"><?=gettext("Servers"); ?></td>
				                  <td width="25%" class="listhdrr"><?=gettext("Status"); ?></td>
				                  <td width="25%" class="listhdr"><?=gettext("Description"); ?></td>
								</tr>
				                </thead>
				                <tbody>
							  <?php $i = 0; foreach ($a_vs as $vsent): ?>
				                <tr>
				                  <td class="listlr">
								<?=$vsent['name'];?>
				                  </td>
				                  <td class="listr" align="center" >
				                                <?=$vsent['ipaddr']." : ".$vsent['port'];?>
				                                <br />
				                  </td>
				                  <td class="listr" align="center" >
							<table border="0" cellpadding="0" cellspacing="2" summary="servers">
				                        <?php
							foreach ($a_pool as $vipent) {
								if ($vipent['name'] == $vsent['poolname']) {
									foreach ((array) $vipent['servers'] as $server) {
										print "<tr><td> {$server} </td></tr>";
									}
								}
							}
							?>
							</table>
				                  </td>
				                  <?php
				                  switch (trim($rdr_a[$vsent['name']]['status'])) {
				                    case 'active':
				                      $bgcolor = "#90EE90";  // lightgreen
				                      $rdr_a[$vsent['name']]['status'] = "Active";
				                      break;
				                    case 'down':
				                      $bgcolor = "#F08080";  // lightcoral
				                      $rdr_a[$vsent['name']]['status'] = "Down";
				                      break;
				                    default:
				                      $bgcolor = "#D3D3D3";  // lightgray
				                      $rdr_a[$vsent['name']]['status'] = 'Unknown - relayd not running?';
				                  }
				                  ?>
				                  <td class="listr nowrap">
							<table border="0" cellpadding="3" cellspacing="2" summary="status">
								<tr><td bgcolor="<?=$bgcolor?>"><?=$rdr_a[$vsent['name']]['status']?> </td></tr>
							</table>
							<?php
							if (!empty($rdr_a[$vsent['name']]['total']))
								echo "Total Sessions: {$rdr_a[$vsent['name']]['total']}\n";
							if (!empty($rdr_a[$vsent['name']]['last']))
								echo "<br />Last: {$rdr_a[$vsent['name']]['last']}\n";
							if (!empty($rdr_a[$vsent['name']]['average']))
								echo "<br />Average: {$rdr_a[$vsent['name']]['average']}\n";
							?>
				                  </td>
				                  <td class="listbg" >
										<?=$vsent['descr'];?>
				                  </td>
				                </tr>
						<?php $i++; endforeach; ?>
				                </tbody>
				             </table>
							</div>
				    </div>
					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
