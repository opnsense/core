{#
 # Copyright (c) 2014-2016 Deciso B.V.
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
        let grid = $("#grid-routes").bootgrid({
            ajax: false,
            selection: false,
            multiSelect: false,
            formatters: {
                "commands": function (column, row) {
                    return '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" title="{{ lang._('Delete') }}" \
                                    data-row-id="' + row.destination + ',' + row.gateway +'"><span class="fa fa-trash-o fa-fw"></span></button>';
                }
            }
        }).on("loaded.rs.jquery.bootgrid", function(){
          grid.find(".command-delete").on("click", function(e){
              let route=$(this).data("row-id").split(',');
              stdDialogConfirm('{{ lang._('Remove static route') }}' + ' ('+$(this).data("row-id")+')',
                               '{{ lang._('Are you sure you want to remove this route? Caution, this could potentially lead to loss of connectivity') }}',
                               '{{ lang._('Yes') }}',
                               '{{ lang._('No') }}',
                               function() {
                  ajaxCall('/api/diagnostics/interface/delRoute/', {'destination': route[0], 'gateway': route[1]},function(data,status){
                      // reload grid after delete
                      $("#update").click();
                  });
              });
          });
        });
        // update routes
        $("#update").click(function() {
            $("#grid-routes").bootgrid('clear');
            $('#processing-dialog').modal('show');
            let resolve = '';
            if ($("#resolve").prop("checked")) {
                resolve = "yes";
            }
            ajaxGet("/api/diagnostics/interface/getRoutes/", {resolve:resolve}, function (data, status) {
                if (status == "success") {
                    $("#grid-routes").bootgrid('append', data).on("loaded.rs.jquery.bootgrid", function () {
                        $('.bootgrid-tooltip').tooltip();
                    });
                }
                $('#processing-dialog').modal('hide');
            });
        });

        // initial load
        $("#update").click();
    });
</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-sm-12">
                <div class="table-responsive">
                    <table id="grid-routes" class="table table-condensed table-hover table-striped table-responsive">
                        <thead>
                        <tr>
                            <th data-column-id="proto" data-type="string" >{{ lang._('Proto') }}</th>
                            <th data-column-id="destination" data-type="string">{{ lang._('Destination') }}</th>
                            <th data-column-id="gateway" data-type="string">{{ lang._('Gateway') }}</th>
                            <th data-column-id="flags" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Flags') }}</th>
                            <th data-column-id="use" data-type="numeric" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Use') }}</th>
                            <th data-column-id="mtu" data-type="numeric" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('MTU') }}</th>
                            <th data-column-id="netif" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Netif') }}</th>
                            <th data-column-id="intf_description" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Netif (name)') }}</th>
                            <th data-column-id="expire" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Expire') }}</th>
                            <th data-column-id="commands" data-searchable="false" data-width="2em" data-formatter="commands" data-sortable="false">{{ lang._('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
            <div  class="col-sm-12">
                <div class="row">
                    <table class="table">
                        <tr>
                            <td>
                                <input type="checkbox" id="resolve" name="resolve" value="yes">
                            </td>
                            <td>
                                <strong><?=gettext("Name resolution");?></strong>
                                <p class="text-muted">
                                    <small>
                                        {{ lang._('Enable this to attempt to resolve names when displaying the tables. By enabling name resolution, the query may take longer.') }}
                                    </small>
                                </p>
                            </td>
                            <td>
                                <div class="pull-right">
                                    <button id="update" type="button" class="btn btn-default">
                                        <span class="fa fa-refresh fa-fw"></span>
                                        <span>{{ lang._('Refresh') }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog_processing") }}
