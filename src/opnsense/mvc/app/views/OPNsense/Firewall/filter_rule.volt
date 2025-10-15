{#
 # Copyright (c) 2020-2025 Deciso B.V.
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
        // Show errors in modal
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

        // Get all advanced fields, used for advanced mode tooltips
        const advancedFieldIds = "{{ advancedFieldIds }}".split(',');

        // Get all column labels, used for advanced mode tooltips
        const columnLabels = {};
        $('#{{formGridFilterRule["table_id"]}} thead th').each(function () {
            const columnId = $(this).attr('data-column-id');
            if (columnId) {
                columnLabels[columnId] = $(this).text().trim();
            }
        });

        // Inspect and Tree are disabled by default
        let treeViewEnabled = localStorage.getItem("firewall_rule_tree") === "1";
        $('#toggle_tree_button').toggleClass('active btn-primary', treeViewEnabled);

        let inspectEnabled = localStorage.getItem("firewall_rule_inspect") === "1";
        $('#toggle_inspect_button').toggleClass('active btn-primary', inspectEnabled);

        function updateStatisticColumns() {
            grid.bootgrid(inspectEnabled ? "setColumns" : "unsetColumns", ['statistics']);
        }

        // read interface from URL hash once, for the first grid load
        const hashMatchInterface = window.location.hash.match(/(?:^#|&)interface=([^&]+)/);
        let pendingUrlInterface = hashMatchInterface ? decodeURIComponent(hashMatchInterface[1]) : null;

        // Lives outside the grid, so the logic of the response handler can be changed after grid initialization
        function dynamicResponseHandler(resp) {
            // convert the flat rows into a tree view (if enabled)
            if (!treeViewEnabled) {
                return resp;
            }

            const buckets = [];
            let current = null;

            resp.rows.forEach(r => {
                // readable label used for grouping
                const label = (r["%categories"] || r.categories || "");

                // start a new bucket whenever the label changes
                if (!current || current._label !== label) {
                    current = {
                        // ensure uuid is as unique as possible for persistence handling
                        uuid           : `${String(r.uuid).replace(/-/g, '')}`,
                        isGroup        : true,
                        _label         : label,          // internal
                        children       : []
                    };

                    // copy the category info from the first child to use as parent
                    current.categories      = label;
                    current.category_colors = r.category_colors || [];

                    buckets.push(current);
                }

                current.children.push(r);
            });

            return Object.assign({}, resp, { rows: buckets });
        }

        // Initialize grid
        const grid = $("#{{formGridFilterRule['table_id']}}").UIBootgrid({
            search:'/api/firewall/filter/search_rule/',
            get:'/api/firewall/filter/get_rule/',
            set:'/api/firewall/filter/set_rule/',
            add:'/api/firewall/filter/add_rule/',
            del:'/api/firewall/filter/del_rule/',
            toggle:'/api/firewall/filter/toggle_rule/',
            tabulatorOptions : {
                // tell Tabulator to render a tree
                dataTree              : true,
                dataTreeChildField    : "children",
                dataTreeElementColumn : "categories",
                rowFormatter: function(row) {
                    const data = row.getData();
                    const $element = $(row.getElement());

                    // opacity when rule is disabled
                    if ('enabled' in data && data.enabled == "0") {
                        $element.addClass('row-disabled');
                    }

                    // hide the row selection checkbox for internal and dataTree group rules
                    if (data.isGroup || !data.uuid || !data.uuid.includes("-")) {
                        $element.addClass('row-no-select');
                    }

                    // bucket row (dataTree) styling
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
                    // Add category selectpicker
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    // Add interface selectpicker, or fall back to hash for the first load
                    let selectedInterface = $('#interface_select').val();
                    if ((!selectedInterface || selectedInterface.length === 0) && pendingUrlInterface) {
                        request['interface'] = pendingUrlInterface;
                        pendingUrlInterface = null; // consume the hash so it is not used again
                    } else if (selectedInterface && selectedInterface.length > 0) {
                        request['interface'] = selectedInterface;
                    }
                    if (inspectEnabled) {
                        // Send as a comma separated string
                        request['show_all'] = true;
                    }
                    return request;
                },
                // convert the flat rows into a tree view
                responseHandler: dynamicResponseHandler,

                headerFormatters: {
                    enabled: function (column) {
                        return '<i class="fa-solid fa-fw fa-check-square" data-toggle="tooltip" title="{{ lang._('Enabled') }}"></i>';;
                    },
                    interface: function (column) {
                        return '<i class="fa-solid fa-fw fa-network-wired" data-toggle="tooltip" title="{{ lang._('Network interface') }}"></i>';
                    },
                    evaluations: function (column) {
                        return '<i class="fa-solid fa-fw fa-bullseye" data-toggle="tooltip" title="{{ lang._('Number of rule evaluations') }}"></i>';
                    },
                    states: function (column) {
                        return '<i class="fa-solid fa-fw fa-chart-line" data-toggle="tooltip" title="{{ lang._('Current active states for this rule') }}"></i>';
                    },
                    packets: function (column) {
                        return '<i class="fa-solid fa-fw fa-box" data-toggle="tooltip" title="{{ lang._('Total packets matched by this rule') }}"></i>';
                    },
                    bytes: function (column) {
                        return '<i class="fa-solid fa-fw fa-database" data-toggle="tooltip" title="{{ lang._('Total bytes matched by this rule') }}"></i>';
                    },
                    categories: function (column) {
                        return '<i class="fa-solid fa-fw fa-tag" data-toggle="tooltip" title="{{ lang._("Categories") }}"></i>';
                    },
                    statistics: function () {
                        const element = $(`
                            <span class="stats-header-icons">
                                <span data-toggle="tooltip" title="{{ lang._('Statistics') }}">
                                    <i class="fa-solid fa-fw fa-eye"></i>
                                </span>
                                <span class="inspect-cache-flush"
                                    style="cursor:pointer; margin-left:4px;"
                                    data-toggle="tooltip"
                                    title="{{ lang._('Refresh') }}">
                                    <i class="fa-solid fa-fw fa-rotate-right"></i>
                                </span>
                            </span>
                        `);

                        element.find('.inspect-cache-flush').on('click', function () {
                            ajaxCall(
                                '/api/firewall/filter/flush_inspect_cache',
                                {},
                                function () {
                                    $('#{{ formGridFilterRule["table_id"] }}').bootgrid('reload');
                                },
                                null,
                                'POST'
                            );
                        });

                        return element[0];
                    },
                },
                formatters:{
                    // Only show command buttons for rules that have a uuid, internal rules will not have one
                    commands: function (column, row) {
                        // All formatters except category must skip processing bucket rows in tree view
                        if (row.isGroup) {
                            return "";
                        }
                        let rowId = row.uuid;

                        // If UUID is invalid, its an internal rule, use the #ref field to show a lookup button.
                        if (!rowId || !rowId.includes('-')) {
                            let ref = row["ref"] || "";
                            if (ref.trim().length > 0) {
                                let url = `/${ref}`;
                                return `
                                    <a href="${url}"
                                    class="btn btn-xs btn-default bootgrid-tooltip"
                                    title="{{ lang._('Lookup Rule') }}">
                                        <span class="fa fa-fw fa-search"></span>
                                    </a>
                                `;
                            }
                            // If ref is empty
                            return "";
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
                                <i class="fa fa-exclamation-circle fa-fw ${row.log == '1' ? 'text-info' : 'text-muted'}"></i>
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
                    // Disable rowtoggle for internal rules
                    rowtoggle: function (column, row) {
                        if (row.isGroup) {
                            return "";
                        }

                        const rowId = row.uuid || '';
                        if (!rowId.includes('-')) {
                            return '';
                        }

                        const isEnabled = row[column.id] === "1";

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
                            return row[column.id];
                        } else {
                            return '*';
                        }
                    },
                    // The category formatter is special as it renders differently for the bucket row
                    category: function (column, row) {
                        const isGroup = row.isGroup;
                        const hasCategories = row.categories && Array.isArray(row.category_colors);

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

                        const categories = (row["%categories"] || row.categories).split(',');
                        const colors     = row.category_colors;

                        const icons = categories.map((cat, idx) => `
                            <span class="category-icon" data-toggle="tooltip" title="${cat}">
                                <i class="fa fa-fw fa-tag" style="color:${colors[idx]};"></i>
                            </span>`).join(' ');

                        return isGroup
                            ? `<span class="category-cell">
                                    <span class="category-cell-content">
                                        <strong>${icons} ${categories.join(', ')}</strong>
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

                        // Only single interfaces can be negated
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
                    // Icons
                    ruleIcons: function(column, row) {
                        if (row.isGroup) {
                            return "";
                        }

                        let result = "";

                        // Rule Type Icons (Determined by first digit of sort_order)
                        const ruleTypeIcons = {
                            '0': { icon: "fa-magic", tooltip: "{{ lang._('Automatic Rule') }}", color: "text-secondary" },
                            '1': { icon: "fa-magic", tooltip: "{{ lang._('Automatic Rule') }}", color: "text-secondary" },
                            '2': { icon: "fa-layer-group", tooltip: "{{ lang._('Floating Rule') }}", color: "text-primary" },
                            '3': { icon: "fa-sitemap", tooltip: "{{ lang._('Group Rule') }}", color: "text-warning" },
                            '4': { icon: "fa-ethernet", tooltip: "{{ lang._('Interface Rule') }}", color: "text-info" },
                            '5': { icon: "fa-magic", tooltip: "{{ lang._('Automatic Rule') }}", color: "text-secondary" },
                        };

                        const sortOrder = row.sort_order ? row.sort_order.toString() : "";
                        if (sortOrder.length > 0) {
                            const typeDigit = sortOrder.charAt(0);
                            if (ruleTypeIcons[typeDigit]) {
                                result += `<i class="fa ${ruleTypeIcons[typeDigit].icon} fa-fw ${ruleTypeIcons[typeDigit].color}"
                                            data-toggle="tooltip" title="${ruleTypeIcons[typeDigit].tooltip}"></i> `;
                            }
                        }

                        // Action
                        if (row.action.toLowerCase() === "block") {
                            result += '<i class="fa fa-times fa-fw text-danger" data-toggle="tooltip" title="{{ lang._("Block") }}"></i> ';
                        } else if (row.action.toLowerCase() === "reject") {
                            result += '<i class="fa fa-times-circle fa-fw text-danger" data-toggle="tooltip" title="{{ lang._("Reject") }}"></i> ';
                        } else {
                            result += '<i class="fa fa-play fa-fw text-success" data-toggle="tooltip" title="{{ lang._("Pass") }}"></i> ';
                        }

                        // Direction
                        if (row.direction.toLowerCase() === "in") {
                            result += '<i class="fa fa-long-arrow-right fa-fw text-info" data-toggle="tooltip" title="{{ lang._("In") }}"></i> ';
                        } else if (row.direction.toLowerCase() === "out") {
                            result += '<i class="fa fa-long-arrow-left fa-fw" data-toggle="tooltip" title="{{ lang._("Out") }}"></i> ';
                        } else {
                            result += '<i class="fa fa-exchange fa-fw" data-toggle="tooltip" title="{{ lang._("Any") }}"></i> ';
                        }

                        // Quick match
                        if (row.quick == 0) {
                            result += '<i class="fa fa-flash fa-fw text-muted" data-toggle="tooltip" title="{{ lang._("Last match") }}"></i> ';
                        } else {
                            result += '<i class="fa fa-flash fa-fw text-warning" data-toggle="tooltip" title="{{ lang._("First match") }}"></i> ';
                        }

                        // XXX: Advanced fields all have different default values, so it cannot be generalized completely
                        const advancedDefaultPrefixes = ["0", "none", "any", "default", "keep"];
                        const usedAdvancedFields = [];

                        advancedFieldIds.forEach(function (fieldId) {
                            const value = row[fieldId];
                            if (value !== undefined) {
                                const lowerValue = value.toString().toLowerCase().trim();
                                // Check: if the value is empty OR starts with any default prefix, consider it default
                                const isDefault = (lowerValue === "") || advancedDefaultPrefixes.some(function(prefix) {
                                    return lowerValue.startsWith(prefix);
                                });

                                if (!isDefault) {
                                    // Use label if available, otherwise fallback to field ID
                                    const label = columnLabels[fieldId] || fieldId;
                                    usedAdvancedFields.push(`${label}: ${value}`);
                                }
                            }
                        });

                        let iconClass;
                        let tooltip;
                        if (usedAdvancedFields.length > 0) {
                            iconClass = "text-warning";
                            tooltip = `{{ lang._("Advanced mode enabled") }}<br>${usedAdvancedFields.join("<br>")}`;
                        } else {
                            iconClass = "text-muted";
                            tooltip = "{{ lang._('Advanced mode disabled') }}";
                        }

                        result += `<i class="fa fa-cog fa-fw ${iconClass}" data-toggle="tooltip" data-html="true" title="${tooltip}"></i>`;

                        // Return all icons
                        return result;
                    },
                    // Show Edit alias icon, alias description and integrate "not" functionality
                    alias: function(column, row) {
                        if (row.isGroup) {
                            return "";
                        }

                        const value = row[column.id] || "";
                        const isNegated = (row[column.id.replace('net', 'not')] == 1) ? "! " : "";

                        // Internal rule source/destination can be an object, skip them
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
                            // Not an alias, return translated value
                            return aliasInfo["%value"];
                        }).join(", ");

                        // There can only be a single negated value
                        return isNegated + renderedItems;
                    },
                    statistics: function(column, row) {
                        if (row.isGroup || !inspectEnabled) {
                            return "";
                        }

                        const evals   = row["evaluations"] ?? "";
                        const states  = row["states"] ?? "";
                        const packets = row["packets"] ?? "";
                        const bytes   = row["bytes"] ?? "";

                        function render(icon, title, value, is_number = false) {
                            if (!value || value === "0") {
                                return "";
                            }

                            const numValue  = parseInt(value, 10);
                            const formatted = byteFormat(numValue, 1, is_number);

                            return `
                                <span data-toggle="tooltip" title="${title}: ${numValue.toLocaleString()}">
                                    <i class="fa fa-fw ${icon} text-muted"></i> ${formatted}
                                </span>
                            `;
                        }

                        const parts = [
                            render("fa-bullseye", "{{ lang._('Evaluations') }}", evals, true),
                            render("fa-chart-line", "{{ lang._('States') }}", states, true),
                            render("fa-box", "{{ lang._('Packets') }}", packets, true),
                            render("fa-database", "{{ lang._('Bytes') }}", bytes)
                        ].filter(Boolean);

                        if (parts.length === 0) {
                            return "";
                        }

                        // Split into two vertical rows
                        const firstGroup  = parts.slice(0, 2).join(" ");
                        const secondGroup = parts.slice(2).join(" ");

                        return `
                            <div class="stats-cell">
                                <div>${firstGroup}</div>
                                <div>${secondGroup}</div>
                            </div>
                        `;
                    },
                },
            },
            commands: {
                move_before: {
                    method: function(event) {
                        // Ensure exactly one rule is selected to be moved
                        const selected = $("#{{ formGridFilterRule['table_id'] }}").bootgrid("getSelectedRows");
                        if (selected.length !== 1) {
                            showDialogAlert(
                                BootstrapDialog.TYPE_WARNING,
                                "{{ lang._('Selection Error') }}",
                                "{{ lang._('Please select exactly one rule to move.') }}"
                            );
                            return;
                        }

                        // The rule the user selected
                        const selectedUuid = selected[0];
                        // The rule the button was pressed on
                        const targetUuid = $(this).data("row-id");

                        // Prevent moving a rule before itself
                        if (selectedUuid === targetUuid) {
                            showDialogAlert(
                                BootstrapDialog.TYPE_WARNING,
                                "{{ lang._('Move Error') }}",
                                "{{ lang._('Cannot move a rule before itself.') }}"
                            );
                            return;
                        }

                        ajaxCall(
                            "/api/firewall/filter/move_rule_before/" + selectedUuid + "/" + targetUuid,
                            {},
                            function(data, status) {
                                if (data.status === "ok") {
                                    $("#{{ formGridFilterRule['table_id'] }}").bootgrid("reload");
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
                            `/api/firewall/filter/toggle_rule_log/${uuid}/${log}`,
                            {},
                            function(data) {
                                if (data.status === "ok") {
                                    $("#{{ formGridFilterRule['table_id'] }}").bootgrid("reload");
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

        grid.on('loaded.rs.jquery.bootgrid', function () {
            updateStatisticColumns(); // ensures inspect columns are consistent after reload
        });

        // Track if user has actually changed a dropdown, or it was the controller
        let interfaceInitialized = false;
        let categoryInitialized = false;

        // Do not reload the grid when selectpickers get refreshed during the reconfigureAct
        let reconfigureActInProgress = false;

        // Populate category selectpicker
        function populateCategoriesSelectpicker() {
            const currentSelection = $("#category_filter").val();

            return $("#category_filter").fetch_options(
                '/api/firewall/filter/list_categories',
                {},
                function (data) {
                    if (!data.rows) return [];

                    // Sort used categories first, then alphabetically
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
                true  // render_html
            );
        }

        // Populate interface selectpicker
        function populateInterfaceSelectpicker() {
            return $('#interface_select').fetch_options(
                '/api/firewall/filter/get_interface_list',
                {},
                function (data) {
                    for (const groupKey in data) {
                        const group = data[groupKey];
                        group.items = group.items.map(item => {
                            const count = item.count ?? 0;
                            const label = (item.label || '');
                            const subtext = group.label;

                            const bgClassMap = {
                                floating: 'bg-primary',
                                group: 'bg-warning',
                                interface: 'bg-info'
                            };
                            const badgeClass = bgClassMap[item.type] || 'bg-info';

                            return {
                                value: item.value,
                                label: label,
                                'data-content': `
                                    <span>
                                        ${count > 0 ? `<span class="badge badge-sm ${badgeClass}">${count}</span>` : ''}
                                        ${label}
                                        <small class="text-muted ms-2"><em>${subtext}</em></small>
                                    </span>
                                `.trim()
                            };
                        });
                    }
                    return data;
                },
                false,
                function (data) {  // post_callback, apply the URL hash logic
                    const match = window.location.hash.match(/^#interface=([^&]+)/);
                    if (match) {
                        const ifaceFromHash = decodeURIComponent(match[1]);

                        const allOptions = Object.values(data).flatMap(group => group.items.map(i => i.value));
                        if (allOptions.includes(ifaceFromHash)) {
                            $('#interface_select').val(ifaceFromHash).selectpicker('refresh');
                        }
                    }
                    interfaceInitialized = true;

                },
                true  // render_html to show counts as badges
            );
        }

        $("#interface_select_container").show();

        // move selectpickers into action bar
        $("#interface_select_container").detach().insertBefore('#{{formGridFilterRule["table_id"]}}-header .search');
        $('#interface_select').on('changed.bs.select', function () {
            // Skip grid reload during reconfigureAct and initial page load
            if (!interfaceInitialized || reconfigureActInProgress) return;

            const hashVal = encodeURIComponent($(this).val() ?? '');
            history.replaceState(null, null, `#interface=${hashVal}`);
            grid.bootgrid('reload');
        });

        $("#type_filter_container").detach().insertAfter("#interface_select_container");
        $("#category_filter").on('changed.bs.select', function(){
            // Skip grid reload during reconfigureAct and initial page load
            if (!categoryInitialized || reconfigureActInProgress) return;
            grid.bootgrid('reload');
        });

        $("#inspect_toggle_container").detach().insertAfter("#type_filter_container");
        $('#toggle_inspect_button').click(function () {
            inspectEnabled = !inspectEnabled;
            localStorage.setItem("firewall_rule_inspect", inspectEnabled ? "1" : "0");
            $(this).toggleClass('active btn-primary', inspectEnabled);
            updateStatisticColumns();
            grid.bootgrid("reload");
        });

        $("#tree_toggle_container").detach().insertAfter("#inspect_toggle_container");
        $('#toggle_tree_button').click(function () {
            treeViewEnabled = !treeViewEnabled;
            localStorage.setItem("firewall_rule_tree", treeViewEnabled ? "1" : "0");
            $(this).toggleClass('active btn-primary', treeViewEnabled);
            $("#{{formGridFilterRule['table_id']}}").toggleClass("tree-enabled", treeViewEnabled);
            $("#tree_expand_container").toggle(treeViewEnabled);
            grid.bootgrid("reload");
        });

        // Visible only when tree view is enabled
        $("#tree_expand_container").detach().insertAfter("#tree_toggle_container");
        $("#tree_expand_container").toggle(treeViewEnabled);
        $('#expand_tree_button').on('click', function () {
            const $table = $('#{{ formGridFilterRule["table_id"] }}');

            // If there are any collapsed controls, expand them all, otherwise collapse them all
            if ($table.find('.tabulator-data-tree-control-expand').length) {
                $table.find('.tabulator-data-tree-control-expand').trigger('click');
            } else {
                $table.find('.tabulator-data-tree-control-collapse').trigger('click');
            }
        });

        // replace all "net" selectors with details retrieved from "list_network_select_options" endpoint
        ajaxGet('/api/firewall/filter/list_network_select_options', [], function(data, status){
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

        // replace all "port" selectors with details retrieved from "list_port_select_options" endpoint
        ajaxGet('/api/firewall/filter/list_port_select_options', [], function (data) {
            if (!data || !data.single) return;
            $(".port_selector").each(function () {
                $(this).replaceInputWithSelector(data, false);
            });
        });

        // Hook into add event
        $('#{{formGridFilterRule["edit_dialog_id"]}}').on('opnsense_bootgrid_mapped', function(e, actionType) {
            if (actionType === 'add') {
                // and choose same interface in new rule as selected in #interface_select
                const selectedInterface = $('#interface_select').val();
                if (selectedInterface) {
                    $('#rule\\.interface').selectpicker('val', [selectedInterface]);
                    $('#rule\\.interface').selectpicker('refresh');
                }
                // and do the same with category selection (supports multiple)
                const selectedCategories = $('#category_filter').val();
                if (selectedCategories && selectedCategories.length > 0) {
                    let categorySelect = $('#rule\\.categories');

                    categorySelect.tokenize2().trigger('tokenize:clear');

                    selectedCategories.forEach(function(categoryUUID) {
                        let categoryLabel = $('#rule\\.categories option[value="' + categoryUUID + '"]').text();
                        categorySelect.tokenize2().trigger('tokenize:tokens:add', [categoryUUID, categoryLabel]);
                    });
                }
            }
        });

        // Dynamically add fa icons to selectpickers
        $('#category_filter').parent().find('.dropdown-toggle').prepend('<i class="fa fa-tag" style="margin-right: 6px;"></i>');

        $("#reconfigureAct").SimpleActionButton({
            onPreAction() {
                reconfigureActInProgress = true;
                return $.Deferred().resolve();
            },
            onAction(data, status) {
                Promise.all([
                    populateInterfaceSelectpicker(),
                    populateCategoriesSelectpicker()
                ])
                .finally(() => {
                    reconfigureActInProgress = false;
                });
            }
        });

        populateInterfaceSelectpicker();
        populateCategoriesSelectpicker();

    });
</script>

<style>
    /* The filter rules column dropdown has many items */
    .actions .dropdown-menu.pull-right {
        max-height: 200px;
        min-width: max-content;
        overflow-y: auto;
        overflow-x: hidden;
    }
    /* Advanced mode tooltip */
    .tooltip-inner {
        max-width: 600px;
        text-align: left;
    }
    /* Align selectpickers */
    #interface_select_container {
        float: left;
    }
    #type_filter_container {
        float: left;
        margin-left: 5px;
    }
    #inspect_toggle_container {
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
    /*
     * XXX: Since the badge class uses its own default background-color, we must override it explicitly.
     *      Essentially we would like to use the main style sheet for this.
     *      bg-info is slightly different from text-info, so we use the text-info color for consistency.
     */
    .badge.bg-primary {
        background-color: #C03E14 !important;
    }
    .badge.bg-warning {
        background-color: #f0ad4e !important;
    }
    .badge.bg-info {
        background-color: #31708f !important;
    }
    .badge-sm {
        font-size: 12px;
        padding: 2px 5px;
    }

    /* bucket row style */
    .bucket-row {
        pointer-events: none;
    }

    /* kill all per-cell borders/shadows and let bg come from ::before */
    .bucket-row .tabulator-cell {
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    /* category label can overhang; raise above ::before */
    .bucket-row .tabulator-cell[tabulator-field="categories"] {
        overflow: visible !important;
        white-space: nowrap !important;
        text-overflow: clip !important;
    }

    /* keep only the collapse toggle clickable */
    .bucket-row .tabulator-data-tree-control,
    .bucket-row .tabulator-data-tree-control * {
        pointer-events: auto;
    }

    /* hide the row selection checkbox for internal and dataTree group rules */
    .row-no-select .tabulator-row-header input[type="checkbox"] {
        visibility: hidden;
        pointer-events: none;
    }

    /* hide rowselect checkbox if tree is enabled, it does not work properly */
    .tree-enabled .tabulator-col.tabulator-row-header input[type="checkbox"] {
        visibility: hidden;
        pointer-events: none;
    }

    /* Do not allow Source/Destination selectpickers to grow infinitely */
    #row_rule\.source_net .bootstrap-select > .dropdown-toggle,
    #row_rule\.destination_net .bootstrap-select > .dropdown-toggle {
        max-width: 348px;
    }

    /* fade disabled rows */
    .row-disabled {
        opacity: 0.4;
    }

    /* Action bar specific layout */
    #interface_select_container,
    #type_filter_container {
        float: none !important;
        flex: 1 1 150px;
        min-width: 0;
        max-width: 400px;
    }

    #interface_select_container .bootstrap-select,
    #type_filter_container .bootstrap-select {
        flex: 1 1 auto;
        min-width: 0;
    }

    .bootgrid-header .actionBar .btn-group {
        align-items: flex-start;
    }

    @media (max-width: 1024px) {
        #interface_select_container,
        #type_filter_container {
            flex: 1 1 100%;
            max-width: 100%;
            margin: 0;
        }

        #dialogFilterRule-header #inspect_toggle_container,
        #dialogFilterRule-header #tree_toggle_container,
        #dialogFilterRule-header #tree_expand_container {
            flex: 1 1 0;
            margin: 0;
        }
    }

    .stats-cell {
        display: flex;
        flex-direction: column;
    }

    .stats-cell div {
        gap: 6px;
    }

</style>

<div class="tab-content content-box">
    <!-- filters -->
    <div class="hidden">
        <div id="type_filter_container" class="btn-group">
            <select id="category_filter" data-title="{{ lang._('Categories') }}" class="selectpicker" data-live-search="true" data-size="30" multiple data-container="body">
            </select>
        </div>
        <div id="interface_select_container" class="btn-group">
            <select id="interface_select" class="selectpicker" data-live-search="true" data-size="30" data-container="body">
            </select>
        </div>
        <div id="inspect_toggle_container" class="btn-group">
            <button id="toggle_inspect_button"
                    type="button"
                    class="btn btn-default"
                    data-toggle="tooltip"
                    data-placement="bottom"
                    data-delay='{"show": 1000}'
                    title="{{ lang._('Show all rules and statistics') }}">
                <i class="fa fa-fw fa-eye" aria-hidden="true"></i>
                {{ lang._('Inspect') }}
            </button>
            <input id="all_rules_checkbox" type="checkbox" style="display: none;">
        </div>
        <div id="tree_toggle_container" class="btn-group">
            <button id="toggle_tree_button"
                    type="button"
                    class="btn btn-default"
                    data-toggle="tooltip"
                    data-placement="bottom"
                    data-delay='{"show": 1000}'
                    title="{{ lang._('Show all categories in a tree') }}">
                <i class="fa fa-fw fa-sitemap" aria-hidden="true"></i>
                {{ lang._('Tree') }}
            </button>
        </div>
        <div id="tree_expand_container" class="btn-group">
            <button id="expand_tree_button"
                    type="button"
                    class="btn btn-default"
                    data-toggle="tooltip"
                    data-placement="bottom"
                    data-delay='{"show": 1000}'
                    title="{{ lang._('Expand/Collapse all') }}">
                <i class="fa fa-fw fa-angle-double-down" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <!-- grid -->
    {{ partial('layout_partials/base_bootgrid_table', formGridFilterRule + {'command_width': '150'}) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/filter/apply'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogFilterRule,'id':formGridFilterRule['edit_dialog_id'],'label':lang._('Edit rule')])}}
