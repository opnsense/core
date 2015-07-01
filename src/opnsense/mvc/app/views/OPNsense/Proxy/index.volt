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

        var data_get_map = {'frm_proxy':"/api/proxy/settings/get"};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            // request service status on load and update status box
            ajaxCall(url="/api/proxy/service/status", sendData={}, callback=function(data,status) {
                updateServiceStatusUI(data['status']);
            });
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

        /**
         *
         * Reconfigure poxy - activate changes
         */
        $("#reconfigureAct").click(function(){
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/proxy/service/reconfigure", sendData={}, callback=function(data,status) {
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");

                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "Error reconfiguring cron",
                        message: data['status'],
                        draggable: true
                    });
                }
            });
        });

        // form save event handlers for all defined forms
        $('[id*="save_"]').each(function(){
            $(this).click(function() {
                var frm_id = $(this).closest("form").attr("id");
                var frm_title = $(this).closest("form").attr("data-title");
                // save data for General TAB
                saveFormToEndpoint(url="/api/proxy/settings/set",formid=frm_id,callback_ok=function(){
                    // on correct save, perform reconfigure. set progress animation when reloading
                    $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                    //
                    ajaxCall(url="/api/proxy/service/reconfigure", sendData={}, callback=function(data,status){
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
                            // request service status after successful save and update status box (wait a few seconds before update)
                            setTimeout(function(){
                                ajaxCall(url="/api/proxy/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                                });
                            },3000);
                        }
                    });
                });
            });
        });


    });


</script>

<ul class="nav nav-tabs" role="tablist"  id="maintabs">
{% for tab in mainForm['tabs']|default([]) %}
    {% if tab['subtabs']|default(false) %}
        {# Tab with dropdown #}

        {# Find active subtab #}
            {% set active_subtab="" %}
            {% for subtab in tab['subtabs']|default({}) %}
                {% if subtab[0]==mainForm['activetab']|default("") %}
                    {% set active_subtab=subtab[0] %}
                {% endif %}
            {% endfor %}

        <li role="presentation" class="dropdown {% if mainForm['activetab']|default("") == active_subtab %}active{% endif %}">
            <a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button" style="border-left: 1px dashed lightgray;">
                <b><span class="caret"></span></b>
            </a>
            <a data-toggle="tab" href="#subtab_{{tab['subtabs'][0][0]}}" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>{{tab[1]}}</b></a>
            <ul class="dropdown-menu" role="menu">
                {% for subtab in tab['subtabs']|default({})%}
                <li class="{% if mainForm['activetab']|default("") == subtab[0] %}active{% endif %}"><a data-toggle="tab" href="#subtab_{{subtab[0]}}"><i class="fa fa-check-square"></i> {{subtab[1]}}</a></li>
                {% endfor %}
            </ul>
        </li>
    {% else %}
        {# Standard Tab #}
        <li {% if mainForm['activetab']|default("") == tab[0] %} class="active" {% endif %}>
                <a data-toggle="tab" href="#tab_{{tab[0]}}">
                    <b>{{tab[1]}}</b>
                </a>
        </li>
    {% endif %}
{% endfor %}
    {# add custom content #}
    <li><a data-toggle="tab" href="#remote_acls"><b>Remote Access Control Lists</b></a></li>
</ul>

<div class="content-box tab-content">
    {% for tab in mainForm['tabs']|default([]) %}
        {% if tab['subtabs']|default(false) %}
            {# Tab with dropdown #}
            {% for subtab in tab['subtabs']|default({})%}
                <div id="subtab_{{subtab[0]}}" class="tab-pane fade{% if mainForm['activetab']|default("") == subtab[0] %} in active {% endif %}">
                    {{ partial("layout_partials/base_form",['fields':subtab[2],'id':'frm_'~subtab[0],'data_title':subtab[1],'apply_btn_id':'save_'~subtab[0]])}}
                </div>
            {% endfor %}
        {% endif %}
        {% if tab['subtabs']|default(false)==false %}
            <div id="tab_{{tab[0]}}" class="tab-pane fade{% if mainForm['activetab']|default("") == tab[0] %} in active {% endif %}">
                {{ partial("layout_partials/base_form",['fields':tab[2],'id':'frm_'~tab[0],'apply_btn_id':'save_'~tab[0]])}}
            </div>
        {% endif %}
    {% endfor %}
    <div id="remote_acls" class="tab-pane fade">

        <table class="table table-striped table-condensed table-responsive">
            <colgroup>
                <col class="col-md-3"/>
                <col class="col-md-9"/>
            </colgroup>
            <tbody>
            <tr>
                <td colspan="2" align="right">
                    <small>{{ lang._('full help') }} </small><a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help_show_all_help_frm_proxy-forward-acl-remoteACLS" type="button"></i></a>
                </td>
            </tr>
            <tr>
                <td><div class="control-label">
                    <a id="help_for_proxy.forward.acl.remoteACLs.blacklist" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
                    <b>Remote Blacklist</b>
                </div>
                </td>
                <td>
                <small class="hidden" for="help_for_proxy.forward.acl.remoteACLs.blacklist">
                    {{ lang._('
                    Add an item to the table to fetch a remote acl for blacklisting.<br/>
                    You can enable or disable the blacklist list.<br/>
                    The active blacklists will be merged with the settings under <b>Forward Proxy -> Access Control List</b>.
                    ') }}
                </small>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <table id="grid-remote-blacklists" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditBlacklist">
                        <thead>
                        <tr>
                            <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false"  data-width="6em">Enabled</th>
                            <th data-column-id="filename" data-type="string" data-sortable="false"  data-visible="true">Filename</th>
                            <th data-column-id="url" data-type="string" data-sortable="false"  data-visible="true">URL</th>
                            <th data-column-id="description" data-type="string" data-sortable="false"  data-visible="true">Description</th>
                            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">Edit | Delete</th>
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
                        <button class="btn btn-primary"  id="reconfigureAct" type="button"><b>Apply</b><i id="reconfigureAct_progress" class=""></i></button>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditBlacklist,'id':'DialogEditBlacklist','label':'Edit Blacklist'])}}

