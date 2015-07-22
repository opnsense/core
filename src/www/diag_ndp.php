<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>
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

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");

exec("/usr/sbin/ndp -na", $rawdata);

$i = 0;

/* if list */
$ifdescrs = get_configured_interface_with_descr();

foreach ($ifdescrs as $key =>$interface) {
	$hwif[$config['interfaces'][$key]['if']] = $interface;
}

$data = array();
array_shift($rawdata);
foreach ($rawdata as $line) {
	$elements = preg_split('/[ ]+/', $line);

	$ndpent = array();
	$ndpent['ipv6'] = trim($elements[0]);
	$ndpent['mac'] = trim($elements[1]);
	$ndpent['interface'] = trim($elements[2]);
	$ndpent['dnsresolve'] = 'Z_ ';
	if (is_ipaddr($ndpent['ipv6'])) {
		list($ip, $scope) = explode('%', $ndpent['ipv6']);
		$hostname = gethostbyaddr($ip);
		if ($hostname !== false && $hostname !== $ip) {
			$ndpent['dnsresolve'] = $hostname;
		}
	}

	$data[] = $ndpent;
}

// Sort the data alpha first
$data = msort($data, "dnsresolve");

// Load MAC-Manufacturer table
$mac_man = load_mac_manufacturer_table();

$pgtitle = array(gettext("Diagnostics"),gettext("NDP Table"));
include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>


<?php

// Flush buffers out to client so that they see Loading, please wait....
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

?>

<section class="page-content-main">
	<div class="container-fluid">

        <div class="row">

            <section class="col-xs-12">
                <div class="content-box">

                    <div class="table-responsive">

                        <table class="table table-striped table-sort sortable __nomb">
                            <tr class="content-box-head">
                                <th>
                                    <table>
                                        <tr>
                                            <td><?= gettext("IPv6 address"); ?></td>
                                            <td>
                                                <span class="table-sort-icon glyphicon glyphicon-sort"></span>
                                            </td>
                                        </tr>
                                    </table>
                                </th>
                                <th>
                                    <table>
                                        <tr>
                                            <td><?= gettext("MAC address"); ?></td>
                                            <td>
                                                <span class="table-sort-icon glyphicon glyphicon-sort"></span>
                                            </td>
                                        </tr>
                                    </table>
                                </th>
                                <th>
                                    <table>
                                        <tr>
                                            <td><?= gettext("Hostname"); ?></td>
                                            <td>
                                                <span class="table-sort-icon glyphicon glyphicon-sort"></span>
                                            </td>
                                        </tr>
                                    </table>
                                </th>
                                <th>
                                    <table>
                                        <tr>
                                            <td><?= gettext("Interface"); ?></td>
                                            <td>
                                                <span class="table-sort-icon glyphicon glyphicon-sort"></span>
                                            </td>
                                        </tr>
                                    </table>
                                </th>
                            </tr>



							<?php foreach ($data as $entry): ?>
								<tr>
									<td class="listlr"><?=$entry['ipv6'];?></td>
									<td class="listr">
										<?php
										$mac=trim($entry['mac']);
										$mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
										print $mac;
										if(isset($mac_man[$mac_hi])){ print "<br /><font size=\"-2\"><i>{$mac_man[$mac_hi]}</i></font>"; }
										?>
									</td>
									<td class="listr">
										<?php
										echo "&nbsp;". str_replace("Z_ ", "", $entry['dnsresolve']);
										?>
									</td>
									<td class="listr">
										<?php
										if(isset($hwif[$entry['interface']]))
											echo $hwif[$entry['interface']];
										else
											echo $entry['interface'];
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>

                    </div>

                </div>
            </section>

        </div>

	</div>
</section>

<?php include('foot.inc');?>
