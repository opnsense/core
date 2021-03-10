
      <footer class="page-foot">
        <div class="container-fluid">
          <a target="_blank" href="<?=$g['product_website']?>"><?=$g['product_name']?></a> (c) <?=$g['product_copyright_years']?>
          <a target="_blank" href="<?=$g['product_copyright_url']?>"><?=$g['product_copyright_owner']?></a>
        </div>
      </footer>

  </div>
  <!-- /row-->
</main>

<?php
    if (isset($widgetCollection)):
      // sort by name
      usort($widgetCollection, function ($item1, $item2) {
        return strcmp(strtolower($item1['name']), strtolower($item2['name']));
      });
    ?>
    <div class="modal fade" id="modal_widgets" tabindex="-1" role="dialog" aria-labelledby="modal_widgets_label" aria-hidden="true">
      <div class="modal-backdrop fade in"></div>
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal">
                <span aria-hidden="true">&times;</span>
                <span class="sr-only"><?=gettext("Close");?></span>
              </button>
              <h4 class="modal-title" id="modal_widgets_label"><?=gettext("Available Widgets"); ?></h4>
          </div>
          <div class="modal-body">
              <table class="table table-condensed table-hover">
<?php
          foreach($widgetCollection as $widgetItem):
              $widgettitle = $widgetItem['name'] . "_title";
              $widgettitlelink = $widgetItem['name'] . "_title_link";?>
              <tr id="add_widget_<?=$widgetItem['name']; ?>">
<?php
              // XXX: ${$} is intentional here, the widgets leave global vars [widget_name]_title_link and [widget_name]_title
              if (isset(${$widgettitle})):?>
                  <td style="cursor: pointer;" onclick='return addWidget("<?= html_safe($widgetItem['name']) ?>")'><?= ${$widgettitle} ?></td>
<?php
              elseif (!empty($widgetItem['display_name'])): ?>
                  <td style="cursor: pointer;" onclick='return addWidget("<?= html_safe($widgetItem['name']) ?>")'><?= $widgetItem['display_name'] ?></td>
<?php
              endif;?>
              </tr>
<?php
          endforeach; ?>
              </table>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal"><?=gettext("Close");?></button>
          </div>
        </div><!-- /modal-content -->
      </div><!-- /modal-dialog -->
    </div><!-- /modal -->
<?php
    endif; ?>
    <!-- dialog "wait for (service) action" -->
    <div class="modal fade" id="OPNsenseStdWaitDialog" tabindex="-1" data-backdrop="static" data-keyboard="false">
      <div class="modal-backdrop fade in"></div>
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-body">
            <p><strong><?=gettext("Please wait...");?></strong></p>
            <div class="progress">
               <div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width:100%"></div>
             </div>
          </div>
        </div>
      </div>
    </div>
    <!-- bootstrap script -->
    <script src="/<?= cache_safe('ui/js/bootstrap.min.js') ?>"></script>
    <!-- Fancy select with search options -->
    <script src="<?= cache_safe('/ui/js/bootstrap-select.min.js') ?>"></script>
    <!-- bootstrap dialog -->
    <script src="<?= cache_safe('/ui/js/bootstrap-dialog.min.js') ?>"></script>
    <!-- service control hook -->
    <script>
      $( document ).ready(function() {
        $('.srv_status_act').click(function(event){
          event.preventDefault();
          let params = {};
          params['action'] = $(this).data('service_action');
          params['service'] = $(this).data('service');
          params['id'] = $(this).data('service_id');
          $("#OPNsenseStdWaitDialog").modal('show');
          $.post('/status_services.php',params, function(data) {
            // refresh page after service action via server
            location.reload(true);
          });
        });
      });
    </script>
  </body>
</html>
