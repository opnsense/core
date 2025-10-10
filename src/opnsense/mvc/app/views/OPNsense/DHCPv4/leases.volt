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
    $( document ).ready(function() {
        let show_inactive = false;
        let selected_interfaces = [];

        if (window.localStorage) {
            if (window.localStorage.getItem("api.dhcp.leases.inactive") !== null) {
                show_inactive = window.localStorage.getItem("api.dhcp.leases.inactive") == 'true'
                $("#show-inactive").prop('checked', show_inactive);
            }
        }

        $("#show-inactive").change(function() {
            show_inactive = this.checked;
            if (window.localStorage) {
                window.localStorage.setItem("api.dhcp.leases.inactive", show_inactive);
            }

            $("#grid-leases").bootgrid('reload');
        });

        $("#interface-selection").on("changed.bs.select", function (e) {
            selected_interfaces = $(this).val();
            $("#grid-leases").bootgrid('reload');
        })

        $("#grid-leases").UIBootgrid({
            search:'/api/dhcpv4/leases/search_lease/',
            del:'/api/dhcpv4/leases/del_lease/',
            options: {
                virtualDOM: true,
                selection: false,
                multiSelect: false,
                useRequestHandlerOnGet: true,
                requestHandler: function(request) {
                    request['inactive'] = show_inactive;
                    request['selected_interfaces'] = selected_interfaces;
                    return request;
                },
                responseHandler: function (response) {
                    if (response.hasOwnProperty('interfaces')) {
                        for ([intf, descr] of Object.entries(response['interfaces'])) {
                            let exists = false;
                            $('#interface-selection option').each(function() {
                                if (this.value == intf) {
                                    exists = true;
                                }
                            });

                            if (!exists) {
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
                    "macformatter": function (column, row) {
                        let mac = '<span class="overflow">' + row.mac + '</span>';
                        if (row.man != '') {
                            mac = mac + '<br/>' + '<small class="overflow"><i>' + row.man + '</i></small>';
                        }
                        return mac;
                    },
                    "tooltipformatter": function (column, row) {
                        return '<span class="overflow">' + row[column.id] + '</span><br/>'
                    },
                    "statusformatter": function (column, row) {
                        let connected = row.status == 'online' ? 'text-success' : 'text-danger';
                        return '<i class="fa fa-plug ' + connected +'" title="' + row.status + '" data-toggle="tooltip"></i>'
                    },
                    "commands": function (column, row) {
                        /* we override the default commands data formatter in order to utilize
                         * two different types of data keys for two different actions. The mapping
                         * action needs a MAC address, while the delete action requires an IP address.
                         */
                        if (row.type == 'static') {
                            return '';
                        }

                        let static_map = '';
                        if (row.if != '') {
                            static_map = '<a class="btn btn-default btn-xs" href="/services_dhcp_edit.php?if=' + row.if +
                                '&amp;mac=' + row.mac + '&amp;hostname=' + row["hostname"] + '">' +
                                '<i class="fa fa-plus fa-fw act-map" data-value="' + row.mac + '" data-toggle="tooltip" ' +
                                'title="{{lang._("Add a static mapping for this MAC address")}}"></i>' +
                                '</a>';
                        }

                        /* The delete action can be hooked up to the default bootgrid behaviour */
                        let deleteip = '<button type="button" class="btn btn-xs btn-default bootgrid-tooltip command-delete"' +
                                'data-row-id="' + row.address + '">' +
                                '<i class="fa fa-trash fa-fw"></i>' +
                                '</a>';

                        return static_map + ' ' + deleteip;
                    }
                }
            }
        });

        $("#inactive-selection-wrapper").detach().insertBefore('#grid-leases-header .search');
        $("#interface-selection-wrapper").detach().insertAfter('#grid-leases-header .search');

        updateServiceControlUI('dhcpv4');
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
    <div id="inactive-selection-wrapper" style="float: left;">
        <label>
            <input id="show-inactive" type="checkbox"/>
            {{ lang._('Show inactive') }}
        </label>
    </div>
    <div class="btn-group" id="interface-selection-wrapper">
        <select class="selectpicker" multiple="multiple" data-live-search="true" id="interface-selection" data-width="auto" title="All Interfaces">
        </select>
    </div>
    <table id="grid-leases" class="table table-condensed table-hover table-striped table-responsive">
        <tr>
        <thead>
        <tr>
            <th data-column-id="if_descr" data-type="string">{{ lang._('Interface') }}</th>
            <th data-column-id="address" data-identifier="true" data-type="string" data-width="8em">{{ lang._('IP Address') }}</th>
            <th data-column-id="mac" data-type="string" data-formatter="macformatter" data-width="9em">{{ lang._('MAC Address') }}</th>
            <th data-column-id="hostname" data-type="string" data-formatter="tooltipformatter">{{ lang._('Hostname') }}</th>
            <th data-column-id="descr" data-type="string" data-formatter="tooltipformatter">{{ lang._('Description') }}</th>
            <th data-column-id="starts" data-type="string" data-formatter="tooltipformatter">{{ lang._('Start') }}</th>
            <th data-column-id="ends" data-type="string" data-formatter="tooltipformatter">{{ lang._('End') }}</th>
            <th data-column-id="status" data-type="string" data-formatter="statusformatter">{{ lang._('Status') }}</th>
            <th data-column-id="state" data-type="string">{{ lang._('State') }}</th>
            <th data-column-id="type" data-type="string">{{ lang._('Lease Type') }}</th>
            <th data-column-id="commands" data-formatter="commands", data-sortable="false"></th>
        </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        </tfoot>
        </tr>
    </table>
</div>
