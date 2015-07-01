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

<script type="text/javascript">

    $( document ).ready(function() {
        //
        var data_get_map = {'frm_GeneralSettings':"/api/ids/settings/get"};

        /**
         * update service status
         */
        function updateStatus() {
            ajaxCall(url="/api/ids/service/status", sendData={}, callback=function(data,status) {
                updateServiceStatusUI(data['status']);
            });
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
         * update list of available alert logs
         */
        function updateAlertLogs() {
            ajaxGet(url="/api/ids/service/getAlertLogs",sendData={}, callback=function(data, status) {
                if (status == "success") {
                    $('#alert-logfile').html("");
                    $.each(data, function(key, value) {
                        if (value['sequence'] == undefined) {
                            $('#alert-logfile').append($("<option></option>").attr("value",'none').text(value['modified']));
                        } else {
                            $('#alert-logfile').append($("<option></option>").attr("value",value['sequence']).text(value['modified']));
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
         * Add classtype to rule filter
         */
        function addRuleFilters(request) {
            var selected =$('#ruleclass').find("option:selected").val();
            if ( selected != "") {
                request['classtype'] = selected;
            }
            return request;
        }

        /**
         * Add fileid to alert filter
         */
        function addAlertQryFilters(request) {
            var selected =$('#alert-logfile').find("option:selected").val();
            if ( selected != "") {
                request['fileid'] = selected;
            }
            return request;
        }

        // load initial data
        mapDataToFormUI(data_get_map).done(function(data){
            // set schedule updates link to cron
            $.each(data.frm_GeneralSettings.ids.general.UpdateCron, function(key, value) {
                if (value.selected == 1) {
                    $("#scheduled_updates").attr("href","/ui/cron/item/open/"+key);
                    $("#scheduled_updates").show();
                }
            });
            //alert(JSON.stringify(data.frm_GeneralSettings.ids.general.UpdateCron));
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        updateStatus();

        /**
         * load content on tab changes
         */
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id == 'rule_tab'){
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
                            options:{
                                multiSelect:false,
                                selection:false,
                                requestHandler:addRuleFilters,
                                formatters:{
                                    rowtoggle: function (column, row) {
                                        if (parseInt(row[column.id], 2) == 1) {
                                            var toggle = "<span style=\"cursor: pointer;\" class=\"fa fa-check-square-o command-toggle\" data-value=\"1\" data-row-id=\"" + row.sid + "\"></span>";
                                        } else {
                                            var toggle = "<span style=\"cursor: pointer;\" class=\"fa fa-square-o command-toggle\" data-value=\"0\" data-row-id=\"" + row.sid + "\"></span>";
                                        }
                                        toggle += " &nbsp; <button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.sid + "\"><span class=\"fa fa-info-circle\"></span></button> ";
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
                $("#grid-alerts").UIBootgrid(
                        {   search:'/api/ids/service/queryAlerts',
                            get:'/api/ids/service/getAlertInfo/',
                            options:{
                                multiSelect:false,
                                selection:false,
                                requestHandler:addAlertQryFilters,
                                formatters:{
                                    info: function (column, row) {
                                        return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.filepos + "\"><span class=\"fa fa-info-circle\"></span></button> ";
                                    }
                                }
                            }
                        });
            }
        })



        /**
         * grid for installable rule files
         */
        $("#grid-rule-files").UIBootgrid(
                {   search:'/api/ids/settings/listInstallableRulesets',
                    toggle:'/api/ids/settings/toggleInstalledRuleset/',
                    options:{
                        multiSelect:false,
                        selection:false,
                        navigation:0,
                        formatters:{
                            rowtoggle: function (column, row) {
                                if (parseInt(row[column.id], 2) == 1) {
                                    var toggle = "<span style=\"cursor: pointer;\" class=\"fa fa-check-square-o command-toggle\" data-value=\"1\" data-row-id=\"" + row.filename + "\"></span>";
                                } else {
                                    var toggle = "<span style=\"cursor: pointer;\" class=\"fa fa-square-o command-toggle\" data-value=\"0\" data-row-id=\"" + row.filename + "\"></span>";
                                }
                                return toggle;
                            }
                        }
                    }
                });

        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

        /**
         * save settings and reconfigure ids
         */
        $("#reconfigureAct").click(function(){
            saveFormToEndpoint(url="/api/ids/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
                ajaxCall(url="/api/ids/service/reconfigure", sendData={}, callback=function(data,status) {
                    // when done, disable progress animation.
                    $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");
                    updateStatus();

                    if (status != "success" || data['status'].toLowerCase().trim() != "ok") {
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_WARNING,
                            title: "Error reconfiguring IDS",
                            message: data['status'],
                            draggable: true
                        });
                    }
                });
            });
        });

        /**
         * update rule definitions
         */
        $("#updateRulesAct").click(function(){
            $("#updateRulesAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/ids/service/updateRules", sendData={}, callback=function(data,status) {
                // when done, disable progress animation and reload grid.
                $("#updateRulesAct_progress").removeClass("fa fa-spinner fa-pulse");
                $('#grid-rule-files').bootgrid('reload');
                updateStatus();
            });
        });


    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings" id="settings_tab">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#rules" id="rule_tab">{{ lang._('Rules') }}</a></li>
    <li><a data-toggle="tab" href="#alerts" id="alert_tab">{{ lang._('Alerts') }}</a></li>
    <li><a href="" id="scheduled_updates" style="display:none">{{ lang._('Schedule') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="settings" class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_GeneralSettings'])}}
        <!-- add installable rule files -->
        <table class="table table-striped table-condensed table-responsive">
            <colgroup>
                <col class="col-md-3"/>
                <col class="col-md-9"/>
            </colgroup>
            <tbody>
            <tr>
                <td><div class="control-label">
                    <i class="fa fa-info-circle text-muted"></i>
                    <b>rulesets</b>
                    </div>
                </td>
                <td>
                <table id="grid-rule-files" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRule">
                    <thead>
                    <tr>
                        <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false"  data-width="10em">enabled</th>
                        <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">description</th>
                        <th data-column-id="modified_local" data-type="string" data-sortable="false"  data-visible="true">last updated</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="rules" class="tab-pane fade in">
        <div class="bootgrid-header container-fluid">
            <div class="row">
                <div class="col-sm-12 actionBar">
                    <b>Classtype &nbsp;</b>
                    <select id="ruleclass" class="selectpicker" data-width="200px"></select>
                </div>
            </div>
        </div>

        <!-- tab page "installed rules" -->
        <table id="grid-installedrules" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRule">
            <thead>
            <tr>
                <th data-column-id="sid" data-type="number" data-visible="true" data-identifier="true" data-width="6em">sid</th>
                <th data-column-id="source" data-type="string">Source</th>
                <th data-column-id="classtype" data-type="string">ClassType</th>
                <th data-column-id="msg" data-type="string">Message</th>
                <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false"  data-width="10em">enabled / info</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div id="alerts" class="tab-pane fade in">
        <div class="bootgrid-header container-fluid">
            <div class="row">
                <div class="col-sm-12 actionBar">
                    <select id="alert-logfile" class="selectpicker" data-width="200px"></select>
                </div>
            </div>
        </div>
        <!-- tab page "alerts" -->
        <table id="grid-alerts" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAlert">
            <thead>
            <tr>
                <th data-column-id="timestamp" data-type="string" data-sortable="false">timestamp</th>
                <th data-column-id="src_ip" data-type="string" data-sortable="false"  data-width="10em">source</th>
                <th data-column-id="dest_ip" data-type="string"  data-sortable="false"  data-width="10em">destination</th>
                <th data-column-id="alert" data-type="string" data-sortable="false" >Alert</th>
                <th data-column-id="info" data-formatter="info" data-sortable="false" data-width="4em">info</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary"  id="reconfigureAct" type="button"><b>Apply</b><i id="reconfigureAct_progress" class=""></i></button>
        <button class="btn btn-primary"  id="updateRulesAct" type="button"><b>Download & Update Rules</b><i id="updateRulesAct_progress" class=""></i></button>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogRule,'id':'DialogRule','label':'Rule details','hasSaveBtn':'false','msgzone_width':1])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAlert,'id':'DialogAlert','label':'Alert details','hasSaveBtn':'false','msgzone_width':1])}}
