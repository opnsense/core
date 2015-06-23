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

        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

        /**
         * re
         * Reconfigure cron - activate changes
         */
        $("#reconfigureAct").click(function(){
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/cron/service/reconfigure", sendData={}, callback=function(data,status) {
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

    });

</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#grid-jobs">{{ lang._('Jobs') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="jobs" class="tab-pane fade in active">
        <!-- tab page "cron items" -->
        <table id="grid-jobs" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEdit">
            <thead>
            <tr>
                <th data-column-id="origin" data-type="string" data-visible="false">Origin</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">Enabled</th>
                <th data-column-id="minutes" data-type="string">Minutes</th>
                <th data-column-id="hours" data-type="string">Hours</th>
                <th data-column-id="days" data-type="string">Days</th>
                <th data-column-id="months" data-type="string">Months</th>
                <th data-column-id="weekdays" data-type="string">Weekdays</th>
                <th data-column-id="description" data-type="string">Description</th>
                <th data-column-id="command" data-type="string">Command</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">Edit | Delete</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">ID</th>
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
    </div>
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary"  id="reconfigureAct" type="button"><b>Apply</b><i id="reconfigureAct_progress" class=""></i></button>
    </div>
</div>

{# include dialog #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':'Edit Job'])}}