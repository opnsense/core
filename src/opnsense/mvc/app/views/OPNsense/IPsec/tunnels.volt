
<script>
  $(function () {
      const $grid = $('#grid-phase1').UIBootgrid({
          search: '/api/ipsec/tunnel/search_phase1',
          options: {
              formatters: {
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
                  }
              }
          }
      });
  });
</script>


<div class="tab-content content-box col-xs-12 __mb">
    <table id="grid-phase1" class="table table-condensed table-hover table-striped">
        <thead>
          <tr>
              <th data-column-id="id" data-type="numeric" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
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
              <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
              <th data-column-id="local_subnet" data-width="20em" data-type="string">{{ lang._('Local Subnet') }}</th>
              <th data-column-id="remote_subnet" data-width="20em" data-type="string">{{ lang._('Remote Subnet') }}</th>
              <th data-column-id="proposal" data-type="string">{{ lang._('Phase 2 Proposal') }}</th>
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
