{#
 # Copyright (c) 2023 Deciso B.V.
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

    let addrs = [];
    function setreservationinfo(event, data) {
        addrs['ip_addr'] = `${data.address}`;
        addrs['hw_addr'] = `${data.hwaddr}`;
    }

    $( document ).ready(function() {
        let selected_interfaces = [];
        $("#interface-selection").on("changed.bs.select", function (e) {
            selected_interfaces = $(this).val();
            $("#grid-leases").bootgrid('reload');
        });

        $("#grid-leases").UIBootgrid({
            search:'/api/kea/leases4/search/',
            add:'/api/kea/leases4/add_reservation/',
            get:'/api/kea/leases4/get_lease/',
            set:'/api/kea/leases4/set_reservation/',
            del:'/api/kea/leases4/del_reservation/',
            options: {
                selection: false,
                multiSelect: false,
                useRequestHandlerOnGet: true,
                requestHandler: function(request) {
                    request['selected_interfaces'] = selected_interfaces;
                    /*
                     * When attempting to add 'ip_addr' and 'hw_addr' the JSON object
                     * is passed via query parameters instead of the request body.
                     * Format params so server can understand.
                     */
                    if (addrs['ip_addr'] !== undefined) {
                        request['ip_addr'] = addrs['ip_addr'];
                        request['hw_addr'] = addrs['hw_addr'];
                        addrs = [];
                        return $.param(request);
                    }
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
                    "timestamp": function (column, row) {
                        return moment.unix(row[column.id]).local().format('YYYY-MM-DD HH:mm:ss');
                    },
                    "state": function (column, row) {
                        return row.state == 0 ? 'active' : 'inactive';
                    },
                    "lease_type": function (column, row) {
                        return row.reserved ? 'reserved' : 'dynamic';
                    },
                    "commands": function (column, row) {
                        if (row.reserved) {
                            let editres = `<button id="${row["reservation.uuid"]}" type="button" class="btn btn-xs btn-default bootgrid-tooltip command-edit"` +
                                `data-row-id="${row["reservation.uuid"]}">` +
                                '<span class="fa fa-pencil fa-fw"></span></button>';

                            let deleteip = '<button type="button" class="btn btn-xs btn-default bootgrid-tooltip command-delete"' +
                                'data-row-id="' + row["reservation.uuid"] + '" data-action="deleteSelected">' +
                                '<i class="fa fa-trash fa-fw"></i>' +
                                '</button>';
                            return  editres + deleteip;
                        } else {
                            let reserve_lease  = `<button id="${row.address}" type="button" class="btn btn-xs btn-primary bootgrid-tooltip command-add"` +
                                `onclick="setreservationinfo(event, {address: '${row.address}', hwaddr: '${row.hwaddr}'})"` +
                                `data-row-id="${row.address}" data-original-title="Add">` +
                                '<span class="fa fa-plus fa-fw"></span></button>';
                            return reserve_lease;
                        }
                   },
               }
           }
        });

        $("#interface-selection-wrapper").detach().prependTo('#grid-leases-header > .row > .actionBar > .actions');

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
    <table id="grid-leases" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogReservation">
        <thead>
            <tr>
                <th data-column-id="if_descr" data-type="string">{{ lang._('Interface') }}</th>
                <th data-column-id="address" data-identifier="true" data-type="string" data-formatter="overflowformatter">{{ lang._('IP Address') }}</th>
                <th data-column-id="hwaddr" data-type="string" data-width="9em">{{ lang._('MAC Address') }}</th>
                <th data-column-id="valid_lifetime" data-width="7em" data-type="integer">{{ lang._('Lifetime') }}</th>
                <th data-column-id="expire" data-type="string" data-formatter="timestamp">{{ lang._('Expire') }}</th>
                <th data-column-id="state" data-width="5em" data-type="string" data-formatter="state">{{ lang._('State') }}</th>
                <th data-column-id="lease_type" data-width="7em" data-type="string" data-formatter="lease_type">{{ lang._('Lease Type') }}</th>
                <th data-column-id="hostname" data-type="string" data-formatter="overflowformatter">{{ lang._('Hostname') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        </tfoot>
    </table>
</div>

{{ partial("layout_partials/base_dialog", ['fields':formDialogReservation,'id':'DialogReservation','label':lang._('Edit Lease')])}}
