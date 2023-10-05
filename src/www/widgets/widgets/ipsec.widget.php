<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2007 Scott Dale
    Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>
    Copyright (C) 2004-2005 Manuel Kasper <mk@neon1.net>
    Copyright (C) 2004-2005 Jonathan Watt <jwatt@jwatt.org>
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

$ipsec_detail_array = [];
$ipsec_tunnels = [];
$ipsec_leases = [];

$ipsec_leases = json_decode(configd_run("ipsec list leases"), true);
if (!is_array($ipsec_leases) || empty($ipsec_leases['leases'])) {
    $ipsec_leases = [];
} else {
    $ipsec_leases = $ipsec_leases['leases'];
}
$ipsec_status = json_decode(configd_run("ipsec list status"), true) ?? [];

// parse configured tunnels
foreach ($ipsec_status as $status_key => $status_value) {
    if (isset($status_value['children']) && is_array($status_value['children'])) {
      foreach($status_value['children'] as $child_status_key => $child_status_value) {
          $ipsec_tunnels[$child_status_key] = array('active' => false,
                                                    'local-addrs' => $status_value['local-addrs'],
                                                    'remote-addrs' => $status_value['remote-addrs'],
                                                  );
          $ipsec_tunnels[$child_status_key]['local-ts'] = implode(', ', $child_status_value['local-ts']);
          $ipsec_tunnels[$child_status_key]['remote-ts'] = implode(', ', $child_status_value['remote-ts']);
      }
    }
    foreach ($status_value['sas'] as $sas_key => $sas_value) {
        foreach ($sas_value['child-sas'] as $child_sa_key => $child_sa_value) {
            if (!isset($ipsec_tunnels[$child_sa_key])) {
                /* XXX bug on strongSwan 5.5.2 appends -3 and -4 here? */
                $child_sa_key = preg_replace('/-[^-]+$/', '', $child_sa_key);
            }
            if (isset($ipsec_tunnels[$child_sa_key])) {
                $ipsec_tunnels[$child_sa_key]['active'] = true;
            }
        }
    }
}
// Initialize variable aggregated_data and loop through the ipsec_leases array to fetch the data for user, address and online status. Used later for div ipsec-mobile. Additionally count the unique_users in the same foreach loop, used for mobile_users count.
$aggregated_data = [];
$unique_users = [];

foreach ($ipsec_leases as $lease) {
    // For each unique user, initialize an empty array
    if (!isset($aggregated_data[$lease['user']])) {
        $aggregated_data[$lease['user']] = [];
    }
    // Add the lease data to this user's array of leases
    $aggregated_data[$lease['user']][] = [
        'address' => $lease['address'],
        'online' => $lease['online']
    ];
    
    // Count unique users in ipsec_leases array if lease is online
    if ($lease['online']) {
        $unique_users[$lease['user']] = true;
    }
}

// Return the number of unique_users as mobile_users
$mobile_users = count($unique_users);

?>
<script>
    $(document).ready(function() {
        $(".ipsec-tab").unbind('click').click(function() {
            $('.ipsec-tab').removeClass('activetab');
            $(this).addClass('activetab');
            $(".ipsec-tab-content").hide();
            $("#"+$(this).attr('data-for')).show();
        });
    });
</script>
<style>
    .dashboard_grid_column table {
        table-layout: fixed;
    }

    #ipsec #ipsec-mobile, #ipsec #ipsec-tunnel, #ipsec #ipsec-overview {
        background-color: #EEEEEE;
    }

    #ipsec .ipsec-tab {
        background-color: #777777;
        color: white;
    }

    #ipsec .ipsec-tab.activetab {
        background-color: #EEEEEE;
        color: black;
    }
</style>
<div id="tabs">
    <div data-for="ipsec-overview" class="ipsec-tab table-cell activetab" style="cursor: pointer; display:table-cell">
        <strong>&nbsp;&nbsp;<?=gettext("Overview");?>&nbsp;&nbsp;</strong>
    </div>
    <div data-for="ipsec-tunnel" class="ipsec-tab table-cell" style="cursor: pointer; display:table-cell">
        <strong>&nbsp;&nbsp;<?=gettext("Tunnels");?>&nbsp;&nbsp;</strong>
    </div>
    <div data-for="ipsec-mobile" class="ipsec-tab table-cell" style="cursor: pointer; display:table-cell">
        <strong>&nbsp;&nbsp;<?=gettext("Mobile");?>&nbsp;&nbsp;</strong>
    </div>
</div>

<div id="ipsec-overview" class="ipsec-tab-content" style="display:block;">
  <table class="table table-striped">
    <thead>
      <tr>
        <th><?= gettext('Active Tunnels');?></th>
        <th><?= gettext('Inactive Tunnels');?></th>
        <th><?= gettext('Mobile Users');?></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
<?php
        $activetunnels = 0;
        foreach ($ipsec_tunnels as $ipsec_key => $ipsec) {
            $activetunnels += $ipsec['active'] === true;
        }
        echo $activetunnels;
?>
        </td>
        <td><?= (count($ipsec_tunnels) - $activetunnels); ?></td>
        <td>
           <!-- mobile_users were counted in the earlier loop where data was aggregated -->
           <?=$mobile_users;?>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<div id="ipsec-tunnel" class="ipsec-tab-content" style="display:none;">
  <table class="table table-striped">
    <thead>
      <tr>
        <th><?= gettext('Connection');?></th>
        <th><?= gettext('Source');?></th>
        <th><?= gettext('Destination');?></th>
        <th><?= gettext('Status');?></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($ipsec_tunnels as $ipsec_key => $ipsec): ?>
      <tr>
        <td>
          <?=$ipsec['local-addrs'];?> <br/>
          (<?=$ipsec['remote-addrs'];?>)
        </td>
        <td><?=$ipsec['local-ts'];?></td>
        <td><?=$ipsec['remote-ts'];?></td>
        <td>
          <i class="fa fa-exchange fa-fw text-<?= $ipsec['active'] ? 'success' : 'danger' ?>"></i>
        </td>
      </tr>
<?php endforeach ?>
    </tbody>
  </table>
</div>

<div id="ipsec-mobile" class="ipsec-tab-content" style="display:none;">
    <table class="table table-striped">
        <thead>
        <tr>
            <th><?=gettext('User');?></th>
            <th><?=gettext('IP');?></th>
            <th><?=gettext('Status');?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        // Generate the user and IP addresses table rows using the aggregated_data variable that was populated earlier
        foreach ($aggregated_data as $user => $user_data):?>
            <tr>
                <td><?=htmlspecialchars($user);?></td>
                <td>
                    <table>
                        <?php foreach($user_data as $lease): ?>
                            <tr>
                                <td><?=htmlspecialchars($lease['address']);?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
                <td>
                    <table>
                        <?php foreach($user_data as $lease):?>
                            <tr>
                                <td>
                                    <!-- Show the online and offline status of each lease a user has. -->
                                    <i class="fa fa-exchange fa-fw text-<?=$lease['online'] ? 'success' : 'danger' ?>"></i>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
