<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
require_once("filter.inc");
require_once("vslb.inc");
require_once("services.inc");
require_once("interfaces.inc");

if (empty($config['load_balancer']) || !is_array($config['load_balancer'])) {
    $config['load_balancer'] = array();
}

if (empty($config['load_balancer']['lbpool']) || !is_array($config['load_balancer']['lbpool'])) {
    $config['load_balancer']['lbpool'] = array();
}
$a_pool = &$config['load_balancer']['lbpool'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['apply'])) {
        relayd_configure();
        filter_configure();
        clear_subsystem_dirty('loadbalancer');
        header(url_safe('Location: /status_lb_pool.php'));
        exit;
    } else {
        // change pool configuration (enabled/disabled servers)
        $pconfig = $_POST;
        if (!empty($pconfig['pools'])) {
            foreach ($pconfig['pools'] as $form_pool) {
                foreach ($a_pool as & $pool) {
                    if ($pool['name'] == $form_pool) {
                        $all_ips = array_merge((array) $pool['servers'], (array) $pool['serversdisabled']);
                        $new_disabled = array_diff($all_ips, (array)$pconfig[$form_pool]);
                        $new_enabled = (array)$pconfig[$form_pool];
                        $pool['servers'] = $new_enabled;
                        $pool['serversdisabled'] = $new_disabled;
                    }
                }
            }
            mark_subsystem_dirty('loadbalancer');
            write_config("Updated load balancer pools via status screen.");
        }
        header(url_safe('Location: /status_lb_pool.php'));
        exit;
    }
}

$service_hook = 'relayd';
include("head.inc");

$relay_hosts = get_lb_summary();
legacy_html_escape_form_data($a_pool);
legacy_html_escape_form_data($relay_hosts);
?>

<body>
<?php include("fbegin.inc"); ?>
    <section class="page-content-main">
      <div class="container-fluid">
        <div class="row">
          <?php if (is_subsystem_dirty('loadbalancer')): ?><br/>
          <?php print_info_box_apply(sprintf(gettext("The load balancer configuration has been changed%sYou must apply the changes in order for them to take effect."), "<br />"));?>
          <?php endif; ?>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <form method="post">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th><?=gettext("Name");?></th>
                        <th><?=gettext("Mode");?></th>
                        <th><?=gettext("Servers");?></th>
                        <th><?=gettext("Monitor");?></th>
                        <th><?=gettext("Description");?></th>
                      </tr>
                    </thead>
                    <tbody>
<?php
                    foreach ($a_pool as & $pool): ?>
                      <tr>
                        <td><?=$pool['name'];?></td>
                        <td>
<?php
                          switch($pool['mode']) {
                              case "loadbalance":
                                  echo "Load balancing";
                                  break;
                            case "failover":
                                  echo "Manual failover";
                                  break;
                            default:
                                  echo "(default)";
                          }?>
                        </td>
                        <td>
                          <input type="hidden" name="pools[]" value="<?=$pool['name'];?>">
                          <table class="table table-condensed">
<?php
                            $pool_hosts=array();
                            $svr = array();
                            foreach ((array) $pool['servers'] as $server) {
                                $svr['addr']=$server;
                                $svr['state']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['state'];
                                $svr['avail']=$relay_hosts[$pool['name'].":".$pool['port']][$server]['avail'];
                                $svr['bgcolor'] = $svr['state'] == 'up' ? "#90EE90" : "#F08080";
                                $pool_hosts[]=$svr;
                            }
                            foreach ((array) $pool['serversdisabled'] as $server) {
                                $svr['addr']="$server";
                                $svr['state']='disabled';
                                $svr['avail']='disabled';
                                $svr['bgcolor']='white';
                                $pool_hosts[]=$svr;
                            }
                            asort($pool_hosts);
                            foreach ($pool_hosts as $server):?>
                            <tr>
                              <td>
                                <input type="<?=$pool['mode'] == "loadbalance" ? "checkbox" : "radio";?>"
                                       name="<?=$pool['name'];?>[]" value="<?=$server['addr'];?>"
                                       <?=$server['state'] != 'disabled' ? "checked=\"checked\"" : "";?>
                                />
                              </td>
                              <td style="background:<?=$server['bgcolor'];?>"><?="{$server['addr']}:{$pool['port']}";?></td>
                              <td style="background:<?=$server['bgcolor'];?>"><?=!empty($server['avail']) ? " ({$server['avail']}) " : "";?></td>
                            </tr>

<?php
                            endforeach;?>
                          </table>
                        </td>
                        <td><?=$pool['monitor']; ?></td>
                        <td><?=$pool['descr'];?></td>
                      </tr>
<?php
                    endforeach; ?>
                      <tr>
                        <td colspan="5">
                          <input name="Submit" type="submit" class="btn btn-primary" value="<?= gettext("Save"); ?>" />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </form>
            </div>
          </section>
        </div>
      </div>
    </section>
<?php include("foot.inc"); ?>
