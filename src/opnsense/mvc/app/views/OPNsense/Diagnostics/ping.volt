{#
 # Copyright (c) 2023 Deciso B.V.
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
        var data_get_map = {'frm_PingSettings':"/api/diagnostics/ping/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id == 'ping_jobs_tab') {
                if (!$("#grid-jobs").hasClass('tabulator')) {
                    let grid_jobs = $("#grid-jobs").UIBootgrid({
                        search:'/api/diagnostics/ping/search_jobs',
                        options:{
                            formatters: {
                                "commands": function (column, row) {
                                    let btns = [];
                                    btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-remove" title="{{ lang._('remove') }}" data-row-id="' + row.id + '"><span class="fa fa-fw fa-remove"></span></button> ');
                                    if (row.status === 'stopped') {
                                        btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-start" title="{{ lang._('(re)start') }}" data-row-id="' + row.id + '"><span class="fa fa-fw fa-play"></span></button> ');
                                    } else if (row.status === 'running') {
                                        btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-stop" title="{{ lang._('stop') }}" data-row-id="' + row.id + '"><span class="fa fa-fw fa-stop"></span></button> ');
                                    }
                                    return btns.join("");
                                },
                                "status": function (column, row) {
                                    if (row.status == 'running') {
                                        return '<i class="fa fa-fw fa-spinner fa-pulse"></i>';
                                    } else {
                                        return '<i class="fa fa-fw fa-stop-circle-o"></i>';
                                    }
                                }
                            }
                        }
                    });
                    grid_jobs.on('loaded.rs.jquery.bootgrid', function() {
                        $(".command-start").click(function(){
                            let id = $(this).data('row-id');
                            ajaxCall("/api/diagnostics/ping/start/" + id, {}, function(){
                                $("#grid-jobs").bootgrid("reload");
                            });
                        });
                        $(".command-stop").click(function(){
                            let id = $(this).data('row-id');
                            ajaxCall("/api/diagnostics/ping/stop/" + id, {}, function(){
                                $("#grid-jobs").bootgrid("reload");
                            });
                        });
                        $(".command-remove").click(function(){
                            let id = $(this).data('row-id');
                            ajaxCall("/api/diagnostics/ping/remove/" + id, {}, function(){
                                $("#grid-jobs").bootgrid("reload");
                            });
                        });
                    });
                } else {
                    $("#grid-jobs").bootgrid("reload");
                }
            }
        });

        $("#btn_start_new").click(function () {
            if (!$("#frm_PingSettings_progress").hasClass("fa-spinner")) {
                $("#frm_PingSettings_progress").addClass("fa fa-spinner fa-pulse");
                let callb = function (data) {
                    $("#frm_PingSettings_progress").removeClass("fa fa-spinner fa-pulse");
                    if (data.result && data.result === 'ok') {
                        ajaxCall("/api/diagnostics/ping/start/" + data.uuid, {}, function(){
                            $("#ping_jobs_tab").click();
                        });
                    }
                }
                saveFormToEndpoint("/api/diagnostics/ping/set", 'frm_PingSettings', callb, true, callb);
            }
        });
    });
</script>



<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#ping">{{ lang._('Ping') }}</a></li>
    <li><a data-toggle="tab" id="ping_jobs_tab" href="#ping_jobs">{{ lang._('Jobs') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="ping" class="tab-pane fade in active">
      <div id="ping">
          {{ partial("layout_partials/base_form",['fields':pingForm,'id':'frm_PingSettings', 'apply_btn_id':'btn_start_new'])}}
      </div>
    </div>
     <div id="ping_jobs" class="tab-pane fade in">
       <table id="grid-jobs" class="table table-condensed table-hover table-striped table-responsive">
           <thead>
             <tr>
                 <th data-column-id="status" data-width="2em"  data-sortable="false" data-formatter="status">&nbsp;</th>
                 <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                 <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                 <th data-column-id="hostname" data-type="string">{{ lang._('Hostname') }}</th>
                 <th data-column-id="source_address" data-type="string">{{ lang._('Source') }}</th>
                 <th data-column-id="send" data-width="6em" data-type="string">{{ lang._('Send') }}</th>
                 <th data-column-id="received" data-width="6em" data-type="string">{{ lang._('Received') }}</th>
                 <th data-column-id="min" data-width="6em" data-type="string">{{ lang._('Min') }}</th>
                 <th data-column-id="max" data-width="6em" data-type="string">{{ lang._('Max') }}</th>
                 <th data-column-id="avg" data-width="6em" data-type="string">{{ lang._('Avg') }}</th>
                 <th data-column-id="loss" data-width="6em" data-type="string">{{ lang._('loss') }}</th>
                 <th data-column-id="last_error" data-type="string">{{ lang._('Error') }}</th>
                 <th data-column-id="commands" data-width="15em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
             </tr>
           </thead>
           <tbody>
           </tbody>
       </table>
     </div>
</div>
