<script>
    $( document ).ready(function() {
        $("#grid-vlans").UIBootgrid(
            {   search:'/api/interfaces/vlan_settings/searchItem/',
                get:'/api/interfaces/vlan_settings/getItem/',
                set:'/api/interfaces/vlan_settings/setItem/',
                add:'/api/interfaces/vlan_settings/addItem/',
                del:'/api/interfaces/vlan_settings/delItem/'
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
  <table id="grid-vlans" class="table table-condensed table-hover table-striped" data-editDialog="DialogVlan" data-editAlert="VlanChangeMessage">
      <thead>
          <tr>
              <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="vlanif" data-type="string">{{ lang._('Device') }}</th>
              <th data-column-id="if" data-type="string">{{ lang._('Parent') }}</th>
              <th data-column-id="tag" data-type="string">{{ lang._('Tag') }}</th>
              <th data-column-id="pcp" data-type="string">{{ lang._('PCP') }}</th>
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
      <div id="VlanChangeMessage" class="alert alert-info" style="display: none" role="alert">
          {{ lang._('After changing settings, please remember to apply them with the button below') }}
      </div>
      <hr/>
      <button class="btn btn-primary" id="reconfigureAct"
              data-endpoint='/api/interfaces/vlan_settings/reconfigure'
              data-label="{{ lang._('Apply') }}"
              data-error-title="{{ lang._('Error reconfiguring vlan') }}"
              type="button"
      ></button>
      <br/><br/>
  </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogVlan,'id':'DialogVlan','label':lang._('Edit Vlan')])}}
