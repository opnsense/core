<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004-2009 Scott Ullrich
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
require_once("vpn.inc");
require_once("services.inc");
require_once("interfaces.inc");

$shortcut_section = 'ipsec';

include("head.inc");

$spd = ipsec_dump_spd();
legacy_html_escape_form_data($spd);

?>
<body>

<?php include("fbegin.inc"); ?>
	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">
			  <section class="col-xs-12">
					<div class="tab-content content-box col-xs-12">
						<div class="table-responsive">
							<table class="table table-striped">
								<?php if (count($spd)): ?>
								<tr>
									<td><?= gettext("Source"); ?></td>
									<td><?= gettext("Destination"); ?></td>
									<td><?= gettext("Direction"); ?></td>
									<td><?= gettext("Protocol"); ?></td>
									<td><?= gettext("Tunnel endpoints"); ?></td>
								</tr>
								<?php foreach ($spd as $sp): ?>
								<tr>
									<td class="listlr" valign="top"><?=$sp['srcid'];?></td>
									<td class="listr" valign="top"><?=$sp['dstid'];?></td>
									<td class="listr" valign="top">
										<span class="glyphicon glyphicon-arrow-<?= $sp['dir'] == "in" ? "right" : "left";?>" aria-hidden="true"></span>
									</td>
									<td class="listr" valign="top"><?=strtoupper($sp['proto']);?></td>
									<td class="listr" valign="top"><?=$sp['src'];?> -> <?=$sp['dst'];?></td>
								</tr>
								<?php endforeach; ?>
								<tr>
									<td colspan="2"></td>
									<td colspan="3">
										<span class="glyphicon glyphicon-arrow-right" aria-hidden="true" alt="in"></span>
										<?= gettext("incoming (as seen by firewall)"); ?> <br/>
										<span class="glyphicon glyphicon-arrow-left" aria-hidden="true" alt="out"></span>
										<?= gettext("outgoing (as seen by firewall)"); ?>
									</td>
								</tr>
								<?php else: ?>
								<tr>
									<td  colspan="5">
										<p><strong><?= gettext("No IPsec security policies."); ?></strong></p>
									</td>
								</tr>
								<?php endif; ?>
							</table>
					</div>
					</div>
				</section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
