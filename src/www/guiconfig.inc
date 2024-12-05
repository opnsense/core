<?php

/*
 * Copyright (C) 2014-2021 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/* check before 'product' class is known to catch all errors */
if (file_exists('/var/run/development')) {
    ini_set('display_errors', 0);
}

require_once("util.inc");
require_once("config.inc");

/* CSRF BEGIN: CHECK MUST BE EXECUTED FIRST; NO EXCEPTIONS */
require_once('csrf.inc');
// hardening
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' 'unsafe-eval';");
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: same-origin');
/* CSRF END: THANK YOU FOR YOUR COOPERATION */

function html_safe($text)
{
    /* gettext() embedded in JavaScript can cause syntax errors */
    return str_replace("\n", '&#10;', htmlspecialchars($text ?? '', ENT_QUOTES | ENT_HTML401));
}

function cache_safe($url)
{
    $info = stat('/usr/local/opnsense/www/index.php');
    if (!empty($info['mtime'])) {
        return "{$url}?v=" . substr(md5($info['mtime']), 0, 16);
    }

    return html_safe($url);
}

function get_current_lang()
{
    global $config, $userindex;

    $lang = 'en_US';

    if (!empty($config['system']['language'])) {
        $lang = $config['system']['language'];
    }

    if (
        !empty($_SESSION['Username']) && array_key_exists($_SESSION['Username'], $userindex) &&
        !empty($config['system']['user'][$userindex[$_SESSION['Username']]]['language'])
    ) {
        $lang = $config['system']['user'][$userindex[$_SESSION['Username']]]['language'];
    }

    /* a language code as per RFC3066 */
    return html_safe(str_replace('_', '-', $lang));
}

/**
 * search for a themed filename or return distribution standard
 * @param string $url relative url
 * @return string
 */
function get_themed_filename($url, $exists = false)
{
    global $config;

    $theme = 'opnsense';

    if (isset($config['theme']) && is_dir('/usr/local/opnsense/www/themes/' . $config['theme'])) {
        $theme = $config['theme'];
    }

    foreach (["/themes/{$theme}/build/", '/'] as $pattern) {
        $filename = "/usr/local/opnsense/www{$pattern}{$url}";
        if (file_exists($filename)) {
            return $exists ? true : str_replace('//', '/', "/ui{$pattern}{$url}");
        }
    }

    /* return source when not found */
    return $exists ? false : $url;
}

require_once("authgui.inc");

/* Reserved table names to avoid collision */
$reserved_table_names = [
    'bogons',
    'bogonsv6',
    'sshlockout',
    'virusprot',
];

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

function do_input_validation($postdata, $reqdfields, $fielddescr, &$input_errors)
{
    /* if $fielddescr is a mapping (name => description) we can use it in our errors, otherwise when it only contains descriptions, we need to fetch the name from the required fields */
    $fieldnames = [];
    foreach ($fielddescr as $seq => $descr) {
        if (preg_match('/^\d+$/', $seq)) {
            if (isset($reqdfields[$seq])) {
                $fieldnames[$reqdfields[$seq]] = $descr;
            }
        } else {
            $fieldnames[$seq] = $descr;
        }
    }

    /* check for bad control characters */
    foreach ($postdata as $pn => $pd) {
        if (is_string($pd) && preg_match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f]/", $pd)) {
            $input_errors[] = sprintf(gettext("The field %s contains invalid characters."), $fieldnames[$pn] ?? $pn);
        }
    }

    if (!empty($reqdfields)) {
        for ($i = 0; $i < count($reqdfields); $i++) {
            $fields = explode(',', $reqdfields[$i]);
            $found = false;

            foreach ($fields as $field) {
                if (!empty($postdata[$field])) {
                    $found = true;
                }
            }

            if (!$found) {
                $input_errors[] = sprintf(gettext("The field %s is required."), $fieldnames[$reqdfields[$i]]);
            }
        }
    }
}

function print_input_errors($input_errors)
{
    echo '<div class="col-xs-12"><div class="alert alert-danger" role="alert">
          <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
    ';

    echo "<p>" . gettext("The following input errors were detected:") . "</p>\n<ul>";
    foreach ($input_errors as $ierr) {
        echo "<li>" . htmlspecialchars($ierr) . "</li>";
    }
    echo "</ul></div></div>";
}

function print_alert_box($msg, $alert = 'warning', $button = '')
{
    echo <<<EOFnp
<div class="col-xs-12">
  <div class="alert alert-{$alert}" role="alert" style="min-height: 65px;">
    {$button}
    <div style="margin-top: 8px;">{$msg}</div>
  </div>
</div>

EOFnp;
}

function print_info_box_apply($msg, $alert = 'info')
{
    $iface = !empty($_POST['if']) ? $_POST['if'] : (!empty($_GET['if']) ? $_GET['if'] : '');
    $label = gettext('Apply changes');
    $value = 'Apply changes';
    $name= 'apply';

    $savebutton = '<form method="post">';
    $savebutton .= sprintf(
        '<button type="submit" name="%s" id="%s" class="btn btn-primary pull-right" value="%s">%s</button>',
        $name,
        $name,
        $value,
        $label
    );
    if (!empty($iface)) {
        $savebutton .= sprintf(
            '<input type="hidden" name="if" value="%s"/>',
            htmlspecialchars($iface)
        );
    }
    $savebutton .= '</form>';

    print_alert_box($msg, $alert, $savebutton);
}

function print_info_box($msg)
{
    print_alert_box($msg, 'info');
}

function print_firewall_banner()
{
    global $config;

    if (isset($config['system']['disablefilter'])) {
        print_alert_box(sprintf(
            gettext(
                'The firewall has globally been disabled and configured rules are ' .
                'currently not enforced. It can be enabled in the %sFirewall/NAT%s ' .
                'settings.'
            ),
            '<a href="/system_advanced_firewall.php">',
            '</a>'
        ));
    }
}

function get_std_save_message($plain = false)
{
    global $config;

    /* we need this wrapped in gettext at least once */
    if (!empty($config['hasync']['synchronizetoip'])) {
        if (!$plain) {
            $lnk_start = '<a href="status_habackup.php">';
            $lnk_end = '</a>';
        }
        return sprintf(
            gettext("The changes have been applied successfully, ".
                    "remember to update your backup server in %sSystem: High availability: status%s"),
            $lnk_start, $lnk_end
        );
    } else {
        return gettext('The changes have been applied successfully.');
    }
}

function get_specialnets($only_enabled = false)
{
    $specialnets = array();
    $specialnets["any"] = gettext("any");
    $specialnets["(self)"] = gettext("This Firewall");
    $filter = $only_enabled ? array("enable" => true) : array();
    foreach (legacy_config_get_interfaces($filter) as $ifent => $ifdetail) {
        $specialnets[$ifent] = htmlspecialchars($ifdetail['descr']) . " " . gettext("net");
        if (!isset($ifdetail['virtual'])) {
            $specialnets[$ifent."ip"] = htmlspecialchars($ifdetail['descr']). " ". gettext("address");
        }
    }

    return $specialnets;
}

function pprint_address($adr) {
    $specialnets = get_specialnets();
    if (isset($adr['any'])) {
        $padr = "*";
    } elseif (isset($adr['network'])) {
        $padr = $specialnets[$adr['network']];
    } else {
        $padr = isset($adr['address']) ? $adr['address'] : null;
    }

    if (isset($adr['not'])) {
        $padr = "! " . $padr;
    }

    return $padr;
}

function pprint_port($port) {
    global $wkports;

    $pport = "";

    if ($port == '') {
        return "*";
    } else {
        $srcport = explode("-", $port);
        if ((empty($srcport[1])) || ($srcport[0] == $srcport[1])) {
            $pport = $srcport[0];
            if (!empty($wkports[$srcport[0]])) {
                $pport .= " (" . $wkports[$srcport[0]] . ")";
            }
        } else {
            $pport .= $srcport[0] . " - " . $srcport[1];
        }
    }

    return $pport;
}

function gentitle($breadcrumbs, $navlevelsep = ': ')
{
    global $gentitle_suffix;
    $output = $breadcrumbs;

    if (isset($breadcrumbs[0]['name'])) {
        $output = array();
        foreach ($breadcrumbs as $crumb) {
            $output[] = gettext($crumb['name']);
        }
    }

    return join($navlevelsep, $output) . "$gentitle_suffix";
}

function address_to_pconfig($adr, &$padr, &$pmask, &$pnot, &$pbeginport, &$pendport, $merge_mask=false)
{
    if (isset($adr['any'])) {
        $padr = "any";
    } elseif (isset($adr['network'])) {
        $padr = $adr['network'];
    } elseif (isset($adr['address'])) {
        if (strpos($adr['address'], '/') !== false) {
            list($padr, $pmask) = explode("/", $adr['address']);
        } else {
            $padr = $adr['address'];
            if (is_ipaddrv6($padr)) {
                $pmask = 128;
            } else {
                $pmask = 32;
            }
        }
        if ($merge_mask && is_ipaddr($padr)) {
            $padr = $padr . '/' . $pmask;
        }
    }

    if (isset($adr['not'])) {
        $pnot = 1;
    } else {
        $pnot = 0;
    }

    if (isset($adr['port'])) {
        if (strpos($adr['port'], '-') !== false) {
            list($pbeginport, $pendport) = explode("-", $adr['port']);
        } else {
            $pbeginport = $adr['port'];
            $pendport = $pbeginport;
        }
    } elseif (!is_alias($pbeginport) && !is_alias($pendport)) {
        $pbeginport = "any";
        $pendport = "any";
    }
}

function pconfig_to_address(&$adr, $padr, $pmask, $pnot = false, $pbeginport = 0, $pendport = 0)
{
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
            if ($pmask != '' && $pmask != 128) {
                $adr['address'] .= "/" . $pmask;
            }
        } else {
            if ($pmask != '' && $pmask != 32) {
                $adr['address'] .= "/" . $pmask;
            }
        }
    }

    if ($pnot) {
        $adr['not'] = true;
    } elseif (isset($adr['not'])) {
        unset($adr['not']);
    }

    if (is_alias($pbeginport)) {
        $adr['port'] = $pbeginport;
    } elseif ($pbeginport !== 0) {
        if ($pbeginport != $pendport && !empty($pendport)) {
            $adr['port'] = $pbeginport . "-" . $pendport;
        } elseif ($pbeginport !== "any") {
            $adr['port'] = $pbeginport;
        }
    }
}

function is_specialnet($net)
{
    if (in_array($net, array('any','(self)'))) {
        return true;
    } else {
        foreach (legacy_config_get_interfaces(array("enable" => true)) as $ifent => $ifdetail) {
            if ($ifent == $net || (!isset($ifdetail['virtual']) &&  $ifent."ip" == $net)) {
                return true;
            }
        }
    }
    return false;
}

$timezone = $config['system']['timezone'];
if (!$timezone) {
    $timezone = 'Etc/UTC';
}

date_default_timezone_set($timezone);

function get_menu_user()
{
    global $config;

    return sprintf(
      '<span class="navbar-text">%s@%s.%s</span>',
      $_SESSION['Username'],
      $config['system']['hostname'],
      $config['system']['domain']
    );
}

function service_control_icon($service, $xs = false)
{
    $output = '';

    if (service_status($service)) {
        $output .= '<span class="label label-opnsense label-opnsense-%s label-success"><i class="fa fa-play fa-fw"></i></span>' . PHP_EOL;
    } else {
        $output .= '<span class="label label-opnsense label-opnsense-%s label-danger"><i class="fa fa-stop fa-fw"></i></span>' . PHP_EOL;
    }

    return sprintf($output, $xs ? 'xs' : 'sm');
}

function service_control_links($service, $xs = false)
{
    $service_id = isset($service['id']) ? $service['id'] : '';

    $template  = '<span data-service_id="%s" data-service_action="%s" data-service="%s" ';
    $template .= 'class="btn btn-%s btn-default %s" data-toggle="tooltip" data-placement="bottom" ';
    $template .= 'title="%s"><i class="%s"></i></span>' . PHP_EOL;

    $output = '';

    if (service_status($service)) {
        $output .= sprintf(
            $template,
            $service_id,
            'restart',
            $service['name'],
            $xs ? 'xs' : 'sm',
            'srv_status_act',
            gettext("restart service"),
            'fa fa-repeat fa-fw'
        );
        if (empty($service['nocheck']) && empty($service['locked'])) {
            $output .= sprintf(
                $template,
                $service_id,
                'stop',
                $service['name'],
                $xs ? 'xs' : 'sm',
                'srv_status_act',
                gettext("stop service"),
                'fa fa-stop fa-fw'
            );
        }
    } else {
        $output .= sprintf(
            $template,
            $service_id,
            'start',
            $service['name'],
            $xs ? 'xs' : 'sm',
            'srv_status_act',
            gettext("start service"),
            'fa fa-play fa-fw'
        );
    }

    return $output;
}
