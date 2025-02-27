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
        // Add column for firewall rule icons
        $('#{{formGridFilterRule['table_id']}} thead tr th[data-column-id="sequence"]')
        .after(
            '<th ' +
                'data-column-id="icons" ' +
                'data-type="string" ' +
                'data-sortable="false" ' +
                'data-width="8em" ' +
                'data-formatter="ruleIcons">' +
                "{{ lang._('Icons') }}" +
            '</th>'
        );

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

        // Trigger change message, e.g., when using move_up or move_down
        function showChangeMessage() {
            $("#change_message_base_form").slideDown(1000, function() {
                setTimeout(function() {
                    $("#change_message_base_form").slideUp(2000);
                }, 2000);
            });
        }

        // Get all advanced fields, used for advanced mode tooltips
        const advancedFieldIds = [
            {% for field in advancedFieldIds %}
                "{{ field }}"{% if not loop.last %}, {% endif %}
            {% endfor %}
        ];

        // Get all column labels, used for advanced mode tooltips
        const columnLabels = {};
        $('#{{formGridFilterRule["table_id"]}} thead th').each(function () {
            const columnId = $(this).attr('data-column-id');
            if (columnId) {
                columnLabels[columnId] = $(this).text().trim();
            }
        });

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
                requestHandler: function(request){
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    let internalTypes = $('#include_internal_select').val();
                    if (internalTypes && internalTypes.length > 0) {
                        // Send as a comma separated string
                        request['include_internal'] = internalTypes.join(',');
                    }
                    return request;
                },
                formatters:{
                    // Show rule inverse status
                    interfacenot: function(column, row) {
                        if (row.interfacenot == true) {
                            return "! " + row.interface;
                        } else {
                            return row.interface;
                        }
                    },
                    source_not: function(column, row) {
                        if (row.source_not == true) {
                            return "! " + row.source_net;
                        } else {
                            return row.source_net;
                        }
                    },
                    destination_not: function(column, row) {
                        if (row.destination_not == true) {
                            return "! " + row.destination_net;
                        } else {
                            return row.destination_net;
                        }
                    },
                    default: function(column, row) {
                        if (row[column.id].toLowerCase() !== 'none') {
                            return row[column.id];
                        } else {
                            return '{{ lang._("default") }}';
                        }
                    },
                    any: function(column, row) {
                        if (row[column.id] !== '') {
                            return row[column.id];
                        } else {
                            return '{{ lang._("any") }}';
                        }
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

                        advancedFieldIds.forEach(function (advId) {
                            // Convert "rule.id" to "id"
                            const shortName = advId.split('.').pop();
                            const value = row[shortName];

                            if (value !== undefined) {
                                const lowerValue = value.toString().toLowerCase().trim();
                                // Check: if the value is empty OR starts with any default prefix, consider it default
                                const isDefault = (lowerValue === "") || advancedDefaultPrefixes.some(function(prefix) {
                                    return lowerValue.startsWith(prefix);
                                });

                                if (!isDefault) {
                                    // Use label if available, otherwise fallback to field ID
                                    const label = columnLabels[shortName] || shortName;
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

                },
            },
            commands: {
                // Move filter rule sequence up or down in the grid
                move_up: {
                    method: function(event) {
                        const currentUuid = $(this).data("row-id");
                        ajaxCall(
                            "/api/firewall/filter/move_up/" + currentUuid,
                            {},
                            function(data, status) {
                                if (data.status === "ok") {
                                    // Animate move_up and move_down commands
                                    sessionStorage.setItem("highlightRuleUuid", currentUuid);
                                    std_bootgrid_reload("{{ formGridFilterRule['table_id'] }}");
                                    showChangeMessage();
                                } else {
                                    showDialogAlert(
                                        BootstrapDialog.TYPE_WARNING,
                                        "{{ lang._('Warning') }}",
                                        "{{ lang._('This rule cannot be moved up.') }}"
                                    );
                                }
                            },
                            function() {
                                showDialogAlert(
                                    BootstrapDialog.TYPE_DANGER,
                                    "{{ lang._('Error') }}",
                                    "{{ lang._('Failed to move the rule.') }}"
                                );
                            },
                            'POST'
                        );
                    },
                    classname: "fa fa-fw fa-arrow-up",
                    title: "{{ lang._('Move Rule Up') }}",
                    sequence: 10
                },
                move_down: {
                    method: function(event) {
                        const currentUuid = $(this).data("row-id");
                        ajaxCall(
                            "/api/firewall/filter/move_down/" + currentUuid,
                            {},
                            function(data, status) {
                                if (data.status === "ok") {
                                    sessionStorage.setItem("highlightRuleUuid", currentUuid);
                                    std_bootgrid_reload("{{ formGridFilterRule['table_id'] }}");
                                    showChangeMessage();
                                } else {
                                    showDialogAlert(
                                        BootstrapDialog.TYPE_WARNING,
                                        "{{ lang._('Warning') }}",
                                        "{{ lang._('This rule cannot be moved down.') }}"
                                    );
                                }
                            },
                            function() {
                                showDialogAlert(
                                    BootstrapDialog.TYPE_DANGER,
                                    "{{ lang._('Error') }}",
                                    "{{ lang._('Failed to move the rule.') }}"
                                );
                            },
                            'POST'
                        );
                    },
                    classname: "fa fa-fw fa-arrow-down",
                    title: "{{ lang._('Move Rule Down') }}",
                    sequence: 20
                },
            },

        });

        $("#{{formGridFilterRule['table_id']}}").on('loaded.rs.jquery.bootgrid', function() {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // XXX: Replace these labels to save some space in the grid
            $(this).find('th[data-column-id="enabled"] .text').text("");
            $(this).find('th[data-column-id="sequence"] .text').text("{{ lang._('Seq') }}");
            $(this).find('th[data-column-id="source_port"] .text').text("{{ lang._('Port') }}");
            $(this).find('th[data-column-id="destination_port"] .text').text("{{ lang._('Port') }}");

            $("[data-row-id]").each(function(){
                const uuid = $(this).data("row-id");
                if (uuid && uuid.indexOf("internal") !== -1) {
                    // Assuming the enabled checkbox is rendered within a cell with data-column-id="enabled"
                    $(this).find("td[data-column-id='enabled']").hide();
                }
            });

            // Animate move_up and move_down commands
            const highlightUuid = sessionStorage.getItem("highlightRuleUuid");
            if (highlightUuid) {
                const $row = $("[data-row-id='" + highlightUuid + "']", this);

                if ($row.length) {
                    $row.addClass("highlight-animate");
                }

                sessionStorage.removeItem("highlightRuleUuid");
            }
        });

        // Reload categories before grid load
        grid.on("loaded.rs.jquery.bootgrid", function () {
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

        // Define the savepoint buttons HTML
        const savepointButtons = `
            <div class="pull-right btn-group">
                <button class="btn" id="savepointAct"
                        data-endpoint="/api/firewall/filter/savepoint"
                        data-label="{{ lang._('Savepoint') }}"
                        data-error-title="{{ lang._('snapshot error') }}"
                        type="button">
                    {{ lang._('Savepoint') }}
                </button>
                <button class="btn" id="revertAction">
                    {{ lang._('Revert') }}
                </button>
            </div>
        `;

        $("#reconfigureAct").SimpleActionButton();
        $("#reconfigureAct").after(savepointButtons);

        $("#savepointAct").SimpleActionButton({
            onAction: function(data, status){
                stdDialogInform(
                    "{{ lang._('Savepoint created') }}",
                    data['revision'],
                    "{{ lang._('Close') }}"
                );
            }
        });

        $("#revertAction").on('click', function(){
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_DEFAULT,
                title: "{{ lang._('Revert to savepoint') }}",
                message: "<p>{{ lang._('Enter a savepoint to rollback to.') }}</p>" +
                    '<div class="form-group" style="display: block;">' +
                    '<input id="revertToTime" type="text" class="form-control"/>' +
                    '<span class="error text-danger" id="revertToTimeError"></span>'+
                    '</div>',
                buttons: [{
                    label: "{{ lang._('Revert') }}",
                    cssClass: 'btn-primary',
                    action: function(dialogRef) {
                        ajaxCall("/api/firewall/filter/revert/" + $("#revertToTime").val(), {}, function (data, status) {
                            if (data.status !== "ok") {
                                $("#revertToTime").parent().addClass("has-error");
                                $("#revertToTimeError").html(data.status);
                            } else {
                                std_bootgrid_reload("{{formGridFilterRule['table_id']}}");
                                dialogRef.close();
                            }
                        });
                    }
                }],
                onshown: function(dialogRef) {
                    $("#revertToTime").parent().removeClass("has-error");
                    $("#revertToTimeError").html("");
                    $("#revertToTime").val("");
                }
            });
        });

        // move filter into action header
        $("#type_filter_container").detach().prependTo('#{{formGridFilterRule['table_id']}}-header > .row > .actionBar > .actions');
        $("#category_filter").change(function(){
            $('#{{formGridFilterRule['table_id']}}').bootgrid('reload');
        });

        $("#internal_rule_selector").insertBefore("#type_filter_container");
        $('#include_internal_select').change(function(){
            $('#{{formGridFilterRule['table_id']}}').bootgrid('reload');
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

        $(".disable_replyto").change(function(){
            const reply_to_tr = $(".enable_replyto").closest('tr');
            if ($(this).is(':checked')) {
                reply_to_tr.hide();
            } else {
                reply_to_tr.show();
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
    /* Animate move_up and move_down commands */
    @keyframes fadeHighlight {
        0%   { opacity: 0.2; }
        100% { opacity: 1; }
    }
    .highlight-animate {
        animation: fadeHighlight 1s ease-in-out;
    }
    /* Advanced mode tooltip */
    .tooltip-inner {
        max-width: 300px;
        text-align: left;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#rules">{{ lang._('Rules') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="rules" class="tab-pane fade in active">
        <div class="hidden">
            <!-- filter per type container -->
            <div id="type_filter_container" class="btn-group">
                <select id="category_filter" data-title="{{ lang._('Categories') }}" class="selectpicker" data-live-search="true" data-size="5" multiple data-width="200px">
                </select>
            </div>
            <div id="internal_rule_selector" class="btn-group" style="width: 200px; margin-left: 10px;">
                <div class="dropdown bootstrap-select show-tick bs3" style="width: 200px;">
                    <select id="include_internal_select" data-title="{{ lang._('Show internal rules') }}" class="selectpicker" data-live-search="false" multiple data-width="200px">
                        <option value="internal">{{ lang._('Internal (Start of Ruleset)') }}</option>
                        <option value="internal2">{{ lang._('Internal (End of Ruleset)') }}</option>
                        <option value="floating">{{ lang._('Floating') }}</option>
                        <option value="group">{{ lang._('Group') }}</option>
                    </select>
                    <!-- selectpicker will generate the button markup -->
                </div>
            </div>
        </div>
        <!-- tab page "rules" -->
        {{ partial('layout_partials/base_bootgrid_table', formGridFilterRule + {'command_width': '10em'}) }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/filter/apply'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogFilterRule,'id':formGridFilterRule['edit_dialog_id'],'label':lang._('Edit rule')])}}
