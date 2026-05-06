{#
 # Copyright (c) 2022-2026 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    $( document ).ready(function() {
        let grid_connections = $("#grid-connections").UIBootgrid({
          search:'/api/ipsec/connections/search_connection',
          get:'/api/ipsec/connections/get_connection/',
          set:'/api/ipsec/connections/set_connection/',
          add:'/api/ipsec/connections/set_connection/',
          del:'/api/ipsec/connections/del_connection/',
          toggle:'/api/ipsec/connections/toggle_connection/',
        });

        let grid_pools = $("#grid-pools").UIBootgrid({
          search:'/api/ipsec/pools/search',
          get:'/api/ipsec/pools/get/',
          set:'/api/ipsec/pools/set/',
          add:'/api/ipsec/pools/add/',
          del:'/api/ipsec/pools/del/',
          toggle:'/api/ipsec/pools/toggle/',
        });

        let detail_grids = {
            locals: 'local',
            remotes: 'remote',
            children: 'child',
        };
        for (const [grid_key, obj_type] of Object.entries(detail_grids)) {
          $("#grid-" + grid_key).UIBootgrid({
            search:'/api/ipsec/connections/search_' + obj_type,
            get:'/api/ipsec/connections/get_' + obj_type + '/',
            set:'/api/ipsec/connections/set_' + obj_type + '/',
            add:'/api/ipsec/connections/add_' + obj_type + '/',
            del:'/api/ipsec/connections/del_' + obj_type + '/',
            toggle:'/api/ipsec/connections/toggle_' + obj_type + '/',
            options:{
                static: true,
                navigation: obj_type === 'child' ? 3 : 0,
                selection: obj_type === 'child' ? true : false,
                useRequestHandlerOnGet: true,
                requestHandler: function(request) {
                    request['connection'] = $("#connection\\.uuid").val();
                    if (request.rowCount === undefined) {
                        // XXX: We can't easily see if we're being called by GET or POST, buf if no rowCount is being offered
                        // it's highly likely a POST from bootgrid
                        return new URLSearchParams(request).toString();
                    } else {
                        return request
                    }

                }
            }
          });
          if (obj_type !== 'child') {
            $("#"+obj_type+"\\.auth").change(function(){
              $("."+obj_type+"_auth").closest("tr").hide();
              $("."+obj_type+"_auth_"+$(this).val()).each(function(){
                  $(this).closest("tr").show();
              });
            });
          }
        }

        $(".hidden_attr").closest('tr').hide();

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if ($(e.relatedTarget).attr('href') == '.edit_connection') {
                $("#connection_details").hide();
            }
        });

        $("#ConnectionDialog").click(function(){
            const $tab = $(this);

            $("#grid-locals").bootgrid("clear");
            $("#grid-remotes").bootgrid("clear");
            $("#grid-children").bootgrid("clear");

            ajaxGet("/api/ipsec/connections/connection_exists/" + $("#connection\\.uuid").val(), {}, function(data){
                if (data.exists) {
                    $("#connection_details").show();
                    $("#grid-locals").bootgrid("reload");
                    $("#grid-remotes").bootgrid("reload");
                    $("#grid-children").bootgrid("reload");
                } else {
                    $("#connection_details").hide();
                }
            });

            $tab.show();
        });

        $("#ConnectionDialog").change(function() {
            $("#ConnectionDialog").click();
        });
        $("#btn_ConnectionDialog_cancel").click(function () {
            $("#tab_connections").click();
            $("#ConnectionDialog").hide();
            $("#connection_details").hide();
        });

        $("#connection\\.description").change(function(){
            if ($(this).val() !== '') {
                // XXX wrong on clone
                $("#ConnectionDialog").text('Connections: ' + $(this).val());
            } else {
                $("#ConnectionDialog").text('Connections: [new]');
            }
        });

        $("#frm_ConnectionDialog").append($("#frm_DialogConnection").detach());
        updateServiceControlUI('ipsec');

        $("#enable").click(function(){
            if (!$(this).hasClass("pending")) {
                $(this).addClass("pending");
                let enabled = $("#enable").prop('checked') ? '1' : '0';
                ajaxCall('/api/ipsec/connections/toggle/' + enabled,  {}, function (data, status) {
                    $("#enable").removeClass("pending");
                });
                $(document).trigger("settings-changed");
            }
        });
        ajaxGet('/api/ipsec/connections/is_enabled', {}, function (data, status) {
            if (data.enabled === true) {
                $("#enable").prop('checked', data.enabled);
            }
            $("#enable").removeClass("pending");
        });

        $("#reconfigureAct").SimpleActionButton({
            onAction: function() { $("#btn_ConnectionDialog_cancel").click(); }
        });

        $(".cipher_tooltip").change(function(){
            let sender = $(this);
            if (!sender.hasClass('tooltip_started') && sender.hasClass('selectpicker')) {
                /**
                 * hook cipher tooltip on initial load
                 */
                sender.parent().tooltip({
                    title: function() {
                        let container = $("<div/>");
                        sender.find('option').each(function(){
                            let option = $(this);
                            if (option.is(':selected')) {
                                container.append(
                                    $("<small/>").html(option.parent().attr('label') + '&nbsp;/&nbsp;' + option.text()),
                                    '<br/>'
                                );
                            }
                        });
                        sender.parent().find('button').attr('title', '');
                        return container.html();
                    },
                    html: true,
                    placement: 'right',
                    container: 'body',
                    trigger: 'hover'
                });
                sender.addClass('tooltip_started');
            }
        });
    });
</script>

<style>
  tr > td > h3 {
      padding-left: 5px;
      margin: 0px;
  }
  .tooltip-inner {
    max-width: 500px;
    text-align: left;
  }
  @media (min-width: 992px) {
    .left-col {
      padding-right: 0;
      border-right: 1px solid #e5e5e5;
    }
    .right-col {
      padding-left: 0;
    }
}
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="tab_connections" href="#connections">{{ lang._('Connections') }}</a></li>
    <li><a data-toggle="tab" href=".edit_connection" id="ConnectionDialog" style="display: none;"> </a></li>
    <li><a data-toggle="tab" href="#pools" id="tab_pools"> {{ lang._('Pools') }} </a></li>
</ul>
<div class="tab-content content-box">
    <div id="connections" class="tab-pane fade in active">
      <table id="grid-connections" class="table table-condensed table-hover table-striped" data-editDialog="ConnectionDialog">
          <thead>
              <tr>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('UUID') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="local_addrs" data-type="string">{{ lang._('Local') }}</th>
                <th data-column-id="remote_addrs" data-type="string">{{ lang._('Remote') }}</th>
                <th data-column-id="local_ts" data-type="string">{{ lang._('Local Nets') }}</th>
                <th data-column-id="remote_ts" data-type="string">{{ lang._('Remote Nets') }}</th>
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
      <div class="col-md-12 form-inline __mb">
        <hr/>
        <div class="form-group" style="vertical-align: sub">
          <input name="enable" class="pending" type="checkbox" id="enable"/>
          <label for="enable"><strong>{{ lang._('Enable IPsec') }}</strong></label>
        </div>
      </div>
    </div>
    <div class="tab-pane fade in edit_connection">
        <div>
          <form id="frm_ConnectionDialog"></form>
        </div>
    </div>
    <div id="pools" class="tab-pane fade in">
      <table id="grid-pools" class="table table-condensed table-hover table-striped" data-editDialog="DialogPool">
          <thead>
              <tr>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('UUID') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
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
    </div>
</div>
<div id="connection_details" class="tab-content" style="display: none;">
    <div class="content-box tab-pane fade in edit_connection __mt">
          <div class="row">
            <div class="col-xs-12 col-md-6 table-responsive left-col">
              <table class="table table-striped table-condensed" style="table-layout: fixed; width: 100%;">
                <tr><td><h3>{{ lang._('Local Authentication')}}</h3></td>
                <tr><td>
                  <table id="grid-locals" class="table table-condensed table-hover table-striped" data-editDialog="DialogLocal">
                      <thead>
                          <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('UUID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="id" data-type="string">{{ lang._('ID') }}</th>
                            <th data-column-id="round" data-type="string">{{ lang._('Round') }}</th>
                            <th data-column-id="auth" data-type="string">{{ lang._('Authentication') }}</th>
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
                                  <button data-action="add" type="button" class="btn btn-xs btn-primary pull-right"><span class="fa fa-fw fa-plus"></span></button>
                              </td>
                          </tr>
                      </tfoot>
                  </table>
                </td></tr>
              </table>
            </div>
            <div class="col-xs-12 col-md-6 table-responsive right-col">
              <table class="table table-striped table-condensed" style="table-layout: fixed; width: 100%;">
                <tr><td><h3>{{ lang._('Remote Authentication')}}</h3></td>
                <tr><td>
                  <table id="grid-remotes" class="table table-condensed table-hover table-striped" data-editDialog="DialogRemote">
                      <thead>
                          <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="id" data-type="string">{{ lang._('ID') }}</th>
                            <th data-column-id="round" data-type="string">{{ lang._('Round') }}</th>
                            <th data-column-id="auth" data-type="string">{{ lang._('Authentication') }}</th>
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
                                  <button data-action="add" type="button" class="btn btn-xs btn-primary pull-right"><span class="fa fa-fw fa-plus"></span></button>
                              </td>
                          </tr>
                      </tfoot>
                  </table>
                </td></tr>
              </table>
            </div>
          </div>
    </div>
    <div class="content-box tab-pane fade in edit_connection __mt">
          <div class="row">
            <div class="col-xs-12 table-responsive">
              <table class="table table-striped table-condensed" style="table-layout: fixed; width: 100%;">
                <tr><td><h3>{{ lang._('Children')}}</h3></td>
                <tr><td>
                  <table id="grid-children" class="table table-condensed table-hover table-striped" data-editDialog="DialogChild">
                      <thead>
                          <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                            <th data-column-id="local_ts" data-type="string">{{ lang._('Local Nets') }}</th>
                            <th data-column-id="remote_ts" data-type="string">{{ lang._('Remote Nets') }}</th>
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
                </td></tr>
              </table>
            </div>
          </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogConnection,'id':'DialogConnection','label':lang._('Edit Connection')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogLocal,'id':'DialogLocal','label':lang._('Edit Local')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogRemote,'id':'DialogRemote','label':lang._('Edit Remote')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogChild,'id':'DialogChild','label':lang._('Edit Child')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPool,'id':'DialogPool','label':lang._('Edit Pool')])}}
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/ipsec/service/reconfigure', 'data_service_widget': 'ipsec'})}}
