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
    .hidden {
        display:none;
    }
</style>

<script type="text/javascript">

    $( document ).ready(function() {

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-pipes").UIBootgrid(
            {   'search':'/api/trafficshaper/settings/searchPipes',
                'get':'/api/trafficshaper/settings/getPipe/',
                'set':'/api/trafficshaper/settings/setPipe/',
                'add':'/api/trafficshaper/settings/addPipe/',
                'del':'/api/trafficshaper/settings/delPipe/'
            }
        );

        $("#grid-queues").UIBootgrid(
                {   'search':'/api/trafficshaper/settings/searchQueues',
                    'get':'/api/trafficshaper/settings/getQueue/',
                    'set':'/api/trafficshaper/settings/setQueue/',
                    'add':'/api/trafficshaper/settings/addQueue/',
                    'del':'/api/trafficshaper/settings/delQueue/'
                }
        );

        $("#grid-rules").UIBootgrid(
                {   'search':'/api/trafficshaper/settings/searchRules',
                    'get':'/api/trafficshaper/settings/getRule/',
                    'set':'/api/trafficshaper/settings/setRule/',
                    'add':'/api/trafficshaper/settings/addRule/',
                    'del':'/api/trafficshaper/settings/delRule/'
                }
        );


        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

        /**
         * Reconfigure ipfw / trafficshaper
         */
        $("#reconfigureAct").click(function(){
            $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/trafficshaper/service/reconfigure", sendData={}, callback=function(data,status) {
                // when done, disable progress animation.
                $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");

                if (status != "success" || data['status'] != 'ok') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "Error reconfiguring trafficshaper",
                        message: data['status'],
                        draggable: true
                    });
                }
            });
        });

    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#pipes">{{ lang._('Pipes') }}</a></li>
    <li><a data-toggle="tab" href="#queues">{{ lang._('Queues') }}</a></li>
    <li><a data-toggle="tab" href="#rules">{{ lang._('Rules') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="pipes" class="tab-pane fade in active">
        <!-- tab page "pipes" -->
        <table id="grid-pipes" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogPipe">
            <thead>
            <tr>
                <th data-column-id="origin" data-type="string" data-visible="false">Origin</th>
                <th data-column-id="number" data-type="number"  data-visible="false">Number</th>
                <th data-column-id="bandwidth" data-type="number">Bandwidth</th>
                <th data-column-id="bandwidthMetric" data-type="string">BandwidthMetric</th>
                <th data-column-id="mask" data-type="string">Mask</th>
                <th data-column-id="description" data-type="string">Description</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">Commands</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">ID</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-pencil"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="queues" class="tab-pane fade in">
        <!-- tab page "queues" -->
        <table id="grid-queues" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogQueue">
            <thead>
            <tr>
                <th data-column-id="origin" data-type="string" data-visible="false">Origin</th>
                <th data-column-id="number" data-type="number" data-visible="false">Number</th>
                <th data-column-id="pipe" data-type="string">Pipe</th>
                <th data-column-id="weight" data-type="string">Weight</th>
                <th data-column-id="description" data-type="string">Description</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">Commands</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">ID</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-pencil"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div id="rules" class="tab-pane fade in">
        <!-- tab page "rules" -->
        <table id="grid-rules" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRule">
            <thead>
            <tr>
                <th data-column-id="sequence" data-type="number">#</th>
                <th data-column-id="origin" data-type="string"  data-visible="false">Origin</th>
                <th data-column-id="interface" data-type="string">Interface</th>
                <th data-column-id="proto" data-type="string">Protocol</th>
                <th data-column-id="source" data-type="string">Source</th>
                <th data-column-id="destination" data-type="string">Destination</th>
                <th data-column-id="target" data-type="string">Target</th>
                <th data-column-id="description" data-type="string">Description</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">Commands</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">ID</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr >
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-pencil"></span></button>
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


{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogPipe,'id':'DialogPipe','label':'Edit pipe'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogQueue,'id':'DialogQueue','label':'Edit queue'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogRule,'id':'DialogRule','label':'Edit rule'])}}
