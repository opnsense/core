{#
 # Copyright (c) 2022 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or withoutmodification,
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
        let grid_jobs = $("#grid-jobs").UIBootgrid({
            search:'/api/diagnostics/packet_capture/search_jobs',
            options:{
                formatters: {
                    "commands": function (column, row) {
                        let btns = [];
                        btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-remove" title="{{ lang._('remove capture') }}" data-row-id="' + row.id + '"><span class="fa fa-fw fa-remove"></span></button> ');
                        if (row.status === 'stopped') {
                            btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-start" title="{{ lang._('(re)start capture') }}" data-row-id="' + row.id + '"><span class="fa fa-fw fa-play"></span></button> ');
                        } else if (row.status === 'running') {
                            btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-stop" title="{{ lang._('stop capture') }}" data-row-id="' + row.id + '"><span class="fa fa-fw fa-stop"></span></button> ');
                        }
                        btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-view" title="{{ lang._('view capture') }}" data-row-id="' + row.id + '"><span class="fa fa-fw fa-file-o"></span></button> ');

                        return btns.join("");
                        return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                            '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                            '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                    },
                }
            }
        });
        grid_jobs.on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
            $(".command-start").click(function(){
                let id = $(this).data('row-id');
                ajaxCall("/api/diagnostics/packet_capture/start/" + id, {}, function(){
                    $("#grid-jobs").bootgrid("reload");
                });
            });
            $(".command-stop").click(function(){
                let id = $(this).data('row-id');
                ajaxCall("/api/diagnostics/packet_capture/stop/" + id, {}, function(){
                    $("#grid-jobs").bootgrid("reload");
                });
            });
            $(".command-remove").click(function(){
                let id = $(this).data('row-id');
                ajaxCall("/api/diagnostics/packet_capture/remove/" + id, {}, function(){
                    $("#grid-jobs").bootgrid("reload");
                });
            });
            $(".command-view").click(function(){
                let id = $(this).data('row-id');
                ajaxGet("/api/diagnostics/packet_capture/view/" + id, {}, function(data){
                    if (data.interfaces !== undefined) {
                        var html = [];
                        $.each(data.interfaces, function(intf, data){
                          $.each(data['rows'], function(idx, line){
                              html.push(
                                $("<tr>").append(
                                  $("<td>").append(
                                    $("<span>").text(data.name),
                                    $("<br>"),
                                    $("<small>").text(intf),
                                  )
                                ).append(
                                   $("<td>").text(line.raw)
                                )
                              );
                          });
                        });
                        $("#capture_output").empty().append(html);
                        $("#pcapview").modal({});
                    }
                });
            });
        });

        var data_get_map = {'frm_CaptureSettings':"/api/diagnostics/packet_capture/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_start_new > b").text("{{ lang._('Start') }}");

        $("#btn_start_new").click(function () {
            if (!$("#frm_CaptureSettings_progress").hasClass("fa-spinner")) {
                $("#dns_results").hide();
                $("#frm_CaptureSettings_progress").addClass("fa fa-spinner fa-pulse");
                let callb = function (data) {
                    $("#frm_CaptureSettings_progress").removeClass("fa fa-spinner fa-pulse");
                    if (data.result && data.result === 'ok') {
                        ajaxCall("/api/diagnostics/packet_capture/start/" + data.uuid, {}, function(){
                            $("#grid-jobs").bootgrid("reload");
                        });
                    }
                }
                saveFormToEndpoint("/api/diagnostics/packet_capture/set", 'frm_CaptureSettings', callb, false, callb);
            }
        });


    });
</script>

<div class="tab-content content-box col-xs-12 __mb">
    <div id="capture">
        {{ partial("layout_partials/base_form",['fields':captureForm,'id':'frm_CaptureSettings', 'apply_btn_id':'btn_start_new'])}}
    </div>
</div>
<div class="tab-content content-box col-xs-12 __mb">
  <table id="grid-jobs" class="table table-condensed table-hover table-striped table-responsive">
      <thead>
        <tr>
            <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
      </thead>
      <tbody>
      </tbody>
  </table>
  <button id="btn_test" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
</div>

<!-- View capture (pcap) modal -->
<div class="modal" tabindex="-1" role="dialog" id="pcapview">
  <div class="modal-dialog" style="width: 80%;" role="document">
    <div class="modal-content">
      <div class="modal-header">
          <div class="bootstrap-dialog-header">
            <div class="bootstrap-dialog-close-button" style="">
              <button class="close" class="close" data-dismiss="modal" aria-label="Close">×</button>
            </div>
            <div class="bootstrap-dialog-title">
              {{ lang._('View capture') }}
            </div>
          </div>
      </div>
      <div class="modal-body">
        <table class="table table-condensed">
          <thead>
              <tr>
                <th><?=gettext("Interface");?></th>
                <th><?=gettext("Capture output");?></th>
              </tr>
          </thead>
          <tbody style="white-space: pre-wrap; font-family: monospace;" id="capture_output">
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ lang._('Close') }}</button>
      </div>
    </div>
  </div>
</div>
