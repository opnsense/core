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

        $("#{{formGridPipe['table_id']}}").UIBootgrid(
            {   search:'/api/trafficshaper/settings/search_pipes',
                get:'/api/trafficshaper/settings/get_pipe/',
                set:'/api/trafficshaper/settings/set_pipe/',
                add:'/api/trafficshaper/settings/add_pipe/',
                del:'/api/trafficshaper/settings/del_pipe/',
                toggle:'/api/trafficshaper/settings/toggle_pipe/'
            }
        );

        $("#{{formGridQueue['table_id']}}").UIBootgrid(
                {   search:'/api/trafficshaper/settings/search_queues',
                    get:'/api/trafficshaper/settings/get_queue/',
                    set:'/api/trafficshaper/settings/set_queue/',
                    add:'/api/trafficshaper/settings/add_queue/',
                    del:'/api/trafficshaper/settings/del_queue/',
                    toggle:'/api/trafficshaper/settings/toggle_queue/'
                }
        );

        $("#{{formGridRule['table_id']}}").UIBootgrid(
                {   search:'/api/trafficshaper/settings/search_rules',
                    get:'/api/trafficshaper/settings/get_rule/',
                    set:'/api/trafficshaper/settings/set_rule/',
                    add:'/api/trafficshaper/settings/add_rule/',
                    del:'/api/trafficshaper/settings/del_rule/',
                    toggle:'/api/trafficshaper/settings/toggle_rule/',
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

        $('<button class="btn btn-primary pull-right" id="flushAct" type="button"><b>{{ lang._("Reset") }}</b> <i id="flushAct_progress" class=""></i></button>')
        .insertAfter('#reconfigureAct');

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
        {{ partial('layout_partials/base_bootgrid_table', formGridPipe)}}
    </div>
    <div id="queues" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridQueue)}}
    </div>
    <div id="rules" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridRule)}}
    </div>
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/trafficshaper/service/reconfigure'}) }}
{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogPipe,'id':formGridPipe['edit_dialog_id'],'label':lang._('Edit pipe')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogQueue,'id':formGridQueue['edit_dialog_id'],'label':lang._('Edit queue')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogRule,'id':formGridRule['edit_dialog_id'],'label':lang._('Edit rule')])}}
