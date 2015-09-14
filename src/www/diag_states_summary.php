<?php
/*
  Copyright (C) 2014 Deciso B.V.
  Copyright (C) 2010-2014 Jim Pingle
  Copyright (C) 2005-2009 Scott Ullrich
  Copyright (C) 2005 Colin Smith
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

function addipinfo(&$iparr, $ip, $proto, $srcport, $dstport) {
    if (!isset($iparr[$ip]['seen'])) {
        $iparr[$ip] = array("seen" => 0, "protos" => array());
    }
    if (!isset($iparr[$ip]['protos'][$proto])) {
        $iparr[$ip]['protos'][$proto] = array("seen" => 0, 'srcports' => array(), 'dstports' => array());
    }
    $iparr[$ip]['seen']++;
    $iparr[$ip]['protos'][$proto]['seen']++;
    if (!empty($srcport)) {
        if (!isset($iparr[$ip]['protos'][$proto]['srcports'][$srcport])) {
            $iparr[$ip]['protos'][$proto]['srcports'][$srcport] = 0;
        }
        $iparr[$ip]['protos'][$proto]['srcports'][$srcport]++;
    }
    if (!empty($dstport)) {
        $iparr[$ip]['protos'][$proto]['dstports'][$dstport]++;
    }
}

function sort_by_ip($a, $b) {
    return ip2ulong($a) < ip2ulong($b) ? -1 : 1;
}

function build_port_info($portarr, $proto) {
    if (empty($portarr)) {
        return '';
    }
    $ports = array();
    asort($portarr);
    foreach (array_reverse($portarr, TRUE) as $port => $count) {
        $str = "";
        $service = getservbyport($port, strtolower($proto));
        $port = "{$proto}/{$port}";
        if (!empty($service)) {
            $port = "{$port} ({$service})";
        }
        $ports[] = "{$port}: {$count}";
    }
    return implode($ports, ', ');
}

$srcipinfo = array();
$dstipinfo = array();
$allipinfo = array();
$pairipinfo = array();

$states = json_decode(configd_run("filter list states json"), true);
if(isset($states['details'])) {
  foreach($states['details'] as $state) {
    if (isset($state['nat_addr']) && $states['direction'] == 'out') {
        $srcip = $state['nat_addr'] ;
        $srcport = $state['nat_port'] ;
    } else {
        $srcip = $state['src_addr'] ;
        $srcport = $state['src_port'] ;
    }
    $dstip = $state['dst_addr'] ;
    $dstport = $state['dst_port'] ;
    $proto = $state['proto'];

    addipinfo($srcipinfo, $srcip, $proto, $srcport, $dstport);
    addipinfo($dstipinfo, $dstip, $proto, $srcport, $dstport);
    addipinfo($pairipinfo, "{$srcip} -> {$dstip}", $proto, $srcport, $dstport);

    addipinfo($allipinfo, $srcip, $proto, $srcport, $dstport);
    addipinfo($allipinfo, $dstip, $proto, $srcport, $dstport);

  }
}


function print_summary_table($label, $iparr, $sort = TRUE) {
    if ($sort) {
        uksort($iparr, "sort_by_ip");
    }
    ?>
    <section class="col-xs-12">
      <div class="content-box">
        <header class="content-box-head container-fluid">
          <h3><?=$label; ?></h3>
        </header>
        <div class="table-responsive">
          <table class="table table-striped">
            <tr>
              <td><?=gettext("IP");?></td>
              <td># <?=gettext("States");?></td>
              <td><?=gettext("Proto");?></td>
              <td># <?=gettext("States");?></td>
              <td><?=gettext("Src Ports");?></td>
              <td><?=gettext("Dst Ports");?></td>
            </tr>
    <?php
    foreach($iparr as $ip => $ipinfo) { ?>
            <tr>
              <td><?= $ip; ?></td>
              <td><?= $ipinfo['seen']; ?></td>
              <td colspan="4">&nbsp;</td>
            </tr>
<?php     foreach($ipinfo['protos'] as $proto => $protoinfo) { ?>
        <tr>
          <td colspan="2">&nbsp;</td>
          <td><?=$proto; ?></td>
          <td ><?=$protoinfo['seen']; ?></td>
          <td ><span data-toggle="tooltip" title="<?=build_port_info($protoinfo['srcports'], $proto); ?>"><?=count($protoinfo['srcports']); ?></span></td>
          <td ><span data-toggle="tooltip" title="<?=build_port_info($protoinfo['dstports'], $proto); ?>"><?=count($protoinfo['dstports']); ?></span></td>
        </tr>
        <?php } ?>
      <?php } ?>
      </table>
     </div>
   </div>
  </section>
<?php
}

$pgtitle = array(gettext("Diagnostics"),gettext("State Table Summary"));
include("head.inc");
echo "<body>";
include("fbegin.inc");
?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?
print_summary_table(gettext("By Source IP"), $srcipinfo);
print_summary_table(gettext("By Destination IP"), $dstipinfo);
print_summary_table(gettext("Total per IP"), $allipinfo);
print_summary_table(gettext("By IP Pair"), $pairipinfo, FALSE);
?>
    </div>
  </div>
</section>

<?php include("foot.inc"); ?>
