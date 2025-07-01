{#
 # Copyright (c) 2016 Deciso B.V.
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
        var data_get_map = {'frm_CaptureSettings':"/api/diagnostics/netflow/getconfig"};
        mapDataToFormUI(data_get_map).done(function(data){
            // place actions to run after load, for example update form styles.
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        // link save button to API set action
        $("#btn_save_capture").click(function () {
            $("#frm_CaptureSettings_progress").addClass("fa fa-spinner fa-pulse");
            saveFormToEndpoint("/api/diagnostics/netflow/setconfig", 'frm_CaptureSettings', function () {
                ajaxCall("/api/diagnostics/netflow/reconfigure", {}, function (data, status) {
                    $("#frm_CaptureSettings_progress").removeClass("fa fa-spinner fa-pulse");
                });
	    }, true, function () {
                $("#frm_CaptureSettings_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        $("#act_refresh_cache_stats").click(function(){
            ajaxGet('/api/diagnostics/netflow/cache_stats', {}, function(data, status) {
                var html = [];
                // convert to plain Array
                var data_arr = $.makeArray(data)[0];
                // sort by flow
                Object.keys(data_arr).sort().forEach(function (index) {
                    let value = data_arr[index];
                    var fields = ["if", "DstIPaddresses", "SrcIPaddresses", "Pkts"];
                    let tr_str = '<tr>';
                    tr_str += '<td>'+index+'</td>';
                    for (var i = 0; i < fields.length; i++) {
                        if (value[fields[i]] != null) {
                            tr_str += '<td>' + value[fields[i]] + '</td>';
                        } else {
                            tr_str += '<td></td>';
                        }
                    }
                    tr_str += '</tr>';
                    html.push(tr_str);
                });
                $("#cache_stats > tbody").html(html.join(''));
            });
        });

        // refresh cache stats on tab open
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id == 'cache_tab'){
                $("#act_refresh_cache_stats").click();
            }
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="capture_tab" href="#capture">{{ lang._('Capture') }}</a></li>
    <li><a data-toggle="tab" id="cache_tab" href="#cache">{{ lang._('Cache') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="capture" class="tab-pane fade in active">
        <!-- tab page capture -->
        {{ partial("layout_partials/base_form",['fields':captureForm,'id':'frm_CaptureSettings', 'apply_btn_id':'btn_save_capture'])}}
    </div>
    <div id="cache" class="tab-pane fade in">
        <!-- tab page netflow cache -->
        <table class="table table-striped" id="cache_stats">
          <thead>
            <tr>
              <th>{{ lang._('Flow') }}</th>
              <th>{{ lang._('Interface') }}</th>
              <th>{{ lang._('Destinations') }}</th>
              <th>{{ lang._('Sources') }}</th>
              <th>{{ lang._('Pkts') }}</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="5">
                <button id="act_refresh_cache_stats" type="button" class="btn btn-default">
                  <span>{{ lang._('Refresh') }}</span>
                  <span class="fa fa-refresh"></span>
                </button>
              </td>
            </tr>
          </tfoot>
        </table>
    </div>
</div>
