<?php

/*
 * Copyright (C) 2016 Deciso B.V.
 * Copyright (C) 2009 Jim Pingle <jimp@pfsense.org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
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
require_once("system.inc");
require_once("interfaces.inc");

$resolved = array();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // set form defaults
    $pconfig = array();
    $pconfig['host'] = isset($_GET['host']) ? $_GET['host'] : null;
    $pconfig['interface'] = isset($_GET['interface']) ? $_GET['interface'] : null;
    $pconfig['dnsserverinput'] = isset($_GET['dnsserverinput']) ? $_GET['dnsserverinput'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate formdata and schedule action
    $pconfig = $_POST;
    $input_errors = array();
    /* input validation */
    $reqdfields = array("host");
    $reqdfieldsn = array(gettext("Host"));
    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
    if (!is_hostname($pconfig['host']) && !is_ipaddr($pconfig['host'])) {
        $input_errors[] = gettext("Host must be a valid hostname or IP address.");
    }
    if (count($input_errors) == 0) {
        $command_args = "";
        list ($ifaddr) = interfaces_primary_address($pconfig['interface']);
        if (!empty($ifaddr)) {
            $command_args .= exec_safe(' -I %s ', $ifaddr);
        }
        if (is_ipaddr($pconfig['host'])) {
            $command_args .= ' -x ';
        }
        $dns_servers = array();
        if (strlen($pconfig['dnsserverinput']) > 0) {
            $dns_servers = explode(' ', $pconfig['dnsserverinput']);
        } else {
            exec("/usr/bin/grep nameserver /etc/resolv.conf | /usr/bin/cut -f2 -d' '", $dns_servers);
        }
        foreach ($dns_servers as $dns_server) {
            if (!is_hostname($dns_server) && !is_ipaddr($dns_server)) {
                $input_errors[] = gettext("DNS Server must be a valid hostname or IP address.") . " " . $dns_server;
            }
            $queryoutput = [];
            $query_time = "";
            $dnsrcode = "";
            $dnsanswer = "";
            $dnssoa = "";
            exec("/usr/bin/drill " . $command_args . " " . $pconfig['host'] . " " . escapeshellarg("@" . trim($dns_server)) . " 2>&1", $queryoutput, $retval);
            if ($retval > 0) {
                $input_errors[] = "command exit code: $retval for server $dns_server : " . join(' ', $queryoutput);
                continue;
            }
            for ($i = 0; $i < count($queryoutput); $i++) {
                $qoutline = $queryoutput[$i];
                if ($i == 0) {
                    $larr = explode(', ', $qoutline);
                    $dnsrcode = trim(explode(': ', $larr[1])[1]);
                } elseif (strpos($qoutline, 'ANSWER SECTION:')) {
                    if (strlen(trim($queryoutput[$i+1])) > 0) {
                        $dnsa = preg_split('/\s+/', trim($queryoutput[$i+1]));
                        $dnsanswer = $dnsa[3]." ".$dnsa[4];
                    }
                } elseif (strpos($qoutline, 'AUTHORITY SECTION:')) {
                    if (strlen(trim($queryoutput[$i+1])) > 0) {
                        $soa = preg_split('/\s+/', trim($queryoutput[$i+1]));
                        $dnssoa = $soa[3]." ".$soa[4];
                    }
                } elseif (strpos($qoutline, 'Query time:')) {
                    $query_time = trim(explode(': ', $qoutline)[1]);
                }
            }
            if (strlen($dnsanswer) == 0 && strlen($dnssoa) > 0) {
                $dnsanswer = $dnssoa;
            }
            $resolved[] = array(
                'dns_server' => $dns_server,
                'query_time' => $query_time,
                'rcode' => $dnsrcode,
                'answer' => $dnsanswer,
                );
        }
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc"); ?>
<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <form method="post" name="iform" id="iform">
        <section class="col-xs-12">
          <div class="content-box">
            <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <td style="width:22%"><strong><?= gettext('Resolve DNS hostname or IP') ?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      &nbsp;
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Hostname or IP");?></td>
                    <td>
                      <input name="host" type="text" value="<?=htmlspecialchars($pconfig['host']);?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dnsserverinput" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Server");?></td>
                    <td>
                      <input name="dnsserverinput" type="text" value="<?=htmlspecialchars($pconfig['dnsserverinput']);?>" />
                      <div class="hidden" data-for="help_for_dnsserverinput">
                          <?=gettext("IP or hostname for the DNS server to be queried. You can define multiple servers separated by spaces. If you do not define servers here, the DNS servers configured for the system itself will be queried."); ?>
                        </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Source Address"); ?></td>
                    <td>
                      <select name="interface" class="selectpicker">
                        <option value=""><?= gettext('Default') ?></option>
<?php foreach (get_configured_interface_with_descr() as $ifname => $ifdescr): ?>
                        <option value="<?= html_safe($ifname) ?>" <?=!link_interface_to_bridge($ifname) && $ifname == $pconfig['interface'] ? 'selected="selected"' : '' ?>>
                          <?= htmlspecialchars($ifdescr) ?>
                        </option>
<?php endforeach ?>
                      </select>
                    </td>
                  </tr>
<?php
                  if (count($resolved) > 0):?>
                  <tr>
                    <td><?=gettext("Response");?></td>
                    <td>
                      <table class="table table-striped table-condensed">
                        <tr>
                          <th><?=gettext("Server");?></th>
                          <th><?=gettext("DNS Result Code");?></th>
                          <th><?=gettext("Type");?></th>
                          <th><?=gettext("Address");?></th>
                          <th><?=gettext("Query time");?></th>
                        </tr>
<?php
                        foreach($resolved as $resolveditem):?>
                        <tr>
                          <td><?=$resolveditem['dns_server'];?>
                          <td><?=$resolveditem['rcode'];?>
                          <td><?=explode(' ',$resolveditem['answer'])[0];?></td>
                          <td><?=explode(' ',$resolveditem['answer'])[1];?></td>
                          <td><?=$resolveditem['query_time'];?>
                        </tr>
<?php
                        endforeach;?>
                      </table>
                    </td>
                  </tr>
<?php
                  endif;?>
                </tbody>
                <tfoot>
                  <tr>
                    <td></td>
                    <td>
                      <input type="submit" class="btn btn-primary btn-fixed" value="<?= html_safe(gettext('DNS Lookup')) ?>" />
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </section>
      </form>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
