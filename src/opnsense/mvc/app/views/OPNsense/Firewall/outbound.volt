<script>
    $( document ).ready(function() {
        let initial_load = true;
        let grid = $("#grid-rules").UIBootgrid({
            search:'/api/firewall/{{ruleController}}/search_rule/',
            get:'/api/firewall/{{ruleController}}/get_rule/',
            set:'/api/firewall/{{ruleController}}/set_rule/',
            add:'/api/firewall/{{ruleController}}/add_rule/',
            del:'/api/firewall/{{ruleController}}/del_rule/',
            toggle:'/api/firewall/{{ruleController}}/toggle_rule/',
            options:{
                requestHandler: function(request){
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    return request;
                }
            }
        });

        $("#grid-autorules").UIBootgrid({
            search:'/api/firewall/{{ruleController}}/search_auto_rule/'
        });

        grid.on("loaded.rs.jquery.bootgrid", function (e){
            // reload categories before grid load
            ajaxCall('/api/firewall/{{ruleController}}/list_categories', {}, function(data, status){
                if (data.rows !== undefined) {
                    let current_selection = $("#category_filter").val();
                    $("#category_filter").empty();
                    for (i=0; i < data.rows.length ; ++i) {
                        let row = data.rows[i];
                        let opt_val = $('<div/>').html(row.name).text();
                        let bgcolor = row.color != "" ? row.color : '31708f;'; // set category color
                        let option = $("<option/>").val(row.uuid).html(row.name);
                        if (row.used > 0) {
                            option.attr(
                              'data-content',
                              "<span>"+opt_val + "</span>"+
                              "<span style='background:#"+bgcolor+";' class='badge pull-right'>" + row.used + "</span>"
                            );
                            option.attr('id', row.uuid);
                        }

                        $("#category_filter").append(option);
                    }
                    $("#category_filter").val(current_selection);
                    $("#category_filter").selectpicker('refresh');
                }
            });
        });

        // open edit dialog when opened with a uuid reference
        if (window.location.hash !== "" && window.location.hash.split("-").length >= 4) {
            grid.on('loaded.rs.jquery.bootgrid', function(){
                if (initial_load) {
                    $(".command-edit:eq(0)").clone(true).data('row-id', window.location.hash.substr(1)).click();
                    initial_load = false;
                }
            });
        }

        $("#reconfigureAct").SimpleActionButton();
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
                        ajaxCall("/api/firewall/{{ruleController}}/revert/" + $("#revertToTime").val(), {}, function (data, status) {
                            if (data.status !== "ok") {
                                $("#revertToTime").parent().addClass("has-error");
                                $("#revertToTimeError").html(data.status);
                            } else {
                                std_bootgrid_reload("grid-rules");
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
        $("#type_filter_container").detach().prependTo('#grid-rules-header > .row > .actionBar > .actions');
        $("#category_filter").change(function(){
            $('#grid-rules').bootgrid('reload');
        });

        // replace all "net" selectors with details retrieved from "list_network_select_options" endpoint
        ajaxGet('/api/firewall/{{ruleController}}/list_network_select_options', [], function(data, status){
            if (data.single) {
                $(".net_selector").each(function(){
                    $(this).replaceInputWithSelector(data, $(this).hasClass('net_selector_multi'));
                    /* enforce single selection when "single host or network" or "any" are selected */
                    if ($(this).hasClass('net_selector_multi')) {
                        $("select[for='" + $(this).attr('id') + "']").on('shown.bs.select', function(){
                            $(this).data('previousValue', $(this).val());
                        }).change(function(){
                            let prev = Array.isArray($(this).data('previousValue')) ? $(this).data('previousValue') : [];
                            let is_single = $(this).val().includes('') || $(this).val().includes('any');
                            let was_single = prev.includes('') || prev.includes('any');
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

        ajaxGet('/api/firewall/{{ruleController}}/list_translation_network', [], function(data, status) {
            if (data.single) {
                let attr_id = $(".translation_net").attr('id');
                $(".translation_net").replaceInputWithSelector(data);
                $("select[for='" + attr_id + "']").change(function() {
                    var selectedValue = $("select[for='" + attr_id + "']").next().attr('title');
                    var nextRow = $("select[for='" + attr_id + "']").closest('tr').next('tr').find('input[type="text"]').first().closest('tr');
                    if (selectedValue === "Interface address") {
                        nextRow.find('input[type="text"]').val('');
                        nextRow.find('input[type="text"]').off('change');
                        nextRow.hide();
                    } else {
                        nextRow.show();
                        if (selectedValue === 'Single host or Network') {
                            nextRow.find('input[type="text"]').off('change').on('change', function() {
                                $("select[for='" + attr_id + "']").prop('selectedIndex', 1);
                            });
                        }
                    }
                });
            }
        });

        grid.on('loaded.rs.jquery.bootgrid', function() {
            var currentRows = grid.bootgrid('getCurrentRows');
            $("button.command-edit, button.command-copy, button.command-add").on('click', function() {
                if ($(this).hasClass('command-add')) {
                    $("#select_rule\\.target select").prop('selectedIndex', 0);
                }
                if ($(this).hasClass('command-edit') || $(this).hasClass('command-copy')) {
                    var row_id = $(this).data('row-id');
                    var target = '';
                    $.each(currentRows, function(index, row) {
                        if (row.uuid === row_id) {
                            target = row.target;
                            return false;
                        }
                    });
                    if (target=='Interface address' || target=='NO NAT') {
                        $("#select_rule\\.target select").prop('selectedIndex', 0);
                    } else {
                        var selected = false;
                        $("#select_rule\\.target select option").each(function() {
                            if ($(this).text() === target) {
                                selected = true;
                                $(this).prop("selected", true);
                            }
                        });
                        var targetValue = $("#select_rule\\.target select").val();        
                        if (!selected && target) {
                            $("#select_rule\\.target select").prop('selectedIndex', 1);
                        }
                        $("#rule\\.target").val(targetValue);
                    }
                }
                $("#select_rule\\.target select").trigger('change');
            });
        });
        
        ajaxGet('/api/firewall/{{ruleController}}/search_mode', [], function(data, status) {
            if (data.mode == '' || $.isEmptyObject(data.mode)) {
                $("#mode_automatic").prop("checked", true);
                showAutomaticRulesTab();
            } else {
                $("#mode_"+data.mode).prop("checked", true);
                if (data.mode=='advanced') {
                    showManualRulesTab();
                } else if (data.mode=='automatic') {
                    showAutomaticRulesTab();
                } else if (data.mode=='hybrid') {
                    $('#maindiv').removeClass('hidden');
                }
            }
        });

        $("#saveModeAction").on("click", function() {
            var selectedMode = $("input[name='mode']:checked").val();
            if (selectedMode) {
                $.ajax({
                    url: '/api/firewall/{{ruleController}}/save_mode',
                    type: 'POST',
                    data: {
                        mode: selectedMode
                    },
                    success: function(response) {
                        if (response.result === "saved") {
                            location.reload();
                        } else {
                            alert("Failed to save mode.");
                        }
                    },
                    error: function() {
                        alert("Error occurred while saving the mode.");
                    }
                });
            } else {
                alert("Please select a mode.");
            }
        });

        function showAutomaticRulesTab() {
            $("#manual").hide();
            $("#rules").hide();
            $("#autorules").show();
            $('#maintabs a[href="#autorules"]').tab('show');
            $('#maindiv').removeClass('hidden');
        }

        function showManualRulesTab() {           
            $("#automatic").hide();
            $("#autorules").hide();
            $("#rules").show();
            $('#maintabs a[href="#rules"]').tab('show');
            $('#maindiv').removeClass('hidden');
        }

        $(".pool_options").change(function() {
            let source_hash_key = $(".source_hash_key").closest('tr');
            source_hash_key.hide();
            if ($(".pool_options").val()=='source_hash') {
                source_hash_key.show();
            }
        });
    });
</script>


<div class="content-box">
    <table class="table table-striped table-condensed">
        <thead>
            <tr>
                <th colspan="4">{{ lang._('Mode') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <input name="mode" type="radio" id="mode_automatic" value="automatic" />
                </td>
                <td>
                    <label for="mode_automatic">
                    <strong>
                        {{ lang._('Automatic outbound NAT rule generation') }}<br />
                        {{ lang._('(no manual rules can be used)') }}
                    </strong>
                    </label>
                </td>
                <td>
                    <input name="mode" type="radio" id="mode_hybrid" value="hybrid" />
                </td>
                <td>
                    <label for="mode_hybrid">
                    <strong>
                        {{ lang._('Hybrid outbound NAT rule generation') }}<br />
                        {{ lang._('(automatically generated rules are applied after manual rules)') }}
                    </strong>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <input name="mode" type="radio" id="mode_advanced" value="advanced" />
                </td>
                <td>
                    <label for="mode_advanced">
                    <strong>
                        {{ lang._('Manual outbound NAT rule generation') }}<br />
                        {{ lang._('(no automatic rules are being generated)') }}
                    </strong>
                    </label>
                </td>
                <td>
                    <input name="mode" type="radio" id="mode_disabled" value="disabled" />
                </td>
                <td>
                    <label for="mode_disabled">
                    <strong>
                        {{ lang._('Disable outbound NAT rule generation') }}<br />
                        {{ lang._('(outbound NAT is disabled)') }}
                    </strong>
                    </label>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <button id="saveModeAction" name="save" type="submit" class="btn btn-primary" value="Save">{{ lang._('Save') }}</button>
                </td>
            </tr>
        </tbody>
    </table>
</div><br /><br />
<div id="maindiv" class="hidden">
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active" id="manual"><a data-toggle="tab" href="#rules">{{ lang._('Manual Rules') }}</a></li>
    <li id="automatic"><a data-toggle="tab" href="#autorules">{{ lang._('Automatic Rules') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="rules" class="tab-pane fade in active">
        <div class="hidden">
            <!-- filter per type container -->
            <div id="type_filter_container" class="btn-group">
                <select id="category_filter"  data-title="{{ lang._('Categories') }}" class="selectpicker" data-live-search="true" data-size="5"  multiple data-width="200px">
                </select>
            </div>
        </div>
        <!-- tab page "manual rules" -->
        <table id="grid-rules" class="table table-condensed table-hover table-striped" data-editDialog="DialogOutboundRule" data-editAlert="FilterRuleChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
{% for fieldlist in gridFields %}
                    <th
                        data-column-id="{{fieldlist['id']}}"
                        data-width="{{fieldlist['width']|default('')}}"
                        data-type="{{fieldlist['type']|default('string')}}"
                        data-formatter="{{fieldlist['formatter']|default('')}}"
                        data-visible="{{fieldlist['visible']|default('')}}"
                    >{{fieldlist['heading']|default('')}}</th>
{% endfor %}
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
        <div id="FilterRuleChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/firewall/{{ruleController}}/apply'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Filter load error') }}"
                type="button"
        ></button>
{% if SavePointBtns is defined %}
        <div class="pull-right">
            <button class="btn" id="savepointAct"
                    data-endpoint='/api/firewall/{{ruleController}}/savepoint'
                    data-label="{{ lang._('Savepoint') }}"
                    data-error-title="{{ lang._('snapshot error') }}"
                    type="button"
            ></button>
            <button  class="btn" id="revertAction">
                {{ lang._('Revert') }}
            </button>
        </div>
{% endif %}
        <br/><br/>
    </div>
    </div>
    <div id="autorules" class="tab-pane fade in">
        <!-- tab page "automatic rules" -->
        <table id="grid-autorules" class="table table-condensed table-hover table-striped" data-editDialog="DialogOutboundRule" data-editAlert="FilterRuleChangeMessage">
            <thead>
                <tr>
{% for fieldlist in gridFields|slice(2, gridFields|length) %}
                    <th
                        data-column-id="{{fieldlist['id']}}"
                        data-width="{{fieldlist['width']|default('')}}"
                        data-type="{{fieldlist['type']|default('string')}}"
                        data-formatter="{{fieldlist['formatter']|default('')}}"
                        data-visible="{{fieldlist['visible']|default('')}}"
                    >{{fieldlist['heading']|default('')}}</th>
{% endfor %}
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogOutboundRule,'id':'DialogOutboundRule','label':lang._('Edit rule')])}}