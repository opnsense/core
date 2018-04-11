<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2012 Seth Mos <seth.mos@dds.nl>
    Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once("system.inc");
require_once("interfaces.inc");
require_once("services.inc");

/* expects $logfile to point to the system path */
/* expects $logclog to be true or false */

require_once 'diag_logs_common.inc';

$filtertext = '';
$nentries = 50;

if (isset($config['syslog']['nentries'])) {
    $nentries = $config['syslog']['nentries'];
}

if (!empty($_POST['clear'])) {
    if ($logclog) {
        system_clear_clog($logfile);
    } else {
        system_clear_log($logfile);
    }
    if (function_exists(clear_hook)) {
        clear_hook();
    }
}

if (isset($_POST['filtertext'])) {
    $filtertext = $_POST['filtertext'];
}

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
          <p>
            <form method="post">
              <div class="input-group">
                <div class="input-group-addon"><i class="fa fa-search"></i></div>
                <input type="text" class="form-control" id="filtertext" name="filtertext" placeholder="<?= html_safe(gettext('Search for a specific message...')) ?>" value="<?= html_safe($filtertext) ?>"/>
              </div>
            </form>
          </p>
          <div class="table-responsive content-box tab-content">
            <table class="table table-striped">
              <tr>
                <th class="col-md-2 col-sm-3 col-xs-4"><?= gettext('Date') ?></th>
                <th class="col-md-10 col-sm-9 col-xs-8"><?= gettext('Message') ?></th>
              </tr>
              <?php if (isset($logpills)): ?>
              <tr>
                <td colspan="2">
                  <ul class="nav nav-pills" role="tablist">
                    <?php foreach ($logpills as $pill): ?>
                    <li role="presentation" <?php if (str_replace('amp;','', $pill[2]) == $_SERVER['REQUEST_URI']):?>class="active"<?php endif; ?>><a href="<?=$pill[2];?>"><?=$pill[0];?></a></li>
                    <?php endforeach; ?>
                  </ul>
                </td>
              </tr>
              <?php endif; ?>
              <?php
                if ($logclog) {
                    dump_clog($logfile, $nentries, $filtertext);
                } else {
                    dump_log($logfile, $nentries, $filtertext);
                }
              ?>
              <tr>
                <td colspan="2">
                  <form method="post">
<?php                   if (isset($mode)): ?>
                    <input type="hidden" name="mode" id="mode" value="<?= html_safe($mode) ?>"/>
<?php                   endif; ?>
                    <input name="clear" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Clear log')) ?>"/>
                  </form>
                </td>
              </tr>
            </table>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
