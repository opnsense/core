<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2005-2009 Scott Ullrich
    Copyright (C) 2005 Colin Smith <ethethlay@gmail.com>
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
require_once("interfaces.inc");

/* handle AJAX operations */
if(isset($_POST['action']) && $_POST['action'] == "remove") {
    if (isset($_POST['srcip']) && isset($_POST['dstip']) && is_ipaddr($_POST['srcip']) && is_ipaddr($_POST['dstip'])) {
        $retval = mwexec("/sbin/pfctl -k " . escapeshellarg($_POST['srcip']) . " -k " . escapeshellarg($_POST['dstip']));
        echo htmlentities("|{$_POST['srcip']}|{$_POST['dstip']}|{$retval}|");
    } else {
        echo gettext("invalid input");
    }
    exit;
}

if (isset($_POST['filter']) && isset($_POST['killfilter'])) {
    if (is_ipaddr($_POST['filter'])) {
        $tokill = escapeshellarg($_POST['filter'] . "/32");
    } elseif (is_subnet($_POST['filter'])) {
        $tokill = escapeshellarg($_POST['filter']);
    } else {
        // Invalid filter
        $tokill = "";
    }
    if (!empty($tokill)) {
        mwexec("/sbin/pfctl -k {$tokill} -k 0/0");
        mwexec("/sbin/pfctl -k 0.0.0.0/0 -k {$tokill}");
    }
}

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
  <script type="text/javascript">
  $( document ).ready(function() {
    // delete state
    $(".act_del").click(function(event){
        event.preventDefault();
        var srcip = $(this).data('srcip');
        var dstip = $(this).data('dstip');
        var rowid = $(this).data('rowid');

        $.post(window.location, {action: 'remove', srcip: srcip, dstip: dstip}, function(data) {
            $("."+rowid).hide();
        });
    });
  });
</script>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
          <div class="content-box">
            <form  method="post" name="iform">
<?php
              $current_statecount=`pfctl -si | grep "current entries" | awk '{ print $3 }'`;?>
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?=gettext("Current total state count");?></th>
                    <th><?=gettext("Filter expression:");?></th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><?=$current_statecount?></td>
                    <td>
                      <input type="text" name="filter" value="<?=!empty($_POST['filter']) ? htmlspecialchars($_POST['filter']) : "";?>"/>
                    </td>
                    <td>
                      <input type="submit" class="btn btn-primary" value="<?=gettext("Filter");?>" />
                      <?php if (isset($_POST['filter']) && (is_ipaddr($_POST['filter']) || is_subnet($_POST['filter']))): ?>
                      <input type="submit" class="btn btn-primary" name="killfilter" value="<?=gettext("Kill");?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </section>
        <section class="col-xs-12">
          <div class="content-box">
            <div class="content-box-main">
              <div class="table-responsive">
                <table id="state_table" class="table table-condensed table-hover table-striped">
                  <thead>
                    <tr>
                      <th data-column-id="int"><?=gettext("Int");?></th>
                      <th data-column-id="proto"><?=gettext("Proto");?></th>
                      <th data-column-id="path"><?=gettext("Source -> Router -> Destination");?></th>
                      <th data-column-id="state"><?=gettext("State");?></th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  $row = 0;
                  /* get our states */
                  $grepline = (isset($_POST['filter'])) ? "| /usr/bin/egrep " . escapeshellarg(htmlspecialchars($_POST['filter'])) : "";
                  $fd = popen("/sbin/pfctl -s state {$grepline}", "r" );
                  while ($line = chop(fgets($fd))):
                    if ($row >= 10000) {
                        break;
                    }

                    $line_split = preg_split("/\s+/", $line);

                    $iface  = array_shift($line_split);
                    $proto = array_shift($line_split);
                    $state = array_pop($line_split);
                    $info  = htmlspecialchars(implode(" ", $line_split));

                    // We may want to make this optional, with a large state table, this could get to be expensive.
                    $iface = convert_real_interface_to_friendly_descr($iface);

                    /* break up info and extract $srcip and $dstip */
                    $ends = preg_split("/\<?-\>?/", $info);
                    $parts = explode(":", $ends[0]);
                    $srcip = trim($parts[0]);
                    $parts = explode(":", $ends[count($ends) - 1]);
                    $dstip = trim($parts[0]);
                    // states can be deleted by source / dest combination, all matching records use the same class.
                    $rowid = str_replace(array('.', ':'), '_', $srcip.$dstip);
                  ?>
                    <tr class="r<?=$rowid;?>">
                      <td><?= $iface ?></td>
                      <td><?= $proto ?></td>
                      <td><?= $info ?></td>
                      <td><?= $state ?></td>
                      <td>
                        <a href="#" data-rowid="r<?=$rowid?>" data-srcip="<?=$srcip?>" data-dstip="<?=$dstip;?>" class="act_del btn btn-default" title="<?= gettext('Remove all state entries from') ?> <?= $srcip ?> <?= gettext('to') ?> <?= $dstip ?>"><span class="glyphicon glyphicon-remove"></span></a>
                      </td>
                    </tr>
<?php
                    $row++;
                  endwhile;
                  if ($row == 0): ?>
                    <tr>
                      <td colspan="5"><?= gettext("No states were found.") ?></td>
                    </tr>
<?php
                  endif;
                  pclose($fd);?>
                  </tbody>
                  <tfoot>
<?php
                    if (!empty($_POST['filter'])): ?>
                    <tr>
                      <td colspan="5"><?=gettext("States matching current filter")?>: <?= $row ?></td>
                    </tr>
<?php
                    endif;?>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>


<?php include('foot.inc');?>
