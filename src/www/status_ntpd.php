<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2013 Dagorlad
 * Copyright (C) 2012 Jim Pingle <jimp@pfsense.org>
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

require_once("guiconfig.inc");
require_once("interfaces.inc");

if (!isset($config['ntpd']['noquery'])) {
    exec("/usr/local/sbin/ntpq -pnw | /usr/bin/tail +3", $ntpq_output);
    $ntpq_servers = array();
    $server = array();
    foreach ($ntpq_output as $line) {
        $status = gettext('Unknown');
        switch (substr($line, 0, 1)) {
            case ' ':
                $status = gettext('Unreach/Pending');
                break;
            case '*':
                $status = gettext('Active Peer');
                break;
            case '+':
                $status = gettext('Candidate');
                break;
            case 'o':
                $status = gettext('PPS Peer');
                break;
            case '#':
                $status = gettext('Selected');
                break;
            case '.':
                $status = gettext('Excess Peer');
                break;
            case 'x':
                $status = gettext('False Ticker');
                break;
            case '-':
                $status = gettext('Outlier');
                break;
        }
        if (empty($server['status'])) {
            $server['status'] = $status;
        }
        $line = substr($line, 1);
        $peerinfo = preg_split('/\s+/', $line);
        if (empty($server['server'])) {
            $server['server'] = $peerinfo[0];
        }
        if (empty($peerinfo[1])) {
            continue;
        }
        $server['refid'] = $peerinfo[1];
        $server['stratum'] = $peerinfo[2];
        $server['type'] = $peerinfo[3];
        $server['when'] = $peerinfo[4];
        $server['poll'] = $peerinfo[5];
        $server['reach'] = $peerinfo[6];
        $server['delay'] = $peerinfo[7];
        $server['offset'] = $peerinfo[8];
        $server['jitter'] = $peerinfo[9];
        $ntpq_servers[] = $server;
        $server = array();
    }

    exec("/usr/local/sbin/ntpq -c clockvar", $ntpq_clockvar_output);
    foreach ($ntpq_clockvar_output as $line) {
        if (substr($line, 0, 9) == "timecode=") {
            $tmp = explode('"', $line);
            $tmp = $tmp[1];
            $gps_vars = explode(',', $tmp);
            if (substr($tmp, 0, 6) == '$GPRMC') {
                if (is_numeric($gps_vars[3]) && is_numeric($gps_vars[5])) {
                    list ($gps_lat_deg, $gps_lat_min) = explode('.', $gps_vars[3]);
                    $gps_lat_min = substr($gps_lat_deg, -2) .".". $gps_lat_min;
                    $gps_lat_deg = substr($gps_lat_deg, 0, strlen($gps_lat_deg) - 2);
                    $gps_lat_min /= 60.0;
                    $gps_lat = $gps_lat_deg + $gps_lat_min;
                    $gps_lat_dir = $gps_vars[4];
                    $gps_lat = $gps_lat * ($gps_lat_dir == 'N' ? 1 : -1);

                    list ($gps_lon_deg, $gps_lon_min) = explode('.', $gps_vars[5]);
                    $gps_lon_min = substr($gps_lon_deg, -2) .".". $gps_lon_min;
                    $gps_lon_deg = substr($gps_lon_deg, 0, strlen($gps_lon_deg) - 2);
                    $gps_lon_min /= 60.0;
                    $gps_lon = $gps_lon_deg + $gps_lon_min;
                    $gps_lon_dir = $gps_vars[6];
                    $gps_lon = $gps_lon * ($gps_lon_dir == 'E' ? 1 : -1);
                }

                $gps_ok = $gps_vars[2] == 'A';
            } elseif (substr($tmp, 0, 6) == '$GPGGA') {
                if (is_numeric($gps_vars[2]) && is_numeric($gps_vars[4])) {
                    list ($gps_lat_deg, $gps_lat_min) = explode('.', $gps_vars[2]);
                    $gps_lat_min = substr($gps_lat_deg, -2) .".". $gps_lat_min;
                    $gps_lat_deg = substr($gps_lat_deg, 0, strlen($gps_lat_deg) - 2);
                    $gps_lat_min /= 60.0;
                    $gps_lat = $gps_lat_deg + $gps_lat_min;
                    $gps_lat_dir = $gps_vars[3];
                    $gps_lat = $gps_lat * ($gps_lat_dir == 'N' ? 1 : -1);

                    list ($gps_lon_deg, $gps_lon_min) = explode('.', $gps_vars[4]);
                    $gps_lon_min = substr($gps_lon_deg, -2) .".". $gps_lon_min;
                    $gps_lon_deg = substr($gps_lon_deg, 0, strlen($gps_lon_deg) - 2);
                    $gps_lon_min /= 60.0;
                    $gps_lon = $gps_lon_deg + $gps_lon_min;
                    $gps_lon_dir = $gps_vars[5];
                    $gps_lon = $gps_lon * ($gps_lon_dir == 'E' ? 1 : -1);

                }

                $gps_ok = $gps_vars[6];
                $gps_alt = $gps_vars[9];
                $gps_alt_unit = $gps_vars[10];
                $gps_sat = $gps_vars[7];
            } elseif (substr($tmp, 0, 6) == '$GPGLL') {
                if (is_numeric($gps_vars[1]) && is_numeric($gps_vars[3])) {
                    list ($gps_lat_deg, $gps_lat_min) = explode('.', $gps_vars[1]);
                    $gps_lat_min = substr($gps_lat_deg, -2) .".". $gps_lat_min;
                    $gps_lat_deg = substr($gps_lat_deg, 0, strlen($gps_lat_deg) - 2);
                    $gps_lat_min /= 60.0;
                    $gps_lat = $gps_lat_deg + $gps_lat_min;
                    $gps_lat_dir = $gps_vars[2];
                    $gps_lat = $gps_lat * ($gps_lat_dir == 'N' ? 1 : -1);

                    list ($gps_lon_deg, $gps_lon_min) = explode('.', $gps_vars[3]);
                    $gps_lon_min = substr($gps_lon_deg, -2) .".". $gps_lon_min;
                    $gps_lon_deg = substr($gps_lon_deg, 0, strlen($gps_lon_deg) - 2);
                    $gps_lon_min /= 60.0;
                    $gps_lon = $gps_lon_deg + $gps_lon_min;
                    $gps_lon_dir = $gps_vars[4];
                    $gps_lon = $gps_lon * ($gps_lon_dir == 'E' ? 1 : -1);

                }

                $gps_ok = $gps_vars[6] == 'A';
            }
        }
    }
}

if (isset($config['ntpd']['gps']['type']) && ($config['ntpd']['gps']['type'] == 'SureGPS') && (isset($gps_ok))) {
    //GSV message is only enabled by init commands in services_ntpd_gps.php for SureGPS board
    $gpsport = fopen("/dev/gps0", "r+");
    while ($gpsport) {
        $buffer = fgets($gpsport);
        if (substr($buffer, 0, 6) == '$GPGSV') {
            $gpgsv = explode(',',$buffer);
            $gps_satview = $gpgsv[3];
            break;
        }
    }
}

$service_hook = 'ntpd';
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <div class="content-box">
          <header class="content-box-head container-fluid">
            <h3><?=gettext("Network Time Protocol Status");?></h3>
          </header>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th><?=gettext("Status"); ?></th>
                  <th><?=gettext("Server"); ?></th>
                  <th><?=gettext("Ref ID"); ?></th>
                  <th><?=gettext("Stratum"); ?></th>
                  <th><?=gettext("Type"); ?></th>
                  <th><?=gettext("When"); ?></th>
                  <th><?=gettext("Poll"); ?></th>
                  <th><?=gettext("Reach"); ?></th>
                  <th><?=gettext("Delay"); ?></th>
                  <th><?=gettext("Offset"); ?></th>
                  <th><?=gettext("Jitter"); ?></th>
                </tr>
              </thead>
              <tbody>
<?php
              if (isset($config['ntpd']['noquery'])): ?>
                <tr>
                  <td colspan="11">
                    <?= sprintf(gettext('Statistics unavailable because ntpq and ntpdc queries are disabled in the %sNTP service settings%s.'), '<a href="services_ntpd.php">','</a>') ?>
                  </td>
                </tr>
<?php
              elseif (count($ntpq_servers) == 0): ?>
                <tr>
                  <td colspan="11">
                    <?= sprintf(gettext('No peers found, %sis the ntp service running%s?'), '<a href="status_services.php">','</a>') ?>
                  </td>
                </tr>
<?php
              else:
              $i = 0;
              foreach ($ntpq_servers as $server): ?>
                <tr>
                  <td><?=$server['status'];?></td>
                  <td><?=$server['server'];?></td>
                  <td><?=$server['refid'];?></td>
                  <td><?=$server['stratum'];?></td>
                  <td><?=$server['type'];?></td>
                  <td><?=$server['when'];?></td>
                  <td><?=$server['poll'];?></td>
                  <td><?=$server['reach'];?></td>
                  <td><?=$server['delay'];?></td>
                  <td><?=$server['offset'];?></td>
                  <td><?=$server['jitter'];?></td>
                </tr>
<?php
                $i++;
                endforeach;
              endif; ?>
              </tbody>
            </table>
<?php
            if ($gps_ok):
            $gps_goo_lnk = 2; ?>
            <table class="table table-striped">
              <thead>
                <tr>
                  <th><?=gettext("Clock Latitude"); ?></th>
                  <th><?=gettext("Clock Longitude"); ?></th>
                  <?php if (isset($gps_alt)) { echo '<th>' . gettext("Clock Altitude") . '</th>'; $gps_goo_lnk++;}?>
                  <?php if (isset($gps_sat) || isset($gps_satview)) { echo '<th>' . gettext("Satellites") . '</th>'; $gps_goo_lnk++;}?>
                </tr>
              </thead>
              <tbody>
                <tr>
<?php if (isset($gps_lat)): ?>
                  <td><?= sprintf("%.5f", $gps_lat); ?> (<?= sprintf("%d", $gps_lat_deg); ?>&deg; <?= sprintf("%.5f", $gps_lat_min*60); ?><?= $gps_lat_dir ?>)</td>
<?php else: ?>
                  <td><?= gettext('N/A') ?></td>
<?php endif ?>
<?php if (isset($gps_lon)): ?>
                  <td><?= sprintf("%.5f", $gps_lon); ?> (<?= sprintf("%d", $gps_lon_deg); ?>&deg; <?= sprintf("%.5f", $gps_lon_min*60); ?><?= $gps_lon_dir ?>)</td>
<?php else: ?>
                  <td><?= gettext('N/A') ?></td>
<?php endif ?>
                  <?php if (isset($gps_alt)) { echo '<td>' . $gps_alt . ' ' . $gps_alt_unit . '</td>';}?>
                  <td>
<?php
                  if (isset($gps_satview)) {echo 'in view ' . intval($gps_satview);}
                  if (isset($gps_sat) && isset($gps_satview)) {echo ', ';}
                  if (isset($gps_sat)) {echo 'in use ' . $gps_sat;}
                  ?>
                  </td>
                </tr>
<?php if (isset($gps_lon) && isset($gps_lat)): ?>
                <tr>
                  <td colspan="<?= html_safe($gps_goo_lnk) ?>"><a target="_gmaps" href="http://maps.google.com/?q=<?= html_safe($gps_lat) ?>,<?= html_safe($gps_lon) ?>">Google Maps Link</a></td>
                </tr>
<?php endif ?>
              </tbody>
            </table>
<?php
            endif; ?>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
