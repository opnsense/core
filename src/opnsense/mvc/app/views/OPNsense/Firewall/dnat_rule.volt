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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
    $(document).ready(function() {

        // XXX: Most code comments are the same as filter_rule.volt, thats why they're omitted.
        //      Large blocks of code are exactly the same, so we could think about a base view.
        //      Though since there are subtle differences leaving them separated might be best for now.

        function showDialogAlert(type, title, message) {
            BootstrapDialog.show({
                type: type,
                title: title,
                message: message,
                buttons: [{
                    label: '{{ lang._('Close') }}',
                    action: function(dialogRef) {
                        dialogRef.close();
                    }
                }]
            });
        }

        let treeViewEnabled = localStorage.getItem("dnat_tree") === "1";
        $('#toggle_tree_button').toggleClass('active btn-primary', treeViewEnabled);

        function dynamicResponseHandler(resp) {
            if (!treeViewEnabled) {
                return resp;
            }

            const buckets = [];
            let current = null;

            resp.rows.forEach(r => {
                const label = (r["%category"] || r.category || "");

                if (!current || current._label !== label) {
                    current = {
                        uuid           : `${String(r.uuid).replace(/-/g, '')}`,
                        isGroup        : true,
                        _label         : label,
                        children       : []
                    };

                    current.category      = label;
                    current.category_colors = r.category_colors || [];

                    buckets.push(current);
                }

                current.children.push(r);
            });

            return Object.assign({}, resp, { rows: buckets });
        }

        const grid = $("#{{ formGridDNatRule['table_id'] }}").UIBootgrid({
            search : '/api/firewall/d_nat/search_rule/',
            get    : '/api/firewall/d_nat/get_rule/',
            set    : '/api/firewall/d_nat/set_rule/',
            add    : '/api/firewall/d_nat/add_rule/',
            del    : '/api/firewall/d_nat/del_rule/',
            toggle : '/api/firewall/d_nat/toggle_rule/',
            tabulatorOptions : {
                dataTree              : true,
                dataTreeChildField    : "children",
                dataTreeElementColumn : "category",
                rowFormatter: function(row) {
                    const data = row.getData();
                    const $element = $(row.getElement());

                    if ('disabled' in data && data.disabled == "1") {
                        $element.addClass('row-disabled');
                    }

                    if (data.isGroup || !data.uuid || !data.uuid.includes("-")) {
                        $element.addClass('row-no-select');
                    }

                    if (data.isGroup) {
                        $element.addClass('bucket-row');
                    }
                },
            },
            options: {
                responsive: true,
                sorting: false,
                initialSearchPhrase: getUrlHash('search'),
                triggerEditFor: getUrlHash('edit'),
                requestHandler: function(request){
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    return request;
                },
                responseHandler: dynamicResponseHandler,
                headerFormatters: {
                    disabled: function (column) {
                        return '<i class="fa-solid fa-fw fa-check-square" data-toggle="tooltip" title="{{ lang._('Enabled') }}"></i>';;
                    },
                    interface: function (column) {
                        return '<i class="fa-solid fa-fw fa-network-wired" data-toggle="tooltip" title="{{ lang._('Network interface') }}"></i>';
                    },
                    category: function (column) {
                        return '<i class="fa-solid fa-fw fa-tag" data-toggle="tooltip" title="{{ lang._("Categories") }}"></i>';
                    },
                },
                formatters:{
                    commands: function (column, row) {
                        if (row.isGroup) {
                            return "";
                        }
                        let rowId = row.uuid;

                        if (!rowId.includes('-')) {
                            return `
                                <a href="/system_advanced_firewall.php"
                                class="btn btn-xs btn-default bootgrid-tooltip"
                                title="{{ lang._('Lookup Rule') }}">
                                    <span class="fa fa-fw fa-search"></span>
                                </a>
                            `;
                        }

                        return `
                            <button type="button" class="btn btn-xs btn-default command-move_before
                                bootgrid-tooltip" data-row-id="${rowId}"
                                title="{{ lang._('Move selected rule before this rule') }}">
                                <span class="fa fa-fw fa-arrow-left"></span>
                            </button>

                            <button type="button" class="btn btn-xs btn-default command-toggle_log bootgrid-tooltip"
                                data-row-id="${row.uuid}" data-value="${row.log}"
                                title="${row.log == '1'
                                    ? '{{ lang._("Disable Logging") }}'
                                    : '{{ lang._("Enable Logging") }}'}">
                                <i class="fa fa-fw
                                    ${row.log == '1'
                                        ? 'fa-bell'
                                        : 'fa-bell-slash'}">
                                </i>
                            </button>

                            <button type="button" class="btn btn-xs btn-default command-edit
                                bootgrid-tooltip" data-row-id="${rowId}"
                                title="{{ lang._('Edit') }}">
                                <span class="fa fa-fw fa-pencil"></span>
                            </button>

                            <button type="button" class="btn btn-xs btn-default command-copy
                                bootgrid-tooltip" data-row-id="${rowId}"
                                title="{{ lang._('Clone') }}">
                                <span class="fa fa-fw fa-clone"></span>
                            </button>

                            <button type="button" class="btn btn-xs btn-default command-delete
                                bootgrid-tooltip" data-row-id="${rowId}"
                                title="{{ lang._('Delete') }}">
                                <span class="fa fa-fw fa-trash-o"></span>
                            </button>
                        `;
                    },
                    rowtoggle: function (column, row) {
                        const rowId = row.uuid || '';
                        if (row.isGroup || !rowId.includes('-')) {
                            return '';
                        }
                        const isEnabled = row[column.id] === "0";
                        return `
                            <span class="fa fa-fw ${isEnabled ? 'fa-check-square-o' : 'fa-square-o text-muted'} bootgrid-tooltip command-toggle"
                                style="cursor: pointer;"
                                data-value="${isEnabled ? 1 : 0}"
                                data-row-id="${rowId}"
                                title="${isEnabled ? '{{ lang._("Enabled") }}' : '{{ lang._("Disabled") }}'}">
                            </span>
                        `;
                    },
                    any: function(column, row) {
                        if (row.isGroup) {
                            return "";
                        }

                        if (
                            row[column.id] !== '' &&
                            row[column.id] !== 'any' &&
                            row[column.id] !== 'None'
                        ) {
                            return row[`%${column.id}`] || row[column.id];
                        } else {
                            return '*';
                        }
                    },
                    category: function (column, row) {
                        const isGroup = row.isGroup;
                        const hasCategories = row.category && Array.isArray(row.category_colors);

                        if (!hasCategories) {

                            return isGroup
                                ? `<span class="category-icon category-cell">
                                    <i class="fa fa-fw fa-tag"></i>
                                    <strong>{{ lang._('Uncategorized') }}</strong>
                                    <span class="badge badge-sm bg-info"
                                            style="margin-left:6px;">${(row.children && row.children.length) || 0}</span>
                                </span>`
                                : '';
                        }

                        const category = (row["%category"] || row.category).split(',');
                        const colors     = row.category_colors;

                        const icons = category.map((cat, idx) => `
                            <span class="category-icon" data-toggle="tooltip" title="${cat}">
                                <i class="fa fa-fw fa-tag" style="color:${colors[idx]};"></i>
                            </span>`).join(' ');

                        return isGroup
                            ? `<span class="category-cell">
                                    <span class="category-cell-content">
                                        <strong>${icons} ${category.join(', ')}</strong>
                                        <span class="badge badge-sm bg-info"
                                                style="margin-left:6px;">${(row.children && row.children.length) || 0}</span>
                                    </span>
                            </span>`
                            : icons;
                    },
                    interfaces: function(column, row) {
                        if (row.isGroup) {
                            return "";
                        }

                        const interfaces = row["%" + column.id] || row[column.id] || "";

                        if (interfaces === "") {
                            return "*";
                        }

                        if (!interfaces.includes(",")) {
                            return (row.interfacenot == 1 ? "! " : "") + interfaces;
                        }

                        const interfaceList = interfaces.split(",");
                        const tooltipText = interfaceList.join("<br>");

                        return `
                            <span data-toggle="tooltip" data-html="true" title="${tooltipText}" style="white-space: nowrap;">
                                <span class="interface-count">${interfaceList.length}</span>
                                <i class="fa-solid fa-fw fa-network-wired"></i>
                            </span>
                        `;
                    },
                    alias: function(column, row) {
                        if (row.isGroup) {
                            return "";
                        }
                        const value = row[column.id] || "";
                        const isNegated = (row[column.id.replace('network', 'not')] == 1) ? "! " : "";

                        if (typeof value !== 'string') {
                            return '';
                        }

                        if (!value || value === "any") {
                            return isNegated + '*';
                        }

                        const aliasMetadataList = row["alias_meta_" + column.id] || [];

                        const renderedItems = aliasMetadataList.map(aliasInfo => {
                            if (aliasInfo.isAlias) {
                                const tooltipHtml = aliasInfo.description || aliasInfo.value || "";
                                return `
                                    <span data-toggle="tooltip" data-html="true" title="${tooltipHtml}">${aliasInfo.value}&nbsp;</span>
                                    <a href="/ui/firewall/alias/index/${encodeURIComponent(aliasInfo.value)}"
                                    data-toggle="tooltip" title="{{ lang._('Edit alias') }}">
                                    <i class="fa fa-fw fa-list"></i>
                                    </a>
                                `;
                            }
                            return aliasInfo["%value"];
                        }).join(", ");

                        return isNegated + renderedItems;
                    },
                },
            },
            commands: {
                move_before: {
                    method: function(event) {
                        const selected = $("#{{ formGridDNatRule['table_id'] }}").bootgrid("getSelectedRows");
                        if (selected.length !== 1) {
                            showDialogAlert(
                                BootstrapDialog.TYPE_WARNING,
                                "{{ lang._('Selection Error') }}",
                                "{{ lang._('Please select exactly one rule to move.') }}"
                            );
                            return;
                        }

                        const selectedUuid = selected[0];
                        const targetUuid = $(this).data("row-id");

                        if (selectedUuid === targetUuid) {
                            showDialogAlert(
                                BootstrapDialog.TYPE_WARNING,
                                "{{ lang._('Move Error') }}",
                                "{{ lang._('Cannot move a rule before itself.') }}"
                            );
                            return;
                        }

                        ajaxCall(
                            "/api/firewall/d_nat/move_rule_before/" + selectedUuid + "/" + targetUuid,
                            {},
                            function(data, status) {
                                if (data.status === "ok") {
                                    $("#{{ formGridDNatRule['table_id'] }}").bootgrid("reload");
                                    $("#change_message_base_form").stop(true, false).slideDown(1000).delay(2000).slideUp(2000);
                                }
                            },
                            function(xhr, textStatus, errorThrown) {
                                showDialogAlert(
                                    BootstrapDialog.TYPE_DANGER,
                                    "{{ lang._('Request Failed') }}",
                                    errorThrown
                                );
                            },
                            'POST'
                        );
                    },
                    classname: 'fa fa-fw fa-arrow-left',
                    title: "{{ lang._('Move selected rule before this rule') }}",
                    sequence: 10
                },
                toggle_log: {
                    method: function(event) {
                        const uuid = $(this).data("row-id");
                        const log = String(+$(this).data("value") ^ 1);
                        ajaxCall(
                            `/api/firewall/d_nat/toggle_rule_log/${uuid}/${log}`,
                            {},
                            function(data) {
                                if (data.status === "ok") {
                                    $("#{{ formGridDNatRule['table_id'] }}").bootgrid("reload");
                                    $("#change_message_base_form").stop(true, false).slideDown(1000).delay(2000).slideUp(2000);
                                }
                            },
                            function(xhr, textStatus, errorThrown) {
                                showDialogAlert(
                                    BootstrapDialog.TYPE_DANGER,
                                    "{{ lang._('Request Failed') }}",
                                    errorThrown
                                );
                            },
                            'POST'
                        );
                    },
                    classname: 'fa fa-fw fa-exclamation-circle',
                    title: "{{ lang._('Toggle Logging') }}",
                    sequence: 20
                }
            },
        });

        let categoryInitialized = false;
        let reconfigureActInProgress = false;

        function populateCategoriesSelectpicker() {
            const currentSelection = $("#category_filter").val();

            return $("#category_filter").fetch_options(
                '/api/firewall/d_nat/list_categories',
                {},
                function (data) {
                    if (!data.rows) return [];

                    data.rows.sort((a, b) => {
                        const aUsed = a.used > 0 ? 0 : 1;
                        const bUsed = b.used > 0 ? 0 : 1;

                        if (aUsed !== bUsed) return aUsed - bUsed;
                        return a.name.localeCompare(b.name);
                    });

                    return data.rows.map(row => {
                        const optVal = $('<div/>').text(row.name).html();
                        const bgColor = row.color || '31708f';

                        return {
                            value: row.uuid,
                            label: row.name,
                            id: row.used > 0 ? row.uuid : undefined,
                            'data-content': row.used > 0
                                ? `<span><span class="badge badge-sm" style="background:#${bgColor};">${row.used}</span> ${optVal}</span>`
                                : undefined
                        };
                    });
                },
                false,
                function () {
                    if (currentSelection?.length) {
                        $("#category_filter").val(currentSelection).selectpicker('refresh');
                    }
                    categoryInitialized = true;
                },
                true
            );
        }

        $("#category_filter_container").detach().insertBefore('#{{ formGridDNatRule["table_id"] }}-header .search');
        $("#category_filter").on('changed.bs.select', function(){
            if (!categoryInitialized || reconfigureActInProgress) return;
            grid.bootgrid('reload');
        });

        $("#tree_toggle_container").detach().insertAfter("#category_filter_container");
        $('#toggle_tree_button').click(function() {
            treeViewEnabled = !treeViewEnabled;
            localStorage.setItem("dnat_tree", treeViewEnabled ? "1" : "0");
            $(this).toggleClass('active btn-primary', treeViewEnabled);
            $("#{{ formGridDNatRule['table_id'] }}").toggleClass("tree-enabled", treeViewEnabled);
            $("#tree_expand_container").toggle(treeViewEnabled);
            grid.bootgrid("reload");
        });

        $("#tree_expand_container").detach().insertAfter("#tree_toggle_container");
        $("#tree_expand_container").toggle(treeViewEnabled);
        $('#expand_tree_button').on('click', function () {
            const $table = $('#{{ formGridDNatRule["table_id"] }}');

            if ($table.find('.tabulator-data-tree-control-expand').length) {
                $table.find('.tabulator-data-tree-control-expand').trigger('click');
            } else {
                $table.find('.tabulator-data-tree-control-collapse').trigger('click');
            }
        });

        ajaxGet('/api/firewall/d_nat/list_network_select_options', [], function(data, status){
            if (data.single) {
                $(".net_selector").each(function(){
                    $(this).replaceInputWithSelector(data, $(this).hasClass('net_selector_multi'));
                    /* enforce single selection when "single host or network" or "any" are selected */
                    if ($(this).hasClass('net_selector_multi')) {
                        $("select[for='" + $(this).attr('id') + "']").on('shown.bs.select', function(){
                            $(this).data('previousValue', $(this).val());
                        }).change(function(){
                            const prev = Array.isArray($(this).data('previousValue')) ? $(this).data('previousValue') : [];
                            const is_single = $(this).val().includes('') || $(this).val().includes('any');
                            const was_single = prev.includes('') || prev.includes('any');
                            let refresh = false;
                            if (was_single && is_single && $(this).val().length > 1) {
                                $(this).val($(this).val().filter(value => !prev.includes(value)));
                                refresh = true;
                            } else if (is_single && $(this).val().length > 1) {
                                if ($(this).val().includes('any') && !prev.includes('any')) {
                                    $(this).val('any');
                                } else{
                                    $(this).val('');
                                }
                                refresh = true;
                            }
                            if (refresh) {
                                $(this).selectpicker('refresh');
                                $(this).trigger('change');
                            }
                            $(this).data('previousValue', $(this).val());
                        });
                    }
                });
            }
        });

        ajaxGet('/api/firewall/d_nat/list_port_select_options', [], function (data) {
            if (!data || !data.single) return;
            // local-port does not support port ranges, so we replace the label for clarity
            const singlePortOnly = $.extend(true, {}, data);
            singlePortOnly.single.label = "{{ lang._('Single port') }}";

            $(".port_selector").each(function () {
                const opts = $(this).is('#row_rule\\.local-port .port_selector')
                    ? singlePortOnly
                    : data;

                $(this).replaceInputWithSelector(opts, false);
            });
        });

        $('#category_filter').parent().find('.dropdown-toggle').prepend('<i class="fa fa-tag" style="margin-right: 6px;"></i>');

        $("#reconfigureAct").SimpleActionButton({
            onPreAction() {
                reconfigureActInProgress = true;
                return $.Deferred().resolve();
            },
            onAction(data, status) {
                Promise.all([
                    populateCategoriesSelectpicker()
                ])
                .finally(() => {
                    reconfigureActInProgress = false;
                });
            }
        });

        populateCategoriesSelectpicker();

    });
</script>

<style>
    .actions .dropdown-menu.pull-right {
        max-height: 200px;
        min-width: max-content;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .tooltip-inner {
        max-width: 600px;
        text-align: left;
    }
    #type_filter_container {
        float: left;
        margin-left: 5px;
    }
    #tree_toggle_container {
        float: left;
        margin-left: 5px;
    }
    #tree_expand_container {
        float: left;
        margin-left: 5px;
    }
    .badge.bg-info {
        background-color: #31708f !important;
    }
    .badge-sm {
        font-size: 12px;
        padding: 2px 5px;
    }
    .bucket-row {
        pointer-events: none;
    }
    .bucket-row .tabulator-cell {
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    .bucket-row .tabulator-cell[tabulator-field="category"] {
        overflow: visible !important;
        white-space: nowrap !important;
        text-overflow: clip !important;
    }
    .bucket-row .tabulator-data-tree-control,
    .bucket-row .tabulator-data-tree-control * {
        pointer-events: auto;
    }
    .row-no-select .tabulator-row-header input[type="checkbox"] {
        visibility: hidden;
        pointer-events: none;
    }
    .tree-enabled .tabulator-col.tabulator-row-header input[type="checkbox"] {
        visibility: hidden;
        pointer-events: none;
    }
    .row-disabled {
        opacity: 0.4;
    }
    #type_filter_container {
        float: none !important;
        flex: 1 1 150px;
        min-width: 0;
        max-width: 400px;
    }
    #type_filter_container .bootstrap-select {
        flex: 1 1 auto;
        min-width: 0;
    }
    .bootgrid-header .actionBar .btn-group {
        align-items: flex-start;
    }
    @media (max-width: 1024px) {
        #type_filter_container {
            flex: 1 1 100%;
            max-width: 100%;
            margin: 0;
        }

        #dialogFilterRule-header #tree_toggle_container,
        #dialogFilterRule-header #tree_expand_container {
            flex: 1 1 0;
            margin: 0;
        }
    }
</style>

<div class="tab-content content-box">
    <div class="hidden">
        <div id="category_filter_container" class="btn-group">
            <select id="category_filter" data-title="{{ lang._('Categories') }}"
                    class="selectpicker" data-live-search="true"
                    data-size="30" multiple data-container="body"></select>
        </div>

        <div id="tree_toggle_container" class="btn-group">
            <button id="toggle_tree_button" type="button" class="btn btn-default"
                    data-toggle="tooltip" title="{{ lang._('Show categories in a tree') }}">
                <i class="fa fa-fw fa-sitemap"></i> {{ lang._('Tree') }}
            </button>
        </div>

        <div id="tree_expand_container" class="btn-group">
            <button id="expand_tree_button" type="button" class="btn btn-default"
                    data-toggle="tooltip" title="{{ lang._('Expand/Collapse all') }}">
                <i class="fa fa-fw fa-angle-double-down"></i>
            </button>
        </div>
    </div>

    {{ partial('layout_partials/base_bootgrid_table', formGridDNatRule + {'command_width':'150'}) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/d_nat/apply'}) }}
{{ partial("layout_partials/base_dialog",{'fields':formDialogDNatRule,'id':formGridDNatRule['edit_dialog_id'],'label':lang._('Edit Destination Nat')}) }}
