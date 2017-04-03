<?php

/*
    Copyright (C) 2017 Franco Fichtner <franco@opnsense.org>
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
require_once("widgets/include/rfc2136.inc");
require_once("services.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/rfc2136.inc");

if (!isset($config['dnsupdates']['dnsupdate'])) {
    $config['dnsupdates']['dnsupdate'] = array();
}

$a_rfc2136 = &$config['dnsupdates']['dnsupdate'];

if (!empty($_REQUEST['getrfc2136status'])) {
    $first_entry = true;
    foreach ($a_rfc2136 as $rfc2136) {
        if ($first_entry) {
            $first_entry = false;
        } else {
            // Put a vertical bar delimiter between the echoed HTML for each entry processed.
            echo '|';
        }

        $filename = rfc2136_cache_file($rfc2136, 4);
        $fdata = '';
        if (!empty($rfc2136['enable']) && (empty($rfc2136['recordtype']) || $rfc2136['recordtype'] == 'A') && file_exists($filename)) {
            $ipaddr = get_dyndns_ip($rfc2136['interface'], 4);
            $fdata = @file_get_contents($filename);
        }

        $filename_v6 = rfc2136_cache_file($rfc2136, 6);
        $fdata6 = '';
        if (!empty($rfc2136['enable']) && (empty($rfc2136['recordtype']) || $rfc2136['recordtype'] == 'AAAA') && file_exists($filename_v6)) {
            $ipv6addr = get_dyndns_ip($rfc2136['interface'], 6);
            $fdata6 = @file_get_contents($filename_v6);
        }

        if (!empty($fdata)) {
            $cached_ip_s = explode('|', $fdata);
            $cached_ip = $cached_ip_s[0];
            echo sprintf(
                'IPv4: <font color="%s">%s</font>',
                $ipaddr != $cached_ip ? 'red' : 'green',
                htmlspecialchars($cached_ip)
            );
        } else {
            echo 'IPv4: ' . gettext('N/A');
        }

        echo '<br />';

        if (!empty($fdata6)) {
            $cached_ipv6_s = explode('|', $fdata6);
            $cached_ipv6 = $cached_ipv6_s[0];
            echo sprintf(
                'IPv6: <font color="%s">%s</font>',
                $ipv6addr != $cached_ipv6 ? 'red' : 'green',
                htmlspecialchars($cached_ipv6)
            );
        } else {
            echo 'IPv6: ' . gettext('N/A');
        }
    }
    exit;
}

?>

<table class="table table-striped table-condensed">
  <thead>
    <tr>
      <th><?=gettext("Interface");?></th>
      <th><?=gettext("Server");?></th>
      <th><?=gettext("Hostname");?></th>
      <th><?=gettext("Cached IP");?></th>
    </tr>
  </thead>
  <tbody>
<?php
  $iflist = get_configured_interface_with_descr();
  $groupslist = return_gateway_groups_array();
  foreach ($a_rfc2136 as $i => $rfc2136) :?>
    <tr ondblclick="document.location='services_rfc2136_edit.php?id=<?=$i;?>'">
      <td <?= isset($rfc2136['enable']) ? '' : 'class="text-muted"' ?>>
<?php
        foreach ($iflist as $if => $ifdesc) {
            if ($rfc2136['interface'] == $if) {
                echo "{$ifdesc}";
                break;
            }
        }
        foreach ($groupslist as $if => $group) {
            if ($rfc2136['interface'] == $if) {
                echo "{$if}";
                break;
            }
        }?>
      </td>
      <td <?= isset($rfc2136['enable']) ? '' : 'class="text-muted"' ?>>
        <?= htmlspecialchars($rfc2136['server']) ?>
      </td>
      <td <?= isset($rfc2136['enable']) ? '' : 'class="text-muted"' ?>>
        <?= htmlspecialchars($rfc2136['host']) ?>
      </td>
      <td <?= isset($rfc2136['enable']) ? '' : 'class="text-muted"' ?>>
        <div id='rfc2136status<?=$i;?>'>
          <?= gettext('Checking...') ?>
        </div>
      </td>
    </tr>
<?php
  endforeach;?>
  </tbody>
</table>
<script type="text/javascript">
  function rfc2136_getstatus()
  {
      scroll(0,0);
      var url = "/widgets/widgets/rfc2136.widget.php";
      var pars = 'getrfc2136status=yes';
      jQuery.ajax(url, {type: 'get', data: pars, complete: rfc2136callback});
      // Refresh the status every 5 minutes
      setTimeout('rfc2136_getstatus()', 5*60*1000);
  }
  function rfc2136callback(transport)
  {
      // The server returns a string of statuses separated by vertical bars
      var responseStrings = transport.responseText.split("|");
      for (var count=0; count<responseStrings.length; count++) {
          var divlabel = '#rfc2136status' + count;
          jQuery(divlabel).prop('innerHTML',responseStrings[count]);
      }
  }
  $( document ).ready(function() {
    // Do the first status check 2 seconds after the dashboard opens
    setTimeout('rfc2136_getstatus()', 2000);
  });
</script>
