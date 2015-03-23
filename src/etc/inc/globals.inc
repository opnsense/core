<?php

/*
    Copyright (C) 2004-2010 Scott Ullrich
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

global $g;

openlog('opnsense', LOG_ODELAY, LOG_USER);
register_shutdown_function('closelog');

$g = array(
	"base_packages" => "siproxd",
	"factory_shipped_username" => "root",
	"factory_shipped_password" => "opnsense",
	"upload_path" => "/root",
	"dhcpd_chroot_path" => "/var/dhcpd",
	"unbound_chroot_path" => "/var/unbound",
	"varrun_path" => "/var/run",
	"varetc_path" => "/var/etc",
	"vardb_path" => "/var/db",
	"varlog_path" => "/var/log",
	"admin_group" => "admins",
	"product_name" => "OPNsense",
	"product_website" => "https://opnsense.org",
	"product_email" => "project@opnsense.org",
	"product_copyright_owner" => "Deciso B.V.",
	"product_copyright_years" => "2014 - 2015",
	"product_copyright_url" => "https://www.deciso.com/",
	"crashreporterurl" => "https://crash.opnsense.org/",
	"latest_config" => "11.1",
	"minimum_ram_warning" => "101",
	"minimum_ram_warning_text" => "128 MB",
	"wan_interface_name" => "wan",
	"nopccard_platforms" => array("wrap", "net48xx"),
	"xmlrpcbaseurl" => "https://not.supported.opnsense.org",
	"captiveportal_path" => "/usr/local/captiveportal",
	"captiveportal_element_path" => "/var/db/cpelements",
	"captiveportal_element_sizelimit" => 1048576,
	"xmlrpcpath" => "/xmlrpc.php",
	"embeddedbootupslice" => "/dev/ad0a",
	"services_dhcp_server_enable" => true,
	"wireless_regex" => "/^(ndis|wi|ath|an|ral|ural|iwi|wlan|rum|run|bwn|zyd|mwl|bwi|ipw|iwn|malo|uath|upgt|urtw|wpi)/",
	"help_base_url" => "/help.php"
);

/* IP TOS flags */
$iptos = array("lowdelay", "throughput", "reliability");

/* TCP flags */
$tcpflags = array("syn", "ack", "fin", "rst", "psh", "urg", "ece", "cwr");

if(file_exists("/usr/local/etc/platform")) {
	$g['platform'] = trim(file_get_contents("/usr/local/etc/platform"));
}

/* Default sysctls */
$sysctls = array(
	"net.inet.ip.portrange.first" => "1024",
	"net.inet.tcp.blackhole" => "2",
	"net.inet.udp.blackhole" => "1",
	"net.inet.ip.random_id" => "1",
	"net.inet.tcp.drop_synfin" => "1",
	"net.inet.ip.redirect" => "1",
	"net.inet6.ip6.redirect" => "1",
	"net.inet6.ip6.use_tempaddr" => "0",
	"net.inet6.ip6.prefer_tempaddr" => "0",
	"net.inet.tcp.syncookies" => "1",
	"net.inet.tcp.recvspace" => "65228",
	"net.inet.tcp.sendspace" => "65228",
	"net.inet.ip.fastforwarding" => "0",
	"net.inet.tcp.delayed_ack" => "0",
	"net.inet.udp.maxdgram" => "57344",
	"net.link.bridge.pfil_onlyip" => "0",
	"net.link.bridge.pfil_member" => "1",
	"net.link.bridge.pfil_bridge" => "0",
	"net.link.tap.user_open" => "1",
	"kern.randompid" => "347",
	"net.inet.ip.intr_queue_maxlen" => "1000",
	"hw.syscons.kbd_reboot" => "0",
	"net.inet.tcp.log_debug" => "0",
	"net.inet.tcp.tso" => "1",
	"net.inet.icmp.icmplim" => "0",
	"vfs.read_max" => "32",
	"kern.ipc.maxsockbuf" => "4262144",
	"debug.pfftpproxy" => "0",
	"net.inet.ip.process_options" => 0,
	"kern.random.sys.harvest.interrupt" => 0,
	"kern.random.sys.harvest.point_to_point" => 0,
	"kern.random.sys.harvest.ethernet" => 0,
	"net.route.netisr_maxqlen" => 1024,
	"net.inet.udp.checksum" => 1,
	"net.bpf.zerocopy_enable" => 1,
	"net.inet.icmp.reply_from_interface" => 1,
	"net.inet6.ip6.rfc6204w3" => 1,
);

function is_install_media()
{
	/*
	 * Despite unionfs underneath, / is still not writeable,
	 * making the following the perfect test for install media.
	 */

	$file = '/.probe.for.install.media';
	$fd = @fopen($file, 'w');
	if ($fd) {
		fclose($fd);
		unlink($file);
		return false;
	}

	return true;
}

$config_parsed = false;
