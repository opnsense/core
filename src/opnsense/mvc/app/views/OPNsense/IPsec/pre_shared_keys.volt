<script>
    $( document ).ready(function() {
        $("#grid-pre-shared-keys").UIBootgrid(
            {   search:'/api/ipsec/pre_shared_keys/searchItem/',
                get:'/api/ipsec/pre_shared_keys/getItem/',
                set:'/api/ipsec/pre_shared_keys/setItem/',
                add:'/api/ipsec/pre_shared_keys/addItem/',
                del:'/api/ipsec/pre_shared_keys/delItem/'
            }
        );
        updateServiceControlUI('ipsec');
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
  <table id="grid-pre-shared-keys" class="table table-condensed table-hover table-striped" data-editDialog="DialogPSK" data-editAlert="PSKChangeMessage">
      <thead>
          <tr>
              <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="ident" data-type="string">{{ lang._('Local Identifier') }}</th>
              <th data-column-id="remote_ident" data-type="string">{{ lang._('Remote Identifier') }}</th>
              <th data-column-id="keyType" data-width="20em" data-type="string">{{ lang._('Key Type') }}</th>
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
      <div id="PSKChangeMessage" class="alert alert-info" style="display: none" role="alert">
          {{ lang._('After changing settings, please remember to apply them with the button below') }}
      </div>
      <hr/>
      <button class="btn btn-primary" id="reconfigureAct"
              data-endpoint="/api/ipsec/service/reconfigure"
              data-label="{{ lang._('Apply') }}"
              data-error-title="{{ lang._('Error reconfiguring IPsec') }}"
              type="button"
      ></button>
      <br/><br/>
  </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogPSK,'id':'DialogPSK','label':lang._('Edit pre-shared-key')])}}
