
<script>
  $(function () {
      function attach_legacy_actions() {
          $(".legacy_action").unbind('click').click(function(e){
              e.stopPropagation();
              if ($(this).data('scope') === 'phase1') {
                  if ($(this).hasClass('command-add')) {
                      window.location = '/vpn_ipsec_phase1.php';
                  } else if ($(this).hasClass('command-edit')) {
                      window.location = '/vpn_ipsec_phase1.php?p1index=' + $(this).data('row-id');
                  } else if ($(this).hasClass('command-copy')) {
                      window.location = '/vpn_ipsec_phase1.php?dup=' + $(this).data('row-id');
                  }
              } else {
                  if ($(this).hasClass('command-add')) {
                      window.location = '/vpn_ipsec_phase2.php?ikeid=' + $(this).data('row-ikeid');
                  } else if ($(this).hasClass('command-edit')) {
                      window.location = '/vpn_ipsec_phase2.php?p2index=' + $(this).data('row-uniqid');
                  } else if ($(this).hasClass('command-copy')) {
                      window.location = '/vpn_ipsec_phase2.php?dup=' + $(this).data('row-uniqid');
                  }
              }
          });
      }

      const $applyLegacyConfig = $('#applyLegacyConfig');
      const $applyLegacyConfigProgress = $('#applyLegacyConfigProgress');
      const $responseMsg = $('#responseMsg');
      const $dirtySubsystemMsg = $('#dirtySubsystemMsg');

      // Helper method to fetch the current status of the legacy subsystem for viewing/hiding the "pending changes" alert
      function updateLegacyStatus() {
          ajaxCall('/api/ipsec/legacy-subsystem/status', {}, function (data, status) {
              $("#enable").prop('checked', data['enabled']);
              $("#enable").prop('disabled', false);
              $("#enable").removeClass("pending");
              if (data['isDirty']) {
                $responseMsg.addClass('hidden');
                $dirtySubsystemMsg.removeClass('hidden');
              } else {
                $dirtySubsystemMsg.addClass('hidden');
              }
          });
      }

      // Apply config in legacy subsystem
      $applyLegacyConfig.on('click', function (e) {
          e.preventDefault();

          $applyLegacyConfig.prop('disabled', true);
          $applyLegacyConfigProgress.addClass('fa fa-spinner fa-pulse');

          ajaxCall('/api/ipsec/legacy-subsystem/applyConfig', {}, function (data, status) {
              // Preliminarily hide the "pending changes" alert and display the response message if available
              if (data['message']) {
                $dirtySubsystemMsg.addClass('hidden');
                $responseMsg.removeClass('hidden').text(data['message']);
              }

              // Reset the state of the "apply changes" button
              $applyLegacyConfig.prop('disabled', false);
              $applyLegacyConfigProgress.removeClass('fa fa-spinner fa-pulse');

              // Fetch the current legacy subsystem status to ensure changes have been processed
              updateLegacyStatus();
              updateServiceControlUI('ipsec');
          });
      });

      const formatters = {
          "commands": function (column, row) {
            let btns = '';
            let data_tags = "";
            if (row.remote_gateway !== undefined) {
                // phase 1
                data_tags = 'data-row-id="' + row.seqid + '" data-scope="phase1" data-row-ikeid="'+row.id+'" ';
                btns = '<button type="button" data-scope="phase2" class="btn btn-xs btn-primary legacy_action command-add bootgrid-tooltip" title="{{ lang._('add phase 2 entry') }}" ' + data_tags + '><span class="fa fa-fw fa-plus"></span></button> '
            } else {
                data_tags = 'data-row-id="' + row.id + '" data-scope="phase2" data-row-uniqid="' + row.uniqid + '"';
            }
            btns = btns + '<button type="button" class="btn btn-xs legacy_action btn-default command-edit bootgrid-tooltip" ' + data_tags + '><span class="fa fa-fw fa-pencil"></span></button> ' +
                '<button type="button" class="btn btn-xs btn-default legacy_action command-copy bootgrid-tooltip" ' + data_tags + '><span class="fa fa-fw fa-clone"></span></button>';

            // delete buttons use standard mvc functionality, id should map to the unique id used by the delete endpoint
            btns = btns +'<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.id + '" ><span class="fa fa-fw fa-trash-o"></span></button>';
            return btns;
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
                  return '<span style="cursor: pointer;" class="fa fa-fw fa-check-square-o command-toggle bootgrid-tooltip" data-value="1" data-row-id="' + row.id + '"></span>';
              } else {
                  return '<span style="cursor: pointer;" class="fa fa-fw fa-square-o command-toggle bootgrid-tooltip" data-value="0" data-row-id="' + row.id + '"></span>';
              }
          }
      };
      const $grid_phase1 = $('#grid-phase1').UIBootgrid({
          search: '/api/ipsec/tunnel/search_phase1',
          del: '/api/ipsec/tunnel/del_phase1/',
          toggle: '/api/ipsec/tunnel/toggle_phase1/',
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
          attach_legacy_actions();
          updateLegacyStatus();
      });
      const $grid_phase2 = $('#grid-phase2').UIBootgrid({
          search: '/api/ipsec/tunnel/search_phase2',
          del: '/api/ipsec/tunnel/del_phase2/',
          toggle: '/api/ipsec/tunnel/toggle_phase2/',
          options: {
              formatters: formatters,
              useRequestHandlerOnGet: true,
              requestHandler: function(request) {
                  let ids = $("#grid-phase1").bootgrid("getSelectedRows");
                  request['ikeid'] = ids.length > 0 ? ids[0] : "__not_found__";
                  return request;
              }
          }
      }).on("loaded.rs.jquery.bootgrid", function (e) {
          attach_legacy_actions();
          updateLegacyStatus();
      });
      $("#enable").click(function(){
          if (!$(this).hasClass("pending")) {
              $(this).addClass("pending");
              $(this).prop('disabled', true);
              ajaxCall('/api/ipsec/tunnel/toggle',  {}, function (data, status) {
                  updateLegacyStatus();
              });
          }
      });
      updateServiceControlUI('ipsec');
      // reformat bootgrid headers to show type of content (phase 1 or 2)
      $("div.actionBar").each(function(){
          let heading_text = "";
          if ($(this).closest(".bootgrid-header").attr("id").includes("phase1")) {
              heading_text = "{{ lang._('Phase 1') }}";
          } else {
              heading_text = "{{ lang._('Phase 2') }}";
          }
          $(this).parent().prepend($('<td class="col-sm-2 theading-text">'+heading_text+'</div>'));
          $(this).removeClass("col-sm-12");
          $(this).addClass("col-sm-10");
      });
  });
</script>

<style>
  .theading-text {
      font-weight: 800;
      font-style: italic;
  }
</style>


<div class="alert alert-info alert-dismissible hidden" role="alert" id="responseMsg"></div>
<div class="alert alert-info hidden" role="alert" id="dirtySubsystemMsg">
    <button class="btn btn-primary pull-right" type="button" id="applyLegacyConfig">
        <i id="applyLegacyConfigProgress" class=""></i>
        {{ lang._('Apply changes') }}
    </button>
    <div>
        {{ lang._('The IPsec tunnel configuration has been changed.') }}<br/>
        {{ lang._('You must apply the changes in order for them to take effect.') }}
    </div>
</div>
<div class="tab-content content-box col-xs-12 __mb">
    <table id="grid-phase1" class="table table-condensed table-hover table-striped">
        <thead>
          <tr>
              <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
              <th data-column-id="id" data-type="numeric" data-identifier="true" data-visible="false">{{ lang._('ikeid') }}</th>
              <th data-column-id="seqid" data-type="numeric" data-visible="false">{{ lang._('seqid') }}</th>
              <th data-column-id="type" data-type="string" data-width="7em">{{ lang._('Type') }}</th>
              <th data-column-id="remote_gateway" data-formatter="gateway" data-width="20em" data-type="string">{{ lang._('Remote Gateway') }}</th>
              <th data-column-id="mode" data-width="10em" data-type="string">{{ lang._('Mode') }}</th>
              <th data-column-id="proposal" data-width="20em" data-type="string">{{ lang._('Phase 1 Proposal') }}</th>
              <th data-column-id="authentication" data-type="string">{{ lang._('Authentication') }}</th>
              <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
              <th data-column-id="commands" data-width="12em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        <tr>
            <td colspan=7></td>
            <td>
                <button data-action="add" type="button" title="{{ lang._('add phase 1 entry') }}" data-scope="phase1" class="btn btn-xs btn-primary legacy_action command-add">
                    <span class="fa fa-fw fa-plus"></span>
                </button>
                {# multi select isn't supported on master/detail views
                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                    <span class="fa fa-fw fa-trash-o"></span>
                </button> #}
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
              <th data-column-id="uniqid" data-type="string" data-visible="false">{{ lang._('uniqid') }}</th>
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
      </table>
</div>
<div class="tab-content content-box col-xs-12 __mb">
    <table class="table table-condensed">
      <tbody>
        <tr>
          <td>
            <input name="enable" class="pending" type="checkbox" id="enable"/>
            <strong>{{ lang._('Enable IPsec') }}</strong>
          </td>
        </tr>
      </tbody>
    </table>
</div>
