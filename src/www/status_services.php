<?php
/*
    Copyright (C) 2014-2015 Deciso B.V.
    (from service-utils.inc) Copyright (C) 2005-2006 Colin Smith (ethethlay@gmail.com)
    Copyright (C) 2004, 2005 Scott Ullrich
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("services.inc");
require_once("shortcuts.inc");

$service_name = '';
if (isset($_GET['service']))
	$service_name = htmlspecialchars($_GET['service']);

if (!empty($service_name)) {
	switch ($_GET['mode']) {
		case "restartservice":
			$savemsg = service_control_restart($service_name, $_GET);
			break;
		case "startservice":
			$savemsg = service_control_start($service_name, $_GET);
			break;
		case "stopservice":
			$savemsg = service_control_stop($service_name, $_GET);
			break;
	}
	sleep(5);
}

function service_control_start($name, $extras) {
	global $g;
	switch($name) {
		case 'radvd':
			services_radvd_configure();
			break;
		case 'captiveportal':
			captiveportal_configure();
			break;
		case 'ntpd':
			system_ntp_configure();
			break;
		case 'apinger':
			setup_gateways_monitor();
			break;
		case 'bsnmpd':
			services_snmpd_configure();
			break;
		case 'dhcrelay':
			services_dhcrelay_configure();
			break;
		case 'dhcrelay6':
			services_dhcrelay6_configure();
			break;
		case 'dnsmasq':
			services_dnsmasq_configure();
			break;
		case 'dhcpd':
			services_dhcpd_configure();
			break;
		case 'igmpproxy':
			services_igmpproxy_configure();
			break;
		case 'miniupnpd':
			upnp_action('start');
			break;
		case 'ipsec':
			vpn_ipsec_force_reload();
			break;
		case 'sshd':
			configd_run("sshd restart");
			break;
		case 'openvpn':
			$vpnmode = isset($extras['vpnmode']) ? htmlspecialchars($extras['vpnmode']) : htmlspecialchars($extras['mode']);
			if (($vpnmode == "server") || ($vpnmode == "client")) {
				$id = isset($extras['vpnid']) ? htmlspecialchars($extras['vpnid']) : htmlspecialchars($extras['id']);
				$configfile = "/var/etc/openvpn/{$vpnmode}{$id}.conf";
				if (file_exists($configfile))
					openvpn_restart_by_vpnid($vpnmode, $id);
			}
			break;
		case 'relayd':
			relayd_configure();
			break;
		case 'squid':
			configd_run("proxy start");
			break;
		case 'suricata':
			configd_run("ids start");
			break;
		default:
			log_error(gettext("Could not start unknown service `%s'", $name));
			break;
	}
	return sprintf(gettext("%s has been started."),htmlspecialchars($name));
}


function service_control_stop($name, $extras) {
	global $g;
	switch($name) {
		case 'radvd':
			killbypid("/var/run/radvd.pid");
			break;
		case 'captiveportal':
			$zone = htmlspecialchars($extras['zone']);
			killbypid("/var/run/lighty-{$zone}-CaptivePortal.pid");
			killbypid("/var/run/lighty-{$zone}-CaptivePortal-SSL.pid");
			break;
		case 'ntpd':
			killbyname("ntpd");
			break;
		case 'apinger':
			killbypid("/var/run/apinger.pid");
			break;
		case 'bsnmpd':
			killbypid("/var/run/snmpd.pid");
			break;
		case 'choparp':
			killbyname("choparp");
			break;
		case 'dhcpd':
			killbyname("dhcpd");
			break;
		case 'dhcrelay':
			killbypid("/var/run/dhcrelay.pid");
			break;
		case 'dhcrelay6':
			killbypid("/var/run/dhcrelay6.pid");
			break;
		case 'dnsmasq':
			killbypid("/var/run/dnsmasq.pid");
			break;
		case 'unbound':
			killbypid("/var/run/unbound.pid");
			break;
		case 'igmpproxy':
			killbyname("igmpproxy");
			break;
		case 'miniupnpd':
			upnp_action('stop');
			break;
		case 'sshd':
			killbyname("sshd");
			break;
		case 'ipsec':
			exec("/usr/local/sbin/ipsec stop");
			break;
		case 'openvpn':
			$vpnmode = htmlspecialchars($extras['vpnmode']);
			if (($vpnmode == "server") or ($vpnmode == "client")) {
				$id = htmlspecialchars($extras['id']);
				$pidfile = "/var/run/openvpn_{$vpnmode}{$id}.pid";
				killbypid($pidfile);
			}
			break;
		case 'relayd':
			mwexec('pkill relayd');
			break;
		case 'squid':
			configd_run("proxy stop");
			break;
		case 'suricata':
			configd_run("ids stop");
			break;
		default:
			log_error(gettext("Could not stop unknown service `%s'", $name));
			break;
	}
	return sprintf(gettext("%s has been stopped."), htmlspecialchars($name));
}


function service_control_restart($name, $extras) {
	global $g;
	switch($name) {
		case 'radvd':
			services_radvd_configure();
			break;
		case 'captiveportal':
			captiveportal_configure();
			break;
		case 'ntpd':
			system_ntp_configure();
			break;
		case 'apinger':
			killbypid("/var/run/apinger.pid");
			setup_gateways_monitor();
			break;
		case 'bsnmpd':
			services_snmpd_configure();
			break;
		case 'dhcrelay':
			services_dhcrelay_configure();
			break;
		case 'dhcrelay6':
			services_dhcrelay6_configure();
			break;
		case 'dnsmasq':
			services_dnsmasq_configure();
			break;
		case 'unbound':
			services_unbound_configure();
			break;
		case 'dhcpd':
			services_dhcpd_configure();
			break;
		case 'igmpproxy':
			services_igmpproxy_configure();
			break;
		case 'miniupnpd':
			upnp_action('restart');
			break;
		case 'ipsec':
			vpn_ipsec_force_reload();
			break;
		case 'sshd':
			configd_run("sshd restart");
			break;
		case 'openvpn':
			$vpnmode = htmlspecialchars($extras['vpnmode']);
			if ($vpnmode == "server" || $vpnmode == "client") {
				$id = htmlspecialchars($extras['id']);
				$configfile = "/var/etc/openvpn/{$vpnmode}{$id}.conf";
				if (file_exists($configfile))
					openvpn_restart_by_vpnid($vpnmode, $id);
			}
			break;
		case 'relayd':
			relayd_configure(true);
			break;
		case 'squid':
			configd_run("proxy restart");
			break;
		case 'suricata':
			configd_run("ids restart");
			break;
		default:
			log_error(gettext("Could not restart unknown service `%s'", $name));
			break;
	}
	return sprintf(gettext("%s has been restarted."),htmlspecialchars($name));
}


$pgtitle = array(gettext("Status"),gettext("Services"));
include("head.inc");

?>

<body>
<?php
include("fbegin.inc");
?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($savemsg)) print_info_box($savemsg); ?>

			    <section class="col-xs-12">

					<div class="content-box">
						<form action="status_services.php" method="post">

						<div class="table-responsive">
							<table class="table table-striped table-sort">
							<thead>
							<tr>
								<td><?=gettext("Service");?></td>
								<td><?=gettext("Description");?></td>
								<td><?=gettext("Status");?></td>
							</tr>
							</thead>
							<tbody>
						<?php

						$services = get_services();

						if (count($services) > 0) {
							uasort($services, "service_name_compare");
							foreach($services as $service) {
								if (empty($service['name']))
									continue;
								echo "<tr><td class=\"listlr\" >" . $service['name'] . "</td>\n";
								echo "<td class=\"listr\">" . $service['description'] . "</td>\n";
								// if service is running then listr else listbg
								$bgclass = null;
								if (get_service_status($service))
									$bgclass = "listr";
								else
									$bgclass = "listbg";
								echo "<td class=\"" . $bgclass . "\">" . get_service_status_icon($service, true, true) . "</td>\n";
								echo "<td valign=\"middle\" class=\"list nowrap\">" . get_service_control_links($service);
								$scut = get_shortcut_by_service_name($service['name']);
								if (!empty($scut)) {
									echo get_shortcut_main_link($scut, true, $service);
									echo get_shortcut_status_link($scut, true, $service);
									echo get_shortcut_log_link($scut, true);
								}
								echo "</td></tr>\n";
							}
						} else {
							echo "<tr><td colspan=\"3\">" . gettext("No services found") . " . </td></tr>\n";
						}

						?>
						</tbody>
						</table>
						</div>
						</form>
					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
