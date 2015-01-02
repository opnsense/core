<?php
/*
	Copyright (C) 2014 Deciso B.V.

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

$allowautocomplete = true;

function fixup_host_logic($value) {
	return str_replace(array(" ", ",", "+", "|", "!"), array("", "and ", "and ", "or ", "not "), $value);
}
function strip_host_logic($value) {
	return str_replace(array(" ", ",", "+", "|", "!"), array("", "", "", "", ""), $value);
}
function get_host_boolean($value, $host) {
	$value = str_replace(array("!", $host), array("", ""), $value);
	$andor = "";
	switch (trim($value)) {
		case "|":
			$andor = "or ";
			break;
		case ",":
		case "+":
			$andor = "and ";
			break;
	}
	return $andor;
}
function has_not($value) {
	return strpos($value, '!') !== false;
}
function fixup_not($value) {
	return str_replace("!", "not ", $value);
}
function strip_not($value) {
	return ltrim(trim($value), '!');
}

function fixup_host($value, $position) {
	$host = strip_host_logic($value);
	$not = has_not($value) ? "not " : "";
	$andor = ($position > 0) ? get_host_boolean($value, $host) : "";
	if (is_ipaddr($host))
		return "{$andor}host {$not}" . $host;
	elseif (is_subnet($host))
		return "{$andor}net {$not}" . $host;
	else
		return "";
}

if ($_POST['downloadbtn'] == gettext("Download Capture"))
	$nocsrf = true;

$pgtitle = array(gettext("Diagnostics"), gettext("Packet Capture"));
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");

$fp = "/root/";
$fn = "packetcapture.cap";
$snaplen = 0;//default packet length
$count = 100;//default number of packets to capture

$fams = array('ip', 'ip6');
$protos = array('icmp', 'icmp6', 'tcp', 'udp', 'arp', 'carp', 'esp',
		'!icmp', '!icmp6', '!tcp', '!udp', '!arp', '!carp', '!esp');

$input_errors = array();

$interfaces = get_configured_interface_with_descr();
if (isset($config['ipsec']['enable']))
	$interfaces['ipsec'] = "IPsec";
foreach (array('server', 'client') as $mode) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$interfaces['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
			}
		}
	}
}

if ($_POST) {
	$host = $_POST['host'];
	$selectedif = $_POST['interface'];
	$count = $_POST['count'];
	$snaplen = $_POST['snaplen'];
	$port = $_POST['port'];
	$detail = $_POST['detail'];
	$fam = $_POST['fam'];
	$proto = $_POST['proto'];

	if (!array_key_exists($selectedif, $interfaces)) {
		$input_errors[] = gettext("Invalid interface.");
	}
	if ($fam !== "" && $fam !== "ip" && $fam !== "ip6") {
		$input_errors[] = gettext("Invalid address family.");
	}
	if ($proto !== "" && !in_array(strip_not($proto), $protos)) {
		$input_errors[] = gettext("Invalid protocol.");
	}

	if ($host != "") {
		$host_string = str_replace(array(" ", "|", ","), array("", "#|", "#+"), $host);
		if (strpos($host_string, '#') === false) {
			$hosts = array($host);
		} else {
			$hosts = explode('#', $host_string);
		}
		foreach ($hosts as $h) {
			if (!is_subnet(strip_host_logic($h)) && !is_ipaddr(strip_host_logic($h))) {
				$input_errors[] = sprintf(gettext("A valid IP address or CIDR block must be specified. [%s]"), $h);
			}
		}
	}
	if ($port != "") {
		if (!is_port(strip_not($port))) {
			$input_errors[] = gettext("Invalid value specified for port.");
		}
	}
	if ($snaplen == "") {
		$snaplen = 0;
	} else {
		if (!is_numeric($snaplen) || $snaplen < 0) {
			$input_errors[] = gettext("Invalid value specified for packet length.");
		}
	}
	if ($count == "") {
		$count = 0;
	} else {
		if (!is_numeric($count) || $count < 0) {
			$input_errors[] = gettext("Invalid value specified for packet count.");
		}
	}

	if (!count($input_errors)) {
		$do_tcpdump = true;

		conf_mount_rw();

		if ($_POST['promiscuous']) {
			//if promiscuous mode is checked
			$disablepromiscuous = "";
		} else {
			//if promiscuous mode is unchecked
			$disablepromiscuous = "-p";
		}

		if ($_POST['dnsquery']) {
			//if dns lookup is checked
			$disabledns = "";
		} else {
			//if dns lookup is unchecked
			$disabledns = "-n";
		}

		if ($_POST['startbtn'] != "" ) {
			$action = gettext("Start");

			//delete previous packet capture if it exists
			if (file_exists($fp.$fn))
				unlink ($fp.$fn);

		} elseif ($_POST['stopbtn']!= "") {
			$action = gettext("Stop");
			$processes_running = trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep tcpdump | /usr/bin/grep {$fn} | /usr/bin/egrep -v '(pflog|grep)'"));

			//explode processes into an array, (delimiter is new line)
			$processes_running_array = explode("\n", $processes_running);

			//kill each of the packetcapture processes
			foreach ($processes_running_array as $process) {
				$process_id_pos = strpos($process, ' ');
				$process_id = substr($process, 0, $process_id_pos);
				exec("kill $process_id");
			}

		} elseif ($_POST['downloadbtn']!= "") {
			//download file
			$fs = filesize($fp.$fn);
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=$fn");
			header("Content-Length: $fs");
			readfile($fp.$fn);
			exit;
		}
	}
} else {
	$do_tcpdump = false;
}

include("head.inc"); ?>

<body>

<?php
include("fbegin.inc");
?>

<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<section class="col-xs-12">
                <div class="content-box">

					<?php if ($input_errors) print_input_errors($input_errors); ?>

                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Packet capture");?></h3>
				    </header>

				    <div class="content-box-main">
				    <div class="table-responsive">
					    <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post" name="iform" id="iform">
			        <table class="table table-striped">
				        <tbody>
					        <tr>
							<td><?=gettext("Interface");?></td>
									<td>
										<select name="interface" class="form-control">
										<?php foreach ($interfaces as $iface => $ifacename): ?>
											<option value="<?=$iface;?>" <?php if ($selectedif == $iface) echo "selected=\"selected\""; ?>>
											<?php echo $ifacename;?>
											</option>
										<?php endforeach; ?>
										</select>
										<p class="text-muted"><em><small><?=gettext("Select the interface on which to capture traffic.");?></small></em></p>
									</td>
					        </tr>
					        <tr>
					          <td><?=gettext("Promiscuous");?></td>
					          <td>
						          <input name="promiscuous" type="checkbox"<?php if($_POST['promiscuous']) echo " checked=\"checked\""; ?> />
						          <p class="text-muted"><em><small><?=gettext("If checked, the");?> <a target="_blank" href="http://www.freebsd.org/cgi/man.cgi?query=tcpdump&amp;apropos=0&amp;sektion=0&amp;manpath=FreeBSD+8.3-stable&amp;arch=default&amp;format=html"><?= gettext("packet capture")?></a> <?= gettext("will be performed using promiscuous mode.");?>
			<br /><b><?=gettext("Note");?>: </b><?=gettext("Some network adapters do not support or work well in promiscuous mode.");?></small></em></p>
						      </td>
					        </tr>
					        <tr>
					          <td><?=gettext("Address Family");?></td>
					          <td>
						          <select name="fam" class="form-control">
											<option value="">Any</option>
											<option value="ip" <?php if ($fam == "ip") echo "selected=\"selected\""; ?>>IPv4 Only</option>
											<option value="ip6" <?php if ($fam == "ip6") echo "selected=\"selected\""; ?>>IPv6 Only</option>
										</select>
										<p class="text-muted"><em><small><?=gettext("Select the type of traffic to be captured, either Any, IPv4 only or IPv6 only.");?></small></em></p>
									</td>
					        </tr>
					        <tr>
					          <td><?=gettext("Protocol");?></td>
					          <td>
						          <select name="proto" class="form-control">
											<option value="">Any</option>
											<option value="icmp" <?php if ($proto == "icmp") echo "selected=\"selected\""; ?>>ICMP</option>
											<option value="!icmp" <?php if ($proto == "!icmp") echo "selected=\"selected\""; ?>>Exclude ICMP</option>
											<option value="icmp6" <?php if ($proto == "icmp6") echo "selected=\"selected\""; ?>>ICMPv6</option>
											<option value="!icmp6" <?php if ($proto == "!icmp6") echo "selected=\"selected\""; ?>>Exclude ICMPv6</option>
											<option value="tcp" <?php if ($proto == "tcp") echo "selected=\"selected\""; ?>>TCP</option>
											<option value="!tcp" <?php if ($proto == "!tcp") echo "selected=\"selected\""; ?>>Exclude TCP</option>
											<option value="udp" <?php if ($proto == "udp") echo "selected=\"selected\""; ?>>UDP</option>
											<option value="!udp" <?php if ($proto == "!udp") echo "selected=\"selected\""; ?>>Exclude UDP</option>
											<option value="arp" <?php if ($proto == "arp") echo "selected=\"selected\""; ?>>ARP</option>
											<option value="!arp" <?php if ($proto == "!arp") echo "selected=\"selected\""; ?>>Exclude ARP</option>
											<option value="carp" <?php if ($proto == "carp") echo "selected=\"selected\""; ?>>CARP (VRRP)</option>
											<option value="!carp" <?php if ($proto == "!carp") echo "selected=\"selected\""; ?>>Exclude CARP (VRRP)</option>
											<option value="esp" <?php if ($proto == "esp") echo "selected=\"selected\""; ?>>ESP</option>
										</select>
										<p class="text-muted"><em><small><?=gettext("Select the protocol to capture, or Any.");?></small></em></p>
					          </td>
					        </tr>
					        <tr>
					          <td><?=gettext("Host Address");?></td>
					          <td>
						          <input name="host" class="form-control host" id="host" size="20" value="<?=htmlspecialchars($host);?>" />
									  <p class="text-muted"><em><small><?=gettext("This value is either the Source or Destination IP address or subnet in CIDR notation. The packet capture will look for this address in either field.");?>
										<br /><?=gettext("Matching can be negated by preceding the value with \"!\". Multiple IP addresses or CIDR subnets may be specified. Comma (\",\") separated values perform a boolean \"and\". Separating with a pipe (\"|\") performs a boolean \"or\".");?>
										<br /><?=gettext("If you leave this field blank, all packets on the specified interface will be captured.");?>
									  </small></em></p>
					          </td>
					        </tr>
					        <tr>
					          <td><?=gettext("Port");?></td>
					          <td>
						          <input name="port" class="formfld unknown" id="port" size="5" value="<?=$port;?>" />

										<p class="text-muted"><em><small><?=gettext("The port can be either the source or destination port. The packet capture will look for this port in either field.");?> <?=gettext("Leave blank if you do not want to filter by port.");?></small></em></p>
									</td>
					        </tr>
					        <tr>
					          <td><?=gettext("Packet Length");?></td>
					          <td>
						          <input name="snaplen" class="formfld unknown" id="snaplen" size="5" value="<?=$snaplen;?>" />
									  <p class="text-muted"><em><small><?=gettext("The Packet length is the number of bytes of each packet that will be captured. Default value is 0, which will capture the entire frame regardless of its size.");?></small></em></p>
					          </td>
					        </tr>
					        <tr>
					          <td><?=gettext("Count");?></td>
					          <td>
						          <input name="count" class="formfld unknown" id="count" size="5" value="<?=$count;?>" />
									  <p class="text-muted"><em><small><?=gettext("This is the number of packets the packet capture will grab. Default value is 100.") . "<br />" . gettext("Enter 0 (zero) for no count limit.");?></small></em></p>
					          </td>
					        </tr>

					        <tr>
					          <td><?=gettext("Level of Detail");?></td>
					          <td>
						          <select name="detail" class="formselect" id="detail" size="1">
											<option value="normal" <?php if ($detail == "normal") echo "selected=\"selected\""; ?>><?=gettext("Normal");?></option>
											<option value="medium" <?php if ($detail == "medium") echo "selected=\"selected\""; ?>><?=gettext("Medium");?></option>
											<option value="high"   <?php if ($detail == "high")   echo "selected=\"selected\""; ?>><?=gettext("High");?></option>
											<option value="full"   <?php if ($detail == "full")   echo "selected=\"selected\""; ?>><?=gettext("Full");?></option>
										</select>
										 <p class="text-muted"><em><small><?=gettext("This is the level of detail that will be displayed after hitting 'Stop' when the packets have been captured.") .  "<br /><b>" .
					gettext("Note:") . "</b> " .
					gettext("This option does not affect the level of detail when downloading the packet capture.");?></small></em></p>
					          </td>
					        </tr>

					        <tr>
					          <td><?=gettext("Reverse DNS Lookup");?></td>
					          <td>
						          <input name="dnsquery" type="checkbox" <?php if($_POST['dnsquery']) echo " checked=\"checked\""; ?> />
						           <p class="text-muted"><em><small><?=gettext("This check box will cause the packet capture to perform a reverse DNS lookup associated with all IP addresses.");?>
			<br /><b><?=gettext("Note");?>: </b><?=gettext("This option can cause delays for large packet captures.");?></small></em></p>
					          </td>
					        </tr>

					       <tr>
									<td>&nbsp;</td>
									<td>
									<?php

									/* check to see if packet capture tcpdump is already running */
									$processcheck = (trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep tcpdump | /usr/bin/grep {$fn} | /usr/bin/egrep -v '(pflog|grep)'")));

									if ($processcheck != "")
										$processisrunning = true;
									else
										$processisrunning = false;

									if (($action == gettext("Stop") or $action == "") and $processisrunning != true)
										echo "<input type=\"submit\" class=\"btn\" name=\"startbtn\" value=\"" . gettext("Start") . "\" />&nbsp;";
									else {
										echo "<input type=\"submit\" class=\"btn\" name=\"stopbtn\" value=\"" . gettext("Stop") . "\" />&nbsp;";
									}
									if (file_exists($fp.$fn) and $processisrunning != true) {
										echo "<input type=\"submit\" class=\"btn\" name=\"viewbtn\" value=\"" . gettext("View Capture") . "\" />&nbsp;";
										echo "<input type=\"submit\" class=\"btn\" name=\"downloadbtn\" value=\"" . gettext("Download Capture") . "\" />";
										echo "<br />" . gettext("The packet capture file was last updated:") . " " . date("F jS, Y g:i:s a.", filemtime($fp.$fn));
									}
									?>
									</td>
								</tr>

				        </tbody>
				    </table>
					    </form>
				    </div>
				</div>
			</section>



<?php

$title = '';

if ($processisrunning == true) {
	$title = gettext("Packet Capture is running.");
}

if ($do_tcpdump) {
	$matches = array();

	if (in_array($fam, $fams))
		$matches[] = $fam;

	if (in_array($proto, $protos)) {
		$matches[] = fixup_not($proto);
	}

	if ($port != "")
		$matches[] = "port ".fixup_not($port);

	if ($host != "") {
		$hostmatch = "";
		$hostcount = 0;
		foreach ($hosts as $h) {
			$h = fixup_host($h, $hostcount++);
			if (!empty($h))
				$hostmatch .= " " . $h;
		}
		if (!empty($hostmatch))
			$matches[] = "({$hostmatch})";
	}

	if ($count != "0" ) {
		$searchcount = "-c " . $count;
	} else {
		$searchcount = "";
	}

	$selectedif = convert_friendly_interface_to_real_interface_name($selectedif);

	if ($action == gettext("Start")) {
		$matchstr = implode($matches, " and ");
		$title = gettext("Packet Capture is running.");

		$cmd = "/usr/sbin/tcpdump -i {$selectedif} {$disablepromiscuous} {$searchcount} -s {$snaplen} -w {$fp}{$fn} " . escapeshellarg($matchstr);
		// Debug
		//echo $cmd;
		mwexec_bg ($cmd);
	} else {
		$show_data = true;
		//action = stop
		$title = gettext("Packet Capture stopped.") . " " . gettext("Packets Captured:");
	}
}

if (!empty($title)): ?>

			<section class="col-xs-12">
                <div class="content-box">
	                <header class="content-box-head container-fluid">
				        <h3><?=$title; ?></h3>
				    </header>
				    <? if (!empty($show_data)): ?>
				    <div class="content-box-main col-xs-12">

						<script type="text/javascript">
						//<![CDATA[
						window.onload=function(){
							document.getElementById("packetsCaptured").wrap='off';
						}
						//]]>
						</script>
						<pre id="packetsCaptured" style="width:98%" readonly="readonly"><?php
						$detail_args = "";
						switch ($detail) {
						case "full":
							$detail_args = "-vv -e";
							break;
						case "high":
							$detail_args = "-vv";
							break;
						case "medium":
							$detail_args = "-v";
							break;
						case "normal":
						default:
							$detail_args = "-q";
							break;
						}
						system("/usr/sbin/tcpdump {$disabledns} {$detail_args} -r {$fp}{$fn}");

						conf_mount_ro();
		?>
						</pre>


			</div>
			<? endif; ?>
                </div>
			</section>

<? endif; ?>
		</div>

	</div>
</section>


<?php
include("foot.inc");
?>
