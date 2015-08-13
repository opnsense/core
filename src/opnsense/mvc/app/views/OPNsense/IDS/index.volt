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
         * @param  callback_funct: callback function, receives result status true/false
         */
        function actionReconfigure(callback_funct) {
            var result_status = false;
            saveFormToEndpoint(url="/api/ids/settings/set",formid='frm_GeneralSettings',callback_ok=function(){
                ajaxCall(url="/api/ids/service/reconfigure", sendData={}, callback=function(data,status) {
                    if (status == "success" || data['status'].toLowerCase().trim() == "ok") {
                        result_status = true;
                    }
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
                    $("#"+gridId).bootgrid("refresh");
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
                                requestHandler:addRuleFilters,
                                rowCount:[10, 25, 50,100,500,1000] ,
                                formatters:{
                                    rowtoggle: function (column, row) {
                                        var toggle = " <button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.sid + "\"><span class=\"fa fa-info-circle\"></span></button> ";
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
                $("#grid-alerts").UIBootgrid(
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
                                        return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.filepos + "\"><span class=\"fa fa-info-circle\"></span></button> ";
                                    }
                                },
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
                        title: "Error reconfiguring IDS",
                        message: data['status'],
                        draggable: true
                    });
                }
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
                if ($('#scheduled_updates').is(':hidden') ){
                    // save and reconfigure on initial download (tries to create a cron job)
                    actionReconfigure(function(status){
                        loadGeneralSettings();
                        $("#updateRulesAct_progress").removeClass("fa fa-spinner fa-pulse");
                    });
                } else {
                    $("#updateRulesAct_progress").removeClass("fa fa-spinner fa-pulse");
                }
            });
        });

        /**
         * disable selected rulesets
         */
        $("#disableSelectedRuleSets").click(function(){
            var gridId = 'grid-rule-files';
            var url = '/api/ids/settings/toggleInstalledRuleset/';
            actionToggleSelected(gridId, url, 0, 20);
        });

        /**
         * enable selected rulesets
         */
        $("#enableSelectedRuleSets").click(function(){
            var gridId = 'grid-rule-files';
            var url = '/api/ids/settings/toggleInstalledRuleset/';
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

        /**
         * Initialize
         */
        loadGeneralSettings();
        updateStatus();

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
                    <b>{{ lang._('rulesets') }}</b>
                    </div>
                </td>
                <td>
                <table id="grid-rule-files" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRule">
                    <thead>
                    <tr>
                        <th data-column-id="filename" data-type="string" data-visible="false" data-identifier="true">filename</th>
                        <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                        <th data-column-id="modified_local" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Last updated') }}</th>
                        <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false"  data-width="10em">{{ lang._('Enabled') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td></td>
                        <td>
                            <button title="{{ lang._('disable selected') }}" id="disableSelectedRuleSets" type="button" class="btn btn-xs btn-default"><span class="fa fa-square-o command-toggle"></span></button>
                            <button title="{{ lang._('enable selected') }}" id="enableSelectedRuleSets" type="button" class="btn btn-xs btn-default"><span class="fa fa-check-square-o command-toggle"></span></button>
                        </td>
                    </tr>
                    </tfoot>
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
                <th data-column-id="sid" data-type="numeric" data-visible="true" data-identifier="true" data-width="6em">{{ lang._('sid') }}</th>
                <th data-column-id="source" data-type="string">{{ lang._('Source') }}</th>
                <th data-column-id="classtype" data-type="string">{{ lang._('ClassType') }}</th>
                <th data-column-id="msg" data-type="string">{{ lang._('Message') }}</th>
                <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false"  data-width="10em">{{ lang._('Info / enabled') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td>
                    <button title="{{ lang._('disable selected') }}" id="disableSelectedRules" type="button" class="btn btn-xs btn-default"><span class="fa fa-square-o command-toggle"></span></button>
                    <button title="{{ lang._('enable selected') }}" id="enableSelectedRules" type="button" class="btn btn-xs btn-default"><span class="fa fa-check-square-o command-toggle"></span></button>
                </td>
                <td></td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="alerts" class="tab-pane fade in">
        <div class="bootgrid-header container-fluid">
            <div class="row">
                <div class="col-sm-12 actionBar">
                    <select id="alert-logfile" class="selectpicker" data-width="200px"></select>
                    <select id="alert-logfile-max" class="selectpicker" data-width="80px">
                        <option value="5">5</option>
                        <option value="6">6</option>
                        <option value="7">7</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                        <option value="500">500</option>
                        <option value="1000">1000</option>
                        <option value="-1">All</option>
                    </select>
                    <div class="search form-group">
                        <div class="input-group">
                            <input class="search-field form-control" placeholder="{{ lang._('Search') }}" type="text" id="inputSearchAlerts">
                            <span  id="actQueryAlerts" class="icon input-group-addon fa fa-refresh" title="{{ lang._('Query') }}" style="cursor: pointer;"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- tab page "alerts" -->
        <table id="grid-alerts" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAlert">
            <thead>
            <tr>
                <th data-column-id="timestamp" data-type="string" data-sortable="false">{{ lang._('Timestamp') }}</th>
                <th data-column-id="src_ip" data-type="string" data-sortable="false"  data-width="10em">{{ lang._('Source') }}</th>
                <th data-column-id="dest_ip" data-type="string"  data-sortable="false"  data-width="10em">{{ lang._('Destination') }}</th>
                <th data-column-id="alert" data-type="string" data-sortable="false" >{{ lang._('Alert') }}</th>
                <th data-column-id="info" data-formatter="info" data-sortable="false" data-width="4em">{{ lang._('Info') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary"  id="reconfigureAct" type="button"><b>{{ lang._('Apply') }}</b><i id="reconfigureAct_progress" class=""></i></button>
        <button class="btn btn-primary"  id="updateRulesAct" type="button"><b>{{ lang._('Download & Update Rules') }}</b><i id="updateRulesAct_progress" class=""></i></button>
        <br/>
        <i>{{ lang._('Please use "Download & Update Rules" to fetch your initial ruleset, automatic updating can be scheduled after the first download') }} </i>
    </div>
</div>

<!-- Static Modal -->
<div class="modal modal-static fade" id="processing-dialog" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="text-center">
                    <i class="fa fa-spinner fa-pulse fa-5x"></i>
                    <h4>{{ lang._('Processing request...') }} </h4>
                </div>
            </div>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogRule,'id':'DialogRule','label':'Rule details','hasSaveBtn':'false','msgzone_width':1])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogAlert,'id':'DialogAlert','label':'Alert details','hasSaveBtn':'false','msgzone_width':1])}}
