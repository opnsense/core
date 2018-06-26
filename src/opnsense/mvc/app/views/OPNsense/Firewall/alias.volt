
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
</style>
<script>
    $( document ).ready(function() {
        $("#grid-aliases").UIBootgrid(
            {   search:'/api/firewall/alias/searchItem',
                get:'/api/firewall/alias/getItem/',
                set:'/api/firewall/alias/setItem/',
                add:'/api/firewall/alias/addItem/',
                del:'/api/firewall/alias/delItem/',
                toggle:'/api/firewall/alias/toggleItem/'
            }
        );

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
                $tr.append(
                    $("<td/>").append($("<select class='selectpicker geoip_select' multiple='multiple' data-id='"+'geoip_region_'+item+"'/>"))
                );
                $("#alias_type_geoip > tbody").append($tr);
            });

            $.each(data, function(country, item) {
                if (item.region != null) {
                    $('.geoip_select[data-id="geoip_region_'+item.region+'"').append(
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
                // link on change event back
                $("#alias\\.content").on('tokenize:tokens:change', function(e, value){
                    $("#alias\\.content").change();
                });
            });
        });

        /**
         * Type selector, show correct type input.
         */
        $("#alias\\.type").change(function(){
            $(".alias_type").hide();
            switch ($(this).val()) {
                case 'geoip':
                    $("#alias_type_geoip").show();
                    break;
                default:
                    $("#alias_type_default").show();
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
                    geo_item.find('option[value="'+items[i]+'"]').prop("selected", true);
                }

            });
            $(".geoip_select").selectpicker('refresh');
        })


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
                            <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
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
                                <tr id="row_alias.enabled" >
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
                                <tr id="row_alias.name" >
                                    <td>
                                        <div class="control-label" id="control_label_alias.name">
                                            <a id="help_for_alias.name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Name')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" size="50" id="alias.name"  >
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
                                <tr id="row_alias.type" >
                                    <td>
                                        <div class="control-label" id="control_label_alias.type">
                                            <i class="fa fa-info-circle text-muted"></i>
                                            <b>{{lang._('Type')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <select  id="alias.type" class="selectpicker" data-width="334px"></select>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.type"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.content" >
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
                                        <table class="table table-condensed alias_table" id="alias_type_geoip" style="display: none;">
                                            <thead>
                                            <tr>
                                                <th>region</th>
                                                <th>countries</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>

                                        <br/><a href="#" class="text-danger" id="clear-options_alias.content"><i class="fa fa-times-circle"></i>
                                        <small>{{lang._('Clear All')}}</small></a>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.content"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.description" >
                                    <td>
                                        <div class="control-label" id="control_label_alias.description">
                                            <a id="help_for_alias.description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Description')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" size="50" id="alias.description"  >
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
