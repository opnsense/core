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
        padding-bottom: : 2px !important;
    }
    .ids-alert-info > tbody > tr > td:first-child {
        width: 150px;
    }
    @media (min-width: 768px) {
        .modal-dialog {
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
        function updateRuleClassTypes() {
            ajaxGet(url="/api/ids/settings/listRuleClasstypes",sendData={}, callback=function(data, status) {
                if (status == "success") {
                    $('#ruleclass').html('<option value="">ALL</option>');
                    $.each(data['items'], function(key, value) {
                        $('#ruleclass').append($("<option></option>").attr("value",value).text(value));
                    });
                    $('.selectpicker').selectpicker('refresh');
                    // link on change event
                    $('#ruleclass').on('change', function(){
                        $('#grid-installedrules').bootgrid('reload');
                    });
                }
            });
        }
        /**
         * link on change event for alert "action" selectionbox
         */
        $('#ruleaction').on('change', function(){
            $('#grid-installedrules').bootgrid('reload');
        });

        /**
         * update list of available alert logs
         */
        function updateAlertLogs() {
            ajaxGet(url="/api/ids/service/getAlertLogs",sendData={}, callback=function(data, status) {
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
            var selected =$('#ruleclass').find("option:selected").val();
            if ( selected != "") {
                request['classtype'] = selected;
            }
            var selected =$('#ruleaction').find("option:selected").val();
            if ( selected != "") {
                request['action'] = selected;
            }
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
         * save (general) settings and reconfigure
         * @param callback_funct: callback function, receives result status true/false
         */
        function actionReconfigure(callback_funct) {
            var result_status = false;
            saveFormToEndpoint(url="/api/ids/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                ajaxCall(url="/api/ids/service/reconfigure", sendData={}, callback=function(data,status) {
                    if (status == "success" || data['status'].toLowerCase().trim() == "ok") {
                        result_status = true;
                    }
                    $('#scheduled_updates').show();
                    callback_funct(result_status);
                });
            });
        }

        /**
         * toggle selected items
         * @param gridId: grid id to to use
         * @param url: ajax action to call
         * @param state: 0/1/undefined
         * @param combine: number of keys to combine (seperate with ,)
         *                 try to avoid too much items per call (results in too long url's)
         */
        function actionToggleSelected(gridId, url, state, combine) {
            var rows =$("#"+gridId).bootgrid('getSelectedRows');
            if (rows != undefined){
                var deferreds = [];
                if (state != undefined) {
                    var url_suffix = state;
                } else {
                    var url_suffix = "";
                }

                var keyset = [];
                $.each(rows, function(key,uuid){
                    keyset.push(uuid);
                    if ( combine == undefined || keyset.length > combine) {
                        deferreds.push(ajaxCall(url + keyset.join(',') +'/'+url_suffix, sendData={},null));
                        keyset = [];
                    }
                });

                // flush remaining items
                if (keyset.length > 0) {
                    deferreds.push(ajaxCall(url + keyset.join(',') +'/'+url_suffix, sendData={},null));
                }

                // refresh when all toggles are executed
                $.when.apply(null, deferreds).done(function(){
                    $("#"+gridId).bootgrid("reload");
                });
            }
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
                ajaxGet(url="/api/ids/settings/getRulesetproperties", sendData={}, callback=function(data, status) {
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
            } else if (e.target.id == 'rule_tab'){
                //
                // activate rule tab page
                //

                // delay refresh for a bit
                setTimeout(updateRuleClassTypes, 500);

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
                        ajaxGet(url='/api/ids/service/getAlertInfo/' + uuid,
                            sendData={}, callback=function(data, status) {
                                if (status == 'success') {
                                    ajaxGet(url="/api/ids/settings/getRuleInfo/"+data['alert_sid'],sendData={}, callback=function(rule_data, rule_status) {
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
                                            var alert_select = $('<select class="selectpicker"/>');
                                            var alert_enabled = $('<input type="checkbox"/>');
                                            if (rule_data.enabled == '1') {
                                                alert_enabled.prop('checked', true);
                                            }
                                            $.each(rule_data.action, function(key, value){
                                                var opt = $('<option/>').attr("value", key).text(value.value)
                                                if (value.selected == 1) {
                                                    opt.attr('selected', 'selected');
                                                }
                                                alert_select.append(opt);
                                            });
                                            tbl_tbody.append(
                                              $("<tr/>").append(
                                                $("<td/>").text("{{ lang._('Configured action') }}"),
                                                $("<td id='alert_sid_action'/>").append(
                                                  alert_enabled, $("<span/>").html("&nbsp; <strong>{{ lang._('Enabled')}}</strong>"), alert_select
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
                                                ajaxCall(url="/api/ids/settings/setRule/"+data['alert_sid'], sendData=rule_params, callback=function() {
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
                                        stdDialogInform("{{ lang._('Alert info') }}", tbl, "{{ lang._('Close') }}", undefined, "info");
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
        })



        /*************************************************************************************************************
         * UI button Commands
         *************************************************************************************************************/

        /**
         * save settings and reconfigure ids
         */
        $("#reconfigureAct").click(function(){
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            actionReconfigure(function(status){
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");
                updateStatus();

                if (!status) {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Error reconfiguring IDS') }}",
                        message: data['status'],
                        draggable: true
                    });
                }
            });
        });
        $("#updateSettings").click(function(){
            $("#updateSettings_progress").addClass("fa fa-spinner fa-pulse");
            var settings = {};
            $(".rulesetprop").each(function(){
                settings[$(this).data('id')] = $(this).val();
            });
            ajaxCall(url="/api/ids/settings/setRulesetproperties", sendData={'properties': settings}, callback=function(data,status) {
                $("#updateSettings_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        /**
         * update (userdefined) rules
         */
        $(".act_update").click(function(){
            $(".act_update_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/ids/service/reloadRules", sendData={}, callback=function(data,status) {
                // when done, disable progress animation.
                $(".act_update_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        /**
         * update rule definitions
         */
        $("#updateRulesAct").click(function(){
            $("#updateRulesAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/ids/service/updateRules", sendData={}, callback=function(data,status) {
                // when done, disable progress animation and reload grid.
                $('#grid-rule-files').bootgrid('reload');
                updateStatus();
                $("#updateRulesAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        /**
         * disable selected rulesets
         */
        $("#disableSelectedRuleSets").click(function(){
            var gridId = 'grid-rule-files';
            var url = '/api/ids/settings/toggleRuleset/';
            actionToggleSelected(gridId, url, 0, 20);
        });

        /**
         * enable selected rulesets
         */
        $("#enableSelectedRuleSets").click(function(){
            var gridId = 'grid-rule-files';
            var url = '/api/ids/settings/toggleRuleset/';
            actionToggleSelected(gridId, url, 1, 20);
        });

        /**
         * disable selected rules
         */
        $("#disableSelectedRules").click(function(){
            var gridId = 'grid-installedrules';
            var url = '/api/ids/settings/toggleRule/';
            actionToggleSelected(gridId, url, 0, 100);
        });

        /**
         * enable selected rules
         */
        $("#enableSelectedRules").click(function(){
            var gridId = 'grid-installedrules';
            var url = '/api/ids/settings/toggleRule/';
            actionToggleSelected(gridId, url, 1, 100);
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
        ajaxGet(url='/api/diagnostics/interface/getInterfaceNames', {}, callback=function(data, status) {
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
                        ajaxCall(url="/api/ids/service/dropAlertLog/",sendData={filename: selected_log.data('filename')},
                                callback=function(data,status){
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
<div class="tab-content content-box tab-content">
    <div id="settings" class="tab-pane fade in">
        {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_GeneralSettings'])}}
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b> <i id="reconfigureAct_progress" class=""></i></button>
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
                            <button data-toggle="tooltip" id="enableSelectedRuleSets" type="button" class="btn btn-xs btn-default btn-primary">{{ lang._('Enable selected') }}</span></button>
                            <button data-toggle="tooltip" id="disableSelectedRuleSets" type="button" class="btn btn-xs btn-default btn-primary">{{ lang._('Disable selected') }}</span></button>
                          </div>
                          <div class="col-xs-3" style="padding-top:0px;">
                            <input type="text" placeholder="{{ lang._('Search') }}" id="grid-rule-files-search" value=""/>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </table>
                  <div style="max-height: 400px; width: 100%; margin: 0; overflow-y: auto;" id="grid-rule-files-container">
                    <table id="grid-rule-files" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRuleset">
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
          <hr/>
          <button class="btn btn-primary" style="display:none" id="updateSettings" type="button"><b>{{ lang._('Save') }}</b> <i id="updateSettings_progress" class=""></i></button>
          <button class="btn btn-primary" id="updateRulesAct" type="button"><b>{{ lang._('Download & Update Rules') }}</b> <i id="updateRulesAct_progress" class=""></i></button>
          <br/>
          <i>{{ lang._('Please use "Download & Update Rules" to fetch your initial ruleset, automatic updating can be scheduled after the first download.') }} </i>
      </div>
    </div>
    <div id="rules" class="tab-pane fade in">
        <div class="bootgrid-header container-fluid">
            <div class="row">
                <div class="col-sm-12 actionBar">
                    <b>{{ lang._('Classtype') }} &nbsp;</b>
                    <select id="ruleclass" class="selectpicker" data-width="200px"></select>
                    &nbsp;
                    <b>{{ lang._('Action') }} &nbsp;</b>
                    <select id="ruleaction" class="selectpicker" data-width="100px">
                        <option value="">{{ lang._('All') }}</option>
                        <option value="drop">{{ lang._('Drop') }}</option>
                        <option value="alert">{{ lang._('Alert') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- tab page "installed rules" -->
        <table id="grid-installedrules" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRule">
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
                    <button title="{{ lang._('Disable selected') }}" id="disableSelectedRules" type="button" class="btn btn-xs btn-default"><span class="fa fa-square-o command-toggle"></span></button>
                    <button title="{{ lang._('Enable selected') }}" id="enableSelectedRules" type="button" class="btn btn-xs btn-default"><span class="fa fa-check-square-o command-toggle"></span></button>
                </td>
                <td></td>
            </tr>
            </tfoot>
        </table>
        <div class="col-md-12">
            <hr/>
            <button class="btn btn-primary act_update" type="button"><b>{{ lang._('Apply') }}</b> <i class="act_update_progress"></i></button>
            <br/>
            <br/>
        </div>
    </div>
    <div id="userrules" class="tab-pane fade in">
        <!-- tab page "userrules" -->
        <table id="grid-userrules" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogUserDefined">
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
            <hr/>
            <button class="btn btn-primary act_update" type="button"><b>{{ lang._('Apply') }}</b> <i class="act_update_progress"></i></button>
            <br/>
            <br/>
        </div>
    </div>
    <div id="alerts" class="tab-pane fade in">
        <!-- tab page "alerts" -->
        <div class="bootgrid-header container-fluid">
            <div class="row">
                <div class="col-sm-12 actionBar">
                    <select id="alert-logfile" class="selectpicker" data-width="200px"></select>
                    <span id="actDeleteLog" class="btn btn-lg fa fa-trash" style="cursor: pointer;"></span>
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
        <table id="grid-alerts" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
              <tr>
                  <th data-column-id="timestamp" data-type="string" data-sortable="false">{{ lang._('Timestamp') }}</th>
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
