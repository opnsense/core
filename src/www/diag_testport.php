<?php

/*
 * Copyright (C) 2018 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2016 Deciso B.V.
 * Copyright (C) 2013 Jim Pingle <jimp@pfsense.org>
 * Copyright (C) 2003-2005 Bob Zoller <bob@kludgebox.com>
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
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
require_once("system.inc");
require_once("interfaces.inc");

$cmd_output = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // set form defaults
    $pconfig = array();
    $pconfig['ipprotocol'] = 'ipv4';
    $pconfig['host'] = null;
    $pconfig['port'] = null;
    $pconfig['showtext'] = null;
    $pconfig['interface'] = null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();

    /* input validation */
    $reqdfields = explode(" ", "host port");
    $reqdfieldsn = array(gettext("Host"),gettext("Port"));
    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!is_ipaddr($pconfig['host']) && !is_hostname($pconfig['host'])) {
        $input_errors[] = gettext("Please enter a valid IP or hostname.");
    }

    if (!is_port($pconfig['port'])) {
        $input_errors[] = gettext("Please enter a valid port number.");
    }

    if (($pconfig['srcport'] != "") && (!is_numeric($pconfig['srcport']) || !is_port($pconfig['srcport']))) {
        $input_errors[] = gettext("Please enter a valid source port number, or leave the field blank.");
    }

    if (count($input_errors) == 0) {
        $nc_args = "-w 10" ;
        if (empty($pconfig['showtext'])) {
            $nc_args .= " -z ";
        }
        if (!empty($pconfig['srcport'])) {
            $nc_args .= exec_safe(' -p %s ', $pconfig['srcport']);
        }
        switch ($pconfig['ipprotocol']) {
            case 'ipv6':
                list ($ifaddr) = interfaces_primary_address6($pconfig['interface']);
                $nc_args .= ' -6';
                break;
            case 'ipv6-ll':
                list ($ifaddr) = interfaces_scoped_address6($pconfig['interface']);
                $nc_args .= ' -6';
                break;
            default:
                list ($ifaddr) = interfaces_primary_address($pconfig['interface']);
                $nc_args .= ' -4';
                break;
        }

        if (!empty($ifaddr)) {
            $nc_args .= exec_safe(' -s %s ', $ifaddr);
        }

        $cmd_action = "/usr/bin/nc -v {$nc_args} " . exec_safe('%s %s', [$pconfig['host'], $pconfig['port']]);
        $process = proc_open($cmd_action, array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w")), $pipes);
        if (is_resource($process)) {
             $cmd_output = "# $cmd_action\n";
             $cmd_output .= stream_get_contents($pipes[1]);
             $cmd_output .= stream_get_contents($pipes[2]);
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
      <section class="col-xs-12">
        <div id="message" style="" class="alert alert-warning" role="alert">
          <?= gettext('This page allows you to perform a simple TCP connection test to determine if a host is up and accepting connections on a given port. This test does not function for UDP since there is no way to reliably determine if a UDP port accepts connections in this manner.') ?>
          <br /><br />
          <?= gettext('No data is transmitted to the remote host during this test, it will only attempt to open a connection and optionally display the data sent back from the server.') ?>
        </div>
        <div class="content-box">
            <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td style="width:22%"><strong><?= gettext('Port Probe') ?></strong></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                        &nbsp;
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Host"); ?></td>
                      <td>
                        <input name="host" type="text" value="<?=$pconfig['host'];?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Port"); ?></td>
                      <td>
                        <input name="port" type="text" value="<?=$pconfig['port'];?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_ipprotocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP Protocol"); ?></td>
                      <td>
                        <select name="ipprotocol" class="selectpicker">
                          <option value="ipv4" <?= $pconfig['ipprotocol'] == "ipv4" ? "selected=\"selected\"" : ""; ?>><?= gettext('IPv4') ?></option>
                          <option value="ipv6" <?= $pconfig['ipprotocol'] == "ipv6" ? "selected=\"selected\"" : ""; ?>><?= gettext('IPv6') ?></option>
                          <option value="ipv6-ll" <?= $pconfig['ipprotocol'] == "ipv6-ll" ? "selected=\"selected\"" : "";?>><?= gettext('IPv6 Link-Local') ?></option>
                        </select>
                        <div class="hidden" data-for="help_for_ipprotocol">
                          <?=gettext("If you force IPv4 or IPv6 and use a hostname that does not contain a result using that protocol, it will result in an error. For example if you force IPv4 and use a hostname that only returns an AAAA IPv6 IP address, it will not work."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Source Address"); ?></td>
                      <td>
                          <select name="interface" class="selectpicker">
                            <option value=""><?= gettext('Default') ?></option>
<?php foreach (get_configured_interface_with_descr() as $ifname => $ifdescr): ?>
                            <option value="<?= html_safe($ifname) ?>" <?= $ifname == $pconfig['interface'] ? 'selected="selected"' : '' ?>>
                              <?= html_safe($ifdescr) ?>
                            </option>
<?php endforeach ?>
                          </select>
                        </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_srcport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Source Port"); ?></td>
                      <td>
                        <input name="srcport" type="text" value="<?=$pconfig['srcport'];?>" />
                        <div class="hidden" data-for="help_for_srcport">
                          <?=gettext("This should typically be left blank."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_showtext" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Show Remote Text"); ?></td>
                      <td>
                        <input name="showtext" type="checkbox" id="showtext" <?= !empty($pconfig['showtext']) ? "checked=\"checked\"" : "";?> />
                        <div class="hidden" data-for="help_for_showtext">
                          <?=gettext("Shows the text given by the server when connecting to the port. Will take 10+ seconds to display if checked."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td><button name="Submit" type="submit" class="btn btn-primary" value="yes"><?= html_safe(gettext('Test')) ?></button></td>
                    </tr>
                </table>
              </div>
            </form>
        </div>
      </section>
<?php if ($cmd_output !== false): ?>
      <section class="col-xs-12">
<?php if (empty($cmd_output) && !empty($pconfig['showtext'])): ?>
        <pre><?= gettext("No output received, or connection failed. Try with \"Show Remote Text\" unchecked first.");?></pre>
<?php elseif (empty($cmd_output)): ?>
        <pre><?=gettext("Connection failed (Refused/Timeout)");?></pre>
<?php else: ?>
        <pre><?=htmlspecialchars($cmd_output);?></pre>
<?php endif ?>
      </section>
<?php endif ?>
    </div>
  </div>
</section>
<?php include('foot.inc'); ?>
