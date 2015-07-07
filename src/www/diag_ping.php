<?php
/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2003-2005 Bob Zoller (bob@kludgebox.com) and Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array(gettext("Diagnostics"), gettext("Ping"));
require_once("guiconfig.inc");


define('MAX_COUNT', 10);
define('DEFAULT_COUNT', 3);

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);
	unset($do_ping);

	/* input validation */
	$reqdfields = explode(" ", "host count");
	$reqdfieldsn = array(gettext("Host"),gettext("Count"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_REQUEST['count'] < 1) || ($_REQUEST['count'] > MAX_COUNT)) {
		$input_errors[] = sprintf(gettext("Count must be between 1 and %s"), MAX_COUNT);
	}

	$host = trim($_REQUEST['host']);
	$ipproto = $_REQUEST['ipproto'];
	if (($ipproto == "ipv4") && is_ipaddrv6($host))
		$input_errors[] = gettext("When using IPv4, the target host must be an IPv4 address or hostname.");
	if (($ipproto == "ipv6") && is_ipaddrv4($host))
		$input_errors[] = gettext("When using IPv6, the target host must be an IPv6 address or hostname.");

	if (!$input_errors) {
		$do_ping = true;
		$sourceip = $_REQUEST['sourceip'];
		$count = $_POST['count'];
		if (preg_match('/[^0-9]/', $count) )
			$count = DEFAULT_COUNT;
	}
}
if (!isset($do_ping)) {
	$do_ping = false;
	$host = '';
	$count = DEFAULT_COUNT;
}

include("head.inc"); ?>
<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<section class="col-xs-12">

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Ping"); ?></h3>
				    </header>

				    <div class="content-box-main">
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
			<option value="ipv4" <?php if ($ipproto == "ipv4") echo "selected=\"selected\"" ?>>IPv4</option>
			<option value="ipv6" <?php if ($ipproto == "ipv6") echo "selected=\"selected\"" ?>>IPv6</option>
		</select></td>
						        </tr>
						        <tr>
						          <td><?=gettext("Source Address"); ?></td>
						          <td><select name="sourceip" class="form-control">
			<option value="">Default</option>
		<?php $sourceips = get_possible_traffic_source_addresses(true);
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
						          <td><?= gettext("Count"); ?></td>
						          <td><select name="count" class="form-control" id="count">
		<?php for ($i = 1; $i <= MAX_COUNT; $i++): ?>
			<option value="<?=$i;?>" <?php if ($i == $count) echo "selected=\"selected\""; ?>><?=$i;?></option>
		<?php endfor; ?>
		</select></td>
						        </tr>
						        <tr>
						          <td>&nbsp;</td>
						          <td><input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Ping"); ?>" /></td>
						        </tr>
					        </tbody>
					    </table>
					    </div>
					    </form>
				    </div>

				</div>
			</section>

			<?php if ($do_ping): ?>
			<section class="col-xs-12">
                <script type="text/javascript">
					//<![CDATA[
					window.onload=function(){
						document.getElementById("pingCaptured").wrap='off';
					}
					//]]>
				</script>

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Ping output"); ?></h3>
				    </header>

					<div class="content-box-main col-xs-12">
						<pre>

<?php

							$ifscope = '';
							$command = "/sbin/ping";
							if ($ipproto == "ipv6") {
								$command .= "6";
								$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ipv6($sourceip);
								if (is_linklocal($ifaddr))
									$ifscope = get_ll_scope($ifaddr);
							} else {
								$ifaddr = is_ipaddr($sourceip) ? $sourceip : get_interface_ip($sourceip);
							}
							if ($ifaddr && (is_ipaddr($host) || is_hostname($host))) {
								$srcip = "-S" . escapeshellarg($ifaddr);
								if (is_linklocal($host) && !strstr($host, "%") && !empty($ifscope))
									$host .= "%{$ifscope}";
							}

							$cmd = "{$command} {$srcip} -c" . escapeshellarg($count) . " " . escapeshellarg($host);
							//echo "Ping command: {$cmd}\n";
							system($cmd);

?>
						</pre>
					</div>
				</div>
			</section>
			<? endif; ?>
		</div>
	</div>
</section>

<?php include('foot.inc'); ?>
