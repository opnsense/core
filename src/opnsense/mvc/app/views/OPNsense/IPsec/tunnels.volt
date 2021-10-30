
<script>
  $(function () {
      const formatters = {
          "commands": function (column, row) {
            return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.id + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.id + '"><span class="fa fa-fw fa-clone"></span></button>' +
                '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.id + '"><span class="fa fa-fw fa-trash-o"></span></button>';
          },
          "gateway": function (column, row) {
              if (row.mobile) {
                  return '<strong>{{ lang._('Mobile Client') }}</strong>';
              } else {
                  return row.remote_gateway ;
              }
          },
          "mode_type": function (column, row) {
              return row.protocol + " " + row.mode;
          },
          "rowtoggle": function (column, row) {
              if (parseInt(row[column.id], 2) === 1) {
                  return '<span style="cursor: pointer;" class="fa fa-fw fa-check-square-o command-toggle bootgrid-tooltip" data-value="1" data-row-id="' + row.uuid + '"></span>';
              } else {
                  return '<span style="cursor: pointer;" class="fa fa-fw fa-square-o command-toggle bootgrid-tooltip" data-value="0" data-row-id="' + row.uuid + '"></span>';
              }
          }
      };
      const $grid_phase1 = $('#grid-phase1').UIBootgrid({
          search: '/api/ipsec/tunnel/search_phase1',
          del: '/api/ipsec/tunnel/del_phase1/',
          options: {
              formatters: formatters,
              multiSelect: false,
              rowSelect: true,
              selection: true
          }
      }).on("selected.rs.jquery.bootgrid", function(e, rows) {
        $("#grid-phase2").bootgrid('reload');
      }).on("deselected.rs.jquery.bootgrid", function(e, rows) {
          $("#grid-phase2").bootgrid('reload');
      }).on("loaded.rs.jquery.bootgrid", function (e) {
          let ids = $("#grid-phase1").bootgrid("getCurrentRows");
          if (ids.length > 0) {
              $("#grid-phase1").bootgrid('select', [ids[0].id]);
          }
      });
      const $grid_phase2 = $('#grid-phase2').UIBootgrid({
          search: '/api/ipsec/tunnel/search_phase2',
          del: '/api/ipsec/tunnel/del_phase2/',
          options: {
              formatters: formatters,
              useRequestHandlerOnGet: true,
              requestHandler: function(request) {
                  let ids = [];
                  let rows = $("#grid-phase1").bootgrid("getSelectedRows");
                  let current_rows = $("#grid-phase1").bootgrid("getCurrentRows");
                  $.each(rows, function(key, seq){
                      if (current_rows[seq] !== undefined) {
                          ids.push(current_rows[seq].id);
                      }
                  });
                  if (ids.length > 0) {
                      request['ikeid'] = ids[0];
                  } else {
                      request['ikeid'] = "__not_found__";
                  }
                  return request;
              }
          }
      });
  });
</script>


<div class="tab-content content-box col-xs-12 __mb">
    <table id="grid-phase1" class="table table-condensed table-hover table-striped">
        <thead>
          <tr>
              <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
              <th data-column-id="id" data-type="numeric" data-identifier="true" data-visible="false">{{ lang._('ikeid') }}</th>
              <th data-column-id="type" data-type="string" data-width="7em">{{ lang._('Type') }}</th>
              <th data-column-id="remote_gateway" data-formatter="gateway" data-width="20em" data-type="string">{{ lang._('Remote Gateway') }}</th>
              <th data-column-id="mode" data-width="10em" data-type="string">{{ lang._('Mode') }}</th>
              <th data-column-id="proposal" data-width="20em" data-type="string">{{ lang._('Phase 1 Proposal') }}</th>
              <th data-column-id="authentication" data-type="string">{{ lang._('Authentication') }}</th>
              <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
              <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        <tr>
            <td colspan=7></td>
            <td>
                <button data-action="add" type="button" class="btn btn-xs btn-primary">
                    <span class="fa fa-fw fa-plus"></span>
                </button>
                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                    <span class="fa fa-fw fa-trash-o"></span>
                </button>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
<div class="tab-content content-box col-xs-12 __mb">
    <table id="grid-phase2" class="table table-condensed table-hover table-striped">
        <thead>
          <tr>
              <th data-column-id="id" data-type="numeric" data-identifier="true" data-visible="false">ID</th>
              <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
              <th data-column-id="type" data-width="8em" data-type="string" data-formatter="mode_type">{{ lang._('Type') }}</th>
              <th data-column-id="local_subnet" data-width="18em" data-type="string">{{ lang._('Local Subnet') }}</th>
              <th data-column-id="remote_subnet" data-width="18em" data-type="string">{{ lang._('Remote Subnet') }}</th>
              <th data-column-id="proposal" data-width="20em" data-type="string">{{ lang._('Phase 2 Proposal') }}</th>
              <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
              <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
          <tr>
              <td colspan=6></td>
              <td>
                  <button data-action="add" type="button" class="btn btn-xs btn-primary">
                      <span class="fa fa-fw fa-plus"></span>
                  </button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                      <span class="fa fa-fw fa-trash-o"></span>
                  </button>
              </td>
          </tr>
        </tfoot>
      </table>
</div>
<div class="tab-content content-box col-xs-12 __mb">
    <table class="table table-condensed">
      <tbody>
        <tr>
          <td>
            <input name="enable" type="checkbox" id="enable" value="yes" checked="checked"/>
            <strong>{{ lang._('Enable IPsec') }}</strong>
          </td>
        </tr>
        <tr>
          <td>
            <input type="submit" name="save" class="btn btn-primary" value="{{ lang._('Save') }}" />
          </td>
        </tr>
      </tbody>
    </table>

</div>
