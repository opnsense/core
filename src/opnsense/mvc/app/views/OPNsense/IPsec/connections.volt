<script>
    $( document ).ready(function() {
        $("#grid-connections").UIBootgrid({
          search:'/api/ipsec/connections/search_connection',
          get:'/api/ipsec/connections/get_connection/',
          set:'/api/ipsec/connections/set_connection/',
          add:'/api/ipsec/connections/add_connection/',
          del:'/api/ipsec/connections/del_connection/',
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#connections">{{ lang._('Connections') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="connections" class="tab-pane fade in active">
      <table id="grid-connections" class="table table-condensed table-hover table-striped" data-editDialog="DialogConnection" data-editAlert="ConnectionChangeMessage">
          <thead>
              <tr>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
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
          <div id="ConnectionChangeMessage" class="alert alert-info" style="display: none" role="alert">
              {{ lang._('After changing settings, please remember to apply them with the button below') }}
          </div>
          <hr/>
      </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogConnection,'id':'DialogConnection','label':lang._('Edit Connection')])}}
