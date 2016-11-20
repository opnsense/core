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
require_once("services.inc");
require_once("plugins.inc.d/relayd.inc");

if (empty($config['load_balancer']['lbpool']) || !is_array($config['load_balancer']['lbpool'])) {
    $a_pool = array();
} else {
    $a_pool = &$config['load_balancer']['lbpool'];
}
if (empty($config['load_balancer']['virtual_server']) || !is_array($config['load_balancer']['virtual_server'])) {
    $a_vs = array();
} else {
    $a_vs = &$config['load_balancer']['virtual_server'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['apply'])) {
        relayd_configure_do();
        filter_configure();
        clear_subsystem_dirty('loadbalancer');
        header(url_safe('Location: /status_lb_vs.php'));
        exit;
    }
}

$rdr_a = relayd_get_lb_redirects();

$service_hook = 'relayd';
legacy_html_escape_form_data($a_vs);
legacy_html_escape_form_data($a_pool);
legacy_html_escape_form_data($rdr_a);
include("head.inc");

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
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <td><?=gettext("Name"); ?></td>
                    <td><?=gettext("Address"); ?></td>
                    <td><?=gettext("Servers"); ?></td>
                    <td><?=gettext("Status"); ?></td>
                    <td><?=gettext("Description"); ?></td>
                  </tr>
                </thead>
                <tbody>
<?php
                $i = 0;
                foreach ($a_vs as $vsent): ?>
                  <tr>
                    <td><?=$vsent['name'];?></td>
                    <td><?=$vsent['ipaddr']." : ".$vsent['port'];?></td>
                    <td>
<?php
                      foreach ($a_pool as $vipent):
                        if ($vipent['name'] == $vsent['poolname']):?>

                        <?=implode('<br/>',$vipent['servers']);?>
<?php
                        endif;
                      endforeach;?>
                    </td>
<?php
                      switch (trim($rdr_a[$vsent['name']]['status'])) {
                        case 'active':
                          $bgcolor = "#90EE90";  // lightgreen
                          $rdr_a[$vsent['name']]['status'] = "Active";
                          break;
                        case 'down':
                          $bgcolor = "#F08080";  // lightcoral
                          $rdr_a[$vsent['name']]['status'] = "Down";
                          break;
                        default:
                          $bgcolor = "#D3D3D3";  // lightgray
                          $rdr_a[$vsent['name']]['status'] = 'Unknown - relayd not running?';
                      }
                      ?>
                    <td>
                      <table border="0" cellpadding="3" cellspacing="2" summary="status">
                        <tr><td bgcolor="<?=$bgcolor?>"><?=$rdr_a[$vsent['name']]['status']?> </td></tr>
                      </table>
                      <?=!empty($rdr_a[$vsent['name']]['total']) ?  "Total Sessions: {$rdr_a[$vsent['name']]['total']}" : "";?>
                      <?=!empty($rdr_a[$vsent['name']]['last']) ? "<br />Last: {$rdr_a[$vsent['name']]['last']}" : "";?>
                      <?=!empty($rdr_a[$vsent['name']]['average']) ? "<br />Average: {$rdr_a[$vsent['name']]['average']}" : "";?>
                    </td>
                    <td><?=$vsent['descr'];?></td>
                  </tr>
<?php
                  $i++;
                endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
