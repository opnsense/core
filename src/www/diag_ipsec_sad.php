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
##|*IDENT=page-status-ipsec-sad
##|*NAME=Status: IPsec: SAD page
##|*DESCR=Allow access to the 'Status: IPsec: SAD' page.
##|*MATCH=diag_ipsec_sad.php*
##|-PRIV

require("guiconfig.inc");
require("ipsec.inc");

$pgtitle = array(gettext("Status"),gettext("IPsec"),gettext("SAD"));
$shortcut_section = "ipsec";
include("head.inc");

$sad = ipsec_dump_sad();

?>

<body>

<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">	
			<div class="row">
				
				<?php if ($input_errors) print_input_errors($input_errors); ?>
				
			    <section class="col-xs-12">
    				
    				<? $active_tab = "/diag_ipsec_sad.php"; include('diag_ipsec_tabs.php'); ?>
					
					<div class="tab-content content-box col-xs-12">	 
							
							<div class="table-responsive">
								
								<table class="table table-striped table-sort">
									<?php if (count($sad)): ?>
									<tr>
										<td class="listhdrr nowrap"><?=gettext("Source");?></td>
										<td class="listhdrr nowrap"><?=gettext("Destination");?></td>
										<td class="listhdrr nowrap"><?=gettext("Protocol");?></td>
										<td class="listhdrr nowrap"><?=gettext("SPI");?></td>
										<td class="listhdrr nowrap"><?=gettext("Enc. alg.");?></td>
										<td class="listhdr nowrap"><?=gettext("Auth. alg.");?></td>
										<td class="listhdr nowrap"><?=gettext("Data");?></td>
										<td class="list nowrap"></td>
									</tr>
									<?php foreach ($sad as $sa): ?>
									<tr>
										<td class="listlr"><?=htmlspecialchars($sa['src']);?></td>
										<td class="listr"><?=htmlspecialchars($sa['dst']);?></td>
										<td class="listr"><?=htmlspecialchars(strtoupper($sa['proto']));?></td>
										<td class="listr"><?=htmlspecialchars($sa['spi']);?></td>
										<td class="listr"><?=htmlspecialchars($sa['ealgo']);?></td>
										<td class="listr"><?=htmlspecialchars($sa['aalgo']);?></td>
										<td class="listr"><?=htmlspecialchars($sa['data']);?></td>
										<td class="list nowrap">
										</td>
									</tr>
									<?php endforeach; ?>
									<?php else: ?>
									<tr>
										<td>
											<p><strong><?=gettext("No IPsec security associations.");?></strong></p>
										</td>
									</tr>
									<?php endif; ?>
								</table>
							
                            <div class="container-fluid">
										
							<p class="vexpl">
							<span class="text-danger"><strong><?=gettext("Note:");?><br /></strong></span>
							<?=gettext("You can configure your IPsec");?> <a href="vpn_ipsec.php"><?=gettext("here.");?></a>
							</p>
							
                            </div>
							
    				    </div>
					</div>
			    </section>
			</div>
		</div>
	</section>


<?php include("foot.inc"); ?>