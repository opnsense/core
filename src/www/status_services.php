<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
    Copyright (C) 2004-2005 Scott Ullrich
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
require_once("vslb.inc");
require_once("system.inc");
require_once("unbound.inc");
require_once("pfsense-utils.inc");
require_once("openvpn.inc");
require_once("filter.inc");
require_once("vpn.inc");
require_once("interfaces.inc");
require_once("rrd.inc");

function openvpn_restart_by_vpnid($mode, $vpnid)
{
    $settings = openvpn_get_settings($mode, $vpnid);
    openvpn_restart($mode, $settings);
}

if (!empty($_GET['service'])) {
    $service_name = $_GET['service'];
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
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
        if (strpos($referer, $_SERVER['PHP_SELF']) === false) {
            /* redirect only if launched from somewhere else */
            header('Location: '. $referer);
            exit;
        }
    }
}

function service_control_start($name, $extras)
{
    $msg = sprintf(gettext('%s has been started.'), htmlspecialchars($name));

    /* XXX openvpn is handled special at the moment */
    if ($name == 'openvpn') {
        $vpnmode = isset($extras['vpnmode']) ? htmlspecialchars($extras['vpnmode']) : htmlspecialchars($extras['mode']);
        if (($vpnmode == "server") || ($vpnmode == "client")) {
            $id = isset($extras['vpnid']) ? htmlspecialchars($extras['vpnid']) : htmlspecialchars($extras['id']);
            $configfile = "/var/etc/openvpn/{$vpnmode}{$id}.conf";
            if (file_exists($configfile)) {
                openvpn_restart_by_vpnid($vpnmode, $id);
            }
        }
        return $msg;
    /* XXX extra argument is extra tricky */
    } elseif ($name == 'miniupnpd') {
        upnp_action('start');
        return $msg;
    }

    $service = find_service_by_name($name);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not start unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['start'])) {
        foreach ($service['configd']['start'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['start'])) {
        foreach ($service['php']['start'] as $cmd) {
            $cmd();
        }
    } elseif (isset($service['mwexec']['start'])) {
        foreach ($service['mwexec']['start'] as $cmd) {
            mwexec($cmd);
        }
    } else {
        $msg = sprintf(gettext("Could not launch service `%s'"), htmlspecialchars($name));
    }

    return $msg;
}

function service_control_stop($name, $extras)
{
    $msg = sprintf(gettext("%s has been stopped."), htmlspecialchars($name));

    switch ($name) {
        case 'igmpproxy':
            killbyname("igmpproxy");
            return $msg;
        case 'miniupnpd':
            upnp_action('stop');
            return $msg;
        case 'sshd':
            killbyname("sshd");
            return $msg;
        case 'openvpn':
            $vpnmode = htmlspecialchars($extras['vpnmode']);
            if (($vpnmode == "server") or ($vpnmode == "client")) {
                $id = htmlspecialchars($extras['id']);
                $pidfile = "/var/run/openvpn_{$vpnmode}{$id}.pid";
                killbypid($pidfile);
            }
            return $msg;
        case 'relayd':
            killbyname('relayd');
            return $msg;
        default:
            break;
    }

    $service = find_service_by_name($name);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not stop unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['stop'])) {
        foreach ($service['configd']['stop'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['stop'])) {
        foreach ($service['php']['stop'] as $cmd) {
            $cmd();
        }
    } elseif (isset($service['mwexec']['stop'])) {
        foreach ($service['mwexec']['stop'] as $cmd) {
            mwexec($cmd);
        }
    } elseif (isset($service['pidfile'])) {
        killbypid($service['pidfile']);
    } else {
        $msg = sprintf(gettext("Could not stop service `%s'"), htmlspecialchars($name));
    }

    return $msg;
}


function service_control_restart($name, $extras) {
    switch($name) {
        case 'radvd':
            services_radvd_configure();
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
                if (file_exists($configfile)) {
                    openvpn_restart_by_vpnid($vpnmode, $id);
                }
            }
            break;
        case 'relayd':
            relayd_configure(true);
            filter_configure();
            break;
        case 'squid':
            configd_run("proxy restart");
            break;
        case 'suricata':
            configd_run("ids restart");
            break;
        case 'configd':
            mwexec('/usr/local/etc/rc.d/configd restart');
            break;
        case 'captiveportal':
            configd_run("captiveportal restart");
            break;
        default:
            log_error(sprintf(gettext("Could not restart unknown service `%s'"), $name));
            break;
    }
    return sprintf(gettext("%s has been restarted."),htmlspecialchars($name));
}

$services = services_get();

if (count($services) > 0) {
    uasort($services, "service_name_compare");
}

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <div class="table-responsive">
              <table class="table table-striped">
              <thead>
                <tr>
                  <td><?=gettext("Service");?></td>
                  <td><?=gettext("Description");?></td>
                  <td><?=gettext("Status");?></td>
                </tr>
              </thead>
              <tbody>
<?php
               if (count($services) > 0):
                foreach($services as $service):?>
                <tr>
                    <td><?=$service['name'];?></td>
                    <td><?=$service['description'];?></td>
                    <td>
                      <?=get_service_status_icon($service, true, true);?>
                      <?=get_service_control_links($service);?>
                    </td>
                </tr>
<?php
                endforeach;
              else:?>
                <tr>
                  <td colspan="3"> <?=gettext("No services found");?></td>
                </tr>
<?php
                 endif;?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
