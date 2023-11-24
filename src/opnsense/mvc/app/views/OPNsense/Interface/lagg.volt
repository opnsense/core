<script>
    $( document ).ready(function() {
        $("#grid-laggs").UIBootgrid(
            {   search:'/api/interfaces/lagg_settings/searchItem',
                get:'/api/interfaces/lagg_settings/getItem/',
                set:'/api/interfaces/lagg_settings/setItem/',
                add:'/api/interfaces/lagg_settings/addItem/',
                del:'/api/interfaces/lagg_settings/delItem/',
                options: {
                    formatters: {
                        members: function (column, row) {
                            return row[column.id].replace(',', '<br/>');
                        }
                    }
                }
            }
        );

        $("#lagg\\.proto").change(function(){
            $(".proto").closest("tr").hide();
            $(".proto_"+$(this).val()).each(function(){
                $(this).closest("tr").show();
            });
        });

        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
  <table id="grid-laggs" class="table table-condensed table-hover table-striped" data-editDialog="DialogEdit" data-editAlert="LaggChangeMessage">
      <thead>
          <tr>
              <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="laggif" data-width="5em" data-type="string">{{ lang._('Device') }}</th>
              <th data-column-id="members" data-type="string"  data-width="12em" data-formatter="members">{{ lang._('Members') }}</th>
              <th data-column-id="proto" data-width="10em" data-type="string">{{ lang._('Protocol') }}</th>
              <th data-column-id="descr" data-type="string">{{ lang._('Description') }}</th>
              <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
          </tr>
      </thead>
      <tbody>
      </tbody>
      <tfoot>
          <tr>
              <td></td>
              <td>
                  <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
              </td>
          </tr>
      </tfoot>
  </table>
  <div class="col-md-12">
      <div id="LaggChangeMessage" class="alert alert-info" style="display: none" role="alert">
          {{ lang._('After changing settings, please remember to apply them with the button below') }}
      </div>
      <hr/>
      <button class="btn btn-primary" id="reconfigureAct"
              data-endpoint='/api/interfaces/lagg_settings/reconfigure'
              data-label="{{ lang._('Apply') }}"
              data-error-title="{{ lang._('Error reconfiguring laggs') }}"
              type="button"
      ></button>
      <br/><br/>
  </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':lang._('Edit Lagg')])}}
