<script>
    $( document ).ready(function() {
        $("#grid").UIBootgrid(
            {   search:'/api/interfaces/gre_settings/searchItem/',
                get:'/api/interfaces/gre_settings/getItem/',
                set:'/api/interfaces/gre_settings/setItem/',
                add:'/api/interfaces/gre_settings/addItem/',
                del:'/api/interfaces/gre_settings/delItem/'
            }
        );
        $("#reconfigureAct").SimpleActionButton();
        ajaxGet('/api/interfaces/gre_settings/get_if_options', [], function(data, status){
            if (data.single) {
                $(".net_selector").replaceInputWithSelector(data);
            }
        });

    });
</script>
<div class="tab-content content-box">
  <table id="grid" class="table table-condensed table-hover table-striped" data-editDialog="DialogGre" data-editAlert="GreChangeMessage">
      <thead>
          <tr>
              <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="greif" data-type="string">{{ lang._('Device') }}</th>
              <th data-column-id="local-addr" data-type="string">{{ lang._('Local address') }}</th>
              <th data-column-id="remote-addr" data-type="string">{{ lang._('Remote address') }}</th>
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
      <div id="GreChangeMessage" class="alert alert-info" style="display: none" role="alert">
          {{ lang._('After changing settings, please remember to apply them with the button below') }}
      </div>
      <hr/>
      <button class="btn btn-primary" id="reconfigureAct"
              data-endpoint='/api/interfaces/gre_settings/reconfigure'
              data-label="{{ lang._('Apply') }}"
              data-error-title="{{ lang._('Error reconfiguring GRE') }}"
              type="button"
      ></button>
      <br/><br/>
  </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogGre,'id':'DialogGre','label':lang._('Edit GRE')])}}
