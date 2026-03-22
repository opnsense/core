{#
 # Copyright (c) 2014-2025 Deciso B.V.
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
    let selectedHostOverride = null;
    const commandOverride = {
        dialog: "DialogHostAlias",
        get: '/api/unbound/settings/get_host_alias/',
        set: '/api/unbound/settings/set_host_alias/',
        add: '/api/unbound/settings/add_host_alias/',
        del: '/api/unbound/settings/del_host_alias/',
        toggle: '/api/unbound/settings/toggle_host_alias/'
    };

    let grid_hosts = $("#{{formGridHostOverride['table_id']}}").UIBootgrid({
        search:'/api/unbound/settings/search_host_override/',
        get:'/api/unbound/settings/get_host_override/',
        set:'/api/unbound/settings/set_host_override/',
        add:'/api/unbound/settings/add_host_override/',
        del:'/api/unbound/settings/del_host_override/',
        toggle:'/api/unbound/settings/toggle_host_override/',
        commands: {
            "edit": {filter: (cell) => !cell.getData()['isAlias']},
            "copy": {filter: (cell) => !cell.getData()['isAlias']},
            "delete": {filter: (cell) => !cell.getData()['isAlias']},
            "add-alias": {
                filter: (cell) => !cell.getData()['isAlias'],
                method: function (event, cell) {
                    const data = cell.getData();
                    selectedHostOverride = data;
                    grid_hosts.bootgrid('command_add', event, cell, commandOverride);
                },
                classname: 'fa fa-plus fa-fw',
                sequence: 20,
                title: "{{ lang._('Add Alias') }}"
            },
            "edit-alias": {
                filter: (cell) => cell.getData()['isAlias'],
                method: function (event, cell) {
                    const data = cell.getData();
                    grid_hosts.bootgrid('command_edit', event, cell, data.uuid, commandOverride);
                },
                classname: 'fa fa-fw fa-pencil',
                sequence: 100,
                title: "{{ lang._('Edit Alias') }}"
            },
            "copy-alias": {
                filter: (cell) => cell.getData()['isAlias'],
                method: function (event, cell) {
                    const data = cell.getData();
                    grid_hosts.bootgrid('command_copy', event, cell, data.uuid, commandOverride);
                },
                classname: 'fa fa-fw fa-clone',
                sequence: 200,
                title: "{{ lang._('Copy Alias') }}"
            },
            "delete-alias": {
                filter: (cell) => cell.getData()['isAlias'],
                method: function (event, cell) {
                    const data = cell.getData();
                    grid_hosts.bootgrid('command_delete', event, cell, data.uuid, commandOverride);
                },
                classname: 'fa fa-fw fa-trash-o',
                sequence: 500,
                title: "{{ lang._('Delete Alias') }}"
            },
            "toggle-alias": {
                filter: (cell) => cell.getData()['isAlias'],
                method: function (event, cell) {
                    const data = cell.getData();
                    grid_hosts.bootgrid('command_toggle', event, cell, data.uuid, commandOverride);
                },
                title: (cell) => {
                    if (parseInt(cell.getValue()) === 1) {
                        return "{{ lang._('Disable Alias') }}";
                    } else {
                        return "{{ lang._('Enable Alias') }}";
                    }
                }
            }
        },
        options: {
            selection: false,
            multiSelect: false, /* disable batched enable/disable behavior, not compatible with this grid */
            rowCount: [7, 20, 50, 100, 200, 500, -1],
            formatters: {
                "mxformatter": function (column, row) {
                    /* Format the "Value" column so it shows either an MX host ("MX" type) or a raw IP address ("A" type) */
                    if (row.mx.length > 0) {
                        row.server = row.mx + ' (prio ' + row.mxprio + ')';
                    }
                    return row.server;
                },
                "rowtoggle": function (column, row) {
                    const command = row.isAlias ? 'command-toggle-alias' : 'command-toggle';
                    if (parseInt(row[column.id], 2) === 1) {
                        return `<span style="cursor: pointer;" class="fa fa-fw fa-check-square-o ${command} bootgrid-tooltip" data-value="1" data-row-id="${row.uuid}"></span>`;
                    } else {
                        return `<span style="cursor: pointer;" class="fa fa-fw fa-square-o ${command} bootgrid-tooltip" data-value="0" data-row-id="${row.uuid}"></span>`;
                    }
                }
            },
            requestHandler: function(request) {
                if (selectedHostOverride) {
                    request['host'] = selectedHostOverride.uuid;
                    selectedHostOverride = null;
                }

                return request;
            }
        },
        tabulatorOptions: {
            dataTree: true,
            dataTreeElementColumn:"tree",
            dataTreeCollapseElement:"<i class='fas fa-minus-square'></i>",
            dataTreeExpandElement:"<i class='fas fa-plus-square'></i>",
            rowFormatter: function(row) {
                const data = row.getData();
                const $element = $(row.getElement());

                if (data.isAlias) {
                    $element.addClass('alias-row');
                }
            }
        }
    });

    /* Hide/unhide input fields based on selected RR (Type) value */
    $('select[id="host.rr"]').on('change', function(e) {
        if (this.value == "A" || this.value == "AAAA") {
            $('tr[id="row_host.txtdata"]').addClass('hidden');
            $('tr[id="row_host.mx"]').addClass('hidden');
            $('tr[id="row_host.mxprio"]').addClass('hidden');
            $('tr[id="row_host.server"]').removeClass('hidden');
            $('tr[id="row_host.addptr"]').removeClass('hidden');
        } else if (this.value == "MX") {
            $('tr[id="row_host.txtdata"]').addClass('hidden');
            $('tr[id="row_host.server"]').addClass('hidden');
            $('tr[id="row_host.addptr"]').addClass('hidden');
            $('tr[id="row_host.mx"]').removeClass('hidden');
            $('tr[id="row_host.mxprio"]').removeClass('hidden');
        } else if (this.value == "TXT") {
            $('tr[id="row_host.server"]').addClass('hidden');
            $('tr[id="row_host.mx"]').addClass('hidden');
            $('tr[id="row_host.mxprio"]').addClass('hidden');
            $('tr[id="row_host.addptr"]').addClass('hidden');
            $('tr[id="row_host.txtdata"]').removeClass('hidden');
        }
    });

    /**
     * Reconfigure unbound - activate changes
     */
    $("#reconfigureAct").SimpleActionButton();
    updateServiceControlUI('unbound');
});
</script>

<style>
    .alias-row .tabulator-cell {
        border: 0 !important;
        box-shadow: none !important;
        padding-left: 2rem;
    }

    .theading-text {
        font-weight: 800;
        font-style: italic;
    }
</style>

<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridHostOverride + {'hide_delete': true, 'command_width': '120'})}}
    <div id="infosection" class="bootgrid-footer container-fluid">
        {{ lang._('Entries in this section override individual results from the forwarders.') }}
        {{ lang._('Use these for changing DNS results or for adding custom DNS records.') }}
        {{ lang._('Keep in mind that all resource record types (i.e. A, AAAA, MX, etc. records) of a specified host below are being overwritten.') }}
    </div>
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/reconfigure', 'data_service_widget': 'unbound'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogHostOverride,'id':formGridHostOverride['edit_dialog_id'],'label':lang._('Edit Host Override')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHostAlias,'id':'DialogHostAlias','label':lang._('Edit Host Override Alias')])}}
