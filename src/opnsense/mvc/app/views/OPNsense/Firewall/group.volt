<script>
    $( document ).ready(function() {
        $("#grid-groups").UIBootgrid(
            {   search:'/api/firewall/group/searchItem',
                get:'/api/firewall/group/getItem/',
                set:'/api/firewall/group/setItem/',
                add:'/api/firewall/group/addItem/',
                del:'/api/firewall/group/delItem/',
                options:{
                        formatters:{
                            commands: function (column, row) {
                                if (row.uuid.includes('-') === true) {
                                    // exclude buttons for internal groups
                                    return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                                        '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                                        '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                                }
                            },
                            ifname: function (column, row) {
                                return '<a href="/firewall_rules.php?if='+row.ifname+'">'+row.ifname+'</a>';
                            },
                        }
                    }
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
  <table id="grid-groups" class="table table-condensed table-hover table-striped" data-editDialog="DialogEdit" data-editAlert="GroupChangeMessage">
      <thead>
          <tr>
              <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="ifname" data-type="string" data-formatter="ifname">{{ lang._('Name') }}</th>
              <th data-column-id="members" data-type="string">{{ lang._('Members') }}</th>
              <th data-column-id="sequence" data-width="7em" data-type="numeric">{{ lang._('Sequence') }}</th>
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
      <div id="GroupChangeMessage" class="alert alert-info" style="display: none" role="alert">
          {{ lang._('After changing settings, please remember to apply them with the button below') }}
      </div>
      <hr/>
      <button class="btn btn-primary" id="reconfigureAct"
              data-endpoint='/api/firewall/group/reconfigure'
              data-label="{{ lang._('Apply') }}"
              data-error-title="{{ lang._('Error reconfiguring groups') }}"
              type="button"
      ></button>
      <br/><br/>
  </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':lang._('Edit Group')])}}
