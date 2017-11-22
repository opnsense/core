<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
    Copyright (C) 2004-2005 Scott Ullrich <sullrich@gmail.com>
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
require_once("services.inc");
require_once("system.inc");
require_once("filter.inc");
require_once("interfaces.inc");
require_once("rrd.inc");

if (!empty($_POST['service'])) {
    $service_name = $_POST['service'];
    switch ($_POST['action']) {
        case 'restart':
          echo service_control_restart($service_name, $_POST);
          break;
        case 'start':
          echo service_control_start($service_name, $_POST);
          break;
        case 'stop':
          echo service_control_stop($service_name, $_POST);
          break;
    }
    exit;
}


$services = services_get();

include("head.inc");

?>
<body>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <div class="table-responsive">
              <table class="table table-striped">
              <thead>
                <tr>
                  <td><?=gettext("Service");?></td>
                  <td><?=gettext("Description");?></td>
                  <td><?=gettext("Status");?></td>
                </tr>
              </thead>
              <tbody>
<?php
               if (count($services) > 0):
                foreach($services as $service):?>
                <tr>
                    <td><?=$service['name'];?></td>
                    <td><?=$service['description'];?></td>
                    <td>
                      <?=get_service_status_icon($service);?>
                      <?=get_service_control_links($service);?>
                    </td>
                </tr>
<?php
                endforeach;
              else:?>
                <tr>
                  <td colspan="3"> <?=gettext("No services found");?></td>
                </tr>
<?php
                 endif;?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
