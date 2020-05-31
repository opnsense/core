{#
 # Copyright (c) 2014-2015 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
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

    $( document ).ready(function() {

        var data_get_map = {'frm_proxy':"/api/proxy/settings/get"};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            // request service status on load and update status box
            updateServiceControlUI('proxy');
        });

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-remote-blacklists").UIBootgrid(
                {   'search':'/api/proxy/settings/searchRemoteBlacklists',
                    'get':'/api/proxy/settings/getRemoteBlacklist/',
                    'set':'/api/proxy/settings/setRemoteBlacklist/',
                    'add':'/api/proxy/settings/addRemoteBlacklist/',
                    'del':'/api/proxy/settings/delRemoteBlacklist/',
                    'toggle':'/api/proxy/settings/toggleRemoteBlacklist/'
                }
        );
        $("#grid-pac-match").UIBootgrid(
                {   'search':'/api/proxy/settings/searchPACMatch',
                    'get':'/api/proxy/settings/getPACMatch/',
                    'set':'/api/proxy/settings/setPACMatch/',
                    'add':'/api/proxy/settings/addPACMatch/',
                    'del':'/api/proxy/settings/delPACMatch/',
                    'options': {
                        responseHandler: function (response) {
                            // concatenate fields for not.
                            if ('rows' in response) {
                                for (var i = 0; i < response.rowCount; i++) {
                                    response.rows[i]['display_match_type'] = {'not':response.rows[i].negate == '1',
                                                                      'val':response.rows[i].match_type}
                                }
                            }
                            return response;
                        }
                    }
                }
        );
        $("#grid-pac-rule").UIBootgrid(
                {   'search':'/api/proxy/settings/searchPACRule',
                    'get':'/api/proxy/settings/getPACRule/',
                    'set':'/api/proxy/settings/setPACRule/',
                    'add':'/api/proxy/settings/addPACRule/',
                    'del':'/api/proxy/settings/delPACRule/',
                    'toggle':'/api/proxy/settings/togglePACRule/'
                }
        );
        $("#grid-pac-proxy").UIBootgrid(
                {   'search':'/api/proxy/settings/searchPACProxy',
                    'get':'/api/proxy/settings/getPACProxy/',
                    'set':'/api/proxy/settings/setPACProxy/',
                    'add':'/api/proxy/settings/addPACProxy/',
                    'del':'/api/proxy/settings/delPACProxy/'
                }
        );

        function update_pac_match_view(event) {
            function show_line(the_id) {
                $('tr[for=' + the_id + ']').show();
            }
            let value = $("#pac\\.match\\.match_type").val();
            if (!value) {
                // retry later
                setTimeout(update_pac_match_view, 100);
                return;
            }
            // hide tr of the element if not needed
            ["pac\\.match\\.network",
             "pac\\.match\\.hostname",
             "pac\\.match\\.url",
             "pac\\.match\\.domain_level_from",
             "pac\\.match\\.domain_level_to",
             "pac\\.match\\.time_from",
             "pac\\.match\\.time_to",
             "pac\\.match\\.date_from",
             "pac\\.match\\.date_to",
             "pac\\.match\\.weekday_from",
             "pac\\.match\\.weekday_to"].forEach (function (the_id) {
                $('tr[for=' + the_id + ']').hide();
            });
            switch (value) {
                case 'hostname_matches':
                    show_line("pac\\.match\\.hostname");
                    break;
                case "url_matches":
                    show_line("pac\\.match\\.url");
                    break;
                case "dns_domain_is":
                    show_line("pac\\.match\\.hostname");
                    break;
                case "destination_in_net":
                case "my_ip_in_net":
                    show_line("pac\\.match\\.network");
                    break;
                case "plain_hostname":
                    break; // has no option
                case "is_resolvable":
                    show_line("pac\\.match\\.hostname");
                    break;
                case "dns_domain_levels":
                    show_line("pac\\.match\\.domain_level_from");
                    show_line("pac\\.match\\.domain_level_to");
                    break;
                case "weekday_range":
                    show_line("pac\\.match\\.weekday_from");
                    show_line("pac\\.match\\.weekday_to");
                    break;
                case "date_range":
                    show_line("pac\\.match\\.date_from");
                    show_line("pac\\.match\\.date_to");
                    break;
                case "time_range":
                    show_line("pac\\.match\\.time_from");
                    show_line("pac\\.match\\.time_to");
                    break;
            }

        }
        // when a modal is created, update the
        $("#DialogEditPACMatch").on("opnsense_bootgrid_mapped", update_pac_match_view);
        $("#pac\\.match\\.match_type").change(update_pac_match_view);

        $('.reload-pac-btn').click(function () {
            $('.reload-pac-btn .fa-refresh').addClass('fa-spin');
            ajaxCall("/api/proxy/service/refreshTemplate", {}, function(data,status) {
                $('.reload-pac-btn .fa-refresh').removeClass('fa-spin');
            });
        });

        /**
         * Reconfigure proxy - activate changes
         */
        $("#reconfigureAct").SimpleActionButton();

        /**
         * Download ACLs and reconfigure poxy - activate changes
         */
        $("#fetchandreconfigureAct").SimpleActionButton();

        /**
         *
         * Download ACLs, no reconfigure
         */
        $("#downloadAct").SimpleActionButton();

        /**
         * setup cron item
         */
        $("#ScheduleAct").click(function() {
            $("#scheduleAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall("/api/proxy/settings/fetchRBCron", {}, function(data,status) {
                $("#scheduleAct_progress").removeClass("fa fa-spinner fa-pulse");
                if (data.uuid !=undefined) {
                    // redirect to cron page
                    $(location).attr('href',"/ui/cron/item/open/"+data.uuid);
                }
            });
        });

        // form save event handlers for all defined forms
        $('[id*="save_"]').each(function(){
            $(this).click(function() {
                var frm_id = $(this).closest("form").attr("id");
                var frm_title = $(this).closest("form").attr("data-title");
                // save data for General TAB
                saveFormToEndpoint("/api/proxy/settings/set", frm_id, function(){
                    // on correct save, perform reconfigure. set progress animation when reloading
                    $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                    //
                    ajaxCall("/api/proxy/service/reconfigure", {}, function(data,status){
                        // when done, disable progress animation.
                        $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");

                        if (status != "success" || data['status'] != 'ok' ) {
                            // fix error handling
                            BootstrapDialog.show({
                                type:BootstrapDialog.TYPE_WARNING,
                                title: frm_title,
                                message: JSON.stringify(data),
                                draggable: true
                            });
                        } else {
                            updateServiceControlUI('proxy');
                        }
                    });
                });
            });
        });

        $("#resetAct").click(function() {
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_DANGER,
                title: '{{ lang._('Reset') }} ',
                message: '{{ lang._('Are you sure you want to flush all generated content and restart the proxy?') }}',
                buttons: [{
                    label: '{{ lang._('Yes') }}',
                    cssClass: 'btn-primary',
                    action: function(dlg){
                        dlg.close();
                        $("#resetAct_progress").addClass("fa fa-spinner fa-pulse");
                        ajaxCall("/api/proxy/service/reset", {}, function(data,status) {
                            $("#resetAct_progress").removeClass("fa fa-spinner fa-pulse");
                            updateServiceControlUI('proxy');
                        });
                    }
                }, {
                    label: '{{ lang._('No') }}',
                    action: function(dlg){
                        dlg.close();
                    }
                }]
            });

        });

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
    });


</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':mainForm]) }}
    {# add custom content #}
    <li role="presentation" class="dropdown">
        <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">
            <b><span class="caret"></span></b>
        </a>
        <a data-toggle="tab" onclick="$('#subtab_item_pac_rules').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{ lang._('Proxy Auto-Config') }}</b></a>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a data-toggle="tab" id="subtab_item_pac_rules" href="#subtab_pac_rules">{{ lang._('Rules') }}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_pac_rules" href="#subtab_pac_proxies">{{ lang._('Proxies') }}</a>
            </li>
            <li>
                <a data-toggle="tab" id="subtab_item_pac_rules" href="#subtab_pac_matches">{{ lang._('Matches') }}</a>
            </li>
        </ul>
    </li>
    <li><a data-toggle="tab" href="#remote_acls"><b>{{ lang._('Remote Access Control Lists') }}</b></a></li>
    <li><a data-toggle="tab" href="#support"><b>{{ lang._('Support') }}</b></a></li>
</ul>

<div class="content-box tab-content">
    {{ partial("layout_partials/base_tabs_content",['formData':mainForm]) }}
    <div id="subtab_pac_matches" class="tab-pane fade">
        <table id="grid-pac-match" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditPACMatch">
            <thead>
                <tr>
                    <th data-column-id="name" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="display_match_type" data-type="notprefixable" data-sortable="false"  data-visible="true">{{ lang._('Match Type') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Action') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <button type="button" class="btn btn-xs btn-primary reload-pac-btn"><span class="fa fa-refresh"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_pac_rules" class="tab-pane fade">
        <table id="grid-pac-rule" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditPACRule">
            <thead>
                <tr>
                    <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false"  data-width="6em">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <button type="button" class="btn btn-xs btn-primary reload-pac-btn"><span class="fa fa-refresh"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="subtab_pac_proxies" class="tab-pane fade">
        <table id="grid-pac-proxy" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditPACProxy">
            <thead>
                <tr>
                    <th data-column-id="name" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Name') }}</th>
                    <th data-column-id="proxy_type" data-type="string" data-sortable="false" data-visible="true">{{ lang._('Type') }}</th>
                    <th data-column-id="url" data-type="string" data-sortable="false" data-visible="true">{{ lang._('URL') }}</th>
                    <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                        <button type="button" class="btn btn-xs btn-primary reload-pac-btn"><span class="fa fa-refresh"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div id="remote_acls" class="tab-pane fade">
        <table class="table table-striped table-condensed table-responsive">
            <colgroup>
                <col class="col-md-3"/>
                <col class="col-md-9"/>
            </colgroup>
            <tbody>
            <tr>
                <td colspan="2" style="text-align:right">
                    <small>{{ lang._('full help') }} </small><a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help_show_all_help_frm_proxy-forward-acl-remoteACLS"></i></a>
                </td>
            </tr>
            <tr>
                <td><div class="control-label">
                    <a id="help_for_proxy.forward.acl.remoteACLs.blacklist" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                    <b>{{ lang._('Remote Blacklist') }}</b>
                </div>
                </td>
                <td>
                  <div class="hidden" data-for="help_for_proxy.forward.acl.remoteACLs.blacklist">
                      <small>
                      {{ lang._('Add an item to the table to fetch a remote acl for blacklisting.%s
                      You can enable or disable the blacklist list.%s
                      The active blacklists will be merged with the settings under %sForward Proxy -> Access Control List%s.') |
                           format('<br/>','<br/>','<b>','</b>') }}
                      </small>
                  </div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div id="remoteACLchangeMessage" class="alert alert-info" style="display: none" role="alert">
                        {{ lang._('After changing categories, please remember to download the ACL again to apply your new settings') }}
                    </div>
                    <table id="grid-remote-blacklists" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditBlacklist" data-editAlert="remoteACLchangeMessage">
                        <thead>
                        <tr>
                            <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false"  data-width="6em">{{ lang._('Enabled') }}</th>
                            <th data-column-id="filename" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Filename') }}</th>
                            <th data-column-id="url" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('URL') }}</th>
                            <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">{{ lang._('Description') }}</th>
                            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit | Delete') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                        <tr>
                            <td></td>
                            <td>
                                <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                    <div class="col-md-12">
                        <hr/>
                        <button class="btn btn-primary" id="reconfigureAct"
                                data-endpoint='/api/proxy/service/reconfigure'
                                data-label="{{ lang._('Apply') }}"
                                data-error-title="{{ lang._('Error reconfiguring proxy') }}"
                                type="button"
                        ></button>
                        <button class="btn btn-primary" id="fetchandreconfigureAct"
                                data-endpoint='/api/proxy/service/fetchacls'
                                data-label="{{ lang._('Download ACLs & Apply') }}"
                                data-error-title="{{ lang._('Error fetching remote acls') }}"
                                type="button"
                        ></button>
                        <button class="btn btn-primary" id="downloadAct"
                                data-endpoint='/api/proxy/service/downloadacls'
                                data-label="{{ lang._('Download ACLs') }}"
                                data-error-title="{{ lang._('Error fetching remote acls') }}"
                                type="button"
                        ></button>
                        <button class="btn btn-primary" id="ScheduleAct" type="button">
                            <b>{{ lang._('Schedule with Cron') }}</b><i id="scheduleAct_progress" class=""></i>
                        </button>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="support" class="tab-pane fade">
        <table class="table table-striped table-condensed">
            <thead>
                <tr>
                    <th>{{ lang._('Action')}}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
              <tr>
                  <td>
                      <button class="btn btn-primary" id="resetAct" type="button">{{ lang._('Reset') }}<i id="resetAct_progress" class=""></button>
                  </td>
                  <td>
                      {{ lang._('Reset all generated content (cached files and certificates included) and restart the proxy.') }}
                  </td>
              </tr>
            </tbody>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBlacklist,'id':'DialogEditBlacklist','label':lang._('Edit blacklist')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditPACProxy,'id':'DialogEditPACProxy','label':lang._('Edit Proxy')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditPACMatch,'id':'DialogEditPACMatch','label':lang._('Edit Match')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditPACRule,'id':'DialogEditPACRule','label':lang._('Edit Rule')])}}
