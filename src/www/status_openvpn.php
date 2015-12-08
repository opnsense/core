<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2010 Jim Pingle
	Copyright (C) 2008 Shrew Soft Inc.
	Copyright (C) 2005 Scott Ullrich, Colin Smith
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
require_once("openvpn.inc");
require_once("services.inc");
require_once("interfaces.inc");

function kill_client($port, $remipp) {
	global $g;

	$tcpsrv = "unix:///var/etc/openvpn/{$port}.sock";
	$errval;
	$errstr;

	/* open a tcp connection to the management port of each server */
	$fp = @stream_socket_client($tcpsrv, $errval, $errstr, 1);
	$killed = -1;
	if ($fp) {
		stream_set_timeout($fp, 1);
		fputs($fp, "kill {$remipp}\n");
		while (!feof($fp)) {
			$line = fgets($fp, 1024);

			$info = stream_get_meta_data($fp);
			if ($info['timed_out'])
				break;

			/* parse header list line */
			if (strpos($line, "INFO:") !== false)
				continue;
			if (strpos($line, "SUCCESS") !== false) {
				$killed = 0;
			}
			break;
		}
		fclose($fp);
	}
	return $killed;
}

$shortcut_section = 'openvpn';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$vpnid = 0;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['action']) && $_POST['action'] == 'kill') {
			$port  = escapeshellarg($_POST['port']);
			$remipp  = escapeshellarg($_POST['remipp']);
			if (!empty($port) and !empty($remipp)) {
					$retval = kill_client($port, $remipp);
					echo htmlentities("|{$port}|{$remipp}|{$retval}|");
			} else {
					echo gettext("invalid input");
			}
			exit;
	}
}

$servers = openvpn_get_active_servers();
legacy_html_escape_form_data($servers);
$sk_servers = openvpn_get_active_servers("p2p");
legacy_html_escape_form_data($sk_servers);
$clients = openvpn_get_active_clients();
legacy_html_escape_form_data($clients);

include("head.inc"); ?>


<body>
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
	// link kill buttons
	$(".act_kill_client").click(function(){
		var port = $(this).attr("data-client-port");
		var ip = $(this).attr("data-client-ip");
		$.post(window.location, {action: 'kill', port:port,remipp:ip}, function(data) {
					location.reload();
		});
	});
	// link show/hide routes
	$(".act_show_routes").click(function(){
		$("*[for='" + $(this).attr('id') + "']").toggleClass("hidden show");
	});

	// minimize all buttons, some pf the buttons come from the shared service
	// functions, which outputs large buttons.
	$(".btn").each(function(){
		$(this).addClass("btn-xs");
	});

});
//]]>
</script>
<section class="page-content-main">
	<div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
	            <header class="content-box-head container-fluid"> <h3><?=gettext("OpenVPN Status");?></h3>
					</header>
						<div class="content-box-main col-xs-12">
							<form action="status_openvpn.php" method="get" name="iform">
										<div class="table-responsive">
										<table class="table table-striped">
											<?php $i = 0; ?>
											<?php foreach ($servers as $server): ?>
											<tr>
												<td colspan="8" class="listtopic">
													<b><?=$server['name'];?> <?=gettext("Client connections"); ?></b>
												</td>
											</tr>
											<tr>
												<td><?=gettext("Common Name"); ?></td>
												<td><?=gettext("Real Address"); ?></td>
												<td><?=gettext("Virtual Address"); ?></td>
												<td><?=gettext("Connected Since"); ?></td>
												<td><?=gettext("Bytes Sent"); ?></td>
												<td><?=gettext("Bytes Received"); ?></td>
												<td></td>
												<td></td>
											</tr>
											<?php foreach ($server['conns'] as $conn): ?>
											<tr id="<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>">
												<td><?=$conn['common_name'];?></td>
												<td><?=$conn['remote_host'];?></td>
												<td><?=$conn['virtual_addr'];?></td>
												<td><?=$conn['connect_time'];?></td>
												<td><?=format_bytes($conn['bytes_sent']);?></td>
												<td><?=format_bytes($conn['bytes_recv']);?></td>
												<td></td>
												<td>
													<a	data-client-port="<?=$server['mgmt'];?>"
															data-client-ip="<?=$conn['remote_host'];?>"
															title="<?=gettext("Kill client connection from"). " ".  $conn['remote_host'] ; ?>"
															class="act_kill_client btn btn-default">
															<span class="glyphicon glyphicon-remove"></span>
													</a>
												</td>
											</tr>
											<?php endforeach; ?>
											<tr>
												<td colspan="2">
													<?php $ssvc = find_service_by_openvpn_vpnid($server['vpnid']); ?>
													<?= get_service_status_icon($ssvc, true, true); ?>
													<?= get_service_control_links($ssvc, true); ?>
												</td>
												<td colspan="6">&nbsp;</td>
											</tr>
											<?php if (isset($server['routes']) && count($server['routes'])): ?>
											<tr>
												<td colspan="8">
														<button class="btn btn-default act_show_routes" type="button" id="showroutes_<?=$i?>"><i class="fa fa-info"></i>
															<?php echo gettext("Show/Hide Routing Table"); ?>
														</button>
														<div  class="hidden"  for="showroutes_<?=$i?>">
															<small>
																<?=$server['name'];?> <?=gettext("Routing Table"); ?>
															</small>
															<table class="table table-striped table-bordered">
																<thead>
																	<tr>
																		<th><?=gettext("Common Name"); ?></th>
																		<th><?=gettext("Real Address"); ?></th>
																		<th><?=gettext("Target Network"); ?></th>
																		<th><?=gettext("Last Used"); ?></th>
																	</tr>
																</thead>
																<tbody>
																	<?php foreach ($server['routes'] as $conn): ?>
																	<tr id="<?php echo "r:{$server['mgmt']}:{$conn['remote_host']}"; ?>">
																		<td><?=$conn['common_name'];?></td>
																		<td><?=$conn['remote_host'];?></td>
																		<td><?=$conn['virtual_addr'];?></td>
																		<td><?=$conn['last_time'];?></td>
																	</tr>
																	<?php endforeach; ?>
																	<tfoot>
																	<tr>
																		<td colspan="6"><?= gettext("An IP address followed by C indicates a host currently connected through the VPN.") ?></td>
																	</tr>
																	</tfoot>
																</tbody>
															</table>
														</div>
												</td>
											</tr>
										<?php endif; ?>
										<?php $i++; ?>
										<?php endforeach; ?>

										<?php if (!empty($sk_servers)) { ?>
											<tr>
												<td colspan="8" class="listtopic">
													<b><?=gettext("Peer to Peer Server Instance Statistics"); ?></b>
												</td>
											</tr>
										<tr>
											<td><?=gettext("Name"); ?></td>
											<td><?=gettext("Remote Host"); ?></td>
											<td><?=gettext("Virtual Addr"); ?></td>
											<td><?=gettext("Connected Since"); ?></td>
											<td><?=gettext("Bytes Sent"); ?></td>
											<td><?=gettext("Bytes Received"); ?></td>
											<td><?=gettext("Status"); ?></td>
											<td></td>
										</tr>
										<?php foreach ($sk_servers as $sk_server): ?>
													<tr id="<?php echo "r:{$sk_server['port']}:{$sk_server['vpnid']}"; ?>">
														<td><?=$sk_server['name'];?></td>
														<td><?=$sk_server['remote_host'];?></td>
														<td><?=$sk_server['virtual_addr'];?></td>
														<td><?=$sk_server['connect_time'];?></td>
														<td><?=format_bytes($sk_server['bytes_sent']);?></td>
														<td><?=format_bytes($sk_server['bytes_recv']);?></td>
														<td><?=$sk_server['status'];?></td>
														<td>
															<div>
																<?php $ssvc = find_service_by_openvpn_vpnid($sk_server['vpnid']); ?>
																<?= get_service_status_icon($ssvc, false, true); ?>
																<?= get_service_control_links($ssvc, true); ?>
															</div>
														</td>
													</tr>
										<?php endforeach; ?>
										<?php
										} ?>

										<?php if (!empty($clients)) { ?>
										<tr>
											<tr>
												<td colspan="8" class="listtopic">
													<b><?=gettext("Client Instance Statistics"); ?><b>
												</td>
											</tr>
											<tr>
												<td><?=gettext("Name"); ?></td>
												<td><?=gettext("Connected Since"); ?></td>
												<td><?=gettext("Virtual Addr"); ?></td>
												<td><?=gettext("Remote Host"); ?></td>
												<td><?=gettext("Bytes Sent"); ?></td>
												<td><?=gettext("Bytes Rcvd"); ?></td>
												<td><?=gettext("Status"); ?></td>
												<td></td>
											</tr>
											<?php foreach ($clients as $client): ?>
											<tr id="<?php echo "r:{$client['port']}:{$client['vpnid']}"; ?>">
												<td><?=$client['name'];?></td>
												<td><?=$client['connect_time'];?></td>
												<td><?=$client['virtual_addr'];?></td>
												<td><?=$client['remote_host'];?></td>
												<td><?=format_bytes($client['bytes_sent']);?></td>
												<td><?=format_bytes($client['bytes_recv']);?></td>
												<td><?=$client['status'];?></td>
												<td>
													<div>
														<?php $ssvc = find_service_by_openvpn_vpnid($client['vpnid']); ?>
														<?= get_service_status_icon($ssvc, false, true); ?>
														<?= get_service_control_links($ssvc, true); ?>
													</div>
												</td>
											</tr>
											<?php endforeach; ?>
										</table>
										</div>
										<?php
										}
										if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))) {
											echo gettext("No OpenVPN instance defined");
										}
										?>
					    </form>
				    </div>
          </section>
	</div>
		</div>
</section>


<?php include("foot.inc"); ?>
