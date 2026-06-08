{#
 # Copyright (c) 2025-2026 Deciso B.V.
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

        // Each NAT controller sets the entrypoint so they can all use this template
        const entrypoint = '{{ entrypoint }}';
        // XXX: Category keys differ in the individual models
        const category_key = '{{ categoryKey }}';

        function setupSnatModeForm() {
            if (entrypoint !== 'source_nat') {
                updateSnatModeUI();
                return;
            }
            mapDataToFormUI({
                'frm_dialogSNatMode': "/api/firewall/source_nat/get"
            }).done(function() {
                $('.selectpicker').selectpicker('refresh');
                updateSnatModeUI();
                $('#filter\\.general\\.snat_mode').change(function () {
                    $(document).trigger("settings-changed");
                });
            });
        }

        function updateSnatModeUI() {
            if (entrypoint !== 'source_nat') {
                $('#rule_grid_container').removeClass('snat-mode-hidden snat-mode-readonly');
                return;
            }

            const snatMode = $('#filter\\.general\\.snat_mode').val();
            const isDisabled = snatMode === 'disabled';
            const isReadonly = snatMode === 'automatic';

            $('#rule_grid_container').toggleClass('snat-mode-hidden', isDisabled);
            $('#rule_grid_container').toggleClass('snat-mode-readonly', isReadonly);
        }

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

        const storageKey = entrypoint + "_tree";
        let treeViewEnabled = localStorage.getItem(storageKey) === "1";
        $('#toggle_tree_button').toggleClass('active btn-primary', treeViewEnabled);

        const ruleTypeMap = {
            '100000': { label: "{{ lang._('Automatically generated rules') }}", icon: "fa-magic", tooltip: "{{ lang._('Automatically generated rules') }}", color: "text-secondary" },
            '400000': { label: "{{ lang._('Interface rules') }}", icon: "fa-ethernet", tooltip: "{{ lang._('Interface rule') }}", color: "text-info" },
            '500000': { label: "{{ lang._('Automatically generated rules') }}", icon: "fa-magic", tooltip: "{{ lang._('Automatically generated rules') }}", color: "text-secondary" },
        };

        const getRuleType = function(row) {
            return ruleTypeMap[row.prio_group] || null;
        };

        // Lives outside the grid, so the logic of the response handler can be changed after grid initialization
        function dynamicResponseHandler(response) {
            const getCategoryLabel = function(row) {
                return row["%categories"] || row.categories || "";
            };

            const makeBucket = function(label, uuid, categoryColors) {
                return {
                    // ensure uuid is as unique as possible for persistence handling
                    uuid           : uuid,
                    isGroup        : true,
                    _label         : label,          // internal
                    categories     : label,
                    /*
                    * Bucket rows reuse the category formatter.
                    * For category buckets, this copies the first child's category metadata
                    * so the bucket can render the same category icon/color as its rules.
                    * For rule type buckets, a synthetic categoryColors entry is supplied.
                    */
                    category_colors: categoryColors,
                    children       : []
                };
            };

            const createBucket = function(parent, label, uuid, categoryColors) {
                let bucket = parent.children.find(child => child.isGroup && child._label === label);

                if (!bucket) {
                    bucket = makeBucket(label, uuid, categoryColors);
                    parent.children.push(bucket);
                }

                return bucket;
            };

            const root = { children: [] };

            response.rows.forEach(row => {
                const ruleType = getRuleType(row);
                const ruleTypeKey = row.prio_group || "other";
                const ruleTypeLabel = ruleType?.label || "{{ lang._('Other rules') }}";
                const categoryLabel = getCategoryLabel(row);

                /*
                * The first tree level is the default view, and always based on the rule priority/type.
                *
                * Automatic rules
                *   rule
                * Interface rules
                *   rule
                */
                const ruleTypeBucket = createBucket(
                    root,
                    ruleTypeLabel,
                    `ruletype${ruleTypeKey}`,
                    [{ name: ruleTypeLabel }]
                );

                if (treeViewEnabled && row.is_automatic !== true && categoryLabel !== "") {
                    /*
                    * When tree view is enabled, categorized non-automatic rules are grouped
                    * one level deeper by category below their rule priority/type bucket.
                    *
                    * Automatic rules and uncategorized rules stay directly below their rule
                    * priority/type bucket to avoid redundant or low-value nesting.
                    *
                    * Automatic rules
                    *   rule
                    * Interface rules
                    *   rule
                    *   Web (Category)
                    *     rule
                    *   Mail (Category)
                    *     rule
                    */
                    const categoryBucket = createBucket(
                        ruleTypeBucket,
                        categoryLabel,
                        `ruletype${ruleTypeKey}category${String(categoryLabel).replace(/[^a-z0-9]/gi, '')}`,
                        row.category_colors || []
                    );

                    categoryBucket.children.push(row);
                } else {
                    ruleTypeBucket.children.push(row);
                }
            });

            return Object.assign({}, response, { rows: root.children });
        }

        const grid = $("#{{ formGridRule['table_id'] }}").UIBootgrid({
            search : '/api/firewall/' + entrypoint + '/search_rule/',
            get    : '/api/firewall/' + entrypoint + '/get_rule/',
            set    : '/api/firewall/' + entrypoint + '/set_rule/',
            add    : '/api/firewall/' + entrypoint + '/add_rule/',
            del    : '/api/firewall/' + entrypoint + '/del_rule/',
            toggle : '/api/firewall/' + entrypoint + '/toggle_rule/',
            tabulatorOptions : {
                dataTree              : true,
                dataTreeChildField    : "children",
                dataTreeElementColumn : category_key,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const $element = $(row.getElement());

                    // XXX: d_nat model provides a disabled key
                    if (
                        ('enabled' in data && data.enabled == "0") ||
                        ('disabled' in data && data.disabled == "1")
                    ) {
                        $element.addClass('row-disabled');
                    }

                    if (data.isGroup || !data.uuid || !data.uuid.includes("-")) {
                        $element.addClass('row-no-select');
                        $element.removeClass('tabulator-selectable');
                    }

                    if (data.isGroup) {
                        $element.addClass('bucket-row');
                    }
                },
                selectableRowsCheck: function(row) {
                    const data = row.getData();
                    return !(data.isGroup || !data.uuid || !data.uuid.includes("-"));
                }
            },
            options: {
                responsive: true,
                sorting: false,
                initialSearchPhrase: getUrlHash('search'),
                requestHandler: function(request){
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    return request;
                },
                responseHandler: dynamicResponseHandler,
                headerFormatters: {
                    // XXX: This cannot be (easily) dynamically decided, so some keys are duplicate for simplicity
                    enabled: function (column) {
                        return '<i class="fa-solid fa-fw fa-check-square" data-toggle="tooltip" title="{{ lang._('Enabled') }}"></i>';;
                    },
                    disabled: function (column) {
                        return '<i class="fa-solid fa-fw fa-check-square" data-toggle="tooltip" title="{{ lang._('Enabled') }}"></i>';;
                    },
                    interface: function (column) {
                        return '<i class="fa-solid fa-fw fa-network-wired" data-toggle="tooltip" title="{{ lang._('Network interface') }}"></i>';
                    },
                    categories: function (column) {
                        return '<i class="fa-solid fa-fw fa-tag" data-toggle="tooltip" title="{{ lang._("Category") }}"></i>';
                    },
                    category: function (column) {
                        return '<i class="fa-solid fa-fw fa-tag" data-toggle="tooltip" title="{{ lang._("Category") }}"></i>';
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
                                <a href="/system_advanced_firewall.php" target="_blank" rel="noopener noreferrer"
                                class="btn btn-xs btn-default bootgrid-tooltip"
                                title="{{ lang._('Lookup rule reference') }}">
                                    <span class="fa fa-fw fa-link"></span>
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
                                <i class="fa fa-fw ${row.log == '1' ? 'fa-bell' : 'fa-bell-slash'}"></i>
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
                        const isEnabled =
                            entrypoint === 'd_nat'  /* flag is inverted in model */
                                ? row[column.id] === "0"
                                : row[column.id] === "1";
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
                        const hasCategories = row.categories && Array.isArray(row.category_colors);

                        // Rows without category metadata render nothing in this column.
                        // This also avoids creating a fake label for rules that
                        // are intentionally kept directly below their rule type bucket.
                        if (!hasCategories) {
                            return '';
                        }

                        const categories = row.category_colors || [];

                        const icons = categories.map(cat => {
                            /*
                            * Top-level tree icons, e.g. automatic/floating/interface rules, are
                            * resolved here as well because each row can only use one formatter for
                            * this column. Rule type buckets provide a synthetic category entry
                            * whose name matches ruleTypeMap, while real category buckets continue
                            * to render normal category tag icons.
                            */
                            const ruleType = Object.values(ruleTypeMap).find(type => type.label === cat.name);

                            if (isGroup && ruleType) {
                                return `
                                    <span class="category-icon" data-toggle="tooltip" title="${ruleType.tooltip}">
                                        <i class="fa ${ruleType.icon} fa-fw ${ruleType.color}"></i>
                                    </span>`;
                            }

                            const bgColor = cat.color ? ` style="color:${cat.color};"` : '';

                            return `
                                <span class="category-icon" data-toggle="tooltip" title="${cat.name}">
                                    <i class="fa fa-fw fa-tag"${bgColor}></i>
                                </span>`;
                        }).join(' ');

                        return isGroup
                            ? `<span class="category-cell">
                                    <span class="category-cell-content">
                                        <strong>${icons} ${categories.map(cat => cat.name).join(', ')}</strong>
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
                        // DNAT uses network, SNAT and ONAT uses net
                        const isNegated = (row[column.id.replace(/network|net/, 'not')] == 1) ? "! " : "";

                        if (typeof value !== 'string') {
                            return '';
                        } else if (column.id === "local-port") {
                            // DNAT: mirror destination port into local-port for better visibility
                            return (!row["local-port"] ? row["destination.port"] : row["local-port"]) || "*";
                        } else if (!value || value === "any") {
                            return isNegated + '*';
                        }

                        const aliasMetadataList = row["alias_meta_" + column.id] || [];

                        const renderedItems = aliasMetadataList.map(aliasInfo => {
                            if (aliasInfo.isAlias) {
                                const tooltipHtml = aliasInfo.summary || aliasInfo.description || aliasInfo.value || "";
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
                upload_rules: {
                    onRendered: function () {
                        const $el = $(this);
                        $el.data('title', "{{ lang._('Import rules') }}");
                        $el.data('endpoint', `/api/firewall/${entrypoint}/upload_rules`);
                        $el.SimpleFileUploadDlg({
                            onAction: function () {
                                $("#{{formGridRule['table_id']}}").bootgrid('reload');
                                $(document).trigger("settings-changed");
                            }
                        });
                    },
                    footer: true,
                    classname: 'fa fa-fw fa-upload',
                    title: "{{ lang._('Import csv') }}",
                    sequence: 400
                },
                download_rules: {
                    footer: true,
                    classname: 'fa fa-fw fa-table',
                    title: "{{ lang._('Export as csv') }}",
                    method: function (e) {
                        e.preventDefault();
                        window.open(`/api/firewall/${entrypoint}/download_rules`);
                    },
                    sequence: 500
                },
                move_before: {
                    method: function(event) {
                        const selected = $("#{{ formGridRule['table_id'] }}").bootgrid("getSelectedRows");
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
                            "/api/firewall/" + entrypoint + "/move_rule_before/" + selectedUuid + "/" + targetUuid,
                            {},
                            function(data, status) {
                                if (data.status === "ok") {
                                    $("#{{ formGridRule['table_id'] }}").bootgrid("reload");
                                    $(document).trigger("settings-changed");
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
                            `/api/firewall/${entrypoint}/toggle_rule_log/${uuid}/${log}`,
                            {},
                            function(data) {
                                if (data.status === "ok") {
                                    $("#{{ formGridRule['table_id'] }}").bootgrid("reload");
                                    $(document).trigger("settings-changed");
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
                '/api/firewall/' + entrypoint + '/list_categories',
                {},
                function (data) {
                    if (!data.rows) return [];

                    return data.rows.map(row => {
                        const optVal = $('<div/>').text(row.name).html();
                        const bgColor = row.color ? ` style="background:#${row.color};"` : '';

                        return {
                            value: row.uuid,
                            label: row.name,
                            id: row.used > 0 ? row.uuid : undefined,
                            'data-content': row.used > 0
                                ? `<span>${optVal} <span class="label label-sm"${bgColor}>${row.used}</span></span>`
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

        $("#category_filter_container").detach().insertBefore('#{{ formGridRule["table_id"] }}-header .search');
        $("#category_filter").on('changed.bs.select', function(){
            if (!categoryInitialized || reconfigureActInProgress) return;
            grid.bootgrid('reload');
        });

        $("#tree_toggle_container").detach().insertAfter("#category_filter_container");
        $('#toggle_tree_button').click(function() {
            treeViewEnabled = !treeViewEnabled;
            localStorage.setItem(storageKey, treeViewEnabled ? "1" : "0");
            $(this).toggleClass('active btn-primary', treeViewEnabled);
            $("#{{ formGridRule['table_id'] }}").toggleClass("tree-enabled", treeViewEnabled);
            grid.bootgrid("reload");
        });

        $("#tree_expand_container").detach().insertAfter("#tree_toggle_container");
        $("#tree_expand_container").show();
        $('#expand_tree_button').on('click', function () {
            const $table = $('#{{ formGridRule["table_id"] }}');

            if ($table.find('.tabulator-data-tree-control-expand').length) {
                $table.find('.tabulator-data-tree-control-expand').trigger('click');
            } else {
                $table.find('.tabulator-data-tree-control-collapse').trigger('click');
            }
        });

        ajaxGet('/api/firewall/' + entrypoint + '/list_network_select_options', [], function(data, status){
            if (!data || !data.single) return;
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
        });

        ajaxGet('/api/firewall/' + entrypoint + '/list_port_select_options', [], function (data) {
            if (!data || !data.single) return;
            // local-port in DNAT does not support port ranges, so we replace the label for clarity
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
                if (entrypoint !== 'source_nat') {
                    return $.Deferred().resolve();
                }
                const dfObj = new $.Deferred();
                saveFormToEndpoint(
                    "/api/firewall/source_nat/set_general",
                    "frm_dialogSNatMode",
                    function() {
                        dfObj.resolve();
                    },
                    true,
                    function() {
                        reconfigureActInProgress = false;
                        dfObj.reject();
                    }
                );
                return dfObj.promise();
            },
            onAction(data, status) {
                Promise.all([
                    populateCategoriesSelectpicker()
                ])
                .finally(() => {
                    reconfigureActInProgress = false;
                    // The search endpoint has different responses based on selected snat_mode
                    updateSnatModeUI();
                    $("#{{formGridRule['table_id']}}").bootgrid('reload');
                });
            }
        });

        setupSnatModeForm();  // All NAT pages have to call this to unhide the shared grid
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
    .label.label-sm {
        display: inline-flex;
        align-items: center;
        height: 18px;
        padding: 0 6px;
        border-radius: 50%;
    }
    .bucket-row {
        pointer-events: none;
    }
    .bucket-row .tabulator-cell {
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    .bucket-row .tabulator-cell {
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

        #dialogRule-header #tree_toggle_container,
        #dialogRule-header #tree_expand_container {
            flex: 1 1 0;
            margin: 0;
        }
    }
    .snat-mode-hidden {
        display: none;
    }
    .snat-mode-readonly [class*="command-"] {
        display: none;
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
                    data-toggle="tooltip" title="{{ lang._('Show categories as folders') }}">
                <i class="fa fa-fw fa-tag" aria-hidden="true"></i>
            </button>
        </div>

        <div id="tree_expand_container" class="btn-group">
            <button id="expand_tree_button" type="button" class="btn btn-default"
                    data-toggle="tooltip" title="{{ lang._('Expand/Collapse all') }}">
                <i class="fa fa-fw fa-angle-double-down"></i>
            </button>
        </div>
    </div>
    {% if entrypoint == 'source_nat' %}
        {{ partial("layout_partials/base_form", ['fields': formSnatMode, 'id': 'frm_dialogSNatMode']) }}
    {% endif %}
    <div id="rule_grid_container" class="snat-mode-hidden">
        {{ partial('layout_partials/base_bootgrid_table', formGridRule + {'command_width':'150'}) }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/' ~ entrypoint ~ '/apply'}) }}
{{ partial("layout_partials/base_dialog",{'fields':formDialogRule,'id':formGridRule['edit_dialog_id'],'label':lang._('Edit Rule')}) }}
