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
            var setUrl = "/api/cron/settings/set_job/";
            var getUrl = "/api/cron/settings/get_job/";
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

        $("#{{formGridJobs['table_id']}}").UIBootgrid(
                {   'search':'/api/cron/settings/search_jobs',
                    'get':'/api/cron/settings/get_job/',
                    'set':'/api/cron/settings/set_job/',
                    'add':'/api/cron/settings/add_job/',
                    'del':'/api/cron/settings/del_job/',
                    'toggle':'/api/cron/settings/toggle_job/'
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
        {{ partial('layout_partials/base_bootgrid_table', formGridJobs)}}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/cron/service/reconfigure'}) }}

{# include dialog #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':formGridJobs['edit_dialog_id'],'label':lang._('Edit job')])}}
