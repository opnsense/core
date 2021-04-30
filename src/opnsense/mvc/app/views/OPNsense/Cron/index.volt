{#
 # Copyright (c) 2014-2015 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
        /**
         * inline open dialog, go back to previous page on exit
         */
        function openDialog(uuid) {
            var editDlg = "DialogEdit";
            var setUrl = "/api/cron/settings/setJob/";
            var getUrl = "/api/cron/settings/getJob/";
            var urlMap = {};
            urlMap['frm_' + editDlg] = getUrl + uuid;
            mapDataToFormUI(urlMap).done(function () {
                // update selectors
                $('.selectpicker').selectpicker('refresh');
                // clear validation errors (if any)
                clearFormValidation('frm_' + editDlg);
                // show
                $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
                $('#'+editDlg).on('hidden.bs.modal', function () {
                    // go back to previous page on exit
                    parent.history.back();
                });
            });


            // define save action
            $("#btn_"+editDlg+"_save").unbind('click').click(function(){
                saveFormToEndpoint(setUrl+uuid, 'frm_' + editDlg, function(){
                    // do reconfigure of cron after save (because we're leaving back to the sender)
                    ajaxCall("/api/cron/service/reconfigure", {}, function(data,status) {
                        $("#"+editDlg).modal('hide');
                    });
                }, true);
            });

        }
        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-jobs").UIBootgrid(
                {   'search':'/api/cron/settings/searchJobs',
                    'get':'/api/cron/settings/getJob/',
                    'set':'/api/cron/settings/setJob/',
                    'add':'/api/cron/settings/addJob/',
                    'del':'/api/cron/settings/delJob/',
                    'toggle':'/api/cron/settings/toggleJob/'
                }
        );

        {% if (selected_uuid|default("") != "") %}
            openDialog('{{selected_uuid}}');
        {% endif %}

        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

        /**
         * Reconfigure cron - activate changes
         */
        $("#reconfigureAct").SimpleActionButton();
    });

</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#grid-jobs">{{ lang._('Jobs') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="jobs" class="tab-pane fade in active">
        <!-- tab page "cron items" -->
        <table id="grid-jobs" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEdit"
                 {% if (selected_uuid|default("") == "") %} data-editAlert="cronChangeMessage" {% endif %} >
            <thead>
            <tr>
                <th data-column-id="origin" data-type="string" data-visible="false">{{ lang._('Origin') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="minutes" data-type="string">{{ lang._('Minutes') }}</th>
                <th data-column-id="hours" data-type="string">{{ lang._('Hours') }}</th>
                <th data-column-id="days" data-type="string">{{ lang._('Days') }}</th>
                <th data-column-id="months" data-type="string">{{ lang._('Months') }}</th>
                <th data-column-id="weekdays" data-type="string">{{ lang._('Weekdays') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="command" data-type="string">{{ lang._('Command') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">
                    <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
		</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div class="col-md-12">
        <div id="cronChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/cron/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring cron') }}"
                type="button"
        ></button>
        <br/><br/>
    </div>
</div>

{# include dialog #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':lang._('Edit job')])}}
