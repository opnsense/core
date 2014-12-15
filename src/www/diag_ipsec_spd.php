<?php
/* $Id$ */
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

/*
	pfSense_BUILDER_BINARIES:	/sbin/setkey
	pfSense_MODULE:	ipsec
*/

##|+PRIV
##|*IDENT=page-status-ipsec-spd
##|*NAME=Status: IPsec: SPD page
##|*DESCR=Allow access to the 'Status: IPsec: SPD' page.
##|*MATCH=diag_ipsec_spd.php*
##|-PRIV

require("guiconfig.inc");
require("ipsec.inc");

$pgtitle = array(gettext("Status"),gettext("IPsec"),gettext("SPD"));
$shortcut_section = "ipsec";
include("head.inc");

$spd = ipsec_dump_spd();
?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">	
			<div class="row">
				
				<?php if ($input_errors) print_input_errors($input_errors); ?>
				
			    <section class="col-xs-12">
    				
    				<? $active_tab = "/diag_ipsec_spd.php"; include('diag_ipsec_tabs.php'); ?>
					
					<div class="tab-content content-box col-xs-12">	    					
    				   	  
							
							<div class="table-responsive">
								
								<table class="table table-striped table-sort __nomb">
								<?php if (count($spd)): ?>
								<tr>
									<td class="listhdrr nowrap"><?= gettext("Source"); ?></td>
									<td class="listhdrr nowrap"><?= gettext("Destination"); ?></td>
									<td class="listhdrr nowrap"><?= gettext("Direction"); ?></td>
									<td class="listhdrr nowrap"><?= gettext("Protocol"); ?></td>
									<td class="listhdrr nowrap"><?= gettext("Tunnel endpoints"); ?></td>
									<td class="list nowrap"></td>
								</tr>
								<?php foreach ($spd as $sp): ?>
								<tr>
									<td class="listlr" valign="top"><?=htmlspecialchars($sp['srcid']);?></td>
									<td class="listr" valign="top"><?=htmlspecialchars($sp['dstid']);?></td>
									<td class="listr" valign="top">
										<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_<?=$sp['dir'];?>.gif" width="11" height="11" style="margin-top: 2px" alt="direction" />
									</td>
									<td class="listr" valign="top"><?=htmlspecialchars(strtoupper($sp['proto']));?></td>
									<td class="listr" valign="top"><?=htmlspecialchars($sp['src']);?> -> <?=htmlspecialchars($sp['dst']);?></td>
									<td class="list nowrap">
										<?php
											$args = "srcid=".rawurlencode($sp['srcid']);
											$args .= "&amp;dstid=".rawurlencode($sp['dstid']);
											$args .= "&amp;dir=".rawurlencode($sp['dir']);
										?>
									</td>
								</tr>
								<?php endforeach; ?>
							</table>
							<br />
							<table class="tabcont" border="0" cellspacing="0" cellpadding="6" summary="policies">
								<tr>
									<td width="16"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_in.gif" width="11" height="11" alt="in" /></td>
									<td><?= gettext("incoming (as seen by firewall)"); ?></td>
								</tr>
								<tr>
									<td colspan="5" height="4"></td>
								</tr>
								<tr>
									<td><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_out.gif" width="11" height="11" alt="out" /></td>
									<td><?= gettext("outgoing (as seen by firewall)"); ?></td>
								</tr>
								<?php else: ?>
								<tr>
									<td>
										<p><strong><?= gettext("No IPsec security policies."); ?></strong></p>
									</td>
								</tr>
								<?php endif; ?>
							</table>
						</div>
						
						 <div class="container-fluid">
						<p class="vexpl">
						<span class="text-danger"><strong><?= gettext("Note:"); ?><br /></strong></span>
						<?= gettext("You can configure your IPsec"); ?> <a href="vpn_ipsec.php"><?= gettext("here."); ?></a>
						</p>
    				    </div>
					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
