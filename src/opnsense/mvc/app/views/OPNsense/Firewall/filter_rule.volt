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
        let pendingUrlInterface = getUrlHash('interface') || null;
        let previousGroupType = null;
        let currentGroupType = null;

        $("#interface_select").on('changed.bs.select', function() {
            const groupData = $(this).data();
            currentGroupType = Object.entries(groupData.store)
                                .find(([, group]) => group.items?.some(item => item.value === $("#interface_select").val()))
                                ?.[0] ?? null;
        });

        const ruleTypeMap = [
            { idx: 0, uuid: "auto0", label: "{{ lang._('Automatically generated rules') }}", icon: "fa-magic", tooltip: "{{ lang._('Automatically generated rules') }}", color: "text-secondary", groupType: null },
            { idx: 2, uuid: "floating", label: "{{ lang._('Floating rules') }}", icon: "fa-layer-group", tooltip: "{{ lang._('Floating rule') }}", color: "text-primary", groupType: "floating" },
            { idx: 3, uuid: "group", label: "{{ lang._('Group rules') }}", icon: "fa-sitemap", tooltip: "{{ lang._('Group rule') }}", color: "text-warning", groupType: "groups" },
            { idx: 4, uuid: "interface", label: "{{ lang._('Interface rules') }}", icon: "fa-ethernet", tooltip: "{{ lang._('Interface rule') }}", color: "text-info", groupType: "interfaces" },
            { idx: 5, uuid: "auto1", label: "{{ lang._('Automatically generated rules') }}", icon: "fa-magic", tooltip: "{{ lang._('Automatically generated rules') }}", color: "text-secondary", groupType: null },
        ];

        // XXX: The "prio_group.sequence" combination in "sort_order" (300000.0000010) is not always static, e.g. in group rules it could also be (300010.0000010).
        //      An exact match is not always possible, using the first digit is the best assumption currently.
        const getRuleTypeDigit = function(row) {
            const sortOrder = row.sort_order ? row.sort_order.toString() : "";
            return Number(sortOrder.charAt(0));
        };

        const getRuleType = function(row) {
            return buckets.find(r => r.idx === getRuleTypeDigit(row)) || null;
        };

        let buckets = [];
        function createBucket(props) {
            return {
                isGroup: true,
                children: [],
                ...props
            };
        }

        function responseHandler(response) {
            // recursively clear children but keep buckets intact
            const clear = (buckets) => {
                for (const bucket of buckets) {
                    if (Array.isArray(bucket.children)) {
                        clear(bucket.children);
                        bucket.children = [];
                    }
                }
            }

            clear(buckets);

            // (re)initialize missing buckets
            for (const type of ruleTypeMap) {
                let bucket = buckets.some(bucket => bucket.idx === type.idx);

                if (!bucket) {
                    buckets.push(createBucket({
                        ...type,
                        _persistence: false,
                        _expanded: false,
                        categories: type.label,
                        category_colors: [{ name: type.label }],
                    }));
                    buckets = buckets.sort((a, b) => a.idx - b.idx);
                }
            }

            // determine tree expansion state of top-level buckets
            for (const bucket of buckets) {
                if (["auto0", "auto1"].includes(bucket.uuid)) {
                    bucket._expanded = false;
                } else if (bucket.groupType === currentGroupType || currentGroupType === "any") {
                    bucket._expanded = true;
                } else if (currentGroupType !== previousGroupType) {
                    bucket._expanded = false;
                }
            }

            const indexMap = {};
            let lastBucketId = null;
            response.rows.forEach(row => {
                // Find bucket this row belongs to. If it doesn't exist, create it.
                let bucket = getRuleType(row);

                const categoryLabel = row["%categories"] || row.categories || "";
                if (treeViewEnabled && row.is_automatic !== true && categoryLabel !== "") {
                    // We're dealing with a category, create bucket id based on this row
                    const bucketId = `${bucket.uuid}category${String(categoryLabel).replace(/[^a-z0-9]/gi, '')}`;

                    // categories with the same name may appear multiple times due to ordering,
                    // indexMap tracks these to uniquely identify them.
                    if (!(bucketId in indexMap)) {
                        indexMap[bucketId] = 0;
                    }

                    if (bucketId !== lastBucketId && lastBucketId !== null) {
                        // moved to next category
                        indexMap[lastBucketId]++;
                    }

                    const id = `${bucketId}${indexMap[bucketId]}`;
                    let newBucket = bucket.children.find(child => child.uuid === id);

                    if (!newBucket) {
                        newBucket = createBucket({
                            uuid: id,
                            _persistence: true,
                            categories: categoryLabel,
                            category_colors: row.category_colors,
                        });
                        bucket.children.push(newBucket);
                    }

                    bucket = newBucket;
                    lastBucketId = bucketId;
                }

                bucket.children.push(row);
            });

            const removeEmptyGroups = (items) => {
                for (let i = items.length - 1; i >= 0; i--) {
                    const item = items[i];

                    if (Array.isArray(item.children)) {
                        if (item.children.length === 0) {
                            items.splice(i, 1);
                        } else {
                            removeEmptyGroups(item.children);
                        }
                    }
                }
            };

            removeEmptyGroups(buckets);
            previousGroupType = currentGroupType;

            return Object.assign({}, response, { rows: buckets });
        }

        $('#download_rules').click(function(e){
            e.preventDefault();
            window.open("/api/firewall/filter/download_rules");
        });

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
                dataTreeStartExpanded : (row, level) => row.getData()._expanded,
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
                        $element.removeClass('tabulator-selectable');
                    }

                    // bucket row (dataTree) styling
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
                rowCount: [500,20,50,100,200,1000,2000,-1],
                initialSearchPhrase: getUrlHash('search'),
                requestHandler: function(request){
                    // Add category selectpicker
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    // Add interface selectpicker, or fall back to hash for the first load
                    let selectedInterface = $('#interface_select').val();
                    if (selectedInterface == null && pendingUrlInterface != null) {
                        selectedInterface = pendingUrlInterface;
                    }
                    if (selectedInterface === '__floating') {
                        request.interface = '';
                    } else if (selectedInterface !== null && selectedInterface !== '__any') {
                        request.interface = selectedInterface;
                        // '__any' omit parameter for all rules
                    }
                    if (inspectEnabled) {
                        // Send as a comma separated string
                        request['show_all'] = true;
                    }
                    return request;
                },
                // convert the flat rows into a tree view
                responseHandler: responseHandler,

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
                    commands: function (column, row) {
                        // All formatters except category must skip processing bucket rows in tree view
                        if (row.isGroup) {
                            return "";
                        }
                        const rowId = row.uuid || "";
                        const hasUuid = rowId.includes("-");

                        const logSearchCommand = (rid, log) => {
                            const loggingEnabled = log === '1' || log === true;
                            if (!loggingEnabled) return '';

                            return `
                                <a href="/ui/diagnostics/firewall/log#${new URLSearchParams({field:'rid',operator:'=',value:rid})}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-xs btn-default bootgrid-tooltip"
                                title="{{ lang._('View log entries for this rule') }}">
                                    <i class="fa fa-fw fa-search"></i>
                                </a>
                            `;
                        };

                        // If UUID is invalid, its an internal rule, use the #ref field to show a lookup button.
                        if (!hasUuid) {
                            const ref = (row["ref"] || "");

                            // optional lookup button if ref exists
                            const lookupRefCommand = ref ? `
                                <a href="/${ref}" target="_blank" rel="noopener noreferrer"
                                class="btn btn-xs btn-default bootgrid-tooltip"
                                title="{{ lang._('Lookup rule reference') }}">
                                    <i class="fa fa-fw fa-link"></i>
                                </a>
                            ` : '';

                            return `
                                ${logSearchCommand(rowId, row.log)}
                                ${lookupRefCommand}
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

                            ${logSearchCommand(rowId, row.log)}
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
                            row[column.id] !== undefined &&
                            !['', 'any', 'None', 'inet46'].includes(row[column.id])
                        ) {
                            return row["%" + column.id] || row[column.id];
                        } else {
                            return '*';
                        }
                    },
                    // Bucket rows reuse the category column because Tabulator only renders one
                    // formatter per cell, so both category buckets and rule type buckets are
                    // represented here.
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
                            const ruleType = ruleTypeMap.find(r => r.uuid === row.uuid);

                            const name = ruleType ? ruleType.tooltip : (cat.name || "");
                            const icon = ruleType ? ruleType.icon : "fa-fw fa-tag";
                            const classColor = ruleType? ruleType.color : '';
                            const bgColor = cat.color ? ` style="color:${cat.color};"` : '';
                            return `
                                <span class="category-icon" data-toggle="tooltip" title="${name}">
                                    <i class="fa fa-fw ${icon} ${classColor}" ${bgColor}></i>
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
                        const ruleType = getRuleType(row);

                        if (ruleType) {
                            result += `<i class="fa ${ruleType.icon} fa-fw ${ruleType.color}"
                                        data-toggle="tooltip" title="${ruleType.tooltip}"></i> `;
                        }

                        // Action
                        if (row.action === "block") {
                            result += `<i class="fa fa-times fa-fw text-danger" data-toggle="tooltip" title="${row['%action']}"></i> `;
                        } else if (row.action === "reject") {
                            result += `<i class="fa fa-times-circle fa-fw text-danger" data-toggle="tooltip" title="${row['%action']}"></i> `;
                        } else {
                            result += `<i class="fa fa-play fa-fw text-success" data-toggle="tooltip" title="${row['%action']}"></i> `;
                        }

                        // Direction
                        if (row.direction === "in") {
                            result += `<i class="fa fa-long-arrow-right fa-fw text-info" data-toggle="tooltip" title="${row['%direction']}"></i> `;
                        } else if (row.direction === "out") {
                            result += `<i class="fa fa-long-arrow-left fa-fw" data-toggle="tooltip" title="${row['%direction']}"></i> `;
                        } else {
                            result += `<i class="fa fa-exchange fa-fw" data-toggle="tooltip" title="${row['%direction']}"></i> `;
                        }

                        // Quick match
                        if (row.quick == 0) {
                            result += `<i class="fa fa-flash fa-fw text-muted" data-toggle="tooltip" title="{{ lang._("Last match") }}"></i> `;
                        } else {
                            result += `<i class="fa fa-flash fa-fw text-warning" data-toggle="tooltip" title="{{ lang._("First match") }}"></i> `;
                        }

                        // XXX: Advanced fields all have different default values, so it cannot be generalized completely
                        const advancedDefaultPrefixes = ["0", "none", "any", "default", "keep"];
                        const usedAdvancedFields = [];

                        advancedFieldIds.forEach(function (fieldId) {
                            const value = row["%" + fieldId] ?? row[fieldId];
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
                    // Show Edit alias icon, alias info and integrate "not" functionality
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
                                const tooltipHtml = aliasInfo.summary || aliasInfo.description || aliasInfo.value || "";
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
                        const uuid    = row["uuid"] ?? "";

                        function render(icon, title, value, is_number = false, link = null) {
                            if (!value || value === "0") {
                                return "";
                            }

                            const numValue  = parseInt(value, 10);
                            const formatted = byteFormat(numValue, 1, is_number);

                            return `
                                <span data-toggle="tooltip" title="${title}: ${numValue.toLocaleString()}">
                                    ${link
                                        ? `<a href="${link}" target="_blank" rel="noopener noreferrer" id="${uuid}_states">
                                            <i class="fa fa-fw ${icon}"></i> ${formatted}
                                        </a>`
                                        : `<i class="fa fa-fw ${icon}"></i> ${formatted}`}
                                </span>
                            `;
                        }

                        const parts = [
                            render("fa-chart-line", "{{ lang._('States') }}", states, true, `/ui/diagnostics/firewall/states#${uuid}`),
                            render("fa-bullseye", "{{ lang._('Evaluations') }}", evals, true),
                            render("fa-box", "{{ lang._('Packets') }}", packets, true),
                            render("fa-database", "{{ lang._('Bytes') }}", bytes)
                        ].filter(Boolean);

                        if (parts.length === 0) {
                            return "";
                        }

                        return `
                            <div class="stats-cell">
                                ${parts.join("")}
                            </div>
                        `;
                    },
                    sched: function(column, row) {
                        if (row.isGroup || typeof row[column.id] !== "string" || row[column.id] === "") {
                            return "";
                        }
                        return `
                            ${row[column.id]} &nbsp;
                            <a href="/firewall_schedule_edit.php?name=${row[column.id]}" data-toggle="tooltip" title="{{ lang._('Edit') }}">
                                <i class="fa fa-calendar text-muted"></i>
                            </a>
                        `;
                    },
                },
            },
            commands: {
                upload_rules: {
                    onRendered: function () {
                        const $el = $(this);
                        $el.data('title', "{{ lang._('Import rules') }}");
                        $el.data('endpoint', '/api/firewall/filter/upload_rules');
                        $el.SimpleFileUploadDlg({
                            onAction: function () {
                                $("#{{formGridFilterRule['table_id']}}").bootgrid('reload');
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
                        window.open("/api/firewall/filter/download_rules");
                    },
                    sequence: 500
                },
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
                            `/api/firewall/filter/toggle_rule_log/${uuid}/${log}`,
                            {},
                            function(data) {
                                if (data.status === "ok") {
                                    $("#{{ formGridFilterRule['table_id'] }}").bootgrid("reload");
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

        function onTreeEvent(row, open) {
            const getBucketById = (buckets, uuid) => {
                for (const bucket of buckets) {
                    if (bucket.uuid === uuid) {
                        return bucket;
                    }

                    if (Array.isArray(bucket.children)) {
                        const found = getBucketById(bucket.children, uuid);

                        if (found) {
                            return found;
                        }
                    }
                }

                return null;
            }

            const bucket = getBucketById(buckets, row.getData().uuid);
            if ('_expanded' in bucket) {
                bucket._expanded = open;
            }
        }

        // persist expansion state of rule type categories
        // during the lifetime of the page, resets on page reload
        const table = grid.bootgrid('getTable');
        table.on('dataTreeRowExpanded', (row) => onTreeEvent(row, true));
        table.on('dataTreeRowCollapsed', (row) => onTreeEvent(row, false));

        // "selectableRowsCheck" doesn't execute when the header checkbox is used,
        // work around this by checking on row selection
        table.on('rowSelected', (row) => {
            const data = row.getData();
            if (data.isGroup) {
                // "select all" triggered, deselect current row since it's a group, but select any nested row that isn't a group recursively
                row.deselect();
                const children = row.getTreeChildren();
                const getAllRows = (rows) => {
                    const result = [];

                    for (const row of rows) {
                        const rowData = row.getData();
                        // do not select rows that aren't visible to avoid confusion (_expanded == false)
                        if (!rowData.isGroup && rowData.uuid.includes("-") && row.getTreeParent().getData()._expanded) {
                            result.push(row);
                            continue;
                        }

                        result.push(...getAllRows(row.getTreeChildren() || []));
                    }

                    return result;
                };

                for (const selectableRow of getAllRows(children)) {
                    selectableRow.select();
                }
            }
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
                true  // render_html
            );
        }

        // Populate interface selectpicker
        function populateInterfaceSelectpicker() {
            const currentSelection = $("#interface_select").val();

            return $('#interface_select').fetch_options(
                '/api/firewall/filter/get_interface_list',
                {},
                function (data) {
                    for (const groupKey in data) {
                        const group = data[groupKey];
                        const icon = group.icon || '';

                        group.items = group.items.map(item => {
                            const label = item.label || '';

                            return {
                                value: item.value,
                                label: label,
                                'data-content': `
                                    <span>
                                        ${icon ? `<i class="${icon}"></i>` : ''}
                                        ${label}
                                    </span>
                                `.trim()
                            };
                        });
                    }

                    return data;
                },
                true,
                function (data) {  // post_callback, apply the URL hash logic
                    const $select = $('#interface_select');
                    const interfaceCandidate = (!interfaceInitialized && pendingUrlInterface)
                        ? pendingUrlInterface
                        : currentSelection;

                    $select.selectpicker('val', interfaceCandidate);

                    if (!$select.val()) {
                        $select.selectpicker('val', '__any');
                    }

                    interfaceInitialized = true;
                    pendingUrlInterface = null; // consume the hash so it is not used again
                },
                true  // render_html to show icons
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
            grid.bootgrid("reload");
        });

        $("#tree_expand_container").detach().insertAfter("#tree_toggle_container");
        $("#tree_expand_container").show();

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
                    $('#rule\\.categories').selectpicker('val', selectedCategories);
                    $('#rule\\.categories').selectpicker('refresh');
                }
            }
        });

        // Hide additional protocol settings in dialog, e.g., ICMP types
        $('#rule\\.protocol').change(function() {
            $('.rule_protocol:not(div)').closest('tr').hide();
            $('.' + $.escapeSelector('protocol_' + $(this).val().toLowerCase()) + ':not(div)')
                .closest('tr')
                .show();
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

    /* labels are rectengular by default, we want them circle shaped */
    .label.label-sm {
        display: inline-flex;
        align-items: center;
        height: 18px;
        padding: 0 6px;
        border-radius: 50%;
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
        flex-wrap: wrap;
        gap: 4px 10px;
        align-items: center;
        container-type: inline-size;
    }

    .stats-cell > span {
        white-space: nowrap;
    }

    @container (max-width: 160px) {
        .stats-cell > span {
            flex: 1 1 50%;
        }
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
                    title="{{ lang._('Show rule statistics') }}">
                <i class="fa fa-fw fa-eye" aria-hidden="true"></i>
            </button>
            <input id="all_rules_checkbox" type="checkbox" style="display: none;">
        </div>
        <div id="tree_toggle_container" class="btn-group">
            <button id="toggle_tree_button"
                    type="button"
                    class="btn btn-default"
                    data-toggle="tooltip"
                    data-placement="bottom"
                    title="{{ lang._('Show categories as folders') }}">
                <i class="fa fa-fw fa-tag" aria-hidden="true"></i>
            </button>
        </div>
        <div id="tree_expand_container" class="btn-group">
            <button id="expand_tree_button"
                    type="button"
                    class="btn btn-default"
                    data-toggle="tooltip"
                    data-placement="bottom"
                    title="{{ lang._('Expand/Collapse all') }}">
                <i class="fa fa-fw fa-angle-double-down" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <!-- grid -->
    {{ partial('layout_partials/base_bootgrid_table', formGridFilterRule + {'command_width': '180'}) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/filter/apply'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogFilterRule,'id':formGridFilterRule['edit_dialog_id'],'label':lang._('Edit rule')])}}
