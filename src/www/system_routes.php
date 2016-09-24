<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("filter.inc");
require_once("system.inc");
require_once("rrd.inc");

function delete_static_route($id)
{
    global $config, $a_routes;

    if (!isset($a_routes[$id])) {
        return;
    }

    $targets = array();
    if (is_alias($a_routes[$id]['network'])) {
        foreach (filter_expand_alias_array($a_routes[$id]['network']) as $tgt) {
            if (is_ipaddrv4($tgt)) {
                $tgt .= "/32";
            } elseif (is_ipaddrv6($tgt)) {
                $tgt .= "/128";
            }
            if (!is_subnet($tgt)) {
                continue;
            }
            $targets[] = $tgt;
        }
    } else {
        $targets[] = $a_routes[$id]['network'];
    }

    foreach ($targets as $tgt) {
        $family = (is_subnetv6($tgt) ? "-inet6" : "-inet");
        mwexec("/sbin/route delete {$family} " . escapeshellarg($tgt));
    }

    unset($targets);
}

if (!isset($config['staticroutes']['route']) || !is_array($config['staticroutes']['route'])) {
    $a_routes = array();
} else {
    $a_routes = &$config['staticroutes']['route'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($_POST['id']) && isset($a_routes[$_POST['id']])) {
        $id = $_POST['id'];
    }
    if (!empty($_POST['act'])) {
        $act = $_POST['act'];
    } else {
        $act = null;
    }

    if (!empty($pconfig['apply'])) {
        // todo: remove this ugly hook
        if (file_exists('/tmp/.system_routes.apply')) {
            $toapplylist = unserialize(file_get_contents('/tmp/.system_routes.apply'));
            foreach ($toapplylist as $toapply) {
                mwexec("{$toapply}");
            }
            @unlink('/tmp/.system_routes.apply');
        }

        system_routing_configure();
        filter_configure();
        setup_gateways_monitor();
        clear_subsystem_dirty('staticroutes');
    } elseif (isset($id) && $act == 'del') {
        delete_static_route($id);
        unset($a_routes[$id]);
        write_config();
    } elseif ($act == 'del_x' && isset($pconfig['route'])) {
        /* delete selected routes */
        if (is_array($pconfig['route'])) {
            foreach ($_POST['route'] as $routei) {
                delete_static_route($routei);
                unset($a_routes[$routei]);
            }
            write_config();
        }
    } elseif (isset($id) && $act == "toggle") {
        if (isset($a_routes[$id]['disabled'])) {
            unset($a_routes[$id]['disabled']);
        } else {
            delete_static_route($id);
            $a_routes[$id]['disabled'] = true;
        }

        write_config();
        mark_subsystem_dirty('staticroutes');
    } elseif ( $act == 'move' && isset($pconfig['route']) && count($pconfig['route']) > 0) {
        // move selected rules
        if (!isset($id)) {
            // if rule not set/found, move to end
            $id = count($a_routes);
        }
        $a_routes = legacy_move_config_list_items($a_routes, $id,  $pconfig['route']);
        write_config();
        mark_subsystem_dirty('staticroutes');
    }
    header(url_safe('Location: /system_routes.php'));
    exit;
}

$a_gateways = return_gateways_array(true, true, true);
legacy_html_escape_form_data($a_routes);
legacy_html_escape_form_data($a_gateways);

$main_buttons = array(
    array('label'=> gettext('Add route'), 'href'=>'system_routes_edit.php'),
);

include("head.inc");

?>


<script type="text/javascript">
$( document ).ready(function() {
    // link remove route
    $(".act-del-route").click(function(event){
        var id = $(this).data('id');
        event.preventDefault();
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: "<?= gettext("Route");?>",
            message: '<?=gettext("Do you really want to delete this route?");?>',
            buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                      dialogRef.close();
                    }}, {
                      label: "<?= gettext("Yes");?>",
                      action: function(dialogRef) {
                        $("#id").val(id);
                        $("#act").val("del");
                        $("#iform").submit();
                    }
            }]
        });
    });

    // link remove list of routes
    $("#del_x").click(function(event){
      event.preventDefault();
      BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("Route");?>",
          message: '<?=gettext("Do you really want to delete the selected routes?");?>',
          buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                    dialogRef.close();
                  }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#id").val(id);
                      $("#act").val("del_x");
                      $("#iform").submit();
                  }
          }]
      });
    });

    // link toggle buttons
    $(".act_toggle").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      $("#id").val(id);
      $("#act").val("toggle");
      $("#iform").submit();
    });

    // link move buttons
    $(".act_move").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      $("#id").val(id);
      $("#act").val("move");
      $("#iform").submit();
    });

    // watch scroll position and set to last known on page load
    watchScrollPosition();
});
</script>
<body>
  <?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
      if (is_subsystem_dirty('staticroutes')):?><p>
        <?php print_info_box_apply(sprintf(gettext("The static route configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br />"));?><br /></p>
<?php
endif; ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
              <input type="hidden" id="act" name="act" value="" />
              <input type="hidden" id="id" name="id" value="" />
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td></td>
                    <td></td>
                    <td><?=gettext("Network");?></td>
                    <td><?=gettext("Gateway");?></td>
                    <td><?=gettext("Interface");?></td>
                    <td><?=gettext("Description");?></td>
                    <td></td>
                  </tr>
<?php
                  $i = 0;
                  foreach ($a_routes as $route) :?>
                  <tr class="<?=isset($route['disabled']) ? "text-muted" : "";?>">
                    <td>
                        <input type="checkbox" name="route[]" value="<?=$i;?>"/>
                    </td>
                    <td>
                      <a href="#" class="act_toggle" data-id="<?=$i;?>">
                        <span class="glyphicon glyphicon-play <?=isset($route['disabled']) ? "text-muted" : "text-success" ;?>" data-toggle="tooltip"
                              title="<?=(!isset($route['disabled'])) ? gettext("disable route") : gettext("enable route");?>" alt="icon">
                        </span>
                      </a>
                    </td>
                    <td>
                      <?=strtolower($route['network']);?>
                    </td>
                    <td>
                      <?=$a_gateways[$route['gateway']]['name'] . " - " . $a_gateways[$route['gateway']]['gateway'];?>
                    </td>
                    <td>
                      <?=convert_friendly_interface_to_friendly_descr($a_gateways[$route['gateway']]['friendlyiface']);?>
                    </td>
                    <td>
                      <?=$route['descr'];?>
                    </td>
                    <td>
                      <a data-id="<?=$i;?>" data-toggle="tooltip" title="<?=gettext("move selected routes before this route");?>" class="act_move btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-arrow-left"></span>
                      </a>
                      <a class="btn btn-default btn-xs" href="system_routes_edit.php?id=<?=$i;?>"
                          title="<?=gettext("edit route");?>" data-toggle="tooltip">
                        <span class="glyphicon glyphicon-pencil" alt="edit" ></span>
                      </a>
                      <button type="button" class="btn btn-default btn-xs act-del-route"
                          data-id="<?=$i?>" title="<?=gettext("delete route");?>" data-toggle="tooltip">
                        <span class="fa fa-trash text-muted"></span>
                      </button>
                      <a class="btn btn-default btn-xs" href="system_routes_edit.php?dup=<?=$i;?>"
                          title="<?=gettext("clone route");?>" data-toggle="tooltip">
                        <span class="fa fa-clone text-muted" alt="duplicate"></span>
                      </a>
                    </td>
                  </tr>

<?php
                  $i++;
                  endforeach; ?>
                  <tr>
                    <td colspan="6"></td>
                    <td>
<?php
                    if ($i == 0) :?>
                        <span class="glyphicon glyphicon-arrow-left text-muted"
                            title="<?=gettext("move selected routes to end");?>" alt="move" />
<?php
                    else :?>
                    <button type="submit" data-id="<?=$i;?>"  data-toggle="tooltip" title="<?=gettext("move selected routes to end");?>" class="act_move btn btn-default btn-xs">
                      <span class="glyphicon glyphicon-arrow-left"></span>
                    </button>
<?php
                    endif;?>
<?php
                    if ($i == 0) :?>
                    <span class="btn btn-default btn-xs">
                        <span class="fa fa-trash text-muted"></span>
                    </span>

<?php
                    else :?>
                    <button id="del_x" title="<?=gettext("delete selected routes");?>" class="btn btn-default btn-xs" data-toggle="tooltip"><span class="fa fa-trash text-muted"></span></button>
<?php
                    endif;?>
                    <a href="system_routes_edit.php" class="btn btn-default btn-xs" title="<?=gettext("add route");?>" data-toggle="tooltip">
                      <span class="glyphicon glyphicon-plus"></span>
                    </a>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="7">
                      <?=gettext("Do not enter static routes for networks assigned on any interface of this firewall. Static routes are only used for networks reachable via a different router, and not reachable via your default gateway.");?>
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
<?php include("foot.inc");
