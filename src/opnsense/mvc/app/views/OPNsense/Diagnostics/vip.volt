<script>
    $( document ).ready(function() {
        $("#grid-vips").UIBootgrid({
          search:'/api/diagnostics/interface/get_vip_status/',
          options:{
              requestHandler: function(request){
                  if ( $('#mode_filter').val().length > 0) {
                      request['mode'] = $('#mode_filter').val();
                  }
                  return request;
              },
              formatters: {
                  vhid: function (column, row) {
                      return row.vhid_txt;
                  },
                  status: function (column, row) {
                      return row.status_txt;
                  },
              }
          }
        });
        $("#mode_filter").change(function(){
            $('#grid-vips').bootgrid('reload');
        });

        $("#mode_filter_container").detach().prependTo('#grid-vips-header > .row > .actionBar > .actions');
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#addresses">{{ lang._('Addresses') }}</a></li>
    <li><a data-toggle="tab" href="#pfsync">{{ lang._('pfSync nodes') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="addresses" class="tab-pane fade in active">
      <div class="hidden">
          <!-- filter per type container -->
          <div id="mode_filter_container" class="btn-group">
              <select id="mode_filter"  data-title="{{ lang._('Filter type') }}" class="selectpicker" multiple="multiple" data-width="200px">
                  <option value="ipalias">{{ lang._('IP Alias') }}</option>
                  <option value="carp" selected="selected">{{ lang._('CARP') }}</option>
              </select>
          </div>
      </div>
      <table id="grid-vips" class="table table-condensed table-hover table-striped">
          <thead>
              <tr>
                  <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
                  <th data-column-id="vhid" data-type="string" data-formatter="vhid" >{{ lang._('VHID') }}</th>
                  <th data-column-id="subnet" data-type="string" data-identifier="true">{{ lang._('Address') }}</th>
                  <th data-column-id="status" data-type="string" data-formatter="status">{{ lang._('Status') }}</th>
                  <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
              </tr>
          </thead>
          <tbody>
          </tbody>
      </table>
    </div>
    <div id="pfsync" class="tab-pane fade in">

    </div>
</div>
