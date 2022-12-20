<script>
    $( document ).ready(function() {
        let grid_vti = $("#grid-vti").UIBootgrid({
          search:'/api/ipsec/vti/search',
          get:'/api/ipsec/vti/get/',
          set:'/api/ipsec/vti/set/',
          add:'/api/ipsec/vti/add/',
          del:'/api/ipsec/vti/del/',
          toggle:'/api/ipsec/vti/toggle/',
          options:{
              formatters: {
                  commands: function (column, row) {
                      if (row.uuid.includes('-') === true) {
                          // exclude buttons for internal aliases (which uses names instead of valid uuid's)
                          return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                              '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                              '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                      }
                  },
                  tunnel: function (column, row) {
                      return row.tunnel_local + ' <-> ' + row.tunnel_remote;
                  }
              }
          }
        });

        updateServiceControlUI('ipsec');

        /**
         * reconfigure
         */
        $("#reconfigureAct").SimpleActionButton();

    });

</script>

<style>
  div.section_header > hr {
      margin: 0px;
  }
  div.section_header > h2 {
      padding-left: 5px;
      margin: 0px;
  }
</style>

<div class="content-box">
    <table id="grid-vti" class="table table-condensed table-hover table-striped" data-editDialog="DialogVTI" data-editAlert="VTIChangeMessage">
        <thead>
            <tr>
              <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="origin" data-type="string"  data-visible="false">{{ lang._('Origin') }}</th>
              <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
              <th data-column-id="reqid" data-type="string">{{ lang._('Reqid') }}</th>
              <th data-column-id="local" data-type="string">{{ lang._('Local') }}</th>
              <th data-column-id="remote" data-type="string">{{ lang._('Remote') }}</th>
              <th data-column-id="tunnel_local"  data-sortable="false" data-formatter="tunnel">{{ lang._('Tunnel') }}</th>
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
        <div id="VTIChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <hr/>
    </div>
    <div class="col-md-12">
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/ipsec/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring IPsec') }}"
                type="button"
        ></button>
        <br/><br/>
    </div>
</div>



{{ partial("layout_partials/base_dialog",['fields':formDialogVTI,'id':'DialogVTI','label':lang._('Edit VirtualTunnelInterface')])}}
