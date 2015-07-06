<?php
/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2014 Ermal LUÇi
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
require_once("ipsec.inc");

$pgtitle = array(gettext("Status"),gettext("IPsec"),gettext("Leases"));
$shortcut_section = "ipsec";
include("head.inc");

$mobile = ipsec_dump_mobile();

?>
<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

				<? $active_tab = "/diag_ipsec_leases.php"; include('diag_ipsec_tabs.inc'); ?>

					<div class="tab-content content-box col-xs-12">
				    <div class="container-fluid">

							<div class="table-responsive">


							<?php if (isset($mobile['pool']) && is_array($mobile['pool'])): ?>
								<?php foreach($mobile['pool'] as $pool): ?>
									<table class="table table-striped table-sort">
										<tr>
											<td colspan="4" valign="top" class="listtopic">
											<?php
												echo gettext("Pool: ") . $pool['name'];
												echo ' ' . gettext("usage: ") . $pool['usage'];
												echo ' ' . gettext("online: ") . $pool['online'];
											?>
											</td>
										</tr>
										<?php if (is_array($pool['lease']) && count($pool['lease']) > 0): ?>
										<tr>
											<td class="listhdrr nowrap"><?=gettext("ID");?></td>
											<td class="listhdrr nowrap"><?=gettext("Host");?></td>
											<td class="listhdrr nowrap"><?=gettext("Status");?></td>
											<td class="list nowrap"></td>
										</tr>
										<?php foreach ($pool['lease'] as $lease): ?>
										<tr>
											<td class="listlr"><?=htmlspecialchars($lease['id']);?></td>
											<td class="listr"><?=htmlspecialchars($lease['host']);?></td>
											<td class="listr"><?=htmlspecialchars($lease['status']);?></td>
											<td class="list nowrap">
											</td>
										</tr>
										<?php endforeach;
										else: ?>
										<tr>
											<td>
												<p><strong><?=gettext("No leases from this pool yet.");?></strong></p>
											</td>
										</tr>
										<?php endif; ?>
									</table>
								<?php endforeach; ?>
							<?php else: ?>
								<p><strong><?=gettext("No IPsec pools.");?></strong></p>
							<?php endif; ?>
						</div>

						<p class="vexpl">
						<span class="text-danger"><strong><?=gettext("Note:");?><br /></strong></span>
						<?=gettext("You can configure your IPsec");?> <a href="vpn_ipsec.php"><?=gettext("here.");?></a>
						</p>
				    </div>
					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
