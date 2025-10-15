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

        // move filter into action header
        $("#type_filter_container").detach().insertAfter('#grid-rules-header .search');
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
        <table id="grid-rules" class="table table-condensed table-hover table-striped" data-editDialog="DialogFilterRule" data-editAlert="change_message_base_form">
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
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/' ~ ruleController ~ '/apply'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogFilterRule,'id':'DialogFilterRule','label':lang._('Edit rule')])}}
