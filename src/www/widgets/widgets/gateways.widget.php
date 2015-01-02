<?php
/*
        Copyright (C) 2014 Deciso B.V.
        Copyright (C) 2008 Seth Mos

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
require_once("/usr/local/www/widgets/include/gateways.inc");

$a_gateways = return_gateways_array();
$gateways_status = array();
$gateways_status = return_gateways_status(true);

$counter = 1;

?>

<table class="table table-striped" width="100%" border="0" cellspacing="0" cellpadding="0" summary="gateway status">
	<tr>
		<td id="gatewayname" align="center"><b><?php echo gettext('Name')?></b></td>
		<td align="center"><b><?php echo gettext('RTT')?></b></td>
		<td align="center"><b><?php echo gettext('Loss')?></b></td>
		<td align="center"><b><?php echo gettext('Status')?></b></td>
	</tr>
	<?php foreach ($a_gateways as $gname => $gateway) { ?>
	<tr>
		<td class="h6" id="gateway<?php echo $counter; ?>" rowspan="2" align="center">
		<strong>
		<?php echo htmlspecialchars($gateway['name']); ?>
		</strong>
		<?php $counter++; ?>
		</td>
			<td colspan="3"  align="left">
				<div class="h6" id="gateway<?php echo $counter; ?>" style="display:inline">
					<?php
						$if_gw = '';
						if (is_ipaddr($gateway['gateway']))
							$if_gw = htmlspecialchars($gateway['gateway']);
						else {
							if($gateway['ipprotocol'] == "inet")
								$if_gw = htmlspecialchars(get_interface_gateway($gateway['friendlyiface']));
							if($gateway['ipprotocol'] == "inet6")
								$if_gw = htmlspecialchars(get_interface_gateway_v6($gateway['friendlyiface']));
						}
						echo ($if_gw == '' ? '~' : $if_gw);
						unset ($if_gw);
						$counter++;
					?>
				</div>
			</td>
	</tr>
	<tr>
			<td align="center" id="gateway<?php echo $counter; ?>">
			<?php
				if ($gateways_status[$gname])
					echo htmlspecialchars($gateways_status[$gname]['delay']);
				else
					echo gettext("Pending");
			?>
			<?php $counter++; ?>
			</td>
			<td align="center" id="gateway<?php echo $counter; ?>">
			<?php
				if ($gateways_status[$gname])
					echo htmlspecialchars($gateways_status[$gname]['loss']);
				else
					echo gettext("Pending");
			?>
			<?php $counter++; ?>
			</td>
			<?php
				if ($gateways_status[$gname]) {
					if (stristr($gateways_status[$gname]['status'], "force_down")) {
						$online = "Offline (forced)";
						$class="danger";
					} elseif (stristr($gateways_status[$gname]['status'], "down")) {
						$online = "Offline";
						$class="danger";
					} elseif (stristr($gateways_status[$gname]['status'], "loss")) {
						$online = "Packetloss";
						$class="warning";
					} elseif (stristr($gateways_status[$gname]['status'], "delay")) {
						$online = "Latency";
						$class="warning";
					} elseif ($gateways_status[$gname]['status'] == "none") {
						$online = "Online";
						$class="success";
					} elseif ($gateways_status[$gname]['status'] == "") {
						$online = "Pending";
						$class="info";
					}
				} else {
					$online = gettext("Unknown");
					$class="info";
				}
				echo "<td class=\"$class\" align=\"center\">$online</td>\n";
				$counter++;
			?>
	</tr>
	<?php } // foreach ?>
</table>
