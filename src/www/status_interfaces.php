<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['if']) && !empty($_POST['submit'])) {
        $interface = $_POST['if'];
        if ($_POST['submit'] == 'remote') {
            configdp_run('interface reconfigure', array($interface));
        } elseif (!empty($_POST['status']) && $_POST['status'] == 'up') {
            interface_bring_down($interface);
        } else {
            interface_configure(false, $interface, true);
        }
        header(url_safe('Location: /status_interfaces.php'));
        exit;
    }
}

include("head.inc");
?>
<body>

<script>
  $( document ).ready(function() {
    $("#collapse_all").click(function(){
        $(".interface_details").collapse('toggle');
    });
  });
</script>

<?php include("fbegin.inc"); ?>
    <section class="page-content-main">
      <div class="container-fluid">
        <div class="row">
          <section class="col-xs-12">
<?php
            $mac_man = json_decode(configd_run('interface list macdb json'), true);
            $pfctl_counters = json_decode(configd_run('filter list counters json'), true);
            $vmstat_interupts = json_decode(configd_run('system list interrupts json'), true);
            $ifsinfo = get_interfaces_info();
            foreach (legacy_config_get_interfaces(array('virtual' => false)) as $ifdescr => $ifcfg):
              $ifinfo = $ifsinfo[$ifdescr];
              $ifpfcounters = $pfctl_counters[$ifinfo['if']];
              legacy_html_escape_form_data($ifinfo);
              $ifdescr = htmlspecialchars($ifdescr);
              $ifname = htmlspecialchars($ifcfg['descr']);
?>
              <div class="tab-content content-box col-xs-12 __mb">
                <div class="table-responsive">
                  <table class="table">
                    <thead>
                      <tr>
                        <th style="cursor: pointer; width: 100%" data-toggle="collapse" data-target="#status_interfaces_<?=$ifname?>">
                          <i class="fa fa-chevron-down" style="margin-right: .4em; float: left"></i>
                          <?= $ifname ?> <?= gettext("interface") ?> (<?= $ifdescr ?>, <?= htmlspecialchars($ifinfo['if']) ?>)
                        </th>
<?php
                        if (!isset($first_row)):
                          $first_row = false; ?>
                        <th id="collapse_all" style="cursor: pointer; padding-left: .5em; padding-right: .5em" data-toggle="tooltip" title="<?= gettext("collapse/expand all") ?>">
                          <div class="pull-right">
                            <i class="fa fa-expand"></i>
                          </div>
                        </th>
<?php
                        endif;?>
                      </tr>
                    </thead>
                  </table>
                </div>
                <div class="interface_details collapse table-responsive"  id="status_interfaces_<?=$ifname?>">
                  <table class="table table-striped">
                  <tbody>
                    <tr>
                      <td style="width:22%"><?= gettext("Status") ?></td>
                      <td style="width:78%"><?= $ifinfo['status'] ?></td>
                    </tr>
<?php
                    if (!empty($ifinfo['dhcplink'])): ?>
                    <tr>
                      <td> <?=gettext("DHCP");?></td>
                      <td>
                        <form name="dhcplink_form" method="post">
                          <input type="hidden" name="if" value="<?= $ifdescr ?>" />
                          <input type="hidden" name="status" value="<?= $ifinfo['dhcplink'] ?>" />
                          <?= $ifinfo['dhcplink'] ?>&nbsp;&nbsp;
                          <button type="submit" name="submit" class="btn btn-primary btn-xs" value="remote"><?= gettext('Reload') ?></button>
                          <button type="submit" name="submit" class="btn btn-xs" value="local"><?= $ifinfo['dhcplink'] == "up" ? gettext("Release") : gettext("Renew") ?></button>
                        </form>
                      </td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['dhcp6link'])): ?>
                    <tr>
                      <td> <?=gettext("DHCP6");?></td>
                      <td>
                        <form name="dhcp6link_form" method="post">
                          <input type="hidden" name="if" value="<?= $ifdescr ?>" />
                          <input type="hidden" name="status" value="<?= $ifinfo['dhcp6link'] ?>" />
                          <?= $ifinfo['dhcp6link'] ?>&nbsp;&nbsp;
                          <button type="submit" name="submit" class="btn btn-primary btn-xs" value="remote"><?= gettext('Reload') ?></button>
                          <button type="submit" name="submit" class="btn btn-xs" value="local"><?= $ifinfo['dhcp6link'] == "up" ? gettext("Release") : gettext("Renew") ?></button>
                        </form>
                      </td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['pppoelink'])): ?>
                    <tr>
                      <td><?=gettext("PPPoE"); ?></td>
                      <td>
                        <form name="pppoelink_form" method="post">
                          <input type="hidden" name="if" value="<?= $ifdescr ?>" />
                          <input type="hidden" name="status" value="<?= $ifinfo['pppoelink'] ?>" />
                          <?= $ifinfo['pppoelink'] ?>&nbsp;&nbsp;
                          <button type="submit" name="submit" class="btn btn-primary btn-xs" value="remote"><?= gettext('Reload') ?></button>
                          <button type="submit" name="submit" class="btn btn-xs" value="local"><?= $ifinfo['pppoelink'] == "up" ? gettext("Disconnect") : gettext("Connect") ?></button>
                        </form>
                      </td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['pptplink'])): ?>
                    <tr>
                      <td><?= gettext("PPTP") ?></td>
                      <td>
                        <form name="pptplink_form" method="post">
                          <input type="hidden" name="if" value="<?= $ifdescr ?>" />
                          <input type="hidden" name="status" value="<?= $ifinfo['pptplink'] ?>" />
                          <?= $ifinfo['pptplink'] ?>&nbsp;&nbsp;
                          <button type="submit" name="submit" class="btn btn-primary btn-xs" value="remote"><?= gettext('Reload') ?></button>
                          <button type="submit" name="submit" class="btn btn-xs" value="local"><?= $ifinfo['pptplink'] == "up" ? gettext("Disconnect") : gettext("Connect") ?></button>
                        </form>
                      </td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['l2tplink'])): ?>
                    <tr>
                      <td><?=gettext("L2TP"); ?></td>
                      <td>
                        <form name="l2tplink_form" method="post">
                          <input type="hidden" name="if" value="<?= $ifdescr ?>" />
                          <input type="hidden" name="status" value="<?= $ifinfo['l2tplink'] ?>" />
                          <?=$ifinfo['l2tplink'];?>&nbsp;&nbsp;
                          <button type="submit" name="submit" class="btn btn-primary btn-xs" value="remote"><?= gettext('Reload') ?></button>
                          <button type="submit" name="submit" class="btn btn-xs" value="local"><?= $ifinfo['l2tplink'] == "up" ? gettext("Disconnect") : gettext("Connect") ?></button>
                        </form>
                      </td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['ppplink'])): ?>
                    <tr>
                      <td><?=gettext("PPP"); ?></td>
                      <td>
                        <form name="ppplink_form" method="post">
                          <input type="hidden" name="if" value="<?= $ifdescr ?>" />
                          <input type="hidden" name="status" value="<?= $ifinfo['ppplink'] ?>" />
                          <?= $ifinfo['pppinfo'] ?>
                          <button type="submit" name="submit" class="btn btn-primary btn-xs" value="remote"><?= gettext('Reload') ?></button>
                          <?php if ($ifinfo['ppplink'] == "up"): ?>
                            <button type="submit" name="submit" class="btn btn-xs" value="local"><?= gettext("Disconnect") ?></button>
                          <?php elseif (!$ifinfo['nodevice']): ?>
                            <button type="submit" name="submit" class="btn btn-xs" value="local"><?= gettext("Connect") ?></button>
                          <?php endif ?>
                        </form>
                      </td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['ppp_uptime']) || !empty($ifinfo['ppp_uptime_accumulated'])): ?>
                    <tr>
                      <td><?= empty($ifinfo['ppp_uptime_accumulated']) ? gettext("Uptime") : gettext("Uptime (historical)") ?></td>
                      <td><?= $ifinfo['ppp_uptime_accumulated'] ?> <?= $ifinfo['ppp_uptime'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['cell_rssi'])): ?>
                    <tr>
                      <td><?= gettext("Cell Signal (RSSI)") ?></td>
                      <td><?= $ifinfo['cell_rssi'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['cell_mode'])): ?>
                    <tr>
                      <td><?= gettext("Cell Mode") ?></td>
                      <td><?= $ifinfo['cell_mode'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['cell_simstate'])): ?>
                    <tr>
                      <td><?= gettext("Cell SIM State") ?></td>
                      <td><?= $ifinfo['cell_simstate'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['cell_service'])): ?>
                    <tr>
                      <td><?= gettext("Cell Service") ?></td>
                      <td><?= $ifinfo['cell_service'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['cell_bwupstream'])): ?>
                    <tr>
                      <td><?= gettext("Cell Upstream");?></td>
                      <td><?= sprintf(gettext("%s kbit/s"),$ifinfo['cell_bwupstream']) ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['cell_bwdownstream'])): ?>
                    <tr>
                      <td><?= gettext("Cell Downstream") ?></td>
                      <td><?= sprintf(gettext("%s kbit/s"),$ifinfo['cell_bwdownstream']) ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['cell_upstream'])): ?>
                    <tr>
                      <td><?= gettext("Cell Current Up");?></td>
                      <td><?= sprintf(gettext("%s kbit/s"), $ifinfo['cell_upstream']) ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['cell_downstream'])): ?>
                    <tr>
                      <td><?= gettext("Cell Current Down") ?></td>
                      <td><?= sprintf(gettext("%s kbit/s"),$ifinfo['cell_downstream']) ?></td>
                    </tr>
<?php
                    endif;
                    if ($ifinfo['macaddr']): ?>
                    <tr>
                      <td><?=gettext("MAC address");?></td>
                      <td>
                        <?php
                        $mac=$ifinfo['macaddr'];
                        $mac_hi = strtoupper($mac[0] . $mac[1] . $mac[3] . $mac[4] . $mac[6] . $mac[7]);
                        if(isset($mac_man[$mac_hi])){ print "<span>" . $mac . " - " . htmlspecialchars($mac_man[$mac_hi]); print "</span>"; }
                              else {print htmlspecialchars($mac);}
                        ?>
                      </td>
                    </tr>
<?php
                  endif;
                  if ($ifinfo['mtu']): ?>
                  <tr>
                    <td><?=gettext("MTU");?></td>
                    <td>
                      <?=$ifinfo['mtu'];?>
                    </td>
                  </tr>
<?php
                endif;
                if ($ifinfo['status'] != "down"):
                  if ($ifinfo['dhcplink'] != "down" && $ifinfo['pppoelink'] != "down" && $ifinfo['pptplink'] != "down"):
                    if (!empty($ifinfo['ipaddr'])):?>
                    <tr>
                      <td><?= gettext("IPv4 address") ?></td>
                      <td>
                        <?=$ifinfo['ipaddr'];?>/<?=$ifinfo['subnet'];?>
<?php
                        foreach($ifinfo['ipv4'] as $ipv4):
                            if ($ipv4['ipaddr'] != $ifinfo['ipaddr']):?>
                            <br/>
                            <i class="fa fa-plus-square-o" aria-hidden="true"></i>
                            <?=$ipv4['ipaddr'];?>/<?=$ipv4['subnetbits'];?> <?= !empty($ipv4['vhid']) ? 'vhid ' . $ipv4['vhid'] : "" ;?>
<?php
                            endif;
                        endforeach;?>
                      </td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['gateway'])): ?>
                    <tr>
                      <td><?= gettext("Gateway IPv4") ?></td>
                      <td><?= htmlspecialchars($config['interfaces'][$ifdescr]['gateway']) ?> <?= $ifinfo['gateway'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['linklocal'])): ?>
                    <tr>
                      <td><?= gettext("IPv6 Link Local") ?></td>
                      <td><?= $ifinfo['linklocal'] ?>/64
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['ipaddrv6'])): ?>
                    <tr>
                      <td><?= gettext("IPv6 address") ?></td>
                      <td>
<?php
                        foreach($ifinfo['ipv6'] as $ipv6):
                            if ($ipv6['ipaddr'] != $ifinfo['ipaddrv6'] && !$ipv6['link-local']):?>
                            <?=$ipv6['ipaddr'];?>/<?=$ipv6['subnetbits'];?> <?= !empty($ipv6['vhid']) ? 'vhid ' . $ipv6['vhid'] : "" ;?> <?= $ipv6['deprecated'] ? 'deprecated' : '' ?>
                            <br />
<?php
                            endif;
                        endforeach;?>
                      </td>
                    </tr>
<?php endif ?>
<?php if (array_key_exists('pdinfo', $ifinfo)): ?>
                    <tr>
                      <td><?= gettext('Delegated prefix') ?></td>
                      <td><?= $ifinfo['pdinfo'] ?></td>
                    </tr>
<?php endif ?>
<?php if (!empty($ifinfo['gatewayv6'])): ?>
                    <tr>
                      <td><?= gettext("Gateway IPv6") ?></td>
                      <td><?= htmlspecialchars($config['interfaces'][$ifdescr]['gatewayv6']) ?> <?= $ifinfo['gatewayv6'] ?></td>
                    </tr>
<?php
                    endif;
                    $dnsall = get_nameservers($ifdescr);
                    if (count($dnsall)): ?>
                    <tr>
                      <td><?= gettext("DNS servers") ?></td>
                      <td>
<?php
                          echo implode('<br />', $dnsall); ?>
                      </td>
                    </tr>
<?php
                    endif;
                  endif;
                  if (!empty($ifinfo['media'])): ?>
                    <tr>
                      <td><?= gettext("Media") ?></td>
                      <td><?= $ifinfo['media'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['laggproto'])):?>
                    <tr>
                      <td><?= gettext("LAGG Protocol") ?></td>
                      <td><?= $ifinfo['laggproto'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['laggport']) && is_array($ifinfo['laggport'])): ?>
                    <tr>
                      <td><?=gettext("LAGG Ports");?></td>
                      <td>
                        <?php  foreach ($ifinfo['laggport'] as $laggport): ?>
                            <?= $laggport ?><br />
                        <?php  endforeach; ?>
                      </td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['channel'])): ?>
                    <tr>
                      <td><?= gettext("Channel") ?></td>
                      <td><?= $ifinfo['channel'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['ssid'])):?>
                    <tr>
                      <td><?= gettext("SSID") ?></td>
                      <td><?= $ifinfo['ssid'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['bssid'])):?>
                    <tr>
                      <td><?= gettext("BSSID") ?></td>
                      <td><?= $ifinfo['bssid'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['rate'])):?>
                    <tr>
                      <td><?= gettext("Rate") ?></td>
                      <td><?= $ifinfo['rate'] ?></td>
                    </tr>
<?php
                    endif;
                    if (!empty($ifinfo['rssi'])): ?>
                    <tr>
                      <td><?= gettext("RSSI") ?></td>
                      <td><?= $ifinfo['rssi'] ?></td>
                    </tr>
<?php
                    endif; ?>
                    <tr>
                      <td><?= gettext("In/out packets") ?></td>
                      <td class="text-nowrap"> <?= $ifpfcounters['inpkts'] ?> / <?= $ifpfcounters['outpkts'] ?>
                          (<?= format_bytes($ifpfcounters['inbytes']);?> / <?=format_bytes($ifpfcounters['outbytes']);?>)
                      </td>
                    </tr>
                    <tr>
                      <td><?= gettext("In/out packets (pass)") ?></td>
                      <td class="text-nowrap"> <?= $ifpfcounters['inpktspass'] ?> / <?= $ifpfcounters['outpktspass'] ?>
                          (<?= format_bytes($ifpfcounters['inbytespass']) ?> / <?= format_bytes($ifpfcounters['outbytespass']) ?>)
                      </td>
                    </tr>
                    <tr>
                      <td><?= gettext("In/out packets (block)") ?></td>
                      <td class="text-nowrap"> <?= $ifpfcounters['inpktsblock'] ?> / <?= $ifpfcounters['outpktsblock'] ?>
                          (<?= format_bytes($ifpfcounters['inbytesblock']) ?> / <?= format_bytes($ifpfcounters['outbytesblock']) ?>)
                      </td>
                    </tr>
<?php
                    if (isset($ifinfo['inerrs'])): ?>
                    <tr>
                      <td><?= gettext("In/out errors") ?></td>
                      <td><?= $ifinfo['inerrs'] . ' / ' . $ifinfo['outerrs'] ?></td>
                    </tr>
<?php
                    endif;
                    if (isset($ifinfo['collisions'])): ?>
                    <tr>
                      <td><?= gettext("Collisions") ?></td>
                      <td><?= $ifinfo['collisions'] ?></td>
                    </tr>
<?php
                    endif;
                  endif;
                  if (!empty($ifinfo['bridge'])): ?>
                    <tr>
                      <td><?= sprintf(gettext('Bridge (%s)'), $ifinfo['bridgeint']) ?></td>
                      <td>
                        <?= $ifinfo['bridge'] ?>
                      </td>
                    </tr>
<?php
                  endif;
                  if (!empty($vmstat_interupts['interrupt_map'][$ifinfo['if']])):
                      $intrpts = $vmstat_interupts['interrupt_map'][$ifinfo['if']];?>
                    <tr>
                      <td><?= gettext("Interrupts") ?></td>
                      <td>
                        <table class="table table-condensed">
                          <thead>
                            <tr>
                              <th><?=gettext("irq");?></th>
                              <th><?=gettext("device");?></th>
                              <th><?=gettext("total");?></th>
                              <th><?=gettext("rate");?></th>
                            </tr>
                          </thead>
<?php
                        foreach ($intrpts as $intrpt):?>
                        <tr>
                          <td><?=$intrpt;?></td>
                          <td><?=implode(' ', $vmstat_interupts['interrupts'][$intrpt]['devices']);?></td>
                          <td><?=$vmstat_interupts['interrupts'][$intrpt]['total'];?></td>
                          <td><?=$vmstat_interupts['interrupts'][$intrpt]['rate'];?></td>
                        </tr>
<?php
                        endforeach; ?>
                        </table>
                      </td>
                    </tr>
<?php
                  endif; ?>
<?php
                  if (!empty($ifinfo['carp'])):?>
                  <tr>
                      <td><?=gettext("CARP");?></td>
                      <td>
                          <table class="table table-condensed">
                            <thead>
                              <tr>
                                <th><?=gettext("status");?></th>
                                <th><?=gettext("vhid");?></th>
                                <th><?=gettext("advbase");?></th>
                                <th><?=gettext("advskew");?></th>
                              </tr>
                            </thead>
                            <tbody>
<?php
                            foreach($ifinfo['carp'] as $carpitem):?>
                                <tr>
                                  <td><?=$carpitem['status'];?></td>
                                  <td><?=$carpitem['vhid'];?></td>
                                  <td><?=$carpitem['advbase'];?></td>
                                  <td><?=$carpitem['advskew'];?></td>
                                </tr>
<?php
                            endforeach;?>
                            </tbody>
                          </table>
                      </td>
                  </tr>
<?php
                  endif;?>
                  </tbody>
                </table>
              </div>
            </div>
<?php
            endforeach; ?>
            <div class="tab-content content-box col-xs-12 __mb">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td>
                      <?= gettext("Using dial-on-demand will bring the connection up again if any packet ".
                      "triggers it. To substantiate this point: disconnecting manually ".
                      "will not prevent dial-on-demand from making connections ".
                      "to the outside. Don't use dial-on-demand if you want to make sure that the line ".
                      "is kept disconnected.") ?>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </section>
        </div>
      </div>
    </section>

<?php include("foot.inc"); ?>
