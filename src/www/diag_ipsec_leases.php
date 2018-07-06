<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2014 Ermal Luçi
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

require_once("guiconfig.inc");
require_once("plugins.inc.d/ipsec.inc");
require_once("services.inc");
require_once("interfaces.inc");

$service_hook = 'ipsec';

include("head.inc");
$ipsec_leases = json_decode(configd_run("ipsec list leases"), true);
?>
<body>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
          <div class="content-box">
<?php
            if (count($ipsec_leases) > 0):
              foreach($ipsec_leases as $pool => $pool_data): ?>
              <div class="content-box-main ">
                <div class="table-responsive">
                  <table class="table table-striped table-condensed">
                    <thead>
                      <tr>
                        <th></th>
                        <th colspan="3">
                          <?= gettext("Pool:") ?> <?= $pool ?>
                          <?= gettext("Usage:") ?> <?= $pool_data['usage'] ?>
                          <?= gettext("Online:") ?> <?= $pool_data['online'] ?>
                        </th>
                        <th></th>
                      </tr>
                      <tr>
                        <th></th>
                        <th><?= gettext("User") ?></th>
                        <th><?= gettext("Host") ?></th>
                        <th><?= gettext("Status") ?></th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
<?php
                    if (count($pool_data['items']) > 0):?>
<?php
                    foreach ($pool_data['items'] as $lease): ?>
                    <tr>
                      <td></td>
                      <td><?= htmlspecialchars($lease['user']) ?></td>
                      <td><?= htmlspecialchars($lease['address']) ?></td>
                      <td>
                        <i class="fa fa-exchange text-<?= $lease['status'] == 'online' ? 'success' : 'danger' ?>"></i>
                        (<?= htmlspecialchars($lease['status']) ?>)
                      </td>
                      <td></td>
                    </tr>
<?php
                    endforeach;
                    else: ?>
                    <tr>
                      <td></td>
                      <td colspan="3">
                        <?= gettext("No leases from this pool yet.") ?>
                      </td>
                      <td></td>
                    </tr>
<?php
                    endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

<?php
              endforeach;
            else: ?>
            <div class="content-box-main ">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?= gettext("No IPsec pools.") ?></th>
                    </tr>
                  </thead>
                </table>
              </div>
            </div>
<?php
            endif; ?>
          </div>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
