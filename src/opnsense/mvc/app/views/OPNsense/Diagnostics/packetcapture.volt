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

<style>
  .mac_selected {
      text-decoration: underline;
      font-weight: bolder;
  }

  .macfield {
      cursor: pointer;
  }
  .macinfo_header{
      font-weight: bolder;
  }
  .tooltip-inner {
      max-width: 500px;
  }
</style>

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

                        btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-download" title="{{ lang._('download capture') }}" data-row-id="' + row.id + '"><span class="fa fa-fw fa-cloud-download"></span></button> ');

                        btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-view" title="{{ lang._('view capture (high detail)') }}" data-detail="high" data-row-id="' + row.id + '"><span class="fa fa-fw fa-file"></span></button> ');
                        btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-view" title="{{ lang._('view capture (medium detail)') }}" data-detail="medium" data-row-id="' + row.id + '"><span class="fa fa-fw fa-file-text"></span></button> ');
                        btns.push('<button type="button" data-toggle="tooltip" class="btn btn-xs btn-default command-view" title="{{ lang._('view capture') }}" data-detail="normal" data-row-id="' + row.id + '"><span class="fa fa-fw fa-file-o"></span></button> ');

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
                let detail = $(this).data('detail');
                ajaxGet("/api/diagnostics/packet_capture/view/" + id + '/' + detail, {}, function(data){
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
                                  $("<td>").append(line.timestamp.replace('T', '<br/>'))
                                ).append(
                                  $("<td>").append(
                                    $("<span class='macfield' data-fam='"+line.fam+"' data-id='"+line.esrc+"'/>").text(line.esrc)
                                  )
                                ).append(
                                  $("<td>").append(
                                    $("<span class='macfield' data-fam='"+line.fam+"' data-id='"+line.edst+"'/>").text(line.edst)
                                  )
                                ).append(
                                   $("<td>").append(
                                     $("<span style='width:100%; word-break: break-word;'/>").html($("<code/>").html(line.raw))
                                   )
                                )
                              );
                          });
                        });
                        $("#capture_output").empty().append(html);
                        $("#pcapview").modal({});
                        $(".macfield").hover(function(){
                            let this_entry = $(this);
                            let this_mac = $(this).data('id');
                            if (!$(this).hasClass("mac_info_fetched")) {
                                $(this).addClass("mac_info_fetched");
                                ajaxGet("/api/diagnostics/packet_capture/mac_info/" + this_mac, {} , function(data){
                                    if (data.status === 'ok') {
                                        $(".macfield").each(function(){
                                            if (this_mac === $(this).data('id')) {
                                                $(this).addClass('mac_info_fetched');
                                                let addresses = [];
                                                if (data[$(this).data('fam')]) {
                                                    addresses =  data[$(this).data('fam')];
                                                }
                                                $(this).tooltip({
                                                  "html": true,
                                                  "title": '<span class="macinfo_header">'+data.org+'</span><br/>' + addresses.join("<br/>")
                                                });
                                            }
                                        });
                                        this_entry.tooltip('show');
                                    }
                                });
                            }
                            $(".macfield").each(function(){
                                if (this_mac === $(this).data('id')) {
                                    $(this).addClass('mac_selected');
                                }
                            });
                        }, function(){
                            $(".macfield").removeClass('mac_selected');
                            $(".macfield").tooltip('hide')
                        });
                    }
                });
            });
            $(".command-download").click(function(){
                let id = $(this).data('row-id');
                $('<a href="/api/diagnostics/packet_capture/download/'+id+'"></a>').get(0).click();
            });
        });

        var data_get_map = {'frm_CaptureSettings':"/api/diagnostics/packet_capture/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_start_new").click(function () {
            if (!$("#frm_CaptureSettings_progress").hasClass("fa-spinner")) {
                $("#frm_CaptureSettings_progress").addClass("fa fa-spinner fa-pulse");
                let callb = function (data) {
                    $("#frm_CaptureSettings_progress").removeClass("fa fa-spinner fa-pulse");
                    if (data.result && data.result === 'ok') {
                        ajaxCall("/api/diagnostics/packet_capture/start/" + data.uuid, {}, function(){
                            $("#capture_jobs_tab").click();
                            $("#grid-jobs").bootgrid("reload");
                        });
                    }
                }
                saveFormToEndpoint("/api/diagnostics/packet_capture/set", 'frm_CaptureSettings', callb, true, callb);
            }
        });

        /**
         *   Reformat static form items
         */
        $("#btn_start_new > b").text("{{ lang._('Start') }}");
        // (de)select all interfaces
        $(".interface_select").closest("td").find('a').remove();
        $(".interface_select").closest("td").find('br').remove();
        let btn_toggle_all = $('<button id="select_all" type="button" class="btn btn-default">');
        btn_toggle_all.append($('<i class="fa fa-check-square-o fa-fw" aria-hidden="true"></i>'));
        btn_toggle_all.tooltip({"title": "{{ lang._('(de)select all') }}"});
        btn_toggle_all.click(function(e){
            e.preventDefault();
            $(".interface_select  option").prop("selected", $("#select_all > i").hasClass("fa-check-square-o"));
            $("#select_all > i").toggleClass("fa-check-square-o fa-square-o");
            $(".interface_select").selectpicker('refresh');
        });
        $(".interface_select").closest("td").append(btn_toggle_all);

    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#capture">{{ lang._('Capture') }}</a></li>
    <li><a data-toggle="tab" id="capture_jobs_tab" href="#capture_jobs">{{ lang._('Jobs') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="capture" class="tab-pane fade in active">
      <div id="capture">
          {{ partial("layout_partials/base_form",['fields':captureForm,'id':'frm_CaptureSettings', 'apply_btn_id':'btn_start_new'])}}
      </div>
    </div>
     <div id="capture_jobs" class="tab-pane fade in">
       <table id="grid-jobs" class="table table-condensed table-hover table-striped table-responsive">
           <thead>
             <tr>
                 <th data-column-id="status" data-width="2em"  data-sortable="false" data-formatter="status">&nbsp;</th>
                 <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                 <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                 <th data-column-id="commands" data-width="15em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
             </tr>
           </thead>
           <tbody>
           </tbody>
       </table>
     </div>
</div>


<!-- View capture (pcap) modal -->
<div class="modal" tabindex="-1" role="dialog" id="pcapview">
  <div class="modal-dialog" style="width: 90%;" role="document">
    <div class="modal-content">
      <div class="modal-header">
          <div class="bootstrap-dialog-header">
            <div class="bootstrap-dialog-close-button" style="">
              <button class="close" class="close" data-dismiss="modal" aria-label="Close">×</button>
            </div>
            <div class="bootstrap-dialog-title">
              <strong>{{ lang._('View capture') }}</strong>
            </div>
          </div>
      </div>
      <div class="modal-body">
        <table class="table table-condensed">
          <thead>
              <tr>
                <th>{{ lang._('Interface') }}</th>
                <th>{{ lang._('Timestamp') }}</th>
                <th>{{ lang._('SRC') }}</th>
                <th>{{ lang._('DST') }}</th>
                <th>{{ lang._('output') }}</th>
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
