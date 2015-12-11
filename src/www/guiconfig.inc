<?php

/*
	Copyright (C) 2015 Franco Fichtner <franco@opnsense.org>
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004 Scott Ullrich
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

require_once("util.inc");
require_once("config.inc");

/* THIS MUST BE ABOVE ALL OTHER CODE */
if (empty($nocsrf)) {
	function csrf_startup()
	{
		csrf_conf('rewrite-js', '/csrf/csrf-magic.js');
		$timeout_minutes = isset($config['system']['webgui']['session_timeout']) ?  $config['system']['webgui']['session_timeout'] : 240;
		csrf_conf('expires', $timeout_minutes * 60);
	}
	require_once('csrf/csrf-magic.php');

        // make sure the session is closed after executing csrf-magic
        if (session_status() != PHP_SESSION_NONE) {
            session_write_close();
        }
}

function set_language($lang)
{
	$lang_encoding = $lang . '.UTF-8';
	$textdomain = 'OPNsense';

	putenv('LANG=' . $lang_encoding);
	setlocale(LC_ALL, $lang_encoding);
	textdomain($textdomain);
	bindtextdomain($textdomain, '/usr/local/share/locale');
	bind_textdomain_codeset($textdomain, $lang_encoding);
}

function get_current_theme()
{
	global $config;

	$theme = 'opnsense';

	if (isset($config['theme']) && is_dir('/usr/local/opnsense/www/themes/' . $config['theme'])) {
		$theme = $config['theme'];
	}

	return $theme;
}

/* make sure nothing is cached */
if (isset($omit_nocacheheaders) && $omit_nocacheheaders) {
	header("Expires: 0");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
}

header("X-Frame-Options: SAMEORIGIN");

$g['theme'] = get_current_theme();

/* Set the default interface language */
if(isset($config['system']['language']) && $config['system']['language'] <> "") {
	$g['language'] = $config['system']['language'];
} elseif (!isset($g['language']) || $g['language'] == "") {
	$g['language'] = 'en_US';
}

set_language($g['language']);

require_once("authgui.inc");

/* Reserved table names to avoid colision */
$reserved_table_names = array(
        "bogons",
        "bogonsv6",
        "negate_networks",
        "sshlockout",
        "tonatsubnets",
        "virusprot",
        "vpn_networks",
        "webConfiguratorlockout"
);


$netbios_nodetypes = array(
	'0' => "none",
	'1' => "b-node",
	'2' => "p-node",
	'4' => "m-node",
	'5' => "h-node");

/* some well knows ports */
$wkports = array(
	5999 => "CVSup",
	53 => "DNS",
	21 => "FTP",
	3000 => "HBCI",
	80 => "HTTP",
	443 => "HTTPS",
	5190 => "ICQ",
	113 => "IDENT/AUTH",
	143 => "IMAP",
	993 => "IMAP/S",
	4500 => "IPsec NAT-T",
	500 => "ISAKMP",
	1701 => "L2TP",
	389 => "LDAP",
	1755 => "MMS/TCP",
	7000 => "MMS/UDP",
	445 => "MS DS",
	3389 => "MS RDP",
	1512 => "MS WINS",
	1863 => "MSN",
	119 => "NNTP",
	123 => "NTP",
	138 => "NetBIOS-DGM",
	137 => "NetBIOS-NS",
	139 => "NetBIOS-SSN",
	1194 => "OpenVPN",
	110 => "POP3",
	995 => "POP3/S",
	1723 => "PPTP",
	1812 => "RADIUS",
	1813 => "RADIUS accounting",
	5004 => "RTP",
	5060 => "SIP",
	25 => "SMTP",
	465 => "SMTP/S",
	161 => "SNMP",
	162 => "SNMP-Trap",
	22 => "SSH",
	3478 => "STUN",
	587 => "SUBMISSION",
	3544 => "Teredo",
	23 => "Telnet",
	69 => "TFTP",
	5900 => "VNC");

$wlan_modes = array(
	'bss' => 'Infrastructure (BSS)',
	'adhoc' => 'Ad-hoc (IBSS)',
	'hostap' => 'Access Point'
);

function do_input_validation($postdata, $reqdfields, $reqdfieldsn, &$input_errors)
{
	/* check for bad control characters */
	foreach ($postdata as $pn => $pd) {
		if (is_string($pd) && preg_match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f]/", $pd)) {
			$input_errors[] = sprintf(gettext("The field %s contains invalid characters."), $pn);
		}
	}

	for ($i = 0; $i < count($reqdfields); $i++) {
		// why.. do you want to do this? we send data to validate and then validate something else....
		// can't now for sure if any page uses this wrong behaviour, but it should die eventually.
		// TODO: kill
		if (empty($_POST[$reqdfields[$i]]) && empty($_REQUEST[$reqdfields[$i]])) {
			$input_errors[] = sprintf(gettext("The field %s is required."), $reqdfieldsn[$i]);
		}
	}
}

function print_input_errors($input_errors) {
	echo '<div class="col-xs-12"><div class="alert alert-danger alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
	';

	echo "<p>" . gettext("The following input errors were detected:") . "</p>\n<ul>";
	foreach ($input_errors as $ierr) {
		echo "<li>" . htmlspecialchars($ierr) . "</li>";
	}
	echo "</ul></div></div>";
}

function print_info_box_apply($msg)
{
	$value = gettext('Apply changes');
	$name= 'apply';

	$savebutton = '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
	$savebutton .= '<input name="' . $name . '" type="submit" class="btn btn-primary pull-right" id="' . $name . '" value="' . $value . '" />';
	if (!empty($_POST['if'])) {
		$savebutton .= '<input type="hidden" name="if" value="' . htmlspecialchars($_POST['if']) . '" />';
	}
	$savebutton .= '</form>';

	echo <<<EOFnp
	    <div class="col-xs-12">
		<div class="alert alert-info alert-dismissible" role="alert">
			{$savebutton}
            <p>{$msg}</p>
		</div>
		</div>
EOFnp;
}

function print_info_box($msg)
{
	$savebutton = '<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>';

	echo <<<EOFnp
<div class="col-xs-12">
	<div class="alert alert-info alert-dismissible" role="alert">
		{$savebutton}
		<p>{$msg}</p>
	</div>
</div>
EOFnp;
}

function print_warning_box($msg) {
	echo <<<EOFnp
<div class="col-xs-12">
	<div class="alert alert-warning alert-dismissible" role="alert">
		<p>{$msg}</p>
	</div>
</div>
EOFnp;
}

function is_service_enabled($service) {
	global $config;

	switch ($service) {
		case 'firewall':
		case 'filter':
			return !isset($config['system']['disablefilter']);
		default:
			return false;
	}
}

function print_service_disabled_banner($service) {
	if (!is_service_enabled($service)) {
		switch ($service) {
			case 'firewall':
			case 'filter':
				print_warning_box(gettext("The firewall has globally been disabled. Configured rules are currently not enforced."));
				break;
		}
	}
}

function get_std_save_message() {
	global $d_sysrebootreqd_path;
	$filter_related = false;
	$filter_pages = array("nat", "filter");
	$to_return = gettext("The changes have been applied successfully.");
	foreach($filter_pages as $fp)
		if(stristr($_SERVER['SCRIPT_FILENAME'], $fp))
			$filter_related = true;
	if($filter_related)
		$to_return .= "<br />" . gettext("You can also <a href=\"status_filter_reload.php\">monitor</a> the filter reload progress.");
	return $to_return;
}

function pprint_address($adr) {
	if (!isset($specialnets)) {
		global $specialnets;
		$specialnets = array("(self)" => "This Firewall", "pptp" => "PPTP clients", "pppoe" => "PPPoE clients", "l2tp" => "L2TP clients");

		$spiflist = get_configured_interface_with_descr(false, true);
		foreach ($spiflist as $ifgui => $ifdesc) {
			$specialnets[$ifgui] = $ifdesc . " net";
			$specialnets[$ifgui . 'ip'] = $ifdesc . " address";
		}
	}

	if (isset($adr['any'])) {
		$padr = "*";
	} else if (isset($adr['network'])) {
		$padr = $specialnets[$adr['network']];
	} else {
		$padr = isset($adr['address']) ? $adr['address'] : null;
	}

	if (isset($adr['not']))
		$padr = "! " . $padr;

	return $padr;
}

function pprint_port($port) {
	global $wkports;

	$pport = "";

	if (!$port)
		return "*";
	else {
		$srcport = explode("-", $port);
		if ((empty($srcport[1])) || ($srcport[0] == $srcport[1])) {
			$pport = $srcport[0];
			if (!empty($wkports[$srcport[0]])) {
				$pport .= " (" . $wkports[$srcport[0]] . ")";
			}
		} else
			$pport .= $srcport[0] . " - " . $srcport[1];
	}

	return $pport;
}

function gentitle($title) {
	$navlevelsep = ": ";	/* navigation level separator string */
	if(!is_array($title))
		return $title;
	else
		return join($navlevelsep, $title);
}

function clear_log($logfile, $restart_syslogd = true)
{
	if ($restart_syslogd) {
		killbyname('syslogd');
	}

	foreach (glob($logfile . '.*') as $rotated) {
		@unlink($rotated);
	}

	/* preserve file ownership and permissions */
	if (file_exists($logfile)) {
		$handle = fopen($logfile, 'r+');
		if ($handle) {
			ftruncate($handle, 0);
			fclose($handle);
		}
	}

	if ($restart_syslogd) {
		system_syslogd_start();
	}
}

function clear_clog($logfile, $restart_syslogd = true)
{
	global $config;

	if ($restart_syslogd) {
		killbyname('syslogd');
	}

	$log_size = isset($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : '511488';
	mwexecf('/usr/local/sbin/clog -i -s %s %s', array($log_size, $logfile));

	if ($restart_syslogd) {
		system_syslogd_start();
	}
}

function print_dump($logarr)
{
	global $config;

	if (!is_array($logarr)) {
		echo "<tr valign=\"top\">\n";
		echo "<td class=\"listlr\" colspan=\"2\">" . htmlspecialchars($logarr) . "</td>\n";
		echo "</tr>\n";
		return;
	}

	foreach ($logarr as $logent) {
		$logent = preg_split("/\s+/", $logent, 6);
		echo "<tr valign=\"top\">\n";
		$entry_date_time = htmlspecialchars(join(" ", array_slice($logent, 0, 3)));
		$entry_text = ($logent[3] ==  $config['system']['hostname']) ? "" : $logent[3] . " ";
		$entry_text .= htmlspecialchars($logent[4] . " " . $logent[5]);
		echo "<td class=\"listlr nowrap\">{$entry_date_time}</td>\n";
		echo "<td class=\"listr\">{$entry_text}</td>\n";
		echo "</tr>\n";
	}
}

function dump_clog($logfile, $tail, $grepfor = '')
{
	global $config;

	$sor = isset($config['syslog']['reverse']) ? '-r' : '';
	$logarr = array();
	$grepline = '';

	$grepfor = preg_split('/\s+/', trim($grepfor));

	foreach ($grepfor as $pattern) {
		$grepline .= ' | /usr/bin/egrep -i ' . escapeshellarg($pattern);
	}

	if (is_dir($logfile)) {
		$logarr = sprintf(gettext('File %s is a directory.'), $logfile);
	} elseif (!file_exists($logfile)) {
		$logarr = sprintf(gettext('File %s doesn\'t exist.'), $logfile);
	} else {
		exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " {$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
	}

	if (is_array($logarr) && !count($logarr)) {
		$logarr = sprintf(gettext('File %s yielded no results.'), $logfile);
	}

	print_dump($logarr);
}

function dump_log($logfile, $tail, $grepfor = '')
{
	global $config;

	$sor = isset($config['syslog']['reverse']) ? '-r' : '';
	$logarr = array();
	$grepline = '';

	$grepfor = preg_split('/\s+/', trim($grepfor));

	foreach ($grepfor as $pattern) {
		$grepline .= ' | /usr/bin/egrep -i ' . escapeshellarg($pattern);
	}

	if (is_dir($logfile)) {
		$logarr = sprintf(gettext('File %s is a directory.'), $logfile);
	} elseif (!file_exists($logfile)) {
		$logarr = sprintf(gettext('File %s doesn\'t exist.'), $logfile);
	} else {
		exec("cat " . escapeshellarg($logfile) . " {$grepline} | /usr/bin/tail {$sor} -n " . escapeshellarg($tail), $logarr);
	}

	if (is_array($logarr) && !count($logarr)) {
		$logarr = sprintf(gettext('File %s yielded no results.'), $logfile);
	}

	print_dump($logarr);
}

/* Check if variable has changed, update and log if it has
 * returns true if var changed
 * varname = variable name in plain text
 * orig = original value
 * new = new value
 */
function update_if_changed($varname, &$orig, $new)
{
	if (is_array($orig) && is_array($new)) {
		$a_diff = array_diff($orig, $new);
		$a_diff = array_diff($new, $orig);
		$orig = $new;
		return true;
	}

	if ($orig != $new) {
		$orig = $new;
		return true;
	}

	return false;
}

function address_to_pconfig($adr, &$padr, &$pmask, &$pnot, &$pbeginport, &$pendport) {
	if (isset($adr['any']))
		$padr = "any";
	else if (isset($adr['network']))
		$padr = $adr['network'];
	else if (isset($adr['address'])) {
		if (strpos($adr['address'], '/') !== false) {
			list($padr, $pmask) = explode("/", $adr['address']);
		} else {
			$padr = $adr['address'];
			if (is_ipaddrv6($padr))
				$pmask = 128;
			else
				$pmask = 32;
		}

	}

	if (isset($adr['not']))
		$pnot = 1;
	else
		$pnot = 0;

	if (isset($adr['port'])) {
		if (strpos($adr['port'], '-') !== false) {
			list($pbeginport, $pendport) = explode("-", $adr['port']);
		} else {
			$pbeginport = $adr['port'];
			$pendport = $pbeginport;
		}
	} else if (!is_alias($pbeginport) && !is_alias($pendport)) {
		$pbeginport = "any";
		$pendport = "any";
	}
}

function pconfig_to_address(&$adr, $padr, $pmask, $pnot=false, $pbeginport=0, $pendport=0) {
		$adr = array();
		if ($padr == "any") {
				$adr['any'] = true;
		} elseif (is_specialnet($padr)) {
				$adr['network'] = $padr;
		} elseif (is_alias($padr)) {
				$adr['address'] = $padr;
		} else {
			$adr['address'] = $padr;
			if (is_ipaddrv6($padr)) {
				if ($pmask != 128)
					$adr['address'] .= "/" . $pmask;
			} else {
				if ($pmask != 32)
					$adr['address'] .= "/" . $pmask;
			}
		}

		if ($pnot) {
				$adr['not'] = true;
		} elseif (isset($adr['not'])) {
				unset($adr['not']);
		}

		if(is_alias($pbeginport)) {
				$adr['port'] = $pbeginport;
		} elseif (($pbeginport != 0) && ($pbeginport != "any")) {
				if ($pbeginport != $pendport) {
						$adr['port'] = $pbeginport . "-" . $pendport;
				} else {
						$adr['port'] = $pbeginport;
				}
		}
}

function is_specialnet($net) {
	global $specialsrcdst;

	if(!$net)
		return false;
	if (is_array($specialsrcdst) && in_array($net, $specialsrcdst))
		return true;
	else
		return false;
}



/****f* guiconfig/display_top_tabs
 * NAME
 *   display_top_tabs - display tabs with rounded edges
 * INPUTS
 *   $text      - array of tabs
 * RESULT
 *   null
 ******/
function display_top_tabs(& $tab_array, $no_drop_down = false)
{
	global $config;

	$tab_array_char_limit = 92;

	/*  does the user have access to this tab?
	 *  master user has access to everything.
	 *  if the user does not have access, simply
	 *  unset the tab item.
	 */
	foreach ($tab_array as $tab_id => $ta){
		if(!isAllowedPage($ta[2]))
			unset ($tab_array[$tab_id]);
	}

	$tabcharcount = 0;
	foreach ($tab_array as $ta)
		$tabcharcount = $tabcharcount + strlen($ta[0]);

	if($no_drop_down == true) {
		$tabcharcount = 0;
		unset($tab_array_char_limit);
	}

	// If the character count of the tab names is > 670
	// then show a select item dropdown menubox.
	if($tabcharcount > $tab_array_char_limit) {
		echo "<h2>".gettext("Currently viewing: ")."</h2>";
		echo "<select class=\"selectpicker\" name=\"TabSelect\" onchange=\"tabs_will_go(this)\" data-live-search=\"true\" data-style=\"btn-default\">\n";
		foreach ($tab_array as $ta) {
			if($ta[1]=="true")
				$selected = " selected=\"selected\"";
			else
				$selected = "";
			// Onclick in option will not work in some browser
			// echo "<option onclick=\"document.location='{$ta[2]}';\"{$selected}>{$ta['0']}</option>\n";
			echo "<option value=\"{$ta[2]}\"{$selected}>{$ta['0']}</option>\n";
		}
		echo "</select>\n<p>&nbsp;</p>";
		echo "<script type=\"text/javascript\">";
		echo "\n//<![CDATA[\n";
		echo " function tabs_will_go(obj){ document.location = obj.value; }\n";
		echo "//]]>\n";
		echo "</script>";
	}  else {
		echo "<!-- Tabbed bar code-->\n";
		echo "<ul class=\"nav nav-tabs nav-justified\" role=\"tablist\">\n";
		$tabscounter = 0;
		foreach ($tab_array as $ta) {
			if ($ta[1] == true) {
				echo "  <li class=\"active\"><a href=\"{$ta[2]}\" role=\"tab\">{$ta[0]}</a></li>\n";
			} else {
				echo "  <li><a href=\"{$ta[2]}\" role=\"tab\">{$ta[0]}</a></li>\n";
			}
			$tabscounter++;
		}
		echo "</ul>\n";
	}
}

$timezone = $config['system']['timezone'];
if (!$timezone) {
	$timezone = 'Etc/UTC';
}

date_default_timezone_set($timezone);

function get_crash_report($pedantic = false)
{
	$savemsg = sprintf(
		gettext('A problem was detected. Click %shere%s for more information.'),
		'<a href="/crash_reporter.php">',
		'</a>'
	);
	$skip_files = array('.', '..', 'minfree', 'bounds', '');
	$PHP_errors_log = '/tmp/PHP_errors.log';
	$excludes = array();
	$count = 0;

	if (!$pedantic) {
		$excludes[] = 'PHP Notice:';
		$excludes[] = 'PHP Startup: Unable to load dynamic library \'/usr/local/lib/php/20131226/suhosin.so\' - /usr/local/lib/php/20131226/suhosin.so: Undefined symbol "ps_globals" in Unknown on line 0';
	}

	if (file_exists($PHP_errors_log)) {
		$extra = '';
		foreach ($excludes as $exclude) {
			$extra .= sprintf('/usr/bin/grep -v %s | ', escapeshellarg($exclude));
		}
		$total = shell_exec(sprintf(
			'/bin/cat %s | %s /usr/bin/wc -l | /usr/bin/awk \'{ print $1 }\'',
			$PHP_errors_log,
			$extra
		));
		if ($total > 0) {
			$count++;
		}
	}

	$crashes = glob('/var/crash/*');
	foreach ($crashes as $crash) {
		if (!in_array(basename($crash), $skip_files)) {
			$count++;
		}
	}

	if (!$count) {
		$savemsg = '';
	}

	return $savemsg;
}

function get_menu_messages()
{
	global $config;

	$menu_messages = '';
	if (are_notices_pending()) {
		$notices = get_notices();
		$requests = array();

		## Get Query Arguments from URL ###
		foreach ($_REQUEST as $key => $value) {
			if ($key != "PHPSESSID") {
				$requests[] = $key.'='.$value;
			}
		}

		if (is_array($requests)) {
			$request_string = implode("&", $requests);
		}

		if (is_array($notices)) {
			$notice_msgs = "<ul class=\"dropdown-menu\" role=\"menu\">";

			$notice_msgs .= "<li><a href=\"#\" onclick=\"notice_action('acknowledge','all');\" >".gettext("Acknowledge All Notices")."</a></li><li class=\"divider\"></li>";

			foreach ($notices as $key => $value) {
				$date = date("m-d-y H:i:s", $key);
				$noticemsg = ($value['notice'] != "" ? $value['notice'] : $value['id']);
				$noticemsg = preg_replace("/(\"|\'|\n|<.?\w+>)/i","",$noticemsg);
				$alert_action ="onclick=\"notice_action('acknowledge','{$key}'); jQuery(this).parent().parent().remove();\"";
				$notice_msgs .= "<li><a href=\"#\"  {$alert_action}>{$date} [ ".htmlspecialchars($noticemsg)."]</a></li>";
			}

			$notice_msgs .="</ul>";

			if (count($notices) == 1) {
				$msg= sprintf("%1$02d",count($notices))." ".gettext("unread notice");
			} else {
				$msg= sprintf("%1$02d",count($notices))." ".gettext("unread notices");
			}

			$menu_messages.="<a href=\"/\" class=\"dropdown-toggle \" data-toggle=\"dropdown\" role=\"button\" aria-expanded=\"false\"><span class=\"text-primary\">{$msg}&nbsp;</span><span class=\"caret text-primary\"></span></a>{$notice_msgs}\n";
		}
	} else {
		$menu_messages .= sprintf(
			'<a href="#">%s@%s.%s</a>',
			$_SESSION['Username'],
			$config['system']['hostname'],
			$config['system']['domain']
		);
	}

	return ($menu_messages);
}
