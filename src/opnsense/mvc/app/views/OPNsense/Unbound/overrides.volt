{#
 # Copyright (c) 2014-2022 Deciso B.V.
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
    /**
     * load content on tab changes
     */
    let heading_appended = false;
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (e.target.id == 'host_overrides_tab') {
            $("#grid-aliases-wrapper").show();
            $("#grid-hosts").bootgrid('destroy');
            let grid_hosts = $("#grid-hosts").UIBootgrid({
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
                        /* commands and rowtoggles added here since adding a custom formatter removes these by default for some reason */
                        "commands": function (column, row) {
                            return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                                '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                                '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                        },
                        "rowtoggle": function (column, row) {
                            if (parseInt(row[column.id], 2) === 1) {
                                return '<span style="cursor: pointer;" class="fa fa-fw fa-check-square-o command-toggle bootgrid-tooltip" data-value="1" data-row-id="' + row.uuid + '"></span>';
                            } else {
                                return '<span style="cursor: pointer;" class="fa fa-fw fa-square-o command-toggle bootgrid-tooltip" data-value="0" data-row-id="' + row.uuid + '"></span>';
                            }
                        },
                    },
                }
            }).on("selected.rs.jquery.bootgrid", function (e, rows) {
                $("#grid-aliases").bootgrid('reload');
            }).on("deselected.rs.jquery.bootgrid", function (e, rows) {
                // de-select not allowed, make sure always one items is selected. (sticky selected)
                if ($("#grid-hosts").bootgrid("getSelectedRows").length == 0) {
                    $("#grid-hosts").bootgrid('select', [rows[0].uuid]);
                }
                $("#grid-aliases").bootgrid('reload');
            }).on("loaded.rs.jquery.bootgrid", function (e) {
                let ids = $("#grid-hosts").bootgrid("getCurrentRows");
                if (ids.length > 0) {
                    $("#grid-hosts").bootgrid('select', [ids[0].uuid]);
                }
                $("#grid-aliases").bootgrid('reload');
            });

            let grid_aliases = $("#grid-aliases").UIBootgrid({
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
                        let uuids = $("#grid-hosts").bootgrid("getSelectedRows");
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

            if (!heading_appended) {
                $("div.actionBar").each(function(){
                    if ($(this).closest(".bootgrid-header").attr("id").includes("alias")) {
                        $(this).parent().prepend($('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Aliases') }}</div>'));
                        $(this).removeClass("col-sm-12");
                        $(this).addClass("col-sm-10");
                        heading_appended = true;
                    }
                });
            }

        } else if (e.target.id == 'domain_overrides_tab') {
            $("#grid-aliases-wrapper").hide();
            $("#grid-domains").bootgrid('destroy');
            let grid_domains = $("#grid-domains").UIBootgrid({
                search:'/api/unbound/settings/searchDomainOverride/',
                get:'/api/unbound/settings/getDomainOverride/',
                set:'/api/unbound/settings/setDomainOverride/',
                add:'/api/unbound/settings/addDomainOverride/',
                del:'/api/unbound/settings/delDomainOverride/',
                toggle:'/api/unbound/settings/toggleDomainOverride/',
                options: {
                    selection: true,
                    multiSelect: true,
                    rowSelect: true,
                }
            });
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


    if (window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click();
    } else {
        $('a[href="#host_overrides"]').click();
    }

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

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#host_overrides" id="host_overrides_tab">{{ lang._('Host Overrides') }}</a></li>
    <li><a data-toggle="tab" href="#domain_overrides" id="domain_overrides_tab">{{ lang._('Domain Overrides') }}</a></li>
</ul>
<div class="tab-content content-box col-xs-12 __mb">
    <!-- host overrides -->
    <div id="host_overrides" class="tab-pane fade in active">
        <table id="grid-hosts" class="table table-condensed table-hover table-striped" data-editDialog="DialogHostOverride" data-editAlert="OverrideChangeMessage">
            <thead>
            <tr>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="hostname" data-type="string">{{ lang._('Host') }}</th>
                <th data-column-id="domain" data-type="string">{{ lang._('Domain') }}</th>
                <th data-column-id="rr" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="server" data-type="string" data-formatter="mxformatter">{{ lang._('Value') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button id="test" data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
        <div id="infosection" class="tab-content col-xs-12 __mb">
            {{ lang._('Entries in this section override individual results from the forwarders.') }}
            {{ lang._('Use these for changing DNS results or for adding custom DNS records.') }}
            {{ lang._('Keep in mind that all resource record types (i.e. A, AAAA, MX, etc. records) of a specified host below are being overwritten.') }}
        </div>
    </div>
    <!-- domain overrides -->
    <div id="domain_overrides" class="tab-pane fade in">
        <table id="grid-domains" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogDomainOverride" data-editAlert="OverrideChangeMessage">
            <thead>
            <tr>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="domain" data-type="string">{{ lang._('Domain') }}</th>
                <th data-column-id="server" data-type="string">{{ lang._('IP') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
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
        <div id="infosection" class="tab-content col-xs-12 __mb">
            {{ lang._('Entries in this area override an entire domain by specifying an authoritative DNS server to be queried for that domain.') }}
        </div>
    </div>
</div>
<!-- aliases for host overrides -->
<div class="tab-content content-box col-xs-12 __mb" id ="grid-aliases-wrapper">
    <table id="grid-aliases" class="table table-condensed table-hover table-striped" data-editDialog="DialogHostAlias" data-editAlert="OverrideChangeMessage">
        <thead>
        <tr>
            <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
            <th data-column-id="hostname" data-type="string">{{ lang._('Host') }}</th>
            <th data-column-id="domain" data-type="string">{{ lang._('Domain') }}</th>
            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot class="host_selected">
        <tr>
            <td></td>
            <td>
                <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
<!-- reconfigure -->
<div class="tab-content content-box col-xs-12 __mb">
    <div id="OverrideChangeMessage" class="alert alert-info" style="display: none" role="alert">
        {{ lang._('After changing settings, please remember to apply them with the button below') }}
    </div>
    <table class="table table-condensed">
        <tbody>
        <tr>
            <td>
                <button class="btn btn-primary" id="reconfigureAct"
                        data-endpoint='/api/unbound/service/reconfigure'
                        data-label="{{ lang._('Apply') }}"
                        data-service-widget="unbound"
                        data-error-title="{{ lang._('Error reconfiguring unbound') }}"
                        type="button"
                ></button>
            </td>
        </tr>
        </tbody>
    </table>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogHostOverride,'id':'DialogHostOverride','label':lang._('Edit Host Override')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHostAlias,'id':'DialogHostAlias','label':lang._('Edit Host Override Alias')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogDomainOverride,'id':'DialogDomainOverride','label':lang._('Edit Domain Override')])}}
