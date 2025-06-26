<script>
    $( document ).ready(function() {
        let grid_vti = $("#{{formGridVTI['table_id']}}").UIBootgrid({
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
    {{ partial('layout_partials/base_bootgrid_table', formGridVTI)}}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/ipsec/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogVTI,'id':formGridVTI['edit_dialog_id'],'label':lang._('Edit VirtualTunnelInterface')])}}
