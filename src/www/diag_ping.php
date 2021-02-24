<?php

/*
 * Copyright (C) 2018 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2016 Deciso B.V.
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
    $pconfig['count'] = isset($_GET['count']) ? $_GET['count'] : 3;
    $pconfig['host'] = isset($_GET['host']) ? $_GET['host'] : null;
    $pconfig['interface'] = isset($_GET['interface']) ? $_GET['interface'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate formdata and schedule action
    $pconfig = $_POST;
    $input_errors = array();
    /* input validation */
    $reqdfields = explode(" ", "host count");
    $reqdfieldsn = array(gettext("Host"),gettext("Count"));
    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (count($input_errors) == 0) {
        $command = '/sbin/ping';
        switch ($pconfig['ipproto']) {
            case 'ipv6':
                list ($ifaddr) = interfaces_primary_address6($pconfig['interface']);
                $command .= '6';
                break;
            case 'ipv6-ll':
                $command .= '6';
                $realif = get_real_interface($pconfig['interface'], 'inet6');
                $ifaddr = find_interface_ipv6_ll($realif) . "%{$realif}";
                break;
            default:
                $ifaddr = find_interface_ip(get_real_interface($pconfig['interface']));
                break;
        }
        $srcip = '';
        if (!empty($ifaddr)) {
            $srcip = exec_safe('-S %s ', $ifaddr);
        }
        // execute ping command and catch both stdout and stderr
        $cmd_action = "{$command} {$srcip}" . exec_safe('-c %s %s', array($pconfig['count'], $pconfig['host']));
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
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped __nomb">
                    <tr>
                      <td><?=gettext("Host"); ?></td>
                      <td><input name="host" type="text" value="<?=$pconfig['host'];?>" /></td>
                    </tr>
                    <tr>
                      <td><?=gettext("IP Protocol"); ?></td>
                      <td>
                        <select name="ipproto" class="selectpicker">
                          <option value="ipv4" <?=$pconfig['ipproto'] == "ipv4" ? "selected=\"selected\"" : "";?>><?= gettext('IPv4') ?></option>
                          <option value="ipv6" <?=$pconfig['ipproto'] == "ipv6" ? "selected=\"selected\"" : "";?>><?= gettext('IPv6') ?></option>
                          <option value="ipv6-ll" <?=$pconfig['ipproto'] == "ipv6-ll" ? "selected=\"selected\"" : "";?>><?= gettext('IPv6 Link-Local') ?></option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><?=gettext("Source Address"); ?></td>
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
                    <tr>
                      <td><?= gettext("Count"); ?></td>
                      <td>
                        <select name="count" class="selectpicker" id="count">
<?php
                        for ($i = 1; $i <= 10; $i++): ?>
                          <option value="<?=$i;?>" <?=$i == $pconfig['count'] ? "selected=\"selected\"" : ""; ?>>
                            <?=$i;?>
                          </option>
<?php
                        endfor; ?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td><button name="submit" type="submit" class="btn btn-primary" value="yes"><?= html_safe(gettext('Ping')) ?></button></td>
                    </tr>
                </table>
              </div>
            </form>
        </div>
      </section>
<?php if (!empty($cmd_output)): ?>
      <section class="col-xs-12">
        <pre><?=htmlspecialchars($cmd_output);?></pre>
      </section>
<?php endif ?>
    </div>
  </div>
</section>
<?php

include('foot.inc');
