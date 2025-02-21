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
        const grid = $("#{{formGridFilterRule['table_id']}}").UIBootgrid({
            search:'/api/firewall/filter/search_rule/',
            get:'/api/firewall/filter/get_rule/',
            set:'/api/firewall/filter/set_rule/',
            add:'/api/firewall/filter/add_rule/',
            del:'/api/firewall/filter/del_rule/',
            toggle:'/api/firewall/filter/toggle_rule/',
            options:{
                triggerEditFor: getUrlHash('edit'),
                initialSearchPhrase: getUrlHash('search'),
                requestHandler: function(request){
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    return request;
                }
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
        </div>
        <!-- tab page "rules" -->
        {{ partial('layout_partials/base_bootgrid_table', formGridFilterRule)}}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/filter/apply'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogFilterRule,'id':formGridFilterRule['edit_dialog_id'],'label':lang._('Edit rule')])}}
