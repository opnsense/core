{#
 # Copyright (c) 2018-2023 Deciso B.V.
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

<link href="{{ cache_safe('/ui/css/flags/flag-icon.css') }}" rel="stylesheet">
<style>
    @media (min-width: 768px) {
        #DialogAlias > .modal-dialog {
            width: 90%;
            max-width:1200px;
        }
    }

    .dropdown-fixup {
        overflow-x: hidden !important;
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

    ul.dropdown-menu.inner > li > a > span.text  {
        width: 100% !important;
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
                    if ( $('#type_filter').val().length > 0) {
                        request['type'] = $('#type_filter').val();
                    }
                    if ( $('#category_filter').val().length > 0) {
                        request['category'] = $('#category_filter').val();
                    }
                    return request;
                },
                formatters: {
                    commands: function (column, row) {
                        if (row.uuid.includes('-') === true) {
                            // exclude buttons for internal aliases (which uses names instead of valid uuid's)
                            return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                                '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                                '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                        }
                    },
                    rowtoggle: function (column, row) {
                        if (!row.uuid.includes('-')) {
                            return '<span class="fa fa-fw fa-check-square-o"></span>';
                        } else if (parseInt(row[column.id], 2) === 1) {
                            return '<span style="cursor: pointer;" class="fa fa-fw fa-check-square-o command-toggle bootgrid-tooltip" data-value="1" data-row-id="' + row.uuid + '"></span>';
                        } else {
                            return '<span style="cursor: pointer;" class="fa fa-fw fa-square-o command-toggle bootgrid-tooltip" data-value="0" data-row-id="' + row.uuid + '"></span>';
                        }
                    },
                    name : function (column, row) {
                        if (row.categories_uuid.length === 0) {
                            return row.name;
                        } else {
                            let html = [row.name + ' '];
                            for (i=0; i < row.categories_uuid.length ; ++i) {
                                let item = $("#"+row.categories_uuid[i]);
                                if (item && item.data('color')) {
                                    html.push("<i class='fa fa-circle category-item' style='color:#"+
                                           item.data('color')+"' title='"+item.text()+"'></i>");
                                }
                            }
                            return html.join('&nbsp;');
                        }
                    },
                    timestamp: function (column, row) {
                        if (row[column.id] && row[column.id].includes('.')) {
                            return row[column.id].split('.')[0].replace('T', ' ');
                        }
                        return row[column.id];
                    }
                }
            }
        });

        $("#type_filter, #category_filter").change(function(){
            $('#grid-aliases').bootgrid('reload');
        });

        $("#grid-aliases").bootgrid().on("loaded.rs.jquery.bootgrid", function (e){
            // network content field should only contain valid aliases, we need to fetch them separately
            // since the form field misses context
            ajaxGet("/api/firewall/alias/list_network_aliases", {}, function(data){
                $("#network_content").empty();
                $.each(data, function(alias, value) {
                    let $opt = $("<option/>").val(alias).text(value.name);
                    $opt.data('subtext', value.description);
                    $("#network_content").append($opt);
                });
                $("#network_content").selectpicker('refresh');
            });
            $(".category-item").tooltip({container: 'body'});

            /**
             * export all configured aliases to json
             */
             $("#exportbtn").unbind('click').click(function(){
                let selected_rows = $("#grid-aliases").bootgrid("getSelectedRows");
                let params = {};
                if (selected_rows.length > 0) {
                    params['ids'] = selected_rows;
                }
                ajaxCall("/api/firewall/alias/export", params, function(data, status){
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
            $("#importbtn").unbind('click').click(function(){
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
        }).on("load.rs.jquery.bootgrid", function (e){
            // reload categories before grid load
            ajaxCall('/api/firewall/alias/list_categories', {}, function(data, status){
                if (data.rows !== undefined) {
                    let current_selection = $("#category_filter").val();
                    $("#category_filter").empty();
                    for (i=0; i < data.rows.length ; ++i) {
                        let row = data.rows[i];
                        let opt_val = $('<div/>').html(row.name).text();
                        let bgcolor = row.color != "" ? row.color : '31708f;'; // set category color
                        let option = $("<option/>").val(row.uuid).html(row.name);
                        if (row.used > 0) {
                            option.data(
                              'content',
                              "<span>"+opt_val + "</span>"+
                              "<span style='background:#"+bgcolor+";' class='badge pull-right'>" + row.used + "</span>"
                            );
                            option.data('color', bgcolor);
                            option.attr('id', row.uuid);
                        }

                        $("#category_filter").append(option);
                    }
                    $("#category_filter").val(current_selection);
                    $("#category_filter").selectpicker('refresh');
                }
            });
        });
        $('#grid-aliases').bootgrid().trigger('load.rs.jquery.bootgrid');

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
         * show tables limits, counts and alerts
         **/
        function get_aliases_stat() {
            ajaxGet("/api/firewall/alias/get_table_size", {}, function(data){
                perc_full = Math.round(100*data.used/data.size);
                $('#room_left').attr('aria-valuenow', perc_full + '%').css("width", perc_full + "%");
                $('#entries_bar > span > span').text(perc_full + "% (" + data.used + "/" + data.size + ")");
                bar_color = (perc_full > 50) ? "orangered" : (perc_full < 50 && perc_full > 30) ? "yellowgreen" : "greenyellow";
                $('#room_left').css("background-color", bar_color);
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
                geo_select.append($("<select class='selectpicker geoip_select' multiple='multiple' data-size='10' data-live-search='true' data-container='body' data-id='"+'geoip_region_'+item+"'/>"));
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
            $("select.geoip_select").change(function(){
                // unlink on change event
                $("#alias\\.content").unbind('tokenize:tokens:change');
                // copy items from geoip fields to content field
                $("#alias\\.content").tokenize2().trigger('tokenize:clear');
                $("select.geoip_select").each(function () {
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
         * fetch user groups
         **/
        ajaxGet("/api/firewall/alias/list_user_groups", {}, function(data){
            $("#authgroup_content").empty();
            $.each(data, function(alias, value) {
                let $opt = $("<option/>").val(alias).text(value.name);
                $opt.data('subtext', value.description);
                $("#authgroup_content").append($opt);
            });
            $("#authgroup_content").selectpicker('refresh');
        });


        /**
         * hook network group type changes, replicate content
         */
        $("#network_content, #authgroup_content").change(function(){
            let target = $(this);
            let $content = $("#alias\\.content");
            $content.unbind('tokenize:tokens:change');
            $content.tokenize2().trigger('tokenize:clear');
            target.each(function () {
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
            $("#row_alias\\.authtype").hide();
            $("#row_alias\\.interface").hide();
            $("#row_alias\\.path_expression").hide();
            $("#copy-paste").hide();
            switch ($(this).val()) {
                case 'authgroup':
                    $("#alias_type_authgroup").show();
                    $("#alias\\.proto").selectpicker('hide');
                    break;
                case 'geoip':
                    $("#alias_type_geoip").show();
                    $("#alias\\.proto").selectpicker('show');
                    /* work around JS injection of nasty overflow scroll bar being injected */
                    $("#row_alias\\.type > td > .dropdown:last > .dropdown-menu > .inner").addClass('dropdown-fixup');
                    break;
                case 'asn':
                    $("#alias_type_default").show();
                    $("#alias\\.proto").selectpicker('show');
                    $("#copy-paste").show();
                    break;
                case 'external':
                    break;
                case 'networkgroup':
                    $("#alias_type_networkgroup").show();
                    $("#alias\\.proto").selectpicker('hide');
                    break;
                case 'dynipv6host':
                    $("#row_alias\\.interface").show();
                    $("#alias\\.proto").selectpicker('hide');
                    $("#alias_type_default").show();
                    break;
                case 'urljson':
                    $("#row_alias\\.path_expression").show();
                    /* FALLTHROUGH */
                case 'urltable':
                    $("#row_alias\\.updatefreq").show();
                    /* FALLTHROUGH */
                case 'url':
                    $("#row_alias\\.authtype").show();

                    $("#alias\\.authtype").change(function() {
                        $("#alias\\.username").hide();
                        $("#alias\\.password").hide();
                        switch ($(this).val()) {
                            case 'Basic':
                                $("#alias\\.username").show();
                                $("#alias\\.password").show().attr('placeholder', '{{lang._('Password')}}');
                                break;
                            case 'Bearer':
                                $("#alias\\.password").show().attr('placeholder', '{{lang._('API token')}}');
                                break;
                        }
                    });
                    $("#alias\\.authtype").change();
                    /* FALLTHROUGH */
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
            ['#authgroup_content', '#network_content', '.geoip_select'].forEach(function(target){
                $(target).each(function(){
                    var content_item = $(this);
                    content_item.val([]);
                    for (var i=0; i < items.length; ++i) {
                        content_item.find('option[value="' + $.escapeSelector(items[i]) + '"]').prop("selected", true);
                    }
                });
                $(target).selectpicker('refresh');
            });
            geoip_update_labels();
        });

        /**
         * update expiration (updatefreq is split into days and hours on the form)
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
                get_aliases_stat();
            });
        }
        loadSettings();


        /**
         * reconfigure
         */
        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/firewall/alias/set", 'frm_GeopIPSettings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
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
        // alias size in service container
        $("#aliases_stat").detach().prependTo('#service_status_container');
        $("#service_status_container").css('width', '250px');
        $("#aliases_stat").tooltip({placement: 'bottom'});



    });
</script>

<div id="aliases_stat"  title="{{ lang._('Current Tables Entries/Firewall Maximum Table Entries') }}">
    <div class="col-xs-1"><i class="fa fa-fw fa-info-circle"></i></div>
    <div id="entries_bar" class="progress" style="text-align: center;">
        <div id="room_left" class="progress-bar" role="progressbar" aria-valuenow="0%" aria-valuemin="0" aria-valuemax="100" style="width: 0%; z-index: 0;"></div>
        <span class="state_text" style="position:absolute;right:0;left:0;">
        <span>{{ lang._('loading data..') }}</span>
        </span>
    </div>
</div>
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
                            <select id="type_filter"  data-title="{{ lang._('Filter type') }}" class="selectpicker" data-size="10" data-live-search="true" multiple="multiple" data-width="200px">
                                <option value="host">{{ lang._('Host(s)') }}</option>
                                <option value="network">{{ lang._('Network(s)') }}</option>
                                <option value="port">{{ lang._('Port(s)') }}</option>
                                <option value="url">{{ lang._('URL (IPs)') }}</option>
                                <option value="urltable">{{ lang._('URL Table (IPs)') }}</option>
                                <option value="urljson">{{ lang._('URL Table in JSON format (IPs)') }}</option>
                                <option value="geoip">{{ lang._('GeoIP') }}</option>
                                <option value="networkgroup">{{ lang._('Network group') }}</option>
                                <option value="mac">{{ lang._('MAC address') }}</option>
                                <option value="asn">{{ lang._('BGP ASN') }}</option>
                                <option value="dynipv6host">{{ lang._('Dynamic IPv6 Host') }}</option>
                                <option value="authgroup">{{ lang._('(OpenVPN) user groups') }}</option>
                                <option value="internal">{{ lang._('Internal (automatic)') }}</option>
                                <option value="external">{{ lang._('External (advanced)') }}</option>
                            </select>
                            <select id="category_filter"  data-title="{{ lang._('Categories') }}" class="selectpicker" data-size="10" data-live-search="true" data-size="5"  multiple data-width="200px">
                            </select>
                        </div>
                    </div>
                    <table id="grid-aliases" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAlias" data-editAlert="aliasChangeMessage">
                        <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="name" data-width="20em" data-formatter="name">{{ lang._('Name') }}</th>
                            <th data-column-id="type" data-width="12em" data-type="string">{{ lang._('Type') }}</th>
                            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                            <th data-column-id="content" data-type="string">{{ lang._('Content') }}</th>
                            <th data-column-id="current_items" data-type="string">{{ lang._('Loaded#') }}</th>
                            <th data-column-id="last_updated"  data-formatter="timestamp" data-type="string">{{ lang._('Last updated') }}</th>
                            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                        <tr>
                            <td></td>
                            <td>
                                <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>
                                <button id="exportbtn" data-toggle="tooltip" title="{{ lang._('download')}}" type="button" class="btn btn-xs btn-default"> <span class="fa fa-fw fa-cloud-download"></span></button>
                                <button id="importbtn" data-toggle="tooltip" title="{{ lang._('upload')}}" type="button" class="btn btn-xs btn-default"> <span class="fa fa-fw fa-cloud-upload"></span></button>
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
<div class="modal fade" id="DialogAlias" tabindex="-1" role="dialog" aria-labelledby="DialogAliasLabel">
    <div class="modal-backdrop fade in"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ lang._('Close') }}"><span aria-hidden="true">&times;</span></button>
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
                                                {{lang._('The name must start with a letter or single underscore, be less than 32 characters and only consist of alphanumeric characters or underscores. Aliases can be nested using this name.')}}
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
                                        <select id="alias.type" class="selectpicker" data-container="body" data-width="245px"></select>
                                        <select id="alias.proto" multiple="multiple" title="IPv4, IPv6" class="selectpicker" data-container="body" data-width="100px"></select>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.type"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.categories">
                                    <td>
                                        <div class="control-label" id="control_label_alias.type">
                                            <a id="help_for_alias.categories" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Categories')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <select id="alias.categories" multiple="multiple" class="tokenize" data-container="body" data-width="348px"></select>
                                        <span class="hidden" data-for="help_for_alias.categories">
                                            {{lang._('For grouping purposes you may select multiple groups here to organize items.')}}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.categories"></span>
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
                                                    data-width="348px"
                                                    data-allownew="true"
                                                    data-nbdropdownelements="10"
                                                    data-live-search="true"
                                                    data-container="body"
                                                    data-separator="#10">
                                            </select>
                                        </div>
                                        <div class="alias_type" id="alias_type_networkgroup">
                                            <select multiple="multiple" class="selectpicker" id="network_content" data-container="body" data-size="10" data-live-search="true">
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
                                        <div class="alias_type" id="alias_type_authgroup" style="display: none;">
                                            <select multiple="multiple" class="selectpicker" id="authgroup_content" data-container="body" data-size="10" data-live-search="true">
                                            </select>
                                        </div>

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
                                <tr id="row_alias.path_expression">
                                    <td>
                                        <div class="control-label" id="control_label_alias.path_expression">
                                            <a id="help_for_alias.path_expression" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Path expression')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" size="50" id="alias.path_expression"/>
                                        <div class="hidden" data-for="help_for_alias.path_expression">
                                            <small>
                                                {{lang._('Simplified expression to select a field inside a container, a dot [.] is used as field separator (e.g. container.fieldname). Expressions using the jq language are also supported.')}}
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.authtype"></span>
                                    </td>
                                </tr>
                                <tr id="row_alias.authtype">
                                    <td>
                                        <div class="control-label" id="control_label_alias.authtype">
                                            <a id="help_for_alias.authtype" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                                            <b>{{lang._('Authorization')}}</b>
                                        </div>
                                    </td>
                                    <td>
                                        <select id="alias.authtype"  data-container="body" class="selectpicker" style="margin-bottom: 3px;"></select>
                                        <input type="text" placeholder="{{lang._('Username')}}" class="form-control" size="50" id="alias.username"/>
                                        <input type="password" class="form-control" size="50" id="alias.password"/>
                                        <div class="hidden" data-for="help_for_alias.authtype">
                                            <small>
                                                {{lang._('If the remote server enforces authorization, you can specify the authorization type here.')}}
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="help-block" id="help_block_alias.authtype"></span>
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
                                        <select  class="selectpicker" id="alias.interface" data-container="body" data-width="200px"></select>
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
