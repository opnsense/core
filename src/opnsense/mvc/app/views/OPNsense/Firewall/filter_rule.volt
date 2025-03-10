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

        // Trigger change message, e.g., when using move_before
        function showChangeMessage() {
            $("#change_message_base_form").slideDown(1000, function() {
                setTimeout(function() {
                    $("#change_message_base_form").slideUp(2000);
                }, 2000);
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

        // Test if the UUID is valid, used to determine if automation model or internal rule
        const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

        const grid = $("#{{formGridFilterRule['table_id']}}").UIBootgrid({
            search:'/api/firewall/filter/search_rule/',
            get:'/api/firewall/filter/get_rule/',
            set:'/api/firewall/filter/set_rule/',
            add:'/api/firewall/filter/add_rule/',
            del:'/api/firewall/filter/del_rule/',
            toggle:'/api/firewall/filter/toggle_rule/',
            options: {
                triggerEditFor: getUrlHash('edit'),
                initialSearchPhrase: getUrlHash('search'),
                rowCount: [14,20,50,100,200,500,1000,2000,5000,-1],
                requestHandler: function(request){
                    // Add category selectpicker
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    // Add interface selectpicker
                    let selectedInterface = $('#interface_select').val();
                    if (selectedInterface && selectedInterface.length > 0) {
                        request['interface'] = selectedInterface;
                    }
                    if ($('#all_rules_checkbox').is(':checked')) {
                        // Send as a comma separated string
                        request['show_all'] = true;
                    }
                    return request;
                },
                formatters:{
                    // Only show command buttons for rules that have a uuid, internal rules will not have one
                    commands: function (column, row) {
                        let rowId = row.uuid;

                        // If UUID is invalid, its an internal rule, use the #ref field to show a lookup button.
                        if (!rowId || !uuidRegex.test(rowId)) {
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
                    // Show rowtoggle for all rules, but disable interaction for internal rules with no valid UUID
                    rowtoggle: function (column, row) {
                        let rowId = row.uuid;
                        let isEnabled = parseInt(row[column.id], 2) === 1;

                        let iconClass = isEnabled
                            ? "fa-check-square-o"
                            : "fa-square-o text-muted";

                        let tooltipText = isEnabled
                            ? "{{ lang._('Enabled') }}"
                            : "{{ lang._('Disabled') }}";

                        // For valid UUIDs, make it interactive
                        if (rowId && uuidRegex.test(rowId)) {
                            return `
                                <span style="cursor: pointer;" class="fa fa-fw ${iconClass}
                                    command-toggle bootgrid-tooltip" data-value="${isEnabled ? 1 : 0}"
                                    data-row-id="${rowId}" title="${tooltipText}">
                                </span>
                            `;
                        }

                        // For internal rules, show a non-interactive toggle
                        return `
                            <span style="opacity: 0.5"
                                class="fa fa-fw ${iconClass} bootgrid-tooltip"
                                data-value="${isEnabled ? 1 : 0}"
                                data-row-id="${rowId}" title="${tooltipText}">
                            </span>
                        `;
                    },
                    any: function(column, row) {
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
                    notavailable: function(column, row) {
                        if (row[column.id] !== '') {
                            return row[column.id];
                        } else {
                            return '{{ lang._("n/a") }}';
                        }
                    },
                    interfaces: function(column, row) {
                        const interfaces = row[column.id] != null ? String(row[column.id]) : "";

                        // Apply negation
                        const isNegated = row.interfacenot == 1 ? "! " : "";

                        if (!interfaces || interfaces.trim() === "") {
                            return isNegated + '*';
                        }

                        const interfaceList = interfaces.split(",").map(iface => iface.trim());

                        if (interfaceList.length === 1) {
                            return isNegated + interfaceList[0];
                        }

                        const tooltipText = interfaceList.join("<br>");

                        return `
                            ${isNegated}
                            <span data-toggle="tooltip" data-html="true" title="${tooltipText}" style="white-space: nowrap;">
                                <span class="interface-count">${interfaceList.length}</span>
                                <i class="fa-solid fa-fw fa-ethernet"></i>
                            </span>
                        `;
                    },
                    // Icons
                    ruleIcons: function(column, row) {
                        let result = "";
                        const iconStyle = (row.enabled == 0)
                            ? 'style="opacity: 0.4; pointer-events: none;"'
                            : '';

                        // Action
                        if (row.action.toLowerCase() === "pass") {
                            result += '<i class="fa fa-play fa-fw text-success" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("Pass") }}"></i> ';
                        } else if (row.action.toLowerCase() === "block") {
                            result += '<i class="fa fa-times fa-fw text-danger" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("Block") }}"></i> ';
                        } else if (row.action.toLowerCase() === "reject") {
                            result += '<i class="fa fa-times-circle fa-fw text-danger" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("Reject") }}"></i> ';
                        }

                        // Direction
                        if (row.direction.toLowerCase() === "in") {
                            result += '<i class="fa fa-long-arrow-right fa-fw text-info" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("In") }}"></i> ';
                        } else if (row.direction.toLowerCase() === "out") {
                            result += '<i class="fa fa-long-arrow-left fa-fw" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("Out") }}"></i> ';
                        } else {
                            result += '<i class="fa fa-exchange fa-fw" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("Any") }}"></i> ';
                        }

                        // Quick match
                        if (row.quick == 1) {
                            result += '<i class="fa fa-flash fa-fw text-warning" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("First match") }}"></i> ';
                        } else if (row.quick == 0) {
                            result += '<i class="fa fa-flash fa-fw text-muted" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("Last match") }}"></i> ';
                        }

                        // Logging
                        if (row.log == 1) {
                            result += '<i class="fa fa-exclamation-circle fa-fw text-info" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("Logging enabled") }}"></i> ';
                        } else if (row.log == 0) {
                            result += '<i class="fa fa-exclamation-circle fa-fw text-muted" ' + iconStyle +
                                    ' data-toggle="tooltip" title="{{ lang._("Logging disabled") }}"></i> ';
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

                        result += `<i class="fa fa-cog fa-fw ${iconClass}" ${iconStyle}
                                    data-toggle="tooltip" data-html="true" title="${tooltip}"></i>`;

                        // Return all icons
                        return result;
                    },
                    // Show Edit alias icon and integrate "not" functionality
                    alias: function(column, row) {
                        const value = row[column.id] != null ? String(row[column.id]) : "";

                        // Explicitly map fields that support negation
                        const notFieldMap = {
                            "source_net": "source_not",
                            "destination_net": "destination_not"
                        };

                        const notField = notFieldMap[column.id];

                        // Apply negation
                        const isNegated = notField && row.hasOwnProperty(notField) && row[notField] == 1 ? "! " : "";

                        if (!value || value.trim() === "" || value === "any" || value === "None") {
                            return isNegated + '*';
                        }

                        // Ensure it's a string, or internal rules will not load anymore
                        const stringValue = typeof value === "string" ? value : String(value);

                        const aliasFlagName = "is_alias_" + column.id;
                        if (!row.hasOwnProperty(aliasFlagName)) {
                            return isNegated + stringValue;
                        }

                        const generateAliasMarkup = (val) => `
                            <span data-toggle="tooltip" title="${val}">
                                ${val}&nbsp;
                            </span>
                            <a href="/ui/firewall/alias/index/${val}" data-toggle="tooltip" title="{{ lang._('Edit alias') }}">
                                <i class="fa fa-fw fa-list"></i>
                            </a>
                        `;

                        // If the alias flag is an array, handle multiple comma-separated aliases
                        if (Array.isArray(row[aliasFlagName])) {
                            const values = stringValue.split(',').map(s => s.trim());
                            const aliasFlags = row[aliasFlagName];

                            return isNegated + values.map((val, index) => aliasFlags[index] ? generateAliasMarkup(val) : val).join(', ');
                        }

                        // If alias flag is not an array, assume it's a boolean and a single alias
                        return isNegated + (row[aliasFlagName] ? generateAliasMarkup(stringValue) : stringValue);
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
                                    std_bootgrid_reload("{{ formGridFilterRule['table_id'] }}");
                                    showChangeMessage();
                                } else {
                                    let msg = data.message || "{{ lang._('Error moving rule.') }}";
                                    showDialogAlert(
                                        BootstrapDialog.TYPE_WARNING,
                                        "{{ lang._('Move Error') }}",
                                        msg
                                    );
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
            },

        });

        grid.on("loaded.rs.jquery.bootgrid", function () {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // XXX: Replace these labels to save some space in the grid
            // This is a workaround, to change labels in the grid, but NOT in the grid selection dropdown
            $(this).find('th[data-column-id="enabled"] .text').text("");
            $(this).find('th[data-column-id="icons"] .text').text("");
            $(this).find('th[data-column-id="sequence"] .text').text("{{ lang._('Seq') }}");
            $(this).find('th[data-column-id="protocol"] .text').text("{{ lang._('Proto') }}");
            $(this).find('th[data-column-id="source_port"] .text').text("{{ lang._('Port') }}");
            $(this).find('th[data-column-id="destination_port"] .text').text("{{ lang._('Port') }}");
            $(this).find('th[data-column-id="interface"] .text').html('<i class="fa-solid fa-fw fa-network-wired"></i>');
            $(this).find('th[data-column-id="evaluations"] .text').html('<i class="fa-solid fa-fw fa-bullseye"></i>');

            // Animate move_up and move_down commands
            const highlightUuid = sessionStorage.getItem("highlightRuleUuid");
            if (highlightUuid) {
                const $row = $("[data-row-id='" + highlightUuid + "']", this);

                if ($row.length) {
                    $row.addClass("highlight-animate");
                }

                sessionStorage.removeItem("highlightRuleUuid");
            }

            // Reload categories before grid load
            ajaxCall('/api/firewall/filter/list_categories', {}, function (data) {
                if (!data.rows) return;

                const $categoryFilter = $("#category_filter");
                const currentSelection = $categoryFilter.val();

                $categoryFilter.empty().append(
                    data.rows.map(row => {
                        const optVal = $('<div/>').text(row.name).html();
                        const bgColor = row.color || '31708f';

                        return $("<option/>", {
                            value: row.uuid,
                            html: row.name,
                            id: row.used > 0 ? row.uuid : undefined,
                            "data-content": row.used > 0
                                ? `<span>${optVal}</span><span style='background:#${bgColor};' class='badge pull-right'>${row.used}</span>`
                                : undefined
                        });
                    })
                );

                $categoryFilter.val(currentSelection).selectpicker('refresh');
            });
        });

        // Populate interface selectpicker
        ajaxCall('/api/firewall/filter/get_interface_list', {},
            function(data, status) {
                const $select = $('#interface_select');
                if (Array.isArray(data)) {
                    data.forEach(function(iface) {
                        $select.append(
                            $('<option>', {
                                value: iface.value,
                                text: iface.label
                            })
                        );
                    });
                    $select.selectpicker('refresh');
                }
            },
            function(xhr, textStatus, errorThrown) {
                console.error("Failed to load interface list:", textStatus, errorThrown);
            }
        );

        $("#reconfigureAct").SimpleActionButton();

        // move selectpickers into action bar
        $("#interface_select_container").detach().insertBefore('#{{formGridFilterRule["table_id"]}}-header > .row > .actionBar > .search');
        $('#interface_select').change(function(){
            grid.bootgrid('reload');
        });

        $("#type_filter_container").detach().insertAfter("#interface_select_container");
        $("#category_filter").change(function(){
            grid.bootgrid('reload');
        });

        $("#internal_rule_selector").detach().insertAfter("#type_filter_container");
        $('#all_rules_checkbox').change(function(){
            grid.bootgrid('reload');
        });

        $('#all_rules_button').click(function(){
            let $checkbox = $('#all_rules_checkbox');

            $checkbox.prop("checked", !$checkbox.prop("checked"));
            $(this).toggleClass('active btn-primary');
            $checkbox.trigger("change");
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

        /**
         * Select the last unassigned filter sequence number
         * When rules are cloned it will also clone the sequence number
         * This button helps the user to always find an available last sequence number.
         */
         const filterSequenceBtn = $("<button type='button' class='btn filter_btn btn-default btn-group' " +
            "data-toggle='tooltip' " +
            "title='{{ lang._('Generate last free sequence') }}'>")
            .html("<i class='fa fa-cog'></i>");

        $("#rule\\.sequence").closest("td").prepend(
            $("<div class='btn-group'>").append(
                $("#rule\\.sequence").detach(),
                filterSequenceBtn
            )
        );

        filterSequenceBtn.click(function(){
            ajaxGet("/api/firewall/filter/get_next_sequence", {}, function(data){
                if (data.sequence !== undefined) {
                    $("#rule\\.sequence").val(data.sequence);
                    filterSequenceBtn.tooltip('hide')
                }
            });
        });

        filterSequenceBtn.mouseleave(function(){
            filterSequenceBtn.tooltip('hide')
        });

        $("#{{ formGridFilterRule['table_id'] }}").wrap('<div class="grid-box"></div>');

    });
</script>

<style>
    /* The filter rules column dropdown has many items */
    .actions .dropdown-menu.pull-right {
        max-height: 400px;
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
    #internal_rule_selector {
        float: left;
        margin-left: 5px;
    }
    /* Prevent grid to break out of content box */
    .grid-box {
        width: 100%;
        overflow-x: auto;
        background: none;
        border: none;
    }
</style>

<div class="tab-content content-box">
    <!-- filters -->
    <div class="hidden">
        <div id="type_filter_container" class="btn-group">
            <select id="category_filter" data-title="{{ lang._('Categories') }}" class="selectpicker" data-live-search="true" data-size="5" multiple data-width="200px">
            </select>
        </div>
        <div id="internal_rule_selector" class="btn-group">
            <!-- XXX: function should show full rule impact (for the selected inferface) -->
            <button id="all_rules_button" type="button" class="btn btn-default selectpicker-toggle">
                {{ lang._('Show hidden rules') }}
            </button>
            <input id="all_rules_checkbox" type="checkbox" style="display: none;">
        </div>
        <div id="interface_select_container" class="btn-group">
            <select id="interface_select" class="selectpicker" data-live-search="true" data-size="10" data-width="200px" title="{{ lang._('Interface') }}">
            </select>
        </div>
    </div>
    <!-- grid -->
    {{ partial('layout_partials/base_bootgrid_table', formGridFilterRule + {'command_width': '9em'}) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/filter/apply'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogFilterRule,'id':formGridFilterRule['edit_dialog_id'],'label':lang._('Edit rule')])}}
