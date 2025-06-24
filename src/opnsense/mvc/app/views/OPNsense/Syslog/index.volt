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

        $("#{{formGridDestination['table_id']}}").UIBootgrid(
            {   search:'/api/syslog/settings/search_destinations',
                get:'/api/syslog/settings/get_destination/',
                set:'/api/syslog/settings/set_destination/',
                add:'/api/syslog/settings/add_destination/',
                del:'/api/syslog/settings/del_destination/',
                toggle:'/api/syslog/settings/toggle_destination/'
            }
        );

        $("#grid-statistics").UIBootgrid({
            search: '/api/syslog/service/stats/'
        });

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id === 'statistics') {
                $("#grid-statistics").bootgrid('reload');
            }
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

        $('#resetAct').insertAfter('#reconfigureAct').show();
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
        {{ partial('layout_partials/base_bootgrid_table', formGridDestination)}}
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
</div>

<button class="btn pull-right" id="resetAct" style="display: none;"
        data-endpoint='/api/syslog/service/reset'
        data-label="{{ lang._('Reset Log Files') }}"
        data-error-title="{{ lang._('Error resetting Syslog') }}"
        type="button"
></button>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/syslog/service/reconfigure', 'data_service_widget': 'syslog'}) }}

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogDestination,'id':formGridDestination['edit_dialog_id'],'label':lang._('Edit destination')])}}
