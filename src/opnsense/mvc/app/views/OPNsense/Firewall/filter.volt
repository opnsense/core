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

        $(".disable_replyto").change(function(){
            let reply_to_tr = $(".enable_replyto").closest('tr');
            if ($(this).is(':checked')) {
                reply_to_tr.hide();
            } else {
                reply_to_tr.show();
            }
        });

        if(window.location.pathname.split('/')[3]=='port_forward') {
            var target = '';
            var targetInput = '';
            var target_port = '';
            var pool_options = '';
            var filter_rule = '';
            grid.on('loaded.rs.jquery.bootgrid', function() {     
                $("button.command-edit, button.command-copy, button.command-add").on('click', function() {
                    target = ''; targetInput = ''; target_port = ''; pool_options = ''; filter_rule = '';
                    var row_id = $(this).data('row-id');
                    if (!$("#rule\\.nordr").prop('checked') && row_id && row_id!=='') {
                        ajaxGet('/api/firewall/{{ruleController}}/get_rule/' + row_id, [], function(data, status) {
                                if (data.rule.target) {
                                    target = data.rule.target;
                                    if (target.indexOf(".") !== -1 || target.indexOf(":") !== -1) {
                                        targetInput = target;
                                        target = '';
                                    }
                                    target_port = data.rule.target_port;
                                    pool_options = data.rule.pool_options;
                                    var selectedOption = null;
                                    for (var key in pool_options) {
                                        if (pool_options.hasOwnProperty(key)) {
                                            if (pool_options[key].selected === 1) {
                                                selectedOption = key;
                                                break;
                                            }
                                        }
                                    }
                                    pool_options = selectedOption;
                                    filter_rule = data.rule.filter_rule;
                                    var selectedRule = null;
                                    for (var key in filter_rule) {
                                        if (filter_rule.hasOwnProperty(key)) {
                                            if (filter_rule[key].selected === 1) {
                                                selectedRule = key;
                                                break;
                                            }
                                        }
                                    }
                                    filter_rule = selectedRule;
                                }
                                $("#rule\\.nordr").trigger('change');
                        });
                    } else {
                        target = '';
                        targetInput = '';
                        target_port = '';
                        pool_options = '';
                        filter_rule = '';
                    }
                });
                if ($("#grid-rules thead tr").length === 1) {
                    $("#grid-rules thead").prepend('<tr><th style="width:2em;"></th><th colspan="4" style="width:20em;"></th><th colspan="2" style="width:10em;">Source</th><th colspan="2" style="width:10em;">Destination</th><th colspan="2" style="width:10em;">NAT</th><th colspan="2" style="width:12em;"></th></tr>');
                } else if ($("#grid-rules thead tr").length === 2) {
                    var firstRow = $("#grid-rules thead tr").first().children('th').length;
                    var newCount = $("#grid-rules tfoot tr td").first().attr("colspan");
                    $("#grid-rules tfoot tr td").first().attr("colspan", newCount - firstRow);
                }
            });

            $("#rule\\.nordr").change(function(){
                if ($("#rule\\.nordr").prop('checked')) {
                    $("#row_rule\\.target").addClass("hidden");
                    $("#row_rule\\.target :input").val('').prop("disabled", true);
                    $("#row_rule\\.target_port").addClass("hidden");
                    $("#row_rule\\.target_port :input").val('').prop("disabled", true);
                    $("#row_rule\\.pool_options").addClass("hidden");
                    $("#row_rule\\.pool_options :input").val('').prop("disabled", true);    
                    $("#row_rule\\.filter_rule").addClass("hidden");
                    $("#row_rule\\.filter_rule :input").val('').prop("disabled", true);
                } else {
                    $("#row_rule\\.target").removeClass("hidden");
                    $('#select_rule\\.target select').val(target).prop('disabled', false);
                    $('#select_rule\\.target button').prop('disabled', false);
                    $('#select_rule\\.target input').val(targetInput).prop('disabled', false);
                    $("#row_rule\\.target_port").removeClass("hidden");
                    $("#row_rule\\.target_port :input").val(target_port).prop('disabled', false);
                    $("#row_rule\\.pool_options").removeClass("hidden");
                    $("#row_rule\\.pool_options :input").val(pool_options).prop('disabled', false);
                    $("#row_rule\\.filter_rule").removeClass("hidden");
                    $("#row_rule\\.filter_rule :input").val(filter_rule).prop('disabled', false);
                    $('.dropdown-toggle').removeClass('disabled');
                }
                $('#select_rule\\.target select').trigger('change');
                $('#select_rule\\.pool_options select').trigger('change');
                $('#select_rule\\.filter_rule select').trigger('change');
            });

            $(".dropdown-item-checkbox").change(function() {
                $("#grid-rules thead tr:first").remove();
            });
        }
    });
</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#rules">{{ lang._('Rules') }}</a></li>
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
        <!-- tab page "rules" -->
        <table id="grid-rules" class="table table-condensed table-hover table-striped" data-editDialog="DialogFilterRule" data-editAlert="FilterRuleChangeMessage">
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
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogFilterRule,'id':'DialogFilterRule','label':lang._('Edit rule')])}}
