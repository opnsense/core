<script>
    $( document ).ready(function() {
        let grid_connections = $("#grid-connections").UIBootgrid({
          search:'/api/ipsec/connections/search_connection',
          get:'/api/ipsec/connections/get_connection/',
          set:'/api/ipsec/connections/set_connection/',
          add:'/api/ipsec/connections/add_connection/',
          del:'/api/ipsec/connections/del_connection/',
        });

        $("#ConnectionDialog").click(function(){
            $(this).show();
        });

        $("#ConnectionDialog").change(function(){
            $("#tab_connections").click();
            $("#ConnectionDialog").hide();
        });

        $("#connection\\.description").change(function(){
            if ($(this).val() !== '') {
                $("#ConnectionDialog").text($(this).val());
            } else {
                $("#ConnectionDialog").text('-');
            }
        });

        $("#frm_ConnectionDialog").append($("#frm_DialogConnection").detach());
    });

</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="tab_connections" href="#connections">{{ lang._('Connections') }}</a></li>
    <li><a data-toggle="tab" href="#edit_connection" id="ConnectionDialog" style="display: none;"> </a></li>
</ul>
<div class="tab-content content-box">
    <div id="connections" class="tab-pane fade in active">
      <table id="grid-connections" class="table table-condensed table-hover table-striped" data-editDialog="ConnectionDialog" data-editAlert="ConnectionChangeMessage">
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
    <div id="edit_connection" class="tab-pane fade in">
        <div>
          <form id="frm_ConnectionDialog">
          </form>
        </div>
        <div id="ConnectionDialogBtns">
            <button type="button" class="btn btn-primary" id="btn_ConnectionDialog_save">
              {{ lang._('Save')}}
              <i id="btn_ConnectionDialog_save_progress" class=""></i>
            </button>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogConnection,'id':'DialogConnection','label':lang._('Edit Connection')])}}
