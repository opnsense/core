{#
 # Copyright (c) 2025 Deciso B.V.
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
        let selected_interfaces = [];
        $("#interface-selection").on("changed.bs.select", function (e) {
            selected_interfaces = $(this).val();
            $("#grid-leases").bootgrid('reload');
        })

        $("#grid-leases").UIBootgrid({
            search:'/api/kea/leases6/search/',
            options: {
                selection: false,
                multiSelect: false,
                useRequestHandlerOnGet: true,
                requestHandler: function(request) {
                    request['selected_interfaces'] = selected_interfaces;
                    return request;
                },
                responseHandler: function (response) {
                    if (response.interfaces !== undefined) {
                        let intfsel = $("#interface-selection > option").map(function () {
                            return $(this).val();
                        }).get();
                        for ([intf, descr] of Object.entries(response['interfaces'])) {
                            if (!intfsel.includes(intf)) {
                                $("#interface-selection").append($('<option>', {
                                    value: intf,
                                    text: descr
                                }));
                            }
                        }
                        $("#interface-selection").selectpicker('refresh');
                    }
                    return response;
                },
                formatters: {
                    "overflowformatter": function (column, row) {
                        return '<span class="overflow">' + row[column.id] + '</span><br/>'
                    },
                    "macformatter": function (column, row) {
                        let mac = '<span class="overflow">' + row.hwaddr + '</span>';
                        if (row.mac_info != '') {
                            mac = mac + '<br/>' + '<small class="overflow"><i>' + row.mac_info + '</i></small>';
                        }
                        return mac;
                    },
                    "timestamp": function (column, row) {
                        return moment.unix(row[column.id]).local().format('YYYY-MM-DD HH:mm:ss');
                    },
                }
            }
        });

        $("#interface-selection-wrapper").detach().insertAfter('#grid-leases-header .search');

        updateServiceControlUI('kea');
    });
</script>

<style>
.overflow {
    text-overflow: clip;
    white-space: normal;
    word-break: break-word;
}
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs"></ul>
<div class="tab-content content-box col-xs-12 __mb">
    <div class="btn-group" id="interface-selection-wrapper">
        <select class="selectpicker" multiple="multiple" data-live-search="true" id="interface-selection" data-width="auto" title="All Interfaces">
        </select>
    </div>
    <table id="grid-leases" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
            <tr>
                <th data-column-id="if_descr" data-type="string">{{ lang._('Interface') }}</th>
                <th data-column-id="address" data-identifier="true" data-type="string" data-formatter="overflowformatter">{{ lang._('IP Address') }}</th>
                <th data-column-id="duid" data-type="string" data-width="9em">{{ lang._('DUID') }}</th>
                <th data-column-id="hwaddr" data-type="string" data-formatter="macformatter" data-width="9em">{{ lang._('MAC Address') }}</th>
                <th data-column-id="valid_lifetime" data-type="integer">{{ lang._('Lifetime') }}</th>
                <th data-column-id="expire" data-type="string" data-formatter="timestamp">{{ lang._('Expire') }}</th>
                <th data-column-id="hostname" data-type="string" data-formatter="overflowformatter">{{ lang._('Hostname') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        </tfoot>
        </tr>
    </table>
</div>
