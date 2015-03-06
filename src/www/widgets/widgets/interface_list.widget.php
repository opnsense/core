<?php

/*
        Copyright (C) 2014 Deciso B.V.
        Copyright (C) 2007 Scott Dale
        Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
        and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("widgets/include/interfaces.inc");

		$i = 0;
		$ifdescrs = get_configured_interface_with_descr();
?>

	         <table class="table table-striped">
				<?php
				foreach ($ifdescrs as $ifdescr => $ifname) {
					$ifinfo = get_interface_info($ifdescr);
					$iswireless = is_interface_wireless($ifdescr);
				?>
				<tr>
				<td class="vncellt" >
				<?php
				if($ifinfo['ppplink']) {
					?> <span alt="3g" class="glyphicon glyphicon-phone text-success"></span> <?php
				} else if($iswireless) {
					if($ifinfo['status'] == "associated") { ?>
						<span alt="wlan" class="glyphicon glyphicon-signal text-success"></span>
					<?php } else { ?>
						<span alt="wlan_d" class="glyphicon glyphicon-signal text-danger"></span>
					<?php } ?>
				<?php } else { ?>
						<?php if ($ifinfo['status'] == "up") { ?>
							<span alt="cablenic" id="<?php echo $ifname . 'icon';?>" class="glyphicon glyphicon-transfer text-success"></span>
						<?php } else { ?>
							<span alt="cablenic" id="<?php echo $ifname . 'icon';?>" class="glyphicon glyphicon-transfer text-danger"></span>
						<?php } ?>
				<?php } ?>&nbsp;
				<strong><u>
				<span onclick="location.href='/interfaces.php?if=<?=$ifdescr; ?>'" style="cursor:pointer">
				<?=htmlspecialchars($ifname);?></span></u></strong>
				<?php
					if ($ifinfo['dhcplink'])
						echo "&nbsp;(DHCP)";
				?>
				</td>
				<?php if($ifinfo['status'] == "up" || $ifinfo['status'] == "associated") { ?>
							<td class="listr" align="center">
								<span id="<?php echo $ifname;?>" class="glyphicon glyphicon-arrow-up text-success"></span>

							</td>
		                <?php } else if ($ifinfo['status'] == "no carrier") { ?>
							<td class="listr" align="center">
								<span id="<?php echo $ifname;?>" class="glyphicon glyphicon-arrow-down text-danger"></span>

							</td>
				<?php }  else if ($ifinfo['status'] == "down") { ?>
							<td class="listr" align="center">
								<span id="<?php echo $ifname;?>" class="glyphicon glyphicon-arrow-remove text-danger"></span>
							</td>
		                <?php } else { ?><?=htmlspecialchars($ifinfo['status']); }?>
							<td class="listr">
								<div id="<?php echo $ifname;?>" style="display:inline"><?=htmlspecialchars($ifinfo['media']);?></div>
							</td>
							<td class="vncellt">
								<?php if($ifinfo['ipaddr'] != "") { ?>
									<div id="<?php echo $ifname;?>-ip" style="display:inline"><?=htmlspecialchars($ifinfo['ipaddr']);?> </div>
									<br />
								<?php }
								if ($ifinfo['ipaddrv6'] != "") { ?>
									<div id="<?php echo $ifname;?>-ipv6" style="display:inline"><?=htmlspecialchars($ifinfo['ipaddrv6']);?> </div>
								<?php } ?>
							</td>
						</tr>
				<?php	}//end for each ?>
			</table>
