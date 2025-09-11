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
    let grid_hosts = $("#{{formGridHostOverride['table_id']}}").UIBootgrid({
        search:'/api/unbound/settings/search_host_override/',
        get:'/api/unbound/settings/get_host_override/',
        set:'/api/unbound/settings/set_host_override/',
        add:'/api/unbound/settings/add_host_override/',
        del:'/api/unbound/settings/del_host_override/',
        toggle:'/api/unbound/settings/toggle_host_override/',
        options: {
            selection: true,
            multiSelect: false,
            rowSelect: true,
            rowCount: [7, 20, 50, 100, 200, 500, -1],
            stickySelect: true,
            formatters: {
                "mxformatter": function (column, row) {
                    /* Format the "Value" column so it shows either an MX host ("MX" type) or a raw IP address ("A" type) */
                    if (row.mx.length > 0) {
                        row.server = row.mx + ' (prio ' + row.mxprio + ')';
                    }
                    return row.server;
                },
            },
        }
    }).on("selected.rs.jquery.bootgrid", function (e, rows) {
        $("#{{formGridHostAlias['table_id']}}").bootgrid('reload');
    }).on("loaded.rs.jquery.bootgrid", function (e) {
        let ids = $("#{{formGridHostOverride['table_id']}}").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#{{formGridHostOverride['table_id']}}").bootgrid('select', [ids[0].uuid]);
        }
    });

    let grid_aliases = $("#{{formGridHostAlias['table_id']}}").UIBootgrid({
        search:'/api/unbound/settings/search_host_alias/',
        get:'/api/unbound/settings/get_host_alias/',
        set:'/api/unbound/settings/set_host_alias/',
        add:'/api/unbound/settings/add_host_alias/',
        del:'/api/unbound/settings/del_host_alias/',
        toggle:'/api/unbound/settings/toggle_host_alias/',
        options: {
            labels: {
                noResults: "{{ lang._('No results found for selected host or none selected') }}"
            },
            selection: true,
            multiSelect: true,
            rowSelect: true,
            rowCount: [7, 20, 50, 100, 200, 500, -1],
            useRequestHandlerOnGet: true,
            requestHandler: function(request) {
                let uuids = $("#{{formGridHostOverride['table_id']}}").bootgrid("getSelectedRows");
                request['host'] = uuids.length > 0 ? uuids[0] : "__not_found__";
                let selected = $(".host_selected");
                uuids.length > 0 ? selected.show() : selected.hide();
                if (request.rowCount === undefined) {
                    // XXX: We can't easily see if we're being called by GET or POST, assume GET uri when there's no rowcount
                    return new URLSearchParams(request).toString();
                } else {
                    return request;
                }
            }
        }
    });

    $("div.actionBar").each(function(){
        if ($(this).closest(".bootgrid-header").attr("id").includes("Alias")) {
            $(this).parent().prepend($('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Aliases') }}</div>'));
        } else {
            $(this).parent().prepend($('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Hosts') }}</div>'));
        }
    });

    /* Hide/unhide input fields based on selected RR (Type) value */
    $('select[id="host.rr"]').on('change', function(e) {
        if (this.value == "A" || this.value == "AAAA") {
            $('tr[id="row_host.txtdata"]').addClass('hidden');
            $('tr[id="row_host.mx"]').addClass('hidden');
            $('tr[id="row_host.mxprio"]').addClass('hidden');
            $('tr[id="row_host.server"]').removeClass('hidden');
        } else if (this.value == "MX") {
            $('tr[id="row_host.txtdata"]').addClass('hidden');
            $('tr[id="row_host.server"]').addClass('hidden');
            $('tr[id="row_host.mx"]').removeClass('hidden');
            $('tr[id="row_host.mxprio"]').removeClass('hidden');
        } else if (this.value == "TXT") {
            $('tr[id="row_host.server"]').addClass('hidden');
            $('tr[id="row_host.mx"]').addClass('hidden');
            $('tr[id="row_host.mxprio"]').addClass('hidden');
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
    .theading-text {
        font-weight: 800;
        font-style: italic;
    }

    #infosection {
        margin: 1em;
    }
</style>

<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridHostOverride)}}
    <div id="infosection" class="tab-content col-xs-12 __mb">
        {{ lang._('Entries in this section override individual results from the forwarders.') }}
        {{ lang._('Use these for changing DNS results or for adding custom DNS records.') }}
        {{ lang._('Keep in mind that all resource record types (i.e. A, AAAA, MX, etc. records) of a specified host below are being overwritten.') }}
    </div>
</div>
<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridHostAlias)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/reconfigure', 'data_service_widget': 'unbound'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogHostOverride,'id':formGridHostOverride['edit_dialog_id'],'label':lang._('Edit Host Override')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHostAlias,'id':formGridHostAlias['edit_dialog_id'],'label':lang._('Edit Host Override Alias')])}}
