<?php
/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2005 Paul Taylor (paultaylor@winndixie.com) and Manuel Kasper <mk@neon1.net>.
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
require_once("system.inc");
require_once("interfaces.inc");

$pgtitle = array(gettext("Diagnostics"),gettext("Traceroute"));
include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
<?php

define('MAX_TTL', 64);
define('DEFAULT_TTL', 18);

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);
	unset($do_traceroute);

	/* input validation */
	$reqdfields = explode(" ", "host ttl");
	$reqdfieldsn = array(gettext("Host"),gettext("ttl"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_REQUEST['ttl'] < 1) || ($_REQUEST['ttl'] > MAX_TTL)) {
		$input_errors[] = sprintf(gettext("Maximum number of hops must be between 1 and %s"), MAX_TTL);
	}
	$host = trim($_REQUEST['host']);
	$ipproto = $_REQUEST['ipproto'];
	if (($ipproto == "ipv4") && is_ipaddrv6($host))
		$input_errors[] = gettext("When using IPv4, the target host must be an IPv4 address or hostname.");
	if (($ipproto == "ipv6") && is_ipaddrv4($host))
		$input_errors[] = gettext("When using IPv6, the target host must be an IPv6 address or hostname.");

	if (!$input_errors) {
		$sourceip = $_REQUEST['sourceip'];
		$do_traceroute = true;
		$ttl = $_REQUEST['ttl'];
		$resolve = $_REQUEST['resolve'];
	}
} else
	$resolve = true;

if (!isset($do_traceroute)) {
	$do_traceroute = false;
	$host = '';
	$ttl = DEFAULT_TTL;
}

?>





<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<section class="col-xs-12">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Traceroute");?></h3>
				    </header>

				    <div class="content-box-main ">
					    <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post" name="iform" id="iform">
					    <div class="table-responsive">
				        <table class="table table-striped __nomb">
					        <tbody>
						        <tr>
						          <td><?=gettext("Host"); ?></td>
						          <td><input name="host" type="text" class="form-control" id="host" value="<?=htmlspecialchars($host);?>" /></td>
						        </tr>
						        <tr>
						          <td><?=gettext("IP Protocol"); ?></td>
						          <td><select name="ipproto" class="form-control">
                      <option value="ipv4" <?php if ($ipproto == "ipv4") echo "selected=\"selected\"" ?>><?= gettext('IPv4') ?></option>
                      <option value="ipv6" <?php if ($ipproto == "ipv6") echo "selected=\"selected\"" ?>><?= gettext('IPv6') ?></option>
		</select></td>
						        </tr>
						        <tr>
						          <td><?=gettext("Source Address"); ?></td>
						          <td><select name="sourceip" class="form-control">
                      <option value=""><?= gettext('Any') ?></option>
										<?php   $sourceips = get_possible_traffic_source_addresses(true);
											foreach ($sourceips as $sip):
												$selected = "";
												if (!link_interface_to_bridge($sip['value']) && ($sip['value'] == $sourceip))
													$selected = "selected=\"selected\"";
										?>
											<option value="<?=$sip['value'];?>" <?=$selected;?>>
												<?=htmlspecialchars($sip['name']);?>
											</option>
											<?php endforeach; ?>
										</select></td>
						        </tr>
						        <tr>
						          <td><?=gettext("Maximum number of hops");?></td>
						          <td><select name="ttl" class="form-control" id="ttl">
										<?php for ($i = 1; $i <= MAX_TTL; $i++): ?>
											<option value="<?=$i;?>" <?php if ($i == $ttl) echo "selected=\"selected\""; ?>><?=$i;?></option>
										<?php endfor; ?>
										</select></td>
						        </tr>
						        <tr>
						          <td><?=gettext("Reverse Address Lookup");?></td>
						          <td><input name="resolve" type="checkbox"<?php echo (!isset($resolve) ? "" : " checked=\"checked\""); ?> /></td>
						        </tr>
						        <tr>
						          <td><?=gettext("Use ICMP");?></td>
						          <td><input name="useicmp" type="checkbox"<?php if($_REQUEST['useicmp']) echo " checked=\"checked\""; ?> /></td>
						        </tr>
						        <tr>
						          <td>&nbsp;</td>
						          <td><input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Traceroute"); ?>" /></td>
						        </tr>
					        </tbody>
					    </table>

					    <div class="container-fluid">
					    <p><span class="text-danger"><b><?=gettext("Note: ");?></b></span>
							<?=gettext("Traceroute may take a while to complete. You may hit the Stop button on your browser at any time to see the progress of failed traceroutes.");?>
							<br /><br />
							<?=gettext("Using a source interface/IP address that does not match selected type (IPv4, IPv6) will result in an error or empty output.");?>
					    </p>
					    </div>

					    </div>
					    </form>
				    </div>

				</div>
			</section>

			<?php if ($do_traceroute): ob_end_flush(); ?>
			<section class="col-xs-12">
                <script type="text/javascript">
					//<![CDATA[
					window.onload=function(){
						document.getElementById("tracerouteCaptured").wrap='off';
					}
					//]]>
				</script>

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Traceroute output"); ?></h3>
				    </header>

					<div class="content-box-main col-xs-12">
						<pre>

<?php

							$useicmp = isset($_REQUEST['useicmp']) ? "-I" : "";
							$n = isset($resolve) ? "" : "-n";

							$command = "/usr/sbin/traceroute";
							if ($ipproto == "ipv6") {
								$command .= "6";
								$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ipv6($sourceip);
							} else {
								$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ip($sourceip);
							}

							if ($ifaddr && (is_ipaddr($host) || is_hostname($host)))
								$srcip = "-s " . escapeshellarg($ifaddr);

							$cmd = "{$command} {$n} {$srcip} -w 2 {$useicmp} -m " . escapeshellarg($ttl) . " " . escapeshellarg($host);

							//echo "Traceroute command: {$cmd}\n";
							system($cmd);

?>
						</pre>
					</div>
				</div>
			</section>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
