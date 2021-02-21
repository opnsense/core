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

$resolved = array();
$dns_speeds = array();
if (!empty($_REQUEST['host'])) {
    $input_errors = array();
    $host = trim($_REQUEST['host'], " \t\n\r\0\x0B[];\"'");
    $host_esc = escapeshellarg($host);

    if (!is_hostname($host) && !is_ipaddr($host)) {
        $input_errors[] = gettext("Host must be a valid hostname or IP address.");
    } else {
        // Test resolution speed of each DNS server.
        $dns_servers = array();
        exec("/usr/bin/grep nameserver /etc/resolv.conf | /usr/bin/cut -f2 -d' '", $dns_servers);
        foreach ($dns_servers as $dns_server) {
            $query_time = exec("/usr/bin/drill {$host_esc} " . escapeshellarg("@" . trim($dns_server)) . " | /usr/bin/grep Query | /usr/bin/cut -d':' -f2");
            if ($query_time == "") {
                $query_time = gettext("No response");
            }
            $dns_speeds[] = array('dns_server' => $dns_server, 'query_time' => $query_time);
        }
    }

    if (count($input_errors) == 0) {
        if (is_ipaddr($host)) {
            $resolved[] = "PTR " . gethostbyaddr($host);
        } elseif (is_hostname($host)) {
            exec("(/usr/bin/drill {$host_esc} AAAA; /usr/bin/drill {$host_esc} A) | /usr/bin/grep 'IN' | /usr/bin/grep -v ';' | /usr/bin/grep -v 'SOA' | /usr/bin/awk '{ print $4 \" \" $5 }'", $resolved);
        }
    }
}

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
            <header class="content-box-head container-fluid">
              <h3><?=gettext("Resolve DNS hostname or IP");?></h3>
            </header>
            <div class="table-responsive">
              <table class="table table-striped">
                <tbody>
                  <tr>
                    <td style="width: 22%"><?=gettext("Hostname or IP");?></td>
                    <td>
                      <input name="host" type="text" value="<?=htmlspecialchars($host);?>" />
                    </td>
                  </tr>
<?php
                  if (count($resolved) > 0):?>
                  <tr>
                    <td><?=gettext("Response");?></td>
                    <td>
                      <table class="table table-striped table-condensed">
                        <tr>
                          <th><?=gettext("Type");?></th>
                          <th><?=gettext("Address");?></th>
                        </tr>
<?php
                        foreach($resolved as $hostitem):?>
                        <tr>
                          <td><?=explode(' ',$hostitem)[0];?></td>
                          <td><?=explode(' ',$hostitem)[1];?></td>
                        </tr>
<?php
                        endforeach;?>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td><?=gettext("Resolution time per server");?></td>
                    <td colspan="2">
                      <table class="table table-striped table-condensed">
                        <tr>
                          <th><?=gettext("Server");?></th>
                          <th><?=gettext("Query time");?></th>
                        </tr>

<?php
                        foreach($dns_speeds as $qt): ?>
                        <tr>
                          <td><?=$qt['dns_server']?></td>
                          <td><?=$qt['query_time']?></td>
                        </tr>
<?php
                        endforeach; ?>
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
