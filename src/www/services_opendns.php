<?php

/*
	Copyright (c) 2015 Franco Fichtner <franco@opnsense.org>
	Copyright (c) 2008 Tellnet AG
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

require_once 'guiconfig.inc';

if (!is_array($config['opendns'])) {
	$config['opendns'] = array();
}

$pconfig['enable'] = isset($config['opendns']['enable']);
$pconfig['username'] = $config['opendns']['username'];
$pconfig['password'] = $config['opendns']['password'];
$pconfig['host'] = $config['opendns']['host'];

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = array();
	$reqdfieldsn = array();
	if ($_POST['enable']) {
		$reqdfields = array_merge($reqdfields, explode(" ", "host username password"));
		$reqdfieldsn = array_merge($reqdfieldsn, explode(",", "Network,Username,Password"));
	}
	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (($_POST['host'] && !is_domain($_POST['host']))) {
		$input_errors[] = 'The host name contains invalid characters.';
	}
	if (($_POST['username'] && empty($_POST['username']))) {
		$input_errors[] = 'The username cannot be empty.';
	}

	if ($_POST['test']) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, sprintf( 'https://updates.opendns.com/nic/update?hostname=%s', $pconfig['host']));
		curl_setopt($ch, CURLOPT_USERPWD, sprintf('%s:%s', $pconfig['username'], $pconfig['password']));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);
		$test_results = explode("\r\n", $output);
	} elseif (!$input_errors) {
		$refresh = $pconfig['enable'] != $config['opendns']['enable'];
		$config['opendns']['enable'] = $_POST['enable'] ? true : false;
		$config['opendns']['username'] = $_POST['username'];
		$config['opendns']['password'] = $_POST['password'];
		$config['opendns']['host'] = $_POST['host'];
		if ($refresh) {
			if ($config['opendns']['enable']) {
				unset($config['system']['dnsserver']);
				$config['system']['dnsserver'][] = '208.67.222.222';
				$config['system']['dnsserver'][] = '208.67.220.220';
				$config['system']['dnsallowoverride'] = false;
			} else {
				unset($config['system']['dnsserver']);
				$config['system']['dnsserver'][] = '';
				$config['system']['dnsallowoverride'] = true;
			}
		}
		write_config('OpenDNS filter configuration change');
		if ($refresh) {
			$retval = system_resolvconf_generate();
			$savemsg = get_std_save_message($retval);
		}
	}
}

$pgtitle = array('Services', 'DNS Filter');

include 'head.inc';

?>

<body>

<?php include 'fbegin.inc'; ?>

<section class="page-content-main">
	<div class="container-fluid">

	<div class="row">
		<?php
			if ($input_errors) {
				print_input_errors($input_errors);
			}
			if ($savemsg) {
				print_info_box($savemsg);
			}
		?>
		<section class="col-xs-12">

		<div class="content-box table-responsive">

			<form action="services_opendns.php" method="post">
				<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area" class="table table-striped">
					<thead>
						<tr>
							<th colspan="2" valign="top" class="listtopic"><?=gettext('OpenDNS Setup'); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td width="22%" valign="top" class="vncellreq"><?=gettext('Enable'); ?></td>
							<td width="78%" class="vtable">
								<input name="enable" type="checkbox" id="enable" value="yes" <?php if ($pconfig['enable']) { echo 'checked="checked"'; } ?>" />
								<strong><?=gettext('Filter DNS requests using OpenDNS'); ?></strong>
								<br />
								<br />
								<span class="vexpl">
									<?=gettext(sprintf(
										'Enabling the OpenDNS service will overwrite DNS servers configured ' .
										'via the General Setup page as well as ignore any DNS servers learned ' .
										'by DHCP/PPP on WAN and use the DNS servers from %s instead.',
										'<a href="http://www.opendns.com" target="_blank">OpenDNS.com</a>'
									)); ?>
								</span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext('Username'); ?></td>
							<td width="78%" class="vtable">
								<input name="username" type="text" id="username" size="20" value="<?=htmlspecialchars($pconfig['username']);?>" />
								<br />
								<span class="vexpl">
									<?=gettext(
										'Signon Username to log into your OpenDNS dashboard. ' .
										'It is used to automatically update the IP address of ' .
										'the registered network.'
									); ?>
								</span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext('Password'); ?></td>
							<td width="78%" class="vtable">
								<input name="password" type="password" id="password" size="20" value="<?=htmlspecialchars($pconfig['password']);?>" />
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncell"><?=gettext('Network'); ?></td>
							<td width="78%" class="vtable">
							<input name="host" type="text" id="host" size="30" value="<?=htmlspecialchars($pconfig['host']);?>" />
								<br />
								<span class="vexpl">
									<?=gettext(sprintf(
										'Enter the network name configured on the %s under ' .
										'\'Manage your networks\'. Used to update the node\'s ' .
										'IP address whenever the WAN interface changes its IP address.',
										'<a href="https://www.opendns.com/dashboard/networks/" target="_blank">' .
										gettext('Networks Dashboard of OpenDNS') .'</a>'
									)); ?>
								</span>
							</td>
						</tr>
						<?php if (is_array($test_results)): ?>
						<tr>
							<td width="22%" valign="top"><?=gettext('Test result');?></td>
							<td width="78%">
							<?php
								foreach ($test_results as $result) {
									if (!strlen($result)) {
										continue;
									}

									echo sprintf(
										'<span class="glyphicon glyphicon-%s"></span> %s<br />',
										strpos($result, 'good') === 0 ? 'ok text-success' : 'remove text-danger',
										$result
									);
								}
							?>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<td width="22%" valign="top">&nbsp;</td>
							<td width="78%">
								<input name="submit" type="submit" class="btn btn-primary" value="<?=gettext('Save');?>" />
								<input name="test" type="submit" class="btn btn-primary" value="<?=gettext('Test/Update');?>" />
							</td>
						</tr>
					</tbody>
				</table>
			</form>
		</div>
		</section>
	</div>
	</div>
</section>

<?php include 'foot.inc'; ?>
