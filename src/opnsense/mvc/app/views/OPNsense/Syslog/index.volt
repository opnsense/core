{#
 # Copyright (c) 2019-2024 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
        var data_get_map = {'frm_local_settings':"/api/syslog/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#grid-destinations").UIBootgrid(
            {   search:'/api/syslog/settings/searchDestinations',
                get:'/api/syslog/settings/getDestination/',
                set:'/api/syslog/settings/setDestination/',
                add:'/api/syslog/settings/addDestination/',
                del:'/api/syslog/settings/delDestination/',
                toggle:'/api/syslog/settings/toggleDestination/'
            }
        );

        $("#grid-statistics").UIBootgrid({
            search: '/api/syslog/service/stats/'
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function () {
              const dfObj = new $.Deferred();
              saveFormToEndpoint("/api/syslog/settings/set", 'frm_local_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
              return dfObj;
            }
        });
        $("#resetAct").SimpleActionButton({
            onPreAction: function () {
                const dfObj = new $.Deferred();
                BootstrapDialog.show({
                    type:BootstrapDialog.TYPE_DANGER,
                    closable: false, // otherwise the spinner keeps running
                    title: "{{ lang._('Syslog') }}",
                    message: "{{ lang._('Do you really want to reset the log files? This will erase all local log data.') }}",
                    buttons: [
                        { label: "{{ lang._('No') }}", action: function(dialogRef) { dialogRef.close(); dfObj.reject(); } },
                        { cssClass: 'btn-danger', label: "{{ lang._('Yes') }}", action: function(dialogRef) { dialogRef.close(); dfObj.resolve(); } }
                   ]
                });
                return dfObj;
            }
        })
        updateServiceControlUI('syslog');

        $("#destination\\.transport").change(function(){
            let transport_type = $(this).val();
            $(".transport_type").each(function(){
                if ($(this).hasClass("transport_type_" + transport_type)) {
                    $(this).closest("tr").show();
                } else {
                    $(this).closest("tr").hide();
                }
            });
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="local" href="#tab_local">{{ lang._('Local') }}</a></li>
    <li><a data-toggle="tab" id="remote" href="#tab_remote">{{ lang._('Remote') }}</a></li>
    <li><a data-toggle="tab" id="statistics" href="#tab_statistics">{{ lang._('Statistics') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="tab_local" class="tab-pane fade in active __mb">
        <!-- tab page "local" -->
        {{ partial("layout_partials/base_form",['fields':localForm,'id':'frm_local_settings'])}}
    </div>
    <div id="tab_remote" class="tab-pane fade in">
        <!-- tab page "remote" -->
        <table id="grid-destinations" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogDestination" data-editAlert="syslogChangeMessage">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="transport" data-type="string">{{ lang._('Transport') }}</th>
                <th data-column-id="hostname" data-type="string">{{ lang._('Hostname') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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
            </tfoot>
        </table>
        <hr/>
    </div>
    <div id="tab_statistics" class="tab-pane fade in">
        <table id="grid-statistics" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="SourceName" data-type="string">{{ lang._('SourceName') }}</th>
                    <th data-column-id="SourceId" data-type="string">{{ lang._('SourceId') }}</th>
                    <th data-column-id="SourceInstance" data-type="string">{{ lang._('SourceInstance') }}</th>
                    <th data-column-id="State" data-type="string">{{ lang._('State') }}</th>
                    <th data-column-id="Type" data-type="string">{{ lang._('Type') }}</th>
                    <th data-column-id="Number" data-type="numeric">{{ lang._('Number') }}</th>
                    <th data-column-id="Description" data-type="string">{{ lang._('Description') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <hr/>
    </div>
    <div class="col-md-12 __mb">
        <div id="syslogChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/syslog/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-service-widget="syslog"
                data-error-title="{{ lang._('Error reconfiguring Syslog') }}"
                type="button"
        ></button>
        <button class="btn pull-right" id="resetAct"
                data-endpoint='/api/syslog/service/reset'
                data-label="{{ lang._('Reset Log Files') }}"
                data-error-title="{{ lang._('Error resetting Syslog') }}"
                type="button"
        ></button>
    </div>
</div>


{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogDestination,'id':'DialogDestination','label':lang._('Edit destination')])}}
