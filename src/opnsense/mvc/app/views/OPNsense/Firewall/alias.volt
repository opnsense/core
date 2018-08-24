
<link href="/ui/css/flags/flag-icon.css" rel="stylesheet">
<style>
    @media (min-width: 768px) {
        .modal-dialog {
            width: 90%;
            max-width:1200px;
        }
    }

    .alias_table {
        background-color: transparent !important;
    }

    .update_table {
        background-color: transparent !important;
    }

    .geo_area_check {
        cursor: pointer;
    }

    .geo_area_uncheck {
        cursor: pointer;
    }

    .geo_label {
        margin-bottom: 0px;
        font-style: italic;
    }
</style>
<script>
    $( document ).ready(function() {
        $("#grid-aliases").UIBootgrid({
                search:'/api/firewall/alias/searchItem',
                get:'/api/firewall/alias/getItem/',
                set:'/api/firewall/alias/setItem/',
                add:'/api/firewall/alias/addItem/',
                del:'/api/firewall/alias/delItem/',
                toggle:'/api/firewall/alias/toggleItem/'
        });

        /**
         * Open form with alias selected
         */
        if ("{{selected_alias}}" !== "") {
            // UIBootgrid doesn't return a promise, wait for some time before opening the requested item
            setTimeout(function(){
                ajaxGet("/api/firewall/alias/getAliasUUID/{{selected_alias}}", {}, function(data, status){
                    if (data.uuid !== undefined) {
                        var edit_item = $(".command-edit:eq(0)").clone(true);
                        edit_item.data('row-id', data.uuid).click();
                    }
                });
            }, 100);
        }

        /**
         * update geoip labels
         **/
        function geoip_update_labels() {
            $("select.geoip_select").each(function(){
                var option_count = $(this).find('option').length;
                var selected_count = $(this).find('option:selected').length;
                if (selected_count > 0) {
                    var label = "{{ lang._('%s out of %s selected')}}";
                    label = label.replace('%s', selected_count).replace('%s', option_count);
                    $("label[data-id='"+$(this).data('id')+"_label']").text(label);
                } else {
                    $("label[data-id='"+$(this).data('id')+"_label']").text("");
                }
            });
        }

        /**
         * fetch regions and countries for geoip selection
         */
        ajaxGet("/api/firewall/alias/listCountries", {}, function(data){
            var regions = [];
            $.each(data, function(country, item) {
                if (!regions.includes(item.region) && item.region != null) {
                    regions.push(item.region);
                }
            });
            regions.sort();
            regions.map(function(item){
                var $tr = $("<tr/>");
                $tr.append($("<td/>").text(item));
                var geo_select = $("<td/>");
                geo_select.append($("<select class='selectpicker geoip_select' multiple='multiple' data-id='"+'geoip_region_'+item+"'/>"));
                geo_select.append($("<i class=\"fa fa-fw geo_area_check fa-check-square-o\" aria-hidden=\"true\" data-id='"+'geoip_region_'+item+"'></i>"));
                geo_select.append($("<i class=\"fa fa-fw geo_area_uncheck fa-square-o\" aria-hidden=\"true\" data-id='"+'geoip_region_'+item+"'></i>"));
                geo_select.append($("<label class='geo_label' data-id='geoip_region_"+item+"_label'/>"));
                $tr.append(geo_select);
                $("#alias_type_geoip > tbody").append($tr);
            });

            $.each(data, function(country, item) {
                if (item.region != null) {
                    $('.geoip_select[data-id="geoip_region_'+item.region+'"]').append(
                        $("<option/>")
                            .val(country)
                            .data('icon', 'flag-icon flag-icon-' + country.toLowerCase() + ' flag-icon-squared')
                            .html(item.name)
                    );
                }
            });

            $(".geoip_select").selectpicker();
            $(".geoip_select").change(function(){
                // unlink on change event
                $("#alias\\.content").unbind('tokenize:tokens:change');
                // copy items from geoip fields to content field
                $("#alias\\.content").tokenize2().trigger('tokenize:clear');
                $(".geoip_select").each(function () {
                    $.each($(this).val(), function(key, item){
                        $("#alias\\.content").tokenize2().trigger('tokenize:tokens:add', item);
                    });
                });
                $("#alias\\.content").tokenize2().trigger('tokenize:select');
                $("#alias\\.content").tokenize2().trigger('tokenize:dropdown:hide');
                // link on change event back
                $("#alias\\.content").on('tokenize:tokens:change', function(e, value){
                    $("#alias\\.content").change();
                });
                geoip_update_labels();
            });
            $(".geo_area_check").click(function(){
                var area_id = $(this).data('id');
                var area_select = $(".geoip_select[data-id='"+area_id+"']");
                area_select.find('option').prop("selected", true);
                area_select.selectpicker('refresh');
                area_select.change();
            });
            $(".geo_area_uncheck").click(function(){
                var area_id = $(this).data('id');
                var area_select = $(".geoip_select[data-id='"+area_id+"']");
                area_select.find('option').prop("selected", false);
                area_select.selectpicker('refresh');
                area_select.change();
            });
        });

        /**
         * Type selector, show correct type input.
         */
        $("#alias\\.type").change(function(){
            $(".alias_type").hide();
            $("#row_alias\\.updatefreq").hide();
            switch ($(this).val()) {
                case 'geoip':
                    $("#alias_type_geoip").show();
                    $("#alias\\.proto").selectpicker('show');
                    break;
                case 'external':
                    break;
                case 'urltable':
                    $("#row_alias\\.updatefreq").show();
                default:
                    $("#alias_type_default").show();
                    $("#alias\\.proto").selectpicker('hide');
                    break;
            }
        });

        /**
         * push content changes to GeopIP selectors
         */
        $("#alias\\.content").change(function(){
            var items = $(this).val();
            $(".geoip_select").each(function(){
                var geo_item = $(this);
                geo_item.val([]);
                for (var i=0; i < items.length; ++i) {
                    geo_item.find('option[value="' + $.escapeSelector(items[i]) + '"]').prop("selected", true);
                }

            });
            $(".geoip_select").selectpicker('refresh');
            geoip_update_labels();
        });

        /**
         * update expiration (updatefreq is splitted into days and hours on the form)
         */
        $("#alias\\.updatefreq").change(function(){
            var freq = $(this).val();
            var freq_hours = ((parseFloat(freq) - parseInt(freq)) * 24.0).toFixed(2);
            var freq_days = parseInt(freq);
            $("input[data-id=\"alias.updatefreq_hours\"]").val(freq_hours);
            $("input[data-id=\"alias.updatefreq_days\"]").val(freq_days);
        });
        $(".updatefreq").keyup(function(){
            var freq = 0.0;
            if ($("input[data-id=\"alias.updatefreq_days\"]").val().trim() != "") {
                freq = parseFloat($("input[data-id=\"alias.updatefreq_days\"]").val());
            }
            if ($("input[data-id=\"alias.updatefreq_hours\"]").val().trim() != "") {
                freq += (parseFloat($("input[data-id=\"alias.updatefreq_hours\"]").val()) / 24.0);
            }
            if (freq != 0.0) {
                $("#alias\\.updatefreq").val(freq);
            } else {
                $("#alias\\.updatefreq").val("");
            }
        });

        /**
         * reconfigure
         */
        $("#reconfigureAct").click(function(){
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall("/api/firewall/alias/reconfigure", {}, function(data,status) {
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");
                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Error reconfiguring aliases') }}",
                        message: data['status'],
                        draggable: true
                    });
                }
            });
        });

    });
</script>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
                <div class="content-box">
                    <table id="grid-aliases" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAlias">
                        <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                            <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
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
                        <hr/>
                        <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="reconfigureAct_progress" class=""></i></button>
                        <br/><br/>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>


{# Edit dialog #}
<div class="modal fade" id="DialogAlias" tabindex="-1" role="dialog" aria-labelledby="DialogAliasLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="formDialogAliasLabel">{{lang._('Edit Alias')}}</h4>
            </div>
            <div class="modal-body">
                <form id="frm_DialogAlias">
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <colgroup>
                                <col class="col-md-3"/>
                                <col class="col-md-5"/>
                                <col class="col-md-4"/>
                            </colgroup>
                            <tbody>
                                <tr>
                                    <td></td>
                                    <td colspan="2" style="text-align:right;">
                                        <small>{{ lang._('full help') }} </small>
                                        <a href="#">
                                            <i class="fa fa-toggle-off text-danger" id="show_all_help_formDialogformDialogAlias">
                                            </i>
                                        </a>
                                    </td>
                                </tr>
                                <tr id="row_alias.enabled">
                                    <td>
                                        <div class="control-label" id="control_label_alias.enabled">
                                            <a id="help_for_alias.enabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>enabled</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="checkbox" id="alias.enabled">
                                        <div class="hidden" data-for="help_for_alias.enabled">
                                            <small>enable this alias</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.enabled"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.name">
                                    <td>
                                        <div class="control-label" id="control_label_alias.name">
                                            <a id="help_for_alias.name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Name')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" size="50" id="alias.name">
                                        <div class="hidden" data-for="help_for_alias.name">
                                            <small>
                                                {{lang._('The name of the alias may only consist of the characters "a-z, A-Z, 0-9 and _". Aliases can be nested using this name.')}}
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.name"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.type">
                                    <td>
                                        <div class="control-label" id="control_label_alias.type">
                                            <i class="fa fa-info-circle text-muted"></i>
                                            <b>{{lang._('Type')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <select id="alias.type" class="selectpicker" data-width="200px"></select>
                                        <select id="alias.proto" multiple="multiple" title="" class="selectpicker" data-width="110px"></select>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.type"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.updatefreq">
                                    <td>
                                        <div class="control-label" id="control_label_alias.updatefreq">
                                            <i class="fa fa-info-circle text-muted"></i>
                                            <b>{{lang._('Expiration')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" id="alias.updatefreq" style="display: none">
                                        <table class="table table-condensed update_table">
                                            <thead>
                                                <tr>
                                                    <th>{{lang._('Days')}}</th>
                                                    <th>{{lang._('Hours')}}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><input data-id="alias.updatefreq_days" type="text" class="updatefreq form-control"></td>
                                                    <td><input data-id="alias.updatefreq_hours" type="text" class="updatefreq form-control"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.updatefreq"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.content">
                                    <td>
                                        <div class="control-label" id="control_label_alias.content">
                                            <i class="fa fa-info-circle text-muted"></i>
                                            <b>{{lang._('Content')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="alias_type" id="alias_type_default">
                                            <select multiple="multiple"
                                                    id="alias.content"
                                                    class="tokenize"
                                                    data-width="334px"
                                                    data-allownew="true"
                                                    data-nbdropdownelements="10"
                                                    data-live-search="true"
                                                    data-separator="#10">
                                            </select>
                                        </div>
                                        <table class="table table-condensed alias_table alias_type" id="alias_type_geoip" style="display: none;">
                                            <thead>
                                            <tr>
                                                <th>region</th>
                                                <th>countries</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>

                                        <a href="#" class="text-danger" id="clear-options_alias.content"><i class="fa fa-times-circle"></i>
                                        <small>{{lang._('Clear All')}}</small></a>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.content"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.description">
                                    <td>
                                        <div class="control-label" id="control_label_alias.description">
                                            <a id="help_for_alias.description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Description')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" size="50" id="alias.description">
                                        <div class="hidden" data-for="help_for_alias.description">
                                            <small>{{lang._('You may enter a description here for your reference (not parsed).')}}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.description"></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
                <button type="button" class="btn btn-primary" id="btn_DialogAlias_save">{{ lang._('Save changes') }}
                    <i id="btn_formDialogAlias_save_progress" class=""></i></button>
            </div>
        </div>
    </div>
</div>
