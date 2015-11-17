<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2012 Darren Embry <dse@webonastick.com>.
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
require_once("interfaces.inc");

$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/system_hasync.php');

if (!isset($config['hasync']) || !is_array($config['hasync'])) {
    $config['hasync'] = array();
}

$a_hasync = &$config['hasync'];

$checkbox_names = array(
            'pfsyncenabled',
            'synchronizeusers',
            'synchronizeauthservers',
            'synchronizecerts',
            'synchronizerules',
            'synchronizeschedules',
            'synchronizealiases',
            'synchronizenat',
            'synchronizeipsec',
            'synchronizeopenvpn',
            'synchronizedhcpd',
            'synchronizewol',
            'synchronizestaticroutes',
            'synchronizelb',
            'synchronizevirtualip',
            'synchronizednsforwarder',
);

if ($_POST) {
    $pconfig = $_POST;
    foreach ($checkbox_names as $name) {
	if (isset($pconfig[$name])) {
		$a_hasync[$name] = $pconfig[$name];
	} else {
		$a_hasync[$name] = false;
	}
    }
    $a_hasync['pfsyncpeerip']    = $pconfig['pfsyncpeerip'];
    $a_hasync['pfsyncinterface'] = $pconfig['pfsyncinterface'];
    $a_hasync['synchronizetoip'] = $pconfig['synchronizetoip'];
    $a_hasync['username']        = $pconfig['username'];
    $a_hasync['password']        = $pconfig['password'];
    write_config("Updated High Availability configuration");
    interfaces_carp_setup();
    header("Location: system_hasync.php");
    exit();
}

foreach ($checkbox_names as $name) {
    if (isset($a_hasync[$name])) {
        $pconfig[$name] = $a_hasync[$name];
    } else {
        $pconfig[$name] = null;
    }
}
foreach (array('pfsyncpeerip','pfsyncinterface','synchronizetoip','username','password') as $tag) {
	if (isset($a_hasync[$tag])) {
		$pconfig[$tag] = $a_hasync[$tag];
	} else {
		$pconfig[$tag] = null;
	}
}

$ifaces = get_configured_interface_with_descr();
$ifaces["lo0"] = "loopback";

$pgtitle = array(gettext('System'), gettext('High Availability'), gettext('Synchronization'));
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>

<!-- row -->

<section class="page-content-main">
	<div class="container-fluid">

        <div class="row">

            <section class="col-xs-12">
                <div class="content-box">

                    <div class="table-responsive">

                        <form action="system_hasync.php" method="post" name="iform" id="iform">

				<table class="table table-primary table-striped" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
				<thead>
					<tr>
						<th colspan="2" class="listtopic"><?=gettext('State Synchronization Settings (pfsync)') ?></th>
					</tr>
				</thead>
				<tbody>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize States') ?></td>
						<td class="vtable">
							<input id='pfsyncenabled' type='checkbox' name='pfsyncenabled' value='on' <?php if ($pconfig['pfsyncenabled'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?= sprintf(gettext('pfsync transfers state insertion, update, and deletion messages between firewalls.%s' .
                'Each firewall sends these messages out via multicast on a specified interface, using the PFSYNC protocol (%sIP Protocol 240%s).%s' .
							  'It also listens on that interface for similar messages from other firewalls, and imports them into the local state table.%s' .
                'This setting should be enabled on all members of a failover group.'), '<br/>','<a href="http://www.openbsd.org/faq/pf/carp.html" target="_blank">','</a>','<br/>','<br/>') ?>
							<div class="well well-sm" ><b><?=gettext('Clicking save will force a configuration sync if it is enabled! (see Configuration Synchronization Settings below)') ?></b></div>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Interface') ?></td>
						<td class="vtable">
							<select id='pfsyncinterface' name="pfsyncinterface" class="selectpicker" data-style="btn-default" data-live-search="true" data-width="auto">
							<?php foreach ($ifaces as $ifname => $iface) {
?>
								<?php $selected = ($pconfig['pfsyncinterface'] === $ifname) ? 'selected="selected"' : ''; ?>
								<option value="<?= htmlentities($ifname);
?>" <?= $selected ?>><?= htmlentities($iface); ?></option>
							<?php
} ?>
							</select>
							<?=gettext('If Synchronize States is enabled, it will utilize this interface for communication.') ?><br/><br/>
							<div class="well">
								<lu>
								<li><?=gettext('We recommend setting this to a interface other than LAN!  A dedicated interface works the best.') ?></li>
								<li><?=gettext('You must define a IP on each machine participating in this failover group.') ?></li>
								<li><?=gettext('You must have an IP assigned to the interface on any participating sync nodes.') ?></li>
								</lu>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('pfsync Synchronize Peer IP') ?></td>
						<td class="vtable">
							<input  id='pfsyncpeerip' name='pfsyncpeerip' type='text' class='formfld unknown' value='<?= htmlentities($pconfig['pfsyncpeerip']); ?>' />
							<?=gettext('Setting this option will force pfsync to synchronize its state table to this IP address.  The default is directed multicast.') ?>
						</td>
					</tr>
					<tr>
                                        <td></td>
						<td>&nbsp;</td>
					</tr>
				</tbody>
				</table>

				<table class="table table-primary table-striped" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
				<thead>
					<tr>
						<th colspan="2" class="listtopic"><?=gettext('Configuration Synchronization Settings (XMLRPC Sync)') ?></th>
					</tr>
				</thead>
				<tbody>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Config to IP') ?></td>
						<td class="vtable">
							<input  id='synchronizetoip' name='synchronizetoip' type='text' class='formfld unknown' value='<?= htmlentities($pconfig['synchronizetoip']); ?>' />
							<?=gettext('Enter the IP address of the firewall to which the selected configuration sections should be synchronized.') ?><br />
							<div class="well">
								<lu>
									<li><?=gettext('XMLRPC sync is currently only supported over connections using the same protocol and port as this system - make sure the remote system\'s port and protocol are set accordingly!') ?></li>
									<li><b><?=gettext('Do not use the Synchronize Config to IP and password option on backup cluster members!') ?></b></li>
								</lu>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Remote System Username') ?></td>
						<td class="vtable">
							<input  id='username' name='username' type='text' class='formfld unknown' value='<?= htmlentities($pconfig['username']); ?>' />
							<br />
							<?=gettext('Enter the webConfigurator username of the system entered above for synchronizing your configuration.') ?><br />
							<div class="well well-sm">
								<b><?=gettext('Do not use the Synchronize Config to IP and username option on backup cluster members!') ?></b>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Remote System Password') ?></td>
						<td class="vtable">
							<input  id='password' type='password' name='password' class='formfld pwd' value='<?= htmlentities($pconfig['password']); ?>' />
							<br />
							<?=gettext('Enter the webConfigurator password of the system entered above for synchronizing your configuration.') ?><br />
							<div class="well well-sm">
								<b><?=gettext('Do not use the Synchronize Config to IP and password option on backup cluster members!') ?></b>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Users and Groups') ?></td>
						<td class="vtable">
							<input id='synchronizeusers' type='checkbox' name='synchronizeusers' value='on' <?php if ($pconfig['synchronizeusers'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the users and groups over to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Auth Servers') ?></td>
						<td class="vtable">
							<input id='synchronizeauthservers' type='checkbox' name='synchronizeauthservers' value='on' <?php if ($pconfig['synchronizeauthservers'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the authentication servers (e.g. LDAP, RADIUS) over to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Certificates') ?></td>
						<td class="vtable">
							<input id='synchronizecerts' type='checkbox' name='synchronizecerts' value='on' <?php if ($pconfig['synchronizecerts'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the Certificate Authorities, Certificates, and Certificate Revocation Lists over to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize rules') ?></td>
						<td class="vtable">
							<input id='synchronizerules' type='checkbox' name='synchronizerules' value='on' <?php if ($pconfig['synchronizerules'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the firewall rules to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Firewall Schedules') ?></td>
						<td class="vtable">
							<input id='synchronizeschedules' type='checkbox' name='synchronizeschedules' value='on' <?php if ($pconfig['synchronizeschedules'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the firewall schedules to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize aliases') ?></td>
						<td class="vtable">
							<input id='synchronizealiases' type='checkbox' name='synchronizealiases' value='on' <?php if ($pconfig['synchronizealiases'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the aliases over to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize NAT') ?></td>
						<td class="vtable">
							<input id='synchronizenat' type='checkbox' name='synchronizenat' value='on' <?php if ($pconfig['synchronizenat'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the NAT rules over to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize IPsec') ?></td>
						<td class="vtable">
							<input id='synchronizeipsec' type='checkbox' name='synchronizeipsec' value='on' <?php if ($pconfig['synchronizeipsec'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the IPsec configuration to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize OpenVPN') ?></td>
						<td class="vtable">
							<input id='synchronizeopenvpn' type='checkbox' name='synchronizeopenvpn' value='on' <?php if ($pconfig['synchronizeopenvpn'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the OpenVPN configuration to the other HA host when changes are made.') ?>
							<div class="well well-sm"><b><?=gettext('Using this option implies "Synchronize Certificates" as they are required for OpenVPN.') ?></b></div>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize DHCPD') ?></td>
						<td class="vtable">
							<input id='synchronizedhcpd' type='checkbox' name='synchronizedhcpd' value='on' <?php if ($pconfig['synchronizedhcpd'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the DHCP Server settings over to the other HA host when changes are made. This only applies to DHCP for IPv4.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Wake on LAN') ?></td>
						<td class="vtable">
							<input id='synchronizewol' type='checkbox' name='synchronizewol' value='on' <?php if ($pconfig['synchronizewol'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the WoL configuration to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Static Routes') ?></td>
						<td class="vtable">
							<input id='synchronizestaticroutes' type='checkbox' name='synchronizestaticroutes' value='on' <?php if ($pconfig['synchronizestaticroutes'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the Static Route configuration to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Load Balancer') ?></td>
						<td class="vtable">
							<input id='synchronizelb' type='checkbox' name='synchronizelb' value='on' <?php if ($pconfig['synchronizelb'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the Load Balancer configuration to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize Virtual IPs') ?></td>
						<td class="vtable">
							<input id='synchronizevirtualip' type='checkbox' name='synchronizevirtualip' value='on' <?php if ($pconfig['synchronizevirtualip'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the CARP Virtual IPs to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr valign="top">
						<td width="22%" class="vncell"><?=gettext('Synchronize DNS Forwarder') ?></td>
						<td class="vtable">
							<input id='synchronizednsforwarder' type='checkbox' name='synchronizednsforwarder' value='on' <?php if ($pconfig['synchronizednsforwarder'] === "on") {
                                echo "checked='checked'";
} ?> />
							<?=gettext('Automatically sync the DNS Forwarder configuration to the other HA host when changes are made.') ?>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
							<input name="id" type="hidden" value="0" />
							<input name="Submit" type="submit" class="btn btn-primary" value="Save" />
							<input type="button" class="btn btn-default" value="<?=gettext("Cancel");
?>" onclick="window.location.href='<?=$referer;?>'" />
						</td>
					</tr>
				</tbody>
                            </table>
                        </form>

                       </div>

                </div>
            </section>

        </div>

	</div>
</section>


<?php include("foot.inc");
