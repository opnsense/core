<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2007 Sam Wenham
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

$nocsrf = true;

require_once("globals.inc");
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
require_once("captiveportal.inc");

if (($_GET['act'] == "del") && (!empty($_GET['zone']))) {
    $cpzone = $_GET['zone'];
    captiveportal_disconnect_client($_GET['id']);
}

flush();

function clientcmp($a, $b)
{
    global $order;
    return strcmp($a[$order], $b[$order]);
}

if (!is_array($config['captiveportal'])) {
        $config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

$cpdb_all = array();

foreach ($a_cp as $cpzone => $cp) {
    $cpdb_handle = new OPNsense\CaptivePortal\DB($cpzone);

        $order = "";
    if ($_GET['order']) {
        if ($_GET['order'] == "ip") {
            $order = "ip";
        } elseif ($_GET['order'] == "mac") {
            $order = "mac";
        } elseif ($_GET['order'] == "user") {
            $order = "username";
        }
    }

    $cpdb = $cpdb_handle->listClients(array(), "and", array($order)) ;
    $cpdb_all[$cpzone] = $cpdb;
}

?>
<table class="table table-striped sortable" id="sortabletable" width="100%" border="0" cellpadding="0" cellspacing="0" summary="captive portal status">
  <tr>
    <td class="listhdrr"><a href="?order=ip&amp;showact=<?=$_GET['showact'];?>"><b>IP address</b></a></td>
    <td class="listhdrr"><a href="?order=mac&amp;showact=<?=$_GET['showact'];?>"><b>MAC address</b></a></td>
    <td class="listhdrr"><a href="?order=user&amp;showact=<?=$_GET['showact'];
?>"><b><?=gettext("Username");?></b></a></td>
	<?php if ($_GET['showact']) :
?>
    <td class="listhdrr"><a href="?order=start&amp;showact=<?=$_GET['showact'];
?>"><b><?=gettext("Session start");?></b></a></td>
    <td class="listhdrr"><a href="?order=start&amp;showact=<?=$_GET['showact'];
?>"><b><?=gettext("Last activity");?></b></a></td>
	<?php
endif; ?>
  </tr>
<?php foreach ($cpdb_all as $cpzone => $cpdb) :
?>
    <?php foreach ($cpdb as $cpent) :
?>
  <tr>
    <td class="listlr"><?=$cpent->ip;?></td>
    <td class="listr"><?=$cpent->mac;?>&nbsp;</td>
    <td class="listr"><?=$cpent->username;?>&nbsp;</td>
	<?php if ($_GET['showact']) :
?>
    <td class="listr"><?=htmlspecialchars(date("m/d/Y H:i:s", $cpent->allow_time));?></td>
    <td class="listr">?</td>
	<?php
endif; ?>
	<td valign="middle" class="list nowrap">
	<a href="?order=<?=$_GET['order'];
?>&amp;showact=<?=$_GET['showact'];
?>&amp;act=del&amp;zone=<?=$cpzone;
?>&amp;id=<?=$cpent->sessionid;?>" onclick="return confirm('<?= gettext('Do you really want to disconnect this client?');?>')"><span class="glyphicon glyphicon-remove"></span></a></td>
  </tr>
    <?php
endforeach; ?>
<?php
endforeach; ?>
</table>
