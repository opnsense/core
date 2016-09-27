<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2007 Scott Dale
    Copyright (C) 2009 Jim Pingle (jpingle@gmail.com)
    Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
    and Jonathan Watt <jwatt@jwatt.org>.
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
require_once("filter_log.inc");

function find_action_image($action)
{
  if ((strstr(strtolower($action), 'p')) || (strtolower($action) == 'rdr')) {
    return 'glyphicon glyphicon-play text-success';
  }

  if (strstr(strtolower($action), 'r')) {
    return 'glyphicon glyphicon-remove text-warning';
  }

  return 'glyphicon glyphicon-remove text-danger';
}

/**
 * original from diag_dns.php
 * temporary solution.
 */
function display_host_results ($address,$hostname,$dns_speeds) {
    $map_lengths = function($element) { return strlen($element[0]); };
    echo gettext("IP Address") . ": {$address} \n";
    echo gettext("Host Name") . ": {$hostname} \n";
    echo "\n";
    $text_table = array();
    $text_table[] = array(gettext("Server"), gettext("Query Time"));
    if (is_array($dns_speeds)) {
        foreach ($dns_speeds as $qt) {
            $text_table[] = array(trim($qt['dns_server']), trim($qt['query_time']));
        }
    }
    $col0_padlength = max(array_map($map_lengths, $text_table)) + 4;
    foreach ($text_table as $text_row) {
        echo str_pad($text_row[0], $col0_padlength) . $text_row[1] . "\n";
    }
}

if (!empty($_GET['host']) && !empty($_GET['dialog_output'])) {
    $host = trim($_GET['host'], " \t\n\r\0\x0B[];\"'");
    $host_esc = escapeshellarg($host);
    $dns_servers = array();
    exec("/usr/bin/grep nameserver /etc/resolv.conf | /usr/bin/cut -f2 -d' '", $dns_servers);
    foreach ($dns_servers as $dns_server) {
        $query_time = exec("/usr/bin/drill {$host_esc} " . escapeshellarg("@" . trim($dns_server)) . " | /usr/bin/grep Query | /usr/bin/cut -d':' -f2");
        if ($query_time == "") {
            $query_time = gettext("No response");
        }
        $dns_speeds[] = array('dns_server' => $dns_server, 'query_time' => $query_time);
    }
    $ipaddr = "";
    if (count($input_errors) == 0) {
        if (is_ipaddr($host)) {
            $resolved[] = " " . gethostbyaddr($host); // add a space to provide an empty type field
            $ipaddr = $host;
        } elseif (is_hostname($host)) {
            exec("/usr/bin/drill {$host_esc} A | /usr/bin/grep 'IN' | /usr/bin/grep -v ';' | /usr/bin/awk '{ print $4 \" \" $5 }'", $resolved);
            $ipaddr = explode(" ", $resolved[count($resolved)-1])[1];
        }
    }

    display_host_results ($host, $resolved[0], $dns_speeds);
    exit;
}


if (is_numeric($_POST['filterlogentries'])) {
    $config['widgets']['filterlogentries'] = $_POST['filterlogentries'];

    $acts = array();
    if ($_POST['actpass']) {
        $acts[] = "Pass";
    }
    if ($_POST['actblock']) {
        $acts[] = "Block";
    }
    if ($_POST['actreject']) {
        $acts[] = "Reject";
    }

    if (!empty($acts)) {
        $config['widgets']['filterlogentriesacts'] = implode(" ", $acts);
    } else {
        unset($config['widgets']['filterlogentriesacts']);
    }
    unset($acts);

    if (($_POST['filterlogentriesinterfaces']) and ($_POST['filterlogentriesinterfaces'] != "All")) {
        $config['widgets']['filterlogentriesinterfaces'] = trim($_POST['filterlogentriesinterfaces']);
    } else {
        unset($config['widgets']['filterlogentriesinterfaces']);
    }

    write_config("Saved Filter Log Entries via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

$nentries = isset($config['widgets']['filterlogentries']) ? $config['widgets']['filterlogentries'] : 5;

//set variables for log
$nentriesacts       = isset($config['widgets']['filterlogentriesacts'])       ? $config['widgets']['filterlogentriesacts']       : 'All';
$nentriesinterfaces = isset($config['widgets']['filterlogentriesinterfaces']) ? $config['widgets']['filterlogentriesinterfaces'] : 'All';

$filterfieldsarray = array(
    "act" => $nentriesacts,
    "interface" => $nentriesinterfaces
);

$filter_logfile = '/var/log/filter.log';
$filterlog = conv_log_filter($filter_logfile, $nentries, 50, $filterfieldsarray);

/* AJAX related routines */
handle_ajax($nentries, $nentries + 20);

?>

<script type="text/javascript">
//<![CDATA[
lastsawtime = '<?= html_safe(time()) ?>';
var lines = Array();
var timer;
var updateDelay = 30000;
var isBusy = false;
var isPaused = false;
var nentries = <?= html_safe($nentries) ?>;

<?php
if (isset($config['OPNsense']['Syslog']['Reverse'])) {
    echo "var isReverse = true;\n";
} else {
    echo "var isReverse = false;\n";
}
?>

/* Called by the AJAX updater */
function format_log_line(row) {
  var line = '<td class="listMRlr" align="center">' + row[0] + '<\/td>' +
    '<td class="listMRr ellipsis" title="' + row[1] + '">' + row[1].slice(0,-3) + '<\/td>' +
    '<td class="listMRr ellipsis" title="' + row[2] + '">' + row[2] + '<\/td>' +
    '<td class="listMRr ellipsis" title="' + row[3] + '">' + row[3] + '<\/td>' +
    '<td class="listMRr ellipsis" title="' + row[4] + '">' + row[4] + '<\/td>';

  var nentriesacts = "<?= html_safe($nentriesacts) ?>";
  var nentriesinterfaces = "<?= html_safe($nentriesinterfaces) ?>";

  var Action = row[0].match(/alt=.*?(pass|block|reject)/i).join("").match(/pass|block|reject/i).join("");
  var Interface = row[2];

  if ( !(in_arrayi(Action,  nentriesacts.replace      (/\s+/g, ',').split(',') ) ) && (nentriesacts != 'All') )      return false;
  if ( !(in_arrayi(Interface,  nentriesinterfaces.replace(/\s+/g, ',').split(',') ) ) && (nentriesinterfaces != 'All') )  return false;

  return line;
}
//]]>
</script>
<script src="/javascript/filter_log.js" type="text/javascript"></script>

<div id="log-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/log.widget.php" method="post" name="iforma">
        <table class="table table-striped">
      <tbody>
        <tr>
          <td>
            <?= gettext('Number of lines to display:') ?>
          </td>
        </tr>
        <tr>
          <td>
        <select name="filterlogentries" class="formfld unknown" id="filterlogentries">
        <?php for ($i = 1; $i <= 20; $i++) {
?>
          <option value="<?= html_safe($i) ?>" <?php if ($nentries == $i) {
                        echo "selected=\"selected\"";
}?>><?= html_safe($i) ?></option>
        <?php
} ?>
        </select>
          </td>
        </tr>
<?php
        $Include_Act = explode(" ", $nentriesacts);
if ($nentriesinterfaces == "All") {
    $nentriesinterfaces = "";
}
?>
    <tr>
      <td>
    <input id="actpass"   name="actpass"   type="checkbox" value="Pass"   <?php if (in_arrayi('Pass', $Include_Act)) {
            echo "checked=\"checked\"";
} ?> /> Pass
    <input id="actblock"  name="actblock"  type="checkbox" value="Block"  <?php if (in_arrayi('Block', $Include_Act)) {
            echo "checked=\"checked\"";
} ?> /> Block
    <input id="actreject" name="actreject" type="checkbox" value="Reject" <?php if (in_arrayi('Reject', $Include_Act)) {
            echo "checked=\"checked\"";
} ?> /> Reject
      </td>
    </tr>
    <tr>
      <td>
        <?= gettext('Interfaces:'); ?>
      </td>
    </tr>
    <tr>
      <td>
    <select id="filterlogentriesinterfaces" name="filterlogentriesinterfaces" class="formselect">
      <option value="All"><?= gettext('ALL') ?></option>
<?php
        $interfaces = get_configured_interface_with_descr();
foreach ($interfaces as $iface => $ifacename) :
?>
    <option value="<?=$iface;?>" <?php if ($nentriesinterfaces == $iface) {
        echo "selected=\"selected\"";
}?>>
        <?=htmlspecialchars($ifacename);?>
    </option>
<?php
endforeach;
        unset($interfaces);
        unset($Include_Act);
?>
    </select>
  </td>
  </tr>
  <tr>
    <td>
    <input id="submita" name="submita" type="submit" class="btn btn-primary formbtn" value="<?= gettext('Save') ?>" />
    </td>
  </tr>
  </tbody>
  </table>
  </form>
</div>

<table class="table table-striped" width="100%" border="0" cellpadding="0" cellspacing="0" style="table-layout: fixed;" summary="logs">
  <colgroup>
    <col style='width:  7%;' />
    <col style='width: 23%;' />
    <col style='width: 11%;' />
    <col style='width: 28%;' />
    <col style='width: 31%;' />
  </colgroup>
  <thead>
    <tr>
      <td class="listhdrr"><?=gettext("Act");?></td>
      <td class="listhdrr"><?=gettext("Time");?></td>
      <td class="listhdrr"><?=gettext("IF");?></td>
      <td class="listhdrr"><?=gettext("Source");?></td>
      <td class="listhdrr"><?=gettext("Destination");?></td>
    </tr>
  </thead>
  <tbody id='filter-log-entries'>
  <?php
    $rowIndex = 0;
    foreach ($filterlog as $filterent) :
        $evenRowClass = $rowIndex % 2 ? " listMReven" : " listMRodd";
        $rowIndex++;
    ?>
    <tr class="<?=$evenRowClass?>">
      <td class="listMRlr nowrap" align="center">
      <a href="#" onclick="javascript:getURL('diag_logs_filter.php?getrulenum=<?= html_safe("{$filterent['rulenum']},{$filterent['act']}") ?>', outputrule);">
      <span class="<?= html_safe(find_action_image($filterent['act'])) ?>" alt="<?= html_safe($filterent['act']) ?>" title="<?= html_safe($filterent['act']) ?>"></span>
      </a>
      </td>
      <td class="listMRr ellipsis nowrap" title="<?= html_safe($filterent['time']) ?>"><?= html_safe(substr($filterent['time'], 0, -3)) ?></td>
      <td class="listMRr ellipsis nowrap" title="<?= html_safe($filterent['interface']) ?>"><?= html_safe($filterent['interface']) ?></td>
      <td class="listMRr ellipsis nowrap" title="<?= html_safe($filterent['src']) ?>">
        <a href="#" onclick="javascript:getURL('widgets/widgets/log.widget.php?host=<?= html_safe($filterent['srcip']) ?>&amp;dialog_output=true', outputrule);"
          title="<?= html_safe(gettext('Reverse Resolve with DNS')) ?>"><?= html_safe($filterent['srcip']) ?></a>
      </td>
      <td class="listMRr ellipsis nowrap" title="<?= html_safe($filterent['dst']) ?>">
        <a href="#" onclick="javascript:getURL('widgets/widgets/log.widget.php?host=<?= html_safe($filterent['dstip']) ?>&amp;dialog_output=true', outputrule);"
          title="<?= html_safe(gettext('Reverse Resolve with DNS')) ?>"><?= html_safe($filterent['dstip']) ?></a>:<?= html_safe($filterent['dstport']) ?>
      </td>
      <?php
            if ($filterent['proto'] == "TCP") {
                $filterent['proto'] .= ":{$filterent['tcpflags']}";
            }
            ?>
    </tr>
  <?php
    endforeach; ?>
    <tr style="display:none;"><td></td></tr>
  </tbody>
</table>

<!-- needed to display the widget settings menu -->
<script type="text/javascript">
//<![CDATA[
  $("#log-configure").removeClass("disabled");
//]]>
</script>
