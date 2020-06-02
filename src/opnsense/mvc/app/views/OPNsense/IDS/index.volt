{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}
<style>
    .tooltip-inner {
        min-width: 250px;
    }

    .ids-alert-info > tbody > tr > td {
        padding-top: 2px !important;
        padding-bottom: 2px !important;
    }
    .ids-alert-info > tbody > tr > td:first-child {
        width: 150px;
    }
    @media (min-width: 768px) {
        .suricata-alert > .modal-dialog {
            width: 90%;
            max-width:1200px;
        }
    }
</style>

<script>

    $( document ).ready(function() {
        var interface_descriptions = {};
        //
        var data_get_map = {'frm_GeneralSettings':"/api/ids/settings/get"};

        /**
         * update service status
         */
        function updateStatus() {
            updateServiceControlUI('ids');
        }

        /**
         * list all known classtypes and add to selection box
         */
        function updateRuleMetadata() {
            ajaxGet("/api/ids/settings/listRuleMetadata", {}, function(data, status) {
                if (status == "success") {
                    $('#rulemetadata').empty();
                    $.each(Object.assign({}, {'action': ['drop', 'alert']}, data), function(key, values) {
                        let $optgroup = $("<optgroup/>");
                        $optgroup.prop('label', key);
                        for (let i=0; i < values.length ; ++i) {
                            $optgroup.append(
                              $("<option>").val(values[i]).text(values[i].substr(0, 50))
                                .data('property', key)
                                .data('value', values[i])
                                .data('content', "<span class='badge'>"+key+"\\"+values[i].substr(0, 50)+"</span>")
                            );
                        }
                        $('#rulemetadata').append($optgroup);
                    });
                    $('.selectpicker').selectpicker('refresh');
                    // link on change event
                    $('#rulemetadata').on('change', function(){
                        $('#grid-installedrules').bootgrid('reload');
                    });
                }
            });
        }

        /**
         * update list of available alert logs
         */
        function updateAlertLogs() {
            ajaxGet("/api/ids/service/getAlertLogs", {}, function(data, status) {
                if (status == "success") {
                    $('#alert-logfile').html("");
                    $.each(data, function(key, value) {
                        if (value['sequence'] == undefined) {
                            $('#alert-logfile').append($("<option/>").data('filename', value['filename']).attr("value",'none').text(value['modified']));
                        } else {
                            $('#alert-logfile').append($("<option/>").data('filename', value['filename']).attr("value",value['sequence']).text(value['modified']));
                        }
                    });
                    $('.selectpicker').selectpicker('refresh');
                    // link on change event
                    $('#alert-logfile').on('change', function(){
                        $('#grid-alerts').bootgrid('reload');
                    });
                }
            });
        }

        /**
         * Add classtype / action to rule filter
         */
        function addRuleFilters(request) {
            $('#rulemetadata').find("option:selected").each(function(){
                let filter_name = $(this).data('property');
                if (request[filter_name] === undefined) {
                    request[filter_name] = $(this).data('value');
                } else {
                    request[filter_name] += "," + $(this).data('value');
                }
            });
            return request;
        }

        /**
         *  add filter criteria to log query
         */
        function addAlertQryFilters(request) {
            var selected_logfile =$('#alert-logfile').find("option:selected").val();
            var selected_max_entries =$('#alert-logfile-max').find("option:selected").val();
            var search_phrase = $("#inputSearchAlerts").val();

            // add loading overlay
            $('#processing-dialog').modal('show');
            $("#grid-alerts").bootgrid().on("loaded.rs.jquery.bootgrid", function (e){
                $('#processing-dialog').modal('hide');
            });


            if ( selected_logfile != "") {
                request['fileid'] = selected_logfile;
                request['rowCount'] = selected_max_entries;
                request['searchPhrase'] = search_phrase;
            }
            return request;
        }

        // load initial data
        function loadGeneralSettings() {
            // hide detect_custom fields when not applicable
            $("#ids\\.general\\.detect\\.Profile").change(function(){
                if ($("#ids\\.general\\.detect\\.Profile").val() == "custom") {
                    $(".detect_custom").closest("tr").removeClass("hidden");
                } else {
                    $(".detect_custom").closest("tr").addClass("hidden");
                }
            });
            mapDataToFormUI(data_get_map).done(function(data){
                // set schedule updates link to cron
                $.each(data.frm_GeneralSettings.ids.general.UpdateCron, function(key, value) {
                    if (value.selected == 1) {
                        $("#scheduled_updates").attr("href","/ui/cron/item/open/"+key);
                        $("#scheduled_updates").show();
                    }
                });
                formatTokenizersUI();
                $('.selectpicker').selectpicker('refresh');
            });
        }


        /**
         * toggle selected items
         * @param gridId: grid id to to use
         * @param url: ajax action to call
         * @param state: 0/1/undefined
         * @param combine: number of keys to combine (separate with ,)
         *                 try to avoid too much items per call (results in too long url's)
         */
        function actionToggleSelected(gridId, url, state, combine) {
            var defer_toggle = $.Deferred();
            var rows = $("#"+gridId).bootgrid('getSelectedRows');
            if (rows != undefined){
                var deferreds = [];
                if (state != undefined) {
                    var url_suffix = state;
                } else {
                    var url_suffix = "";
                }
                var base = $.when({});
                var keyset = [];
                $.each(rows, function(key, uuid){
                    // only perform action in visible items
                    if ($("#"+gridId).find("tr[data-row-id='"+uuid+"']").is(':visible')) {
                        keyset.push(uuid);
                        if ( combine === undefined || keyset.length > combine || rows[rows.length - 1] === uuid) {
                            var call_url = url + keyset.join(',') +'/'+url_suffix;
                            base = base.then(function() {
                                var defer = $.Deferred();
                                ajaxCall(call_url, {}, function(){
                                    defer.resolve();
                                });
                                return defer.promise();
                            });
                            keyset = [];
                        }
                    }
                });
                // last action in the list, reload grid and release this promise
                base.then(function(){
                    $("#"+gridId).bootgrid("reload");
                    let changemsg = $("#"+gridId).data("editalert");
                    if (changemsg !== undefined) {
                        $("#"+changemsg).slideDown(1000, function(){
                            setTimeout(function(){
                                $("#"+changemsg).slideUp(2000);
                            }, 2000);
                        });
                    }
                    defer_toggle.resolve();
                });
            } else {
                defer_toggle.resolve();
            }
            return defer_toggle.promise();
        }

        /*************************************************************************************************************
         * UI load grids (on tab change)
         *************************************************************************************************************/

        /**
         * load content on tab changes
         */
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            loadGeneralSettings();
            if (e.target.id == 'download_settings_tab') {
                /**
                 * grid for installable rule files
                 */
                $('#grid-rule-files').bootgrid('destroy'); // always destroy previous grid, so data is always fresh
                $("#grid-rule-files").UIBootgrid({
                    search:'/api/ids/settings/listRulesets',
                    get:'/api/ids/settings/getRuleset/',
                    set:'/api/ids/settings/setRuleset/',
                    toggle:'/api/ids/settings/toggleRuleset/',
                    options:{
                        navigation:0,
                        formatters:{
                            editor: function (column, row) {
                                return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.filename + "\"><span class=\"fa fa-pencil\"></span></button>";
                            },
                            boolean: function (column, row) {
                                if (parseInt(row[column.id], 2) == 1) {
                                    return "<span class=\"fa fa-check command-boolean\" data-value=\"1\" data-row-id=\"" + row.filename + "\"></span>";
                                } else {
                                    return "<span class=\"fa fa-times command-boolean\" data-value=\"0\" data-row-id=\"" + row.filename + "\"></span>";
                                }
                            }
                        },
                        converters: {
                            // show "not installed" for rules without timestamp (not on disc)
                            rulets: {
                                from: function (value) {
                                    return value;
                                },
                                to: function (value) {
                                    if ( value == null ) {
                                        return "{{ lang._('not installed') }}";
                                    } else {
                                        return value;
                                    }
                                }
                            }
                        }
                    }
                });
                // display file settings (if available)
                ajaxGet("/api/ids/settings/getRulesetproperties", {}, function(data, status) {
                    if (status == "success") {
                        var rows = [];
                        // generate rows with field references
                        $.each(data['properties'], function(key, value) {
                            rows.push('<tr><td>'+key+'</td><td><input class="rulesetprop" data-id="'+key+'" type="text"></td></tr>');
                        });
                        $("#grid-rule-files-settings > tbody").html(rows.join(''));
                        // update with data
                        $(".rulesetprop").each(function(){
                            $(this).val(data['properties'][$(this).data('id')]);
                        });
                        if (rows.length > 0) {
                            $("#grid-rule-files-settings").parent().parent().show();
                            $("#updateSettings").show();
                        }
                    }
                });
                /**
                 * disable/enable[with optional filter] selected rulesets
                 */
                $("#disableSelectedRuleSets").unbind('click').click(function(){
                    actionToggleSelected('grid-rule-files', '/api/ids/settings/toggleRuleset/', 0, 20);
                });
                $("#enableSelectedRuleSets").unbind('click').click(function(){
                    actionToggleSelected('grid-rule-files', '/api/ids/settings/toggleRuleset/', 1, 20);
                });
                $("#enabledropSelectedRuleSets").unbind('click').click(function(){
                    actionToggleSelected('grid-rule-files', '/api/ids/settings/toggleRuleset/', "drop", 20);
                });
                $("#enableclearSelectedRuleSets").click(function(){
                    actionToggleSelected('grid-rule-files', '/api/ids/settings/toggleRuleset/', "clear", 20);
                });
            } else if (e.target.id == 'rule_tab'){
                //
                // activate rule tab page
                //

                // delay refresh for a bit
                setTimeout(updateRuleMetadata, 500);

                /**
                 * grid installed rules
                 */
                $('#grid-installedrules').bootgrid('destroy'); // always destroy previous grid, so data is always fresh
                $("#grid-installedrules").UIBootgrid(
                        {   search:'/api/ids/settings/searchinstalledrules',
                            get:'/api/ids/settings/getRuleInfo/',
                            set:'/api/ids/settings/setRule/',
                            options:{
                                requestHandler:addRuleFilters,
                                rowCount:[10, 25, 50,100,500,1000] ,
                                formatters:{
                                    rowtoggle: function (column, row) {
                                        var toggle = " <button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.sid + "\"><span class=\"fa fa-pencil\"></span></button> ";
                                        if (parseInt(row[column.id], 2) == 1) {
                                            toggle += "&nbsp; <span style=\"cursor: pointer;\" class=\"fa fa-check-square-o command-toggle\" data-value=\"1\" data-row-id=\"" + row.sid + "\"></span>";
                                        } else {
                                            toggle += "&nbsp; <span style=\"cursor: pointer;\" class=\"fa fa-square-o command-toggle\" data-value=\"0\" data-row-id=\"" + row.sid + "\"></span>";
                                        }
                                        return toggle;
                                    }
                                }
                            },
                            toggle:'/api/ids/settings/toggleRule/'
                        }
                );
                /**
                 * disable/enable [+action] selected rules
                 */
                $("#disableSelectedRules").unbind('click').click(function(event){
                    event.preventDefault();
                    $("#disableSelectedRules > span").removeClass("fa-square-o").addClass("fa-spinner fa-pulse");
                    actionToggleSelected('grid-installedrules', '/api/ids/settings/toggleRule/', 0, 100).done(function(){
                        $("#disableSelectedRules > span").removeClass("fa-spinner fa-pulse");
                        $("#disableSelectedRules > span").addClass("fa-square-o");
                    });
                });
                $("#enableSelectedRules").unbind('click').click(function(){
                    $("#enableSelectedRules > span").removeClass("fa-check-square-o").addClass("fa-spinner fa-pulse");
                    actionToggleSelected('grid-installedrules', '/api/ids/settings/toggleRule/', 1, 100).done(function(){
                        $("#enableSelectedRules > span").removeClass("fa-spinner fa-pulse").addClass("fa-check-square-o");
                    });
                });
                $("#alertSelectedRules").unbind('click').click(function(){
                    $("#alertSelectedRules > span").addClass("fa-spinner fa-pulse");
                    actionToggleSelected('grid-installedrules', '/api/ids/settings/toggleRule/', "alert", 100).done(function(){
                        $("#alertSelectedRules > span").removeClass("fa-spinner fa-pulse");
                    });
                });
                $("#dropSelectedRules").unbind('click').click(function(){
                    $("#dropSelectedRules > span").addClass("fa-spinner fa-pulse");
                    actionToggleSelected('grid-installedrules', '/api/ids/settings/toggleRule/', "drop", 100).done(function(){
                        $("#dropSelectedRules > span").removeClass("fa-spinner fa-pulse");
                    });
                });
            } else if (e.target.id == 'alert_tab') {
                updateAlertLogs();
                /**
                 * grid query alerts
                 */
                $('#grid-alerts').bootgrid('destroy'); // always destroy previous grid, so data is always fresh
                var grid_alerts = $("#grid-alerts").UIBootgrid(
                        {   search:'/api/ids/service/queryAlerts',
                            get:'/api/ids/service/getAlertInfo/',
                            options:{
                                multiSelect:false,
                                selection:false,
                                templates : {
                                    header: ""
                                },
                                requestHandler:addAlertQryFilters,
                                formatters:{
                                    info: function (column, row) {
                                        return "<button type=\"button\" class=\"btn btn-xs btn-default command-alertinfo\" data-row-id=\"" + row.filepos + "/" + row.fileid + "\"><span class=\"fa fa-pencil\"></span></button> ";
                                    }
                                },
                                converters: {
                                    // convert interface to name
                                    interface: {
                                        from: function (value) { return value; },
                                        to: function (value) {
                                          if (value == null || value == undefined) {
                                              return "";
                                          }
                                          return interface_descriptions[value.replace(/\+$/, '')];
                                        }
                                    }
                                }
                            }
                        });
                // tooltip wide fields in alert grid
                grid_alerts.on("loaded.rs.jquery.bootgrid", function(){
                    $("#grid-alerts > tbody > tr > td").each(function(){
                        if ($(this).outerWidth() < $(this)[0].scrollWidth) {
                            var grid_td = $("<span/>");
                            grid_td.html($(this).html());
                            grid_td.tooltip({title: $(this).text()});
                            $(this).html(grid_td);
                        }
                    });
                });
                // hook in alert details on alertinfo command
                grid_alerts.on("loaded.rs.jquery.bootgrid", function(){
                    grid_alerts.find(".command-alertinfo").on("click", function(e) {
                        var uuid=$(this).data("row-id");
                        ajaxGet('/api/ids/service/getAlertInfo/' + uuid, {}, function(data, status) {
                                if (status == 'success') {
                                    ajaxGet("/api/ids/settings/getRuleInfo/"+data['alert_sid'], {}, function(rule_data, rule_status) {
                                        var tbl = $('<table class="table table-condensed table-hover ids-alert-info"/>');
                                        var tbl_tbody = $("<tbody/>");
                                        var alert_fields = {};
                                        alert_fields['timestamp'] = "{{ lang._('Timestamp') }}";
                                        alert_fields['alert'] = "{{ lang._('Alert') }}";
                                        alert_fields['alert_sid'] = "{{ lang._('Alert sid') }}";
                                        alert_fields['proto'] = "{{ lang._('Protocol') }}";
                                        alert_fields['src_ip'] = "{{ lang._('Source IP') }}";
                                        alert_fields['dest_ip'] = "{{ lang._('Destination IP') }}";
                                        alert_fields['src_port'] = "{{ lang._('Source port') }}";
                                        alert_fields['dest_port'] = "{{ lang._('Destination port') }}";
                                        alert_fields['in_iface'] = "{{ lang._('Interface') }}";
                                        alert_fields['http.hostname'] = "{{ lang._('http hostname') }}";
                                        alert_fields['http.url'] = "{{ lang._('http url') }}";
                                        alert_fields['http.http_user_agent'] = "{{ lang._('http user_agent') }}";
                                        alert_fields['http.http_content_type'] = "{{ lang._('http content_type') }}";
                                        alert_fields['tls.subject'] = "{{ lang._('tls subject') }}";
                                        alert_fields['tls.issuerdn'] = "{{ lang._('tls issuer') }}";
                                        alert_fields['tls.session_resumed'] = "{{ lang._('tls session resumed') }}";
                                        alert_fields['tls.fingerprint'] = "{{ lang._('tls fingerprint') }}";
                                        alert_fields['tls.serial'] = "{{ lang._('tls serial') }}";
                                        alert_fields['tls.version'] = "{{ lang._('tls version') }}";
                                        alert_fields['tls.notbefore'] = "{{ lang._('tls notbefore') }}";
                                        alert_fields['tls.notafter'] = "{{ lang._('tls notafter') }}";

                                        $.each( alert_fields, function( fieldname, fielddesc ) {
                                            var data_ptr = data;
                                            $.each(fieldname.split('.'),function(indx, keypart){
                                                if (data_ptr != undefined) {
                                                    data_ptr = data_ptr[keypart];
                                                }
                                            });

                                            if (data_ptr != undefined) {
                                                var row = $("<tr/>");
                                                row.append($("<td/>").text(fielddesc));
                                                if (fieldname == 'in_iface' && interface_descriptions[data_ptr.replace(/\+$/, '')] != undefined) {
                                                    row.append($("<td/>").text(interface_descriptions[data_ptr.replace(/\+$/, '')]));
                                                } else {
                                                    row.append($("<td/>").text(data_ptr));
                                                }
                                                tbl_tbody.append(row);
                                            }
                                        });

                                        if (rule_data.action != undefined) {
                                            var alert_select = $('<select/>');
                                            var alert_enabled = $('<input type="checkbox"/>');
                                            if (rule_data.enabled == '1') {
                                                alert_enabled.prop('checked', true);
                                            }
                                            $.each(rule_data.action, function(key, value){
                                                var opt = $('<option/>').attr("value", key).text(value.value);
                                                if (value.selected == 1) {
                                                    opt.attr('selected', 'selected');
                                                }
                                                alert_select.append(opt);
                                            });
                                            tbl_tbody.append(
                                              $("<tr/>").append(
                                                $("<td/>").text("{{ lang._('Configured action') }}"),
                                                $("<td id='alert_sid_action'/>").append(
                                                  alert_enabled, $("<span/>").html("&nbsp; <strong>{{lang._('Enabled')}}</strong><br/>"), alert_select, $("<br/>")
                                                )
                                              )
                                            );
                                            alert_select.change(function(){
                                                var rule_params = {'action': alert_select.val()};
                                                if (alert_enabled.is(':checked')) {
                                                    rule_params['enabled'] = 1;
                                                } else {
                                                    rule_params['enabled'] = 0;
                                                }
                                                ajaxCall("/api/ids/settings/setRule/"+data['alert_sid'], rule_params, function() {
                                                    $("#alert_sid_action > small").remove();
                                                    $("#alert_sid_action").append($('<small/>').html("{{ lang._('Changes will be active after apply (rules tab)') }}"));
                                                });
                                            });
                                            alert_enabled.change(function(){
                                                alert_select.change();
                                            });
                                        }
                                        if (data['payload_printable'] != undefined && data['payload_printable'] != null) {
                                            tbl_tbody.append(
                                              $("<tr/>").append(
                                                $("<td colspan=2/>").append(
                                                  $("<strong/>").text("{{ lang._('Payload') }}")
                                                )
                                              )
                                            );

                                            var row = $("<tr/>");
                                            row.append( $("<td colspan=2/>").append($("<pre/>").html($("<code/>").text(data['payload_printable']))));
                                            tbl_tbody.append(row);
                                        }

                                        tbl.append(tbl_tbody);
                                        stdDialogInform("{{ lang._('Alert info') }}", tbl, "{{ lang._('Close') }}", undefined, "info", 'suricata-alert');
                                        alert_select.selectpicker('refresh');
                                  });
                                }
                            });
                    }).end();
              });
            } else if (e.target.id == 'userrules_tab') {
                $('#grid-userrules').bootgrid('destroy'); // always destroy previous grid, so data is always fresh
                $("#grid-userrules").UIBootgrid({
                        search:'/api/ids/settings/searchUserRule',
                        get:'/api/ids/settings/getUserRule/',
                        set:'/api/ids/settings/setUserRule/',
                        add:'/api/ids/settings/addUserRule/',
                        del:'/api/ids/settings/delUserRule/',
                        toggle:'/api/ids/settings/toggleUserRule/'
                    }
                );
            }
        });



        /*************************************************************************************************************
         * UI button Commands
         *************************************************************************************************************/

        /**
         * save settings and reconfigure ids
         */
        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/ids/settings/set", 'frm_GeneralSettings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            }
        });
        $("#updateSettings").click(function(){
            $("#updateSettings_progress").addClass("fa fa-spinner fa-pulse");
            var settings = {};
            $(".rulesetprop").each(function(){
                settings[$(this).data('id')] = $(this).val();
            });
            ajaxCall("/api/ids/settings/setRulesetproperties", {'properties': settings}, function(data,status) {
                $("#updateSettings_progress").removeClass("fa fa-spinner fa-pulse");
                $("#rulesetChangeMessage").slideDown(1000, function(){
                    setTimeout(function(){
                        $("#rulesetChangeMessage").slideUp(2000);
                    }, 2000);
                });
            });
        });

        /**
         * update (userdefined) rules
         */
        $(".act_update").SimpleActionButton();

        /**
         * update rule definitions
         */
        $("#updateRulesAct").SimpleActionButton({
            onAction: function(){
                $('#grid-rule-files').bootgrid('reload');
            }
        });

        /**
         * link query alerts button.
         */
        $("#actQueryAlerts").click(function(){
            $('#grid-alerts').bootgrid('reload');
        });
        $("#inputSearchAlerts").keypress(function (e) {
            if (e.which == 13) {
                $("#actQueryAlerts").click();
            }
        });

        $("#grid-rule-files-search").keydown(function (e) {
            var searchString = $(this).val();
            $("#grid-rule-files > tbody > tr").each(function(){
                var itemName = $(this).children('td:eq(1)').html();
                if (itemName.toLowerCase().indexOf(searchString.toLowerCase())>=0) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        /**
         * Initialize
         */
        // fetch interface mappings on load
        ajaxGet('/api/diagnostics/interface/getInterfaceNames', {}, function(data, status) {
            interface_descriptions = data;
        });

        updateStatus();

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click();
        } else {
            $('a[href="#settings"]').click();
        }

        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        // delete selected alert log
        $("#actDeleteLog").click(function(){
            var selected_log = $("#alert-logfile > option:selected");
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_DANGER,
                title: '{{ lang._('Remove log file ') }} ' + selected_log.html(),
                message: '{{ lang._('Removing this file will cleanup disk space, but cannot be undone.') }}',
                buttons: [{
                    icon: 'fa fa-trash-o',
                    label: '{{ lang._('Yes') }}',
                    cssClass: 'btn-primary',
                    action: function(dlg){
                        ajaxCall("/api/ids/service/dropAlertLog/", {filename: selected_log.data('filename')}, function(data,status){
                            updateAlertLogs();
                        });
                        dlg.close();
                    }
                }, {
                    label: '{{ lang._('Close') }}',
                    action: function(dlg){
                        dlg.close();
                    }
                }]
            });

        });
    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#settings" id="settings_tab">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#download_settings" id="download_settings_tab">{{ lang._('Download') }}</a></li>
    <li><a data-toggle="tab" href="#rules" id="rule_tab">{{ lang._('Rules') }}</a></li>
    <li><a data-toggle="tab" href="#userrules" id="userrules_tab">{{ lang._('User defined') }}</a></li>
    <li><a data-toggle="tab" href="#alerts" id="alert_tab">{{ lang._('Alerts') }}</a></li>
    <li><a href="" id="scheduled_updates" style="display:none">{{ lang._('Schedule') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="settings" class="tab-pane fade in">
        {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_GeneralSettings'])}}
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/ids/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring IDS') }}"
                    data-service-widget="ids"
                    type="button"
            ></button>
            <br/>
            <br/>
        </div>
    </div>
    <div id="download_settings" class="tab-pane fade in">
      <!-- add installable rule files -->
      <table class="table table-striped table-condensed table-responsive">
          <tbody>
            <tr>
                <td><div class="control-label">
                    <i class="fa fa-info-circle text-muted"></i>
                    <b>{{ lang._('Rulesets') }}</b>
                    </div>
                </td>
                <td>
                  <table class="table table-condensed table-responsive">
                    <tr>
                      <td>
                        <div class="row">
                          <div class="col-xs-9">
                            <div>
                              <button data-toggle="tooltip" id="enableSelectedRuleSets" type="button" class="btn btn-xs btn-default btn-primary">
                                  {{ lang._('Enable selected') }}
                              </button>
                              <button data-toggle="tooltip" id="enabledropSelectedRuleSets" type="button" class="btn btn-xs btn-default btn-primary">
                                  {{ lang._('Enable (drop filter)') }}
                              </button>
                              <button data-toggle="tooltip" id="enableclearSelectedRuleSets" type="button" class="btn btn-xs btn-default btn-primary">
                                  {{ lang._('Enable (clear filter)') }}
                              </button>
                              <button data-toggle="tooltip" id="disableSelectedRuleSets" type="button" class="btn btn-xs btn-default btn-primary">
                                  {{ lang._('Disable selected') }}
                              </button>
                            </div>
                          </div>
                          <div class="col-xs-3" style="padding-top:0px;">
                            <input type="text" placeholder="{{ lang._('Search') }}" id="grid-rule-files-search" value=""/>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </table>
                  <div style="max-height: 400px; width: 100%; margin: 0; overflow-y: auto;" id="grid-rule-files-container">
                    <table id="grid-rule-files" class="table table-condensed table-hover table-striped table-responsive" data-editAlert="rulesetChangeMessage" data-editDialog="DialogRuleset">
                        <thead>
                        <tr>
                            <th data-column-id="filename" data-type="string" data-visible="false" data-identifier="true">{{ lang._('Filename') }}</th>
                            <th data-column-id="description" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Description') }}</th>
                            <th data-column-id="modified_local" data-type="rulets" data-sortable="false" data-visible="true">{{ lang._('Last updated') }}</th>
                            <th data-column-id="enabled" data-formatter="boolean" data-sortable="false" data-width="10em">{{ lang._('Enabled') }}</th>
                            <th data-column-id="filter_str" data-type="string" data-identifier="true">{{ lang._('Filter') }}</th>
                            <th data-column-id="edit" data-formatter="editor" data-sortable="false" data-width="10em">{{ lang._('Edit') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                  </div>
                </td>
            </tr>
            <tr style="display:none">
                <td><div class="control-label">
                    <i class="fa fa-info-circle text-muted"></i>
                    <b>{{ lang._('Settings') }}</b>
                    </div>
                </td>
                <td>
                  <table id="grid-rule-files-settings" class="table-condensed table-hover">
                    <tbody>
                    </tbody>
                  </table>
                </td>
            </tr>
          </tbody>
      </table>
      <div class="col-md-12">
          <div id="rulesetChangeMessage" class="alert alert-info" style="display: none" role="alert">
              {{ lang._('Please use "Download & Update Rules" to fetch your initial ruleset, automatic updating can be scheduled after the first download.') }}
          </div>
          <hr/>
          <button class="btn btn-primary" style="display:none" id="updateSettings" type="button"><b>{{ lang._('Save') }}</b> <i id="updateSettings_progress" class=""></i></button>

          <button class="btn btn-primary" id="updateRulesAct"
                  data-endpoint='/api/ids/service/updateRules'
                  data-label="{{ lang._('Download & Update Rules') }}"
                  data-error-title="{{ lang._('Error reconfiguring IDS') }}"
                  data-service-widget="ids"
                  type="button"
          ></button>
          <br/><br/>
      </div>
    </div>
    <div id="rules" class="tab-pane fade in">
        <div class="bootgrid-header container-fluid">
            <div class="row">
                <div class="col-sm-12 actionBar">
                    <select id="rulemetadata" title="{{ lang._('Filters') }}" class="selectpicker" multiple=multiple data-live-search="true" data-size="10" data-width="100%">
                    </select>
                </div>
            </div>
        </div>

        <!-- tab page "installed rules" -->
        <table id="grid-installedrules" data-store-selection="true" class="table table-condensed table-hover table-striped table-responsive" data-editAlert="ruleChangeMessage" data-editDialog="DialogRule">
            <thead>
            <tr>
                <th data-column-id="sid" data-type="numeric" data-visible="true" data-identifier="true" data-width="6em">{{ lang._('sid') }}</th>
                <th data-column-id="action" data-type="string">{{ lang._('Action') }}</th>
                <th data-column-id="source" data-type="string">{{ lang._('Source') }}</th>
                <th data-column-id="classtype" data-type="string">{{ lang._('ClassType') }}</th>
                <th data-column-id="msg" data-type="string">{{ lang._('Message') }}</th>
                <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false" data-width="10em">{{ lang._('Info / Enabled') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td>
                    <button title="{{ lang._('Disable selected') }}" id="disableSelectedRules" data-toggle="tooltip" type="button" class="btn btn-xs btn-default"><span class="fa fa-square-o"></span></button>
                    <button title="{{ lang._('Enable selected') }}" id="enableSelectedRules" data-toggle="tooltip" type="button" class="btn btn-xs btn-default"><span class="fa fa-check-square-o"></span></button>
                    <button title="{{ lang._('Alert selected') }}" id="alertSelectedRules" data-toggle="tooltip" type="button" class="btn btn-xs btn-default"><span class="fa"></span>{{ lang._('alert') }}</button>
                    <button title="{{ lang._('Drop selected') }}" id="dropSelectedRules" data-toggle="tooltip" type="button" class="btn btn-xs btn-default"><span class="fa"></span>{{ lang._('drop') }}</button>
                </td>
                <td></td>
            </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
            <div id="ruleChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <hr/>
            <button class="btn btn-primary act_update"
                    data-endpoint='/api/ids/service/reloadRules'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring IDS') }}"
                    type="button"
            ></button>
            <br/>
            <br/>
        </div>
    </div>
    <div id="userrules" class="tab-pane fade in">
        <!-- tab page "userrules" -->
        <table id="grid-userrules" data-store-selection="true" class="table table-condensed table-hover table-striped table-responsive" data-editAlert="userdefineChangeMessage" data-editDialog="DialogUserDefined">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false" data-width="10em">{{ lang._('Enabled') }}</th>
                    <th data-column-id="action" data-type="string" data-sortable="true">{{ lang._('Action') }}</th>
                    <th data-column-id="description" data-type="string" data-sortable="true">{{ lang._('Description') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr >
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
            <div id="userdefineChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them with the button below') }}
            </div>
            <hr/>
            <button class="btn btn-primary act_update"
                    data-endpoint='/api/ids/service/reloadRules'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring IDS') }}"
                    type="button"
            ></button>
            <br/>
            <br/>
        </div>
    </div>
    <div id="alerts" class="tab-pane fade in">
        <!-- tab page "alerts" -->
        <div id="grid-alerts-header" class="bootgrid-header container-fluid">
            <div class="row">
                <div class="col-sm-12 actionBar">
                    <select id="alert-logfile" class="selectpicker" data-width="200px"></select>
                    <span id="actDeleteLog" class="btn btn-lg fa fa-trash" style="cursor: pointer;" title="{{ lang._('Delete Alert Log') }}"></span>
                    <select id="alert-logfile-max" class="selectpicker" data-width="80px">
                        <option value="7">7</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                        <option value="500">500</option>
                        <option value="1000">1000</option>
                        <option value="-1">{{ lang._('All') }}</option>
                    </select>
                    <div class="search form-group">
                        <div class="input-group">
                            <input class="search-field form-control" placeholder="{{ lang._('Search') }}" type="text" id="inputSearchAlerts">
                            <span id="actQueryAlerts" class="icon input-group-addon fa fa-refresh" title="{{ lang._('Query') }}" style="cursor: pointer;"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <table id="grid-alerts" data-store-selection="true" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
              <tr>
                  <th data-column-id="timestamp" data-type="string" data-sortable="false">{{ lang._('Timestamp') }}</th>
                  <th data-column-id="alert_sid" data-type="string" data-sortable="false"  data-width="70px">{{ lang._('SID') }}</th>
                  <th data-column-id="alert_action" data-type="string" data-sortable="false" data-width="70px">{{ lang._('Action') }}</th>
                  <th data-column-id="in_iface" data-type="interface" data-sortable="false" data-width="100px">{{ lang._('Interface') }}</th>
                  <th data-column-id="src_ip" data-type="string" data-sortable="false" data-width="150px">{{ lang._('Source') }}</th>
                  <th data-column-id="src_port" data-type="string" data-sortable="false" data-width="70px">{{ lang._('Port') }}</th>
                  <th data-column-id="dest_ip" data-type="string" data-sortable="false" data-width="150px">{{ lang._('Destination') }}</th>
                  <th data-column-id="dest_port" data-type="string" data-sortable="false" data-width="70px">{{ lang._('Port') }}</th>
                  <th data-column-id="alert" data-type="string" data-sortable="false" >{{ lang._('Alert') }}</th>
                  <th data-column-id="info" data-formatter="info" data-sortable="false" data-width="4em">{{ lang._('Info') }}</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog_processing") }}

{{ partial("layout_partials/base_dialog",['fields':formDialogRule,'id':'DialogRule','label':lang._('Rule details'),'hasSaveBtn':'true','msgzone_width':1])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogRuleset,'id':'DialogRuleset','label':lang._('Ruleset details'),'hasSaveBtn':'true','msgzone_width':1])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogUserDefined,'id':'DialogUserDefined','label':lang._('Rule details'),'hasSaveBtn':'true'])}}
