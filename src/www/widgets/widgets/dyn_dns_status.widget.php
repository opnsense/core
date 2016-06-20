<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2008 Ermal Luci
    Copyright (C) 2013 Stanley P. Miller \ stan-qaz
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("widgets/include/dyn_dns_status.inc");
require_once("services.inc");
require_once("interfaces.inc");

if (!isset($config['dyndnses']['dyndns'])) {
    $config['dyndnses']['dyndns'] = array();
}

$a_dyndns = &$config['dyndnses']['dyndns'];

if (!empty($_REQUEST['getdyndnsstatus'])) {
    $first_entry = true;
    foreach ($a_dyndns as $dyndns) {
        if ($first_entry) {
            $first_entry = false;
        } else {
        // Put a vertical bar delimiter between the echoed HTML for each entry processed.
            echo "|";
        }

        $filename = "/conf/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . "{$dyndns['id']}.cache";
        $filename_v6 = "/conf/dyndns_{$dyndns['interface']}{$dyndns['type']}" . escapeshellarg($dyndns['host']) . "{$dyndns['id']}_v6.cache";
        if (file_exists($filename) && !empty($dyndns['enable'])) {
            $ipaddr = dyndnsCheckIP($dyndns['interface']);
            $cached_ip_s = preg_split('/:/', file_get_contents($filename));
            $cached_ip = $cached_ip_s[0];
            if ($ipaddr <> $cached_ip) {
                echo "<font color='red'>";
            } else {
                echo "<font color='green'>";
            }
            echo htmlspecialchars($cached_ip);
            echo "</font>";
        } elseif (file_exists($filename_v6) && !empty($dyndns['enable'])) {
            $ipv6addr = get_interface_ipv6($dyndns['interface']);
            $cached_ipv6_s = explode("|", file_get_contents($filename_v6));
            $cached_ipv6 = $cached_ipv6_s[0];
            if ($ipv6addr <> $cached_ipv6) {
                echo "<font color='red'>";
            } else {
                echo "<font color='green'>";
            }
            echo htmlspecialchars($cached_ipv6);
            echo "</font>";
        } else {
            echo '<span class="text-muted">' . gettext('N/A') . '</span>';
        }
    }
    exit;
}

?>

<table class="table table-striped table-condensed">
  <thead>
    <tr>
      <th><?=gettext("Int.");?></th>
      <th><?=gettext("Service");?></th>
      <th><?=gettext("Hostname");?></th>
      <th><?=gettext("Cached IP");?></th>
    </tr>
  </thead>
  <tbody>
<?php
  $iflist = get_configured_interface_with_descr();
  $types = services_dyndns_list();
  $groupslist = return_gateway_groups_array();
  foreach ($a_dyndns as $i => $dyndns) :?>
    <tr ondblclick="document.location='services_dyndns_edit.php?id=<?=$i;?>'">
      <td>
<?php
        foreach ($iflist as $if => $ifdesc) {
            if ($dyndns['interface'] == $if) {
                if (!isset($dyndns['enable'])) {
                    echo "<span class=\"text-muted\">{$ifdesc}</span>";
                } else {
                    echo "{$ifdesc}";
                }
                break;
            }
        }
        foreach ($groupslist as $if => $group) {
            if ($dyndns['interface'] == $if) {
                if (!isset($dyndns['enable'])) {
                    echo "<span class=\"text-muted\">{$if}</span>";
                } else {
                    echo "{$if}";
                }
                break;
            }
        }?>
      </td>
      <td>
<?php
        if (isset($types[$dyndns['type']])) {
            if (!isset($dyndns['enable'])) {
                echo '<span class="text-muted">' . htmlspecialchars($types[$dyndns['type']]) . '</span>';
            } else {
                echo htmlspecialchars($types[$dyndns['type']]);
            }
        }
?>
      </td>
      <td>
<?php
        if (!isset($dyndns['enable'])) {
            echo "<span class=\"text-muted\">".htmlspecialchars($dyndns['host'])."</span>";
        } else {
            echo htmlspecialchars($dyndns['host']);
        }
?>
      </td>
      <td>
        <div id='dyndnsstatus<?=$i;?>'><?=gettext("Checking ...");?></div>
      </td>
    </tr>
<?php
  endforeach;?>
  </tbody>
</table>
<script type="text/javascript">
  function dyndns_getstatus()
  {
      scroll(0,0);
      var url = "/widgets/widgets/dyn_dns_status.widget.php";
      var pars = 'getdyndnsstatus=yes';
      jQuery.ajax(url, {type: 'get', data: pars, complete: dyndnscallback});
      // Refresh the status every 5 minutes
      setTimeout('dyndns_getstatus()', 5*60*1000);
  }
  function dyndnscallback(transport)
  {
      // The server returns a string of statuses separated by vertical bars
      var responseStrings = transport.responseText.split("|");
      for (var count=0; count<responseStrings.length; count++) {
          var divlabel = '#dyndnsstatus' + count;
          jQuery(divlabel).prop('innerHTML',responseStrings[count]);
      }
  }
  $( document ).ready(function() {
    // Do the first status check 2 seconds after the dashboard opens
    setTimeout('dyndns_getstatus()', 2000);
  });
</script>
