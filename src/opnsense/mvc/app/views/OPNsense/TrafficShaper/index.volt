{#
 #
 # Copyright (c) 2014-2016 Deciso B.V.
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

<style>
    .hidden {
        display:none;
    }
</style>

<script>

    $( document ).ready(function() {

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-pipes").UIBootgrid(
            {   search:'/api/trafficshaper/settings/searchPipes',
                get:'/api/trafficshaper/settings/getPipe/',
                set:'/api/trafficshaper/settings/setPipe/',
                add:'/api/trafficshaper/settings/addPipe/',
                del:'/api/trafficshaper/settings/delPipe/',
                toggle:'/api/trafficshaper/settings/togglePipe/'
            }
        );

        $("#grid-queues").UIBootgrid(
                {   search:'/api/trafficshaper/settings/searchQueues',
                    get:'/api/trafficshaper/settings/getQueue/',
                    set:'/api/trafficshaper/settings/setQueue/',
                    add:'/api/trafficshaper/settings/addQueue/',
                    del:'/api/trafficshaper/settings/delQueue/',
                    toggle:'/api/trafficshaper/settings/toggleQueue/'
                }
        );

        $("#grid-rules").UIBootgrid(
                {   search:'/api/trafficshaper/settings/searchRules',
                    get:'/api/trafficshaper/settings/getRule/',
                    set:'/api/trafficshaper/settings/setRule/',
                    add:'/api/trafficshaper/settings/addRule/',
                    del:'/api/trafficshaper/settings/delRule/',
                    toggle:'/api/trafficshaper/settings/toggleRule/',
                    options: {
                        responseHandler: function (response) {
                            // concatenate fields for not.
                            if ('rows' in response) {
                                for (var i = 0; i < response.rowCount; i++) {
                                    response.rows[i]['displaysrc'] = {'not':response.rows[i].source_not == '1',
                                                                      'val':response.rows[i].source};
                                    response.rows[i]['displaydst'] = {'not':response.rows[i].destination_not == '1',
                                                                      'val':response.rows[i].destination};
                                }
                            }
                            return response;
                        }
                    }
                }
        );


        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

        /**
         * Reconfigure ipfw / trafficshaper
         */
        $("#reconfigureAct").SimpleActionButton();

        $("#flushAct").click(function(){
          // Ask user if it's ok to flush all of ipfw
          BootstrapDialog.show({
              type:BootstrapDialog.TYPE_WARNING,
              title: '{{ lang._('Flush') }}',
              message: "{{ lang._('Are you sure you want to flush and reload all? this might have impact on other services using the same technology underneath (such as Captive portal)') }}",
              buttons: [{
                  label: '{{ lang._('Yes') }}',
                  action: function(dialogRef){
                      dialogRef.close();
                      $("#flushAct_progress").addClass("fa fa-spinner fa-pulse");
                      ajaxCall("/api/trafficshaper/service/flushreload", {}, function(data,status) {
                          // when done, disable progress animation.
                          $("#flushAct_progress").removeClass("fa fa-spinner fa-pulse");
                      });

                  }
              },{
                  label: '{{ lang._('No') }}',
                  action: function(dialogRef){
                      dialogRef.close();
                  }
              }]
          });
        });

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#pipes">{{ lang._('Pipes') }}</a></li>
    <li><a data-toggle="tab" href="#queues">{{ lang._('Queues') }}</a></li>
    <li><a data-toggle="tab" href="#rules">{{ lang._('Rules') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="pipes" class="tab-pane fade in active">
        <!-- tab page "pipes" -->
        <table id="grid-pipes" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogPipe" data-editAlert="shaperChangeMessage">
            <thead>
            <tr>
                <th data-column-id="origin" data-type="string" data-visible="false">{{ lang._('Origin') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="number" data-type="number"  data-visible="false">{{ lang._('Number') }}</th>
                <th data-column-id="bandwidth" data-type="number">{{ lang._('Bandwidth') }}</th>
                <th data-column-id="bandwidthMetric" data-type="string">{{ lang._('Metric') }}</th>
                <!--<th data-column-id="burst" data-type="number">{{ lang._('Burst') }}</th>--> <!-- disabled, burst does not work -->
                <th data-column-id="mask" data-type="string">{{ lang._('Mask') }}</th>
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
    </div>
    <div id="queues" class="tab-pane fade in">
        <!-- tab page "queues" -->
        <table id="grid-queues" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogQueue"  data-editAlert="shaperChangeMessage">
            <thead>
            <tr>
                <th data-column-id="origin" data-type="string" data-visible="false">{{ lang._('Origin') }}</th>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="number" data-type="number" data-visible="false">{{ lang._('Number') }}</th>
                <th data-column-id="pipe" data-type="string">{{ lang._('Pipe') }}</th>
                <th data-column-id="weight" data-type="string">{{ lang._('Weight') }}</th>
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
    </div>
    <div id="rules" class="tab-pane fade in">
        <!-- tab page "rules" -->
        <table id="grid-rules" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRule"  data-editAlert="shaperChangeMessage">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="sequence"  data-width="6em" data-type="number">{{ lang._('#') }}</th>
                <th data-column-id="origin" data-type="string"  data-visible="false">{{ lang._('Origin') }}</th>
                <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
                <th data-column-id="proto" data-type="string">{{ lang._('Protocol') }}</th>
                <th data-column-id="displaysrc" data-type="notprefixable">{{ lang._('Source') }}</th>
                <th data-column-id="displaydst" data-type="notprefixable">{{ lang._('Destination') }}</th>
                <th data-column-id="target" data-type="string">{{ lang._('Target') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr >
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div class="col-md-12">
        <div id="shaperChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/trafficshaper/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring trafficshaper') }}"
                type="button"
        ></button>

        <button class="btn btn-primary pull-right" id="flushAct" type="button"><b>{{ lang._('Reset') }}</b> <i id="flushAct_progress" class=""></i></button>
        <br/><br/>
    </div>
</div>


{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogPipe,'id':'DialogPipe','label':lang._('Edit pipe')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogQueue,'id':'DialogQueue','label':lang._('Edit queue')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogRule,'id':'DialogRule','label':lang._('Edit rule')])}}
