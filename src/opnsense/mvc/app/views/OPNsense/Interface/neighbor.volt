<script>
    $( document ).ready(function() {
        $("#grid-neighbor").UIBootgrid(
            {   search:'/api/interfaces/neighbor_settings/searchItem',
                get:'/api/interfaces/neighbor_settings/getItem/',
                set:'/api/interfaces/neighbor_settings/setItem/',
                add:'/api/interfaces/neighbor_settings/addItem/',
                del:'/api/interfaces/neighbor_settings/delItem/',
                options:{
                    formatters: {
                        commands: function (column, row) {
                            if (row.origin == 'manual') {
                                // exclude buttons for internal (dynamic) neighbors
                                return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                                    '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                                    '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                            }
                        }
                    }
                }
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
  <table id="grid-neighbor" class="table table-condensed table-hover table-striped" data-editDialog="DialogEdit" data-editAlert="NeighborChangeMessage">
      <thead>
          <tr>
              <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="origin"  data-type="string">{{ lang._('Origin') }}</th>
              <th data-column-id="etheraddr"  data-type="string">{{ lang._('Mac') }}</th>
              <th data-column-id="ipaddress" data-type="string" >{{ lang._('IP address') }}</th>
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
      <div id="NeighborChangeMessage" class="alert alert-info" style="display: none" role="alert">
          {{ lang._('After changing settings, please remember to apply them with the button below') }}
      </div>
      <hr/>
      <button class="btn btn-primary" id="reconfigureAct"
              data-endpoint='/api/interfaces/neighbor_settings/reconfigure'
              data-label="{{ lang._('Apply') }}"
              data-error-title="{{ lang._('Error reconfiguring neighbors') }}"
              type="button"
      ></button>
      <br/><br/>
  </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':lang._('Edit Neighbor')])}}
