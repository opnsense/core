{#
 # Copyright (c) 2022 Deciso B.V.
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
    'use strict';

    $( document ).ready(function() {
        let grid_phase1 = $("#grid-phase1").UIBootgrid({
            search:'/api/ipsec/sessions/search_phase1',
            options:{
                multiSelect: false,
                rowSelect: true,
                selection: true,
                formatters:{
                    commands: function (column, row) {
                        if (row['connected']) {
                            return  '<button type="button" class="btn btn-xs btn-default command-disconnect" data-toggle="tooltip" title="{{ lang._('Disconnect') }}" data-row-id="' + row.name + '"><span class="fa fa-remove fa-fw"></span></button>';
                        } else {
                            return  '<button type="button" class="btn btn-xs btn-default command-connect" data-toggle="tooltip" title="{{ lang._('Connect') }}" data-row-id="' + row.name + '"><span class="fa fa-play fa-fw"></span></button>';
                        }
                    },
                    status: function (column, row) {
                        if (row['connected']) {
                            return '<span class="fa fa-play fa-fw text-success" data-toggle="tooltip" title="{{ lang._('Connected') }}"></span>';
                        } else {
                            return '<span class="fa fa-remove fa-fw text-danger" data-toggle="tooltip" title="{{ lang._('Disconnected') }}"></span>';
                        }
                    }
                }
            }
        });
        grid_phase1.on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
            let ids = $("#grid-phase1").bootgrid("getCurrentRows");
            if (ids.length > 0) {
                $("#grid-phase1").bootgrid('select', [ids[0].name]);
            }
            $('.command-disconnect').click(function(){
                ajaxCall("/api/ipsec/sessions/disconnect/" + $(this).data('row-id'), {}, function(){
                    $('#grid-phase1').bootgrid('reload');
                });
            });
            $('.command-connect').click(function(){
                ajaxCall("/api/ipsec/sessions/connect/" + $(this).data('row-id'), {}, function(){
                    $('#grid-phase1').bootgrid('reload');
                });
            });
        });
        grid_phase1.on("selected.rs.jquery.bootgrid", function(e, rows) {
            $("#grid-phase2").bootgrid('reload');
        });
        grid_phase1.on("deselected.rs.jquery.bootgrid", function(e, rows) {
            $("#grid-phase2").bootgrid('reload');
        });

        let grid_phase2 = $("#grid-phase2").UIBootgrid({
            search:'/api/ipsec/sessions/search_phase2',
            options:{
                formatters:{
                    addresses: function (column, row) {
                        if (typeof row[column.id] === 'string') {
                            return row[column.id].replaceAll(/,/g, "<br/>");
                        }
                    }
                },
                useRequestHandlerOnGet: true,
                requestHandler: function(request) {
                    let ids = $("#grid-phase1").bootgrid("getSelectedRows");
                    request['id'] = ids.length > 0 ? ids[0] : "__not_found__";
                    return request;
                }
            }
        });

        grid_phase2.on('loaded.rs.jquery.bootgrid', function() {
            if (grid_phase2.bootgrid("getTotalRowCount") > 0) {
                $("#box-phase2").show();
            } else {
                $("#box-phase2").hide();
            }
        });

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

        updateServiceControlUI('ipsec');
    });

</script>


<div class="tab-content content-box __mb">
    <table id="grid-phase1" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
          <tr>
              <th data-column-id="name" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
              <th data-column-id="connected" data-width="6em" data-type="string" data-width="3em"  data-formatter="status">{{ lang._('Status') }}</th>
              <th data-column-id="phase1desc" data-type="string">{{ lang._('Connection') }}</th>
              <th data-column-id="version" data-width="6em"  data-type="string">{{ lang._('Version') }}</th>
              <th data-column-id="local-id" data-type="string">{{ lang._('Local ID') }}</th>
              <th data-column-id="local-addrs" data-type="string">{{ lang._('Local IP') }}</th>
              <th data-column-id="remote-id" data-type="string">{{ lang._('Remote ID') }}</th>
              <th data-column-id="remote-addrs" data-type="string">{{ lang._('Remote IP') }}</th>
              <th data-column-id="install-time" data-type="string">{{ lang._('Time') }}</th>
              <th data-column-id="bytes-in" data-type="numeric" data-formatter="bytes">{{ lang._('Bytes in') }}</th>
              <th data-column-id="bytes-out" data-type="numeric"  data-formatter="bytes">{{ lang._('Bytes out') }}</th>
              <th data-column-id="local-class"  data-visible="false" data-type="string">{{ lang._('Local Auth') }}</th>
              <th data-column-id="remote-class"  data-visible="false" data-type="string">{{ lang._('Remote Auth') }}</th>
              <th data-column-id="commands" data-width="4em" data-formatter="commands" data-sortable="false"></th>
          </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<div class="tab-content content-box __mb" id="box-phase2" style="display:none">
    <table id="grid-phase2" class="table table-condensed table-hover table-striped table-responsive">
      <thead>
        <tr>
            <th data-column-id="name" data-type="string" data-sortable="false" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="local-ts" data-type="string" data-formatter="addresses">{{ lang._('Local subnets	') }}</th>
            <th data-column-id="spi-in" data-type="string">{{ lang._('spi-in') }}</th>
            <th data-column-id="spi-out" data-type="string">{{ lang._('spi-out') }}</th>
            <th data-column-id="remote-ts" data-type="string" data-formatter="addresses">{{ lang._('Remote subnets') }}</th>
            <th data-column-id="state" data-type="string">{{ lang._('State') }}</th>
            <th data-column-id="install-time" data-type="string">{{ lang._('Time') }}</th>
            <th data-column-id="bytes-in" data-type="numeric" data-formatter="bytes">{{ lang._('Bytes in') }}</th>
            <th data-column-id="bytes-out" data-type="numeric"  data-formatter="bytes">{{ lang._('Bytes out') }}</th>
        </tr>
      </thead>
      <tbody>
      </tbody>
    </table>
</div>
