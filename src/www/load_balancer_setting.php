<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2008 Bill Marquette <bill.marquette@gmail.com>
    Copyright (C) 2012 Pierre POMES <pierre.pomes@gmail.com>
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
require_once("vslb.inc");
require_once("interfaces.inc");

if (empty($config['load_balancer']) || !is_array($config['load_balancer'])) {
    $config['load_balancer'] = array();
}

if (empty($config['load_balancer']['setting']) || !is_array($config['load_balancer']['setting'])) {
    $config['load_balancer']['setting'] = array();
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['timeout'] = !empty($config['load_balancer']['setting']['timeout']) ? $config['load_balancer']['setting']['timeout'] : null;
    $pconfig['interval'] = !empty($config['load_balancer']['setting']['interval']) ? $config['load_balancer']['setting']['interval'] : null;
    $pconfig['prefork'] = !empty($config['load_balancer']['setting']['prefork']) ? $config['load_balancer']['setting']['prefork'] : null;
    $pconfig['lb_use_sticky'] = isset($config['load_balancer']['setting']['lb_use_sticky']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();
    if (!empty($pconfig['apply'])) {
        relayd_configure();
        filter_configure();
        clear_subsystem_dirty('loadbalancer');
        header("Location: load_balancer_setting.php");
        exit;
    } else {
        /* input validation */
        if (!empty($pconfig['timeout']) && !is_numeric($pconfig['timeout'])) {
            $input_errors[] = gettext("Timeout must be a numeric value");
        }

        if (!empty($pconfig['interval']) && !is_numeric($pconfig['interval'])) {
            $input_errors[] = gettext("Interval must be a numeric value");
        }

        if (!empty($pconfig['prefork'])) {
            if (!is_numeric($pconfig['prefork'])) {
                $input_errors[] = gettext("Prefork must be a numeric value");
            } elseif ($pconfig['prefork']<=0 || $pconfig['prefork']>32) {
                $input_errors[] = gettext("Prefork value must be between 1 and 32");
            }
        }
        if (count($input_errors) == 0) {
            $config['load_balancer']['setting']['timeout'] = $pconfig['timeout'];
            $config['load_balancer']['setting']['interval'] = $pconfig['interval'];
            $config['load_balancer']['setting']['prefork'] = $pconfig['prefork'];

            if (!empty($pconfig['lb_use_sticky'])) {
                $config['load_balancer']['setting']['lb_use_sticky'] = true;
            } elseif (isset($config['load_balancer']['setting']['lb_use_sticky'])) {
                unset($config['load_balancer']['setting']['lb_use_sticky']);
            }

            write_config();
            mark_subsystem_dirty('loadbalancer');
            header("Location: load_balancer_setting.php");
            exit;
        }
    }
}


$service_hook = 'relayd';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <?php if (is_subsystem_dirty('loadbalancer')): ?><br/>
        <?php print_info_box_apply(gettext("The load balancer configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
        <?php endif; ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td width="22%">
                        <strong><?=gettext("Global settings"); ?></strong>
                      </td>
                      <td width="78%" align="right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                      </td>
                    </tr>
                    <tr>
                       <td><a id="help_for_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Timeout") ; ?></td>
                       <td>
                         <input type="text" name="timeout" id="timeout" value="<?=$pconfig['timeout'];?>" />
                         <div class="hidden" for="help_for_timeout">
                           <?=gettext("Set the global timeout in milliseconds for checks. Leave blank to use the default value of 1000 ms "); ?>
                         </div>
                       </td>
                    </tr>
                    <tr>
                       <td><a id="help_for_interval" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interval") ; ?></td>
                       <td>
                         <input type="text" name="interval" id="interval" value="<?=$pconfig['interval']; ?>"/>
                         <div class="hidden" for="help_for_interval">
                           <?=gettext("Set the interval in seconds at which the member of a pool will be checked. Leave blank to use the default interval of 10 seconds"); ?>
                         </div>
                      </td>
                   </tr>
                    <tr>
                       <td><a id="help_for_prefork" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Prefork") ; ?></td>
                       <td>
                         <input type="text" name="prefork" id="prefork" value="<?=$pconfig['prefork']; ?>"/>
                         <div class="hidden" for="help_for_prefork">
                           <?=gettext("Number of processes used by relayd for dns protocol. Leave blank to use the default value of 5 processes"); ?>
                         </div>
                      </td>
                   </tr>
                   <tr>
                     <td><a id="help_for_lb_use_sticky" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Sticky connections");?> </td>
                     <td>
                       <input name="lb_use_sticky" type="checkbox" id="lb_use_sticky" value="yes" <?= !empty($pconfig['lb_use_sticky']) ? 'checked="checked"' : '';?>/>
                       <strong><?=gettext("Use sticky connections"); ?></strong><br />
                       <div class="hidden" for="help_for_lb_use_sticky">
                         <?=gettext("Successive connections will be redirected to the servers " .
                                             "in a round-robin manner with connections from the same " .
                                             "source being sent to the same web server. This 'sticky " .
                                             "connection' will exist as long as there are states that " .
                                             "refer to this connection. Once the states expire, so will " .
                                             "the sticky connection. Further connections from that host " .
                                             "will be redirected to the next web server in the round-robin."); ?>
                       </div>
                     </td>
                   </tr>
                   <tr>
                       <td>&nbsp;</td>
                       <td>
                          <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                       </td>
                  </tr>
                 </table>
                </div>
            </form>

          </div>
          </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
