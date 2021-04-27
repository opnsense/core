
<link href="{{ cache_safe('/ui/css/flags/flag-icon.css') }}" rel="stylesheet">
<style>
    @media (min-width: 768px) {
        #DialogAlias > .modal-dialog {
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
            toggle:'/api/firewall/alias/toggleItem/',
            options:{
                requestHandler: function(request){
                    let selected = $('#type_filter').find("option:selected").val();
                    if ( $('#type_filter').val().length > 0) {
                        request['type'] = $('#type_filter').val();
                    }
                    return request;
                }
            }
        });

        $("#type_filter").change(function(){
            $('#grid-aliases').bootgrid('reload');
        });

        $("#grid-aliases").bootgrid().on("loaded.rs.jquery.bootgrid", function (e){
            // network content field should only contain valid aliases, we need to fetch them separately
            // since the form field misses context
            ajaxGet("/api/firewall/alias/listNetworkAliases", {}, function(data){
                $("#network_content").empty();
                $.each(data, function(alias, value) {
                    $("#network_content").append($("<option/>").val(alias).text(value));
                });
                $("#network_content").selectpicker('refresh');
            });
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
         * hook network group type changes, replicate content
         */
        $("#network_content").change(function(){
            let $content = $("#alias\\.content");
            $content.unbind('tokenize:tokens:change');
            $content.tokenize2().trigger('tokenize:clear');
            $("#network_content").each(function () {
               $.each($(this).val(), function(key, item){
                   $content.tokenize2().trigger('tokenize:tokens:add', item);
               });
            });
            $content.tokenize2().trigger('tokenize:select');
            $content.tokenize2().trigger('tokenize:dropdown:hide');
            // link on change event back
            $content.on('tokenize:tokens:change', function(e, value){
               $content.change();
            });
        });


        /**
         * Type selector, show correct type input.
         */
        $("#alias\\.type").change(function(){
            $(".alias_type").hide();
            $("#row_alias\\.updatefreq").hide();
            $("#row_alias\\.interface").hide();
            $("#copy-paste").hide();
            switch ($(this).val()) {
                case 'geoip':
                    $("#alias_type_geoip").show();
                    $("#alias\\.proto").selectpicker('show');
                    break;
                case 'external':
                    break;
                case 'networkgroup':
                    $("#alias_type_networkgroup").show();
                    $("#alias\\.proto").selectpicker('hide');
                    break;
                case 'dynipv6host':
                    $("#row_alias\\.interface").show();
                    $("#alias_type_default").show();
                    break;
                case 'urltable':
                    $("#row_alias\\.updatefreq").show();
                    /* FALLTROUGH */
                default:
                    $("#alias_type_default").show();
                    $("#alias\\.proto").selectpicker('hide');
                    $("#copy-paste").show();
                    break;
            }
            if ($(this).val() === 'port') {
                $("#row_alias\\.counters").hide();
            } else {
                $("#row_alias\\.counters").show();
            }
        });

        /**
         * push content changes to GeopIP selectors and network groups
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
            $("#network_content").each(function(){
                var network_item = $(this);
                network_item.val([]);
                for (var i=0; i < items.length; ++i) {
                    network_item.find('option[value="' + $.escapeSelector(items[i]) + '"]').prop("selected", true);
                }
            });
            $("#network_content").selectpicker('refresh');
        });

        /**
         * update expiration (updatefreq is splitted into days and hours on the form)
         */
        $("#alias\\.updatefreq").change(function(){
            if ($(this).val() !== "") {
                var freq = $(this).val();
                var freq_hours = ((parseFloat(freq) - parseInt(freq)) * 24.0).toFixed(2);
                var freq_days = parseInt(freq);
                $("input[data-id=\"alias.updatefreq_hours\"]").val(freq_hours);
                $("input[data-id=\"alias.updatefreq_days\"]").val(freq_days);
            } else {
                $("input[data-id=\"alias.updatefreq_hours\"]").val("");
                $("input[data-id=\"alias.updatefreq_days\"]").val("");
            }
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
         * export all configured aliases to json
         */
        $("#exportbtn").click(function(){
            ajaxGet("/api/firewall/alias/export", {}, function(data, status){
                if (data.aliases) {
                  let output_data = JSON.stringify(data, null, 2);
                  let a_tag = $('<a></a>').attr('href','data:application/json;charset=utf8,' + encodeURIComponent(output_data))
                      .attr('download','aliases.json').appendTo('body');

                  a_tag.ready(function() {
                      if ( window.navigator.msSaveOrOpenBlob && window.Blob ) {
                          var blob = new Blob( [ output_data ], { type: "text/csv" } );
                          navigator.msSaveOrOpenBlob( blob, 'aliases.json' );
                      } else {
                          a_tag.get(0).click();
                      }
                  });
                }
            });
        });

        /**
         * import aliases from json file
         */
        $("#importbtn").click(function(){
            let $msg = $("<div/>");
            let $imp_file = $("<input type='file' id='import_filename' />");
            let $table = $("<table class='table table-condensed'/>");
            let $tbody = $("<tbody/>");
            $table.append(
              $("<thead/>").append(
                $("<tr>").append(
                  $("<th/>").text("{{ lang._('source')}}")
                ).append(
                  $("<th/>").text("{{ lang._('message')}}")
                )
              )
            );
            $table.append($tbody);
            $table.append(
              $("<tfoot/>").append(
                $("<tr/>").append($("<td colspan='2'/>").text(
                  "{{ lang._('Please note that none of the aliases provided are imported due to the errors above')}}"
                ))
              )
            );

            $imp_file.click(function(){
                // make sure upload resets when new file is provided (bug in some browsers)
                this.value = null;
            });
            $msg.append($imp_file);
            $msg.append($("<hr/>"));
            $msg.append($table);
            $table.hide();


            BootstrapDialog.show({
              title: "{{ lang._('Import aliases') }}",
              message: $msg,
              type: BootstrapDialog.TYPE_INFO,
              draggable: true,
              buttons: [{
                  label: '<i class="fa fa-cloud-upload" aria-hidden="true"></i>',
                  action: function(sender){
                      $table.hide();
                      $tbody.empty();
                      if ($imp_file[0].files[0] !== undefined) {
                          const reader = new FileReader();
                          reader.readAsBinaryString($imp_file[0].files[0]);
                          reader.onload = function(readerEvt) {
                              let import_data = null;
                              try {
                                  import_data = JSON.parse(readerEvt.target.result);
                              } catch (error) {
                                  $tbody.append(
                                    $("<tr/>").append(
                                      $("<td>").text("*")
                                    ).append(
                                      $("<td>").text(error)
                                    )
                                  );
                                  $table.show();
                              }
                              if (import_data !== null) {
                                  ajaxCall("/api/firewall/alias/import", {'data': import_data}, function(data,status) {
                                      if (data.validations !== undefined) {
                                          Object.keys(data.validations).forEach(function(key) {
                                              $tbody.append(
                                                $("<tr/>").append(
                                                  $("<td>").text(key)
                                                ).append(
                                                  $("<td>").text(data.validations[key])
                                                )
                                              );
                                          });
                                          $table.show();
                                      } else {
                                          sender.close();
                                      }
                                  });
                              }
                          }
                      }
                  }
              },{
                 label:  "{{ lang._('Close') }}",
                 action: function(sender){
                    sender.close();
                 }
               }]
            });
        });

        function loadSettings() {
            let data_get_map = {'frm_GeopIPSettings':"/api/firewall/alias/getGeoIP"};
            mapDataToFormUI(data_get_map).done(function(data){
                if (data.frm_GeopIPSettings.alias.geoip.usages) {
                    if (!data.frm_GeopIPSettings.alias.geoip.subscription && !data.frm_GeopIPSettings.alias.geoip.address_count) {
                        let $msg = "{{ lang._('In order to use GeoIP, you need to configure a source in the GeoIP settings tab') }}";
                        BootstrapDialog.show({
                          title: "{{ lang._('GeoIP') }}",
                          message: $msg,
                          type: BootstrapDialog.TYPE_INFO,
                          buttons: [{
                              label:  "{{ lang._('Close') }}",
                              action: function(sender){
                                 sender.close();
                              }
                          }]
                        });
                    }
                }
                formatTokenizersUI();
                $('.selectpicker').selectpicker('refresh');
            });
        }
        loadSettings();

        /**
         * reconfigure
         */
        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/firewall/alias/set", 'frm_GeopIPSettings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            },
            onAction: function(data, status){
                loadSettings();
            }
        });

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click();
        } else {
            $('a[href="#aliases"]').click();
        }

        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        // move filter into action header
        $("#type_filter_container").detach().prependTo('#grid-aliases-header > .row > .actionBar > .actions');

    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#aliases" id="aliases_tab">{{ lang._('Aliases') }}</a></li>
    <li><a data-toggle="tab" href="#geoip" id="geoip_tab">{{ lang._('GeoIP settings') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="aliases" class="tab-pane fade in">
        <div class="row">
            <section class="col-xs-12">
                <div class="content-box">
                    <div class="hidden">
                        <!-- filter per type container -->
                        <div id="type_filter_container" class="btn-group">
                            <select id="type_filter"  data-title="{{ lang._('Filter type') }}" class="selectpicker" multiple="multiple" data-width="200px">
                                <option value="host">{{ lang._('Host(s)') }}</option>
                                <option value="network">{{ lang._('Network(s)') }}</option>
                                <option value="mac">{{ lang._('MAC address') }}</option>
                                <option value="port">{{ lang._('Port(s)') }}</option>
                                <option value="url">{{ lang._('URL (IPs)') }}</option>
                                <option value="urltable">{{ lang._('URL Table (IPs)') }}</option>
                                <option value="geoip">{{ lang._('GeoIP') }}</option>
                                <option value="networkgroup">{{ lang._('Network group') }}</option>
                                <option value="dynipv6host">{{ lang._('Dynamic IPv6 Host') }}</option>
                                <option value="external">{{ lang._('External (advanced)') }}</option>
                            </select>
                        </div>
                    </div>
                    <table id="grid-aliases" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAlias" data-editAlert="aliasChangeMessage" data-store-selection="true">
                        <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="name" data-width="20em" data-type="string">{{ lang._('Name') }}</th>
                            <th data-column-id="type" data-width="12em" data-type="string">{{ lang._('Type') }}</th>
                            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                            <th data-column-id="content" data-type="string">{{ lang._('Content') }}</th>
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
                        <tr>
                            <td></td>
                            <td>
                                <button id="exportbtn" data-toggle="tooltip" title="{{ lang._('download')}}" type="button" class="btn btn-xs btn-default"> <span class="fa fa-cloud-download"></span></button>
                                <button id="importbtn" data-toggle="tooltip" title="{{ lang._('upload')}}" type="button" class="btn btn-xs btn-default"> <span class="fa fa-cloud-upload"></span></button>
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </div>
    <div id="geoip" class="tab-pane fade in">
      {{ partial("layout_partials/base_form",['fields':formGeoIPSettings,'id':'frm_GeopIPSettings'])}}
    </div>
</div>
<section class="page-content-main">
  <div class="content-box">
    <div class="col-md-12">
        <br/>
        <div id="aliasChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/firewall/alias/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring aliases') }}"
                type="button"
        ></button>
        <br/><br/>
    </div>
  </div>
</section>

{# Edit dialog #}
<div class="modal fade" id="DialogAlias" tabindex="-1" role="dialog" aria-labelledby="DialogAliasLabel" aria-hidden="true">
    <div class="modal-backdrop fade in"></div>
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
                                            <b>{{lang._('Enabled')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="checkbox" id="alias.enabled">
                                        <div class="hidden" data-for="help_for_alias.enabled">
                                            <small>{{lang._('Enable this alias')}}</small>
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
                                            <a id="help_for_alias.frequency" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Refresh Frequency')}}</b>
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
                                        <div class="hidden" data-for="help_for_alias.frequency">
                                            <small>
                                                {{lang._('The frequency that the list will be refreshed, in days + hours, so 1 day and 8 hours means the alias will be refreshed after 32 hours. ')}}
                                            </small>
                                        </div>
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
                                        <div class="alias_type" id="alias_type_networkgroup">
                                            <select multiple="multiple" class="selectpicker" id="network_content" data-live-search="true">
                                            </select>
                                        </div>
                                        <table class="table table-condensed alias_table alias_type" id="alias_type_geoip" style="display: none;">
                                            <thead>
                                            <tr>
                                                <th>{{lang._('Region')}}</th>
                                                <th>{{lang._('Countries')}}</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>

                                        <a href="#" class="text-danger" id="clear-options_alias.content"><i class="fa fa-times-circle"></i>
                                        <small>{{lang._('Clear All')}}</small></a><span id="copy-paste">
                                        &nbsp;&nbsp;<a href="#" class="text-danger" id="copy-options_alias.content"><i class="fa fa-copy"></i>
                                        <small>{{ lang._('Copy') }}</small></a>
                                        &nbsp;&nbsp;<a href="#" class="text-danger" id="paste-options_alias.content" style="display:none"><i class="fa fa-paste"></i>
                                        <small>{{ lang._('Paste') }}</small></a></span>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.content"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.interface">
                                    <td>
                                        <div class="alias interface" id="alias_interface">
                                            <a id="help_for_alias.interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Interface')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <select  class="selectpicker" id="alias.interface" data-width="200px"></select>
                                        <div class="hidden" data-for="help_for_alias.interface">
                                            <small>{{lang._('Select the interface for the V6 dynamic IP')}}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.interface"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.counters">
                                    <td>
                                        <div class="control-label" id="control_label_alias.counters">
                                            <a id="help_for_alias.counters" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Statistics')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="checkbox" id="alias.counters">
                                        <div class="hidden" data-for="help_for_alias.counters">
                                            <small>{{lang._('Maintain a set of counters for each table entry')}}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.enabled"></span>
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
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="btn_DialogAlias_save">{{ lang._('Save') }}
                    <i id="btn_formDialogAlias_save_progress" class=""></i></button>
            </div>
        </div>
    </div>
</div>
