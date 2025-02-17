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
        search:'/api/unbound/settings/searchHostOverride/',
        get:'/api/unbound/settings/getHostOverride/',
        set:'/api/unbound/settings/setHostOverride/',
        add:'/api/unbound/settings/addHostOverride/',
        del:'/api/unbound/settings/delHostOverride/',
        toggle:'/api/unbound/settings/toggleHostOverride/',
        options: {
            selection: true,
            multiSelect: false,
            rowSelect: true,
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
    }).on("deselected.rs.jquery.bootgrid", function (e, rows) {
        // de-select not allowed, make sure always one items is selected. (sticky selected)
        if ($("#{{formGridHostOverride['table_id']}}").bootgrid("getSelectedRows").length == 0) {
            $("#{{formGridHostOverride['table_id']}}").bootgrid('select', [rows[0].uuid]);
        }
        $("#{{formGridHostAlias['table_id']}}").bootgrid('reload');
    }).on("loaded.rs.jquery.bootgrid", function (e) {
        let ids = $("#{{formGridHostOverride['table_id']}}").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#{{formGridHostOverride['table_id']}}").bootgrid('select', [ids[0].uuid]);
        }
        $("#{{formGridHostAlias['table_id']}}").bootgrid('reload');
    });

    let grid_aliases = $("#{{formGridHostAlias['table_id']}}").UIBootgrid({
        search:'/api/unbound/settings/searchHostAlias/',
        get:'/api/unbound/settings/getHostAlias/',
        set:'/api/unbound/settings/setHostAlias/',
        add:'/api/unbound/settings/addHostAlias/',
        del:'/api/unbound/settings/delHostAlias/',
        toggle:'/api/unbound/settings/toggleHostAlias/',
        options: {
            labels: {
                noResults: "{{ lang._('No results found for selected host or none selected') }}"
            },
            selection: true,
            multiSelect: true,
            rowSelect: true,
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
            $('tr[id="row_host.mx"]').addClass('hidden');
            $('tr[id="row_host.mxprio"]').addClass('hidden');
            $('tr[id="row_host.server"]').removeClass('hidden');
        } else if (this.value == "MX") {
            $('tr[id="row_host.server"]').addClass('hidden');
            $('tr[id="row_host.mx"]').removeClass('hidden');
            $('tr[id="row_host.mxprio"]').removeClass('hidden');
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

<!-- host overrides -->
<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridHostOverride)}}
    <div id="infosection" class="tab-content col-xs-12 __mb">
        {{ lang._('Entries in this section override individual results from the forwarders.') }}
        {{ lang._('Use these for changing DNS results or for adding custom DNS records.') }}
        {{ lang._('Keep in mind that all resource record types (i.e. A, AAAA, MX, etc. records) of a specified host below are being overwritten.') }}
    </div>
</div>
<!-- aliases for host overrides -->
<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridHostAlias)}}
</div>
<!-- reconfigure -->
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <div id="HostOverrideChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them.') }}
            </div>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/unbound/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-service-widget="unbound"
                    data-error-title="{{ lang._('Error reconfiguring unbound') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':formDialogHostOverride,'id':formGridHostOverride['edit_dialog_id'],'label':lang._('Edit Host Override')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHostAlias,'id':formGridHostAlias['edit_dialog_id'],'label':lang._('Edit Host Override Alias')])}}
