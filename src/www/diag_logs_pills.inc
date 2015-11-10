<?php

/*
	Copyright (C) 2014 Deciso B.V.
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

$tab_group = isset($tab_group) ? $tab_group : 'system';
$tab_array = array();

if ($tab_group == 'vpn') {
	$tab_array[] = array(gettext("PPTP Logins"), (($vpntype == "pptp") && ($mode != "raw")), "/diag_logs_vpn.php?vpntype=pptp");
	$tab_array[] = array(gettext("PPTP Raw"), (($vpntype == "pptp") && ($mode == "raw")), "/diag_logs_vpn.php?vpntype=pptp&amp;mode=raw");
	$tab_array[] = array(gettext("PPPoE Logins"), (($vpntype == "poes") && ($mode != "raw")), "/diag_logs_vpn.php?vpntype=poes");
	$tab_array[] = array(gettext("PPPoE Raw"), (($vpntype == "poes") && ($mode == "raw")), "/diag_logs_vpn.php?vpntype=poes&amp;mode=raw");
	$tab_array[] = array(gettext("L2TP Logins"), (($vpntype == "l2tp") && ($mode != "raw")), "/diag_logs_vpn.php?vpntype=l2tp");
	$tab_array[] = array(gettext("L2TP Raw"), (($vpntype == "l2tp") && ($mode == "raw")), "/diag_logs_vpn.php?vpntype=l2tp&amp;mode=raw");
} else {
	$tab_array[] = array(gettext('Cache'), true, '/diag_logs_proxy.php?type=cache');
	$tab_array[] = array(gettext('Access'), false, '/diag_logs_proxy.php?type=access');
}

?><ul class="nav nav-pills" role="tablist">
<?php foreach ($tab_array as $tab): ?>
	<li role="presentation" <?php if (str_replace('amp;','', $tab[2]) == $_SERVER['REQUEST_URI']):?>class="active"<?php endif; ?>><a href="<?=$tab[2];?>"><?=$tab[0];?></a></li>
<?php endforeach; ?>
</ul>
