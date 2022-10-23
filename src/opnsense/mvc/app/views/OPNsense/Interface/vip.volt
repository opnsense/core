<script>
    $( document ).ready(function() {
        $("#grid-vips").UIBootgrid(
            {   search:'/api/interfaces/vip_settings/searchItem/',
                get:'/api/interfaces/vip_settings/getItem/',
                set:'/api/interfaces/vip_settings/setItem/',
                add:'/api/interfaces/vip_settings/addItem/',
                del:'/api/interfaces/vip_settings/delItem/'
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
  <table id="grid-vips" class="table table-condensed table-hover table-striped" data-editDialog="DialogVip" data-editAlert="VipChangeMessage">
      <thead>
          <tr>
              <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
              <th data-column-id="mode" data-type="string">{{ lang._('Type') }}</th>
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
      <div id="VipChangeMessage" class="alert alert-info" style="display: none" role="alert">
          {{ lang._('After changing settings, please remember to apply them with the button below') }}
      </div>
      <hr/>
      <button class="btn btn-primary" id="reconfigureAct"
              data-endpoint='/api/interfaces/vip_settings/reconfigure'
              data-label="{{ lang._('Apply') }}"
              data-error-title="{{ lang._('Error reconfiguring virtual IPs') }}"
              type="button"
      ></button>
      <br/><br/>
  </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogVip,'id':'DialogVip','label':lang._('Edit Virtual IP')])}}
