{#
 # Copyright (c) 2023 Deciso B.V.
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
        var data_get_map = {'frm_PortprobeSettings':"/api/diagnostics/portprobe/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_query").click(function () {
            if (!$("#frm_PortprobeSettings_progress").hasClass("fa-spinner")) {
                $("#portprobe_results").hide();
                $("#frm_PortprobeSettings_progress").addClass("fa fa-spinner fa-pulse");
                let callb = function (data) {
                    $("#frm_PortprobeSettings_progress").removeClass("fa fa-spinner fa-pulse");
                    if (data.result === 'ok') {
                          $("#portprobe_results").show();
                          if (data.response.message){
                                $("#portprobe_response").text(data.response.message);
                          }
                          $("#portprobe_results > tbody").empty();
                          if (data.response.payload) {
                            $tr = $("<tr/>");
                            $tr.append($("<td/>").append(data.response.payload));
                            $("#portprobe_results > tbody").append($tr);
                          }
                    }
                }
                saveFormToEndpoint("/api/diagnostics/portprobe/set", 'frm_PortprobeSettings', callb, true, callb);
            }
        });

    });
</script>

<div id="message" class="alert alert-warning" role="alert">
  <?= gettext('This page allows you to perform a simple TCP connection test to determine if a host is up and accepting connections on a given port. This test does not function for UDP since there is no way to reliably determine if a UDP port accepts connections in this manner.') ?>
  <br />
  <?= gettext('No data is transmitted to the remote host during this test, it will only attempt to open a connection and optionally display the data sent back from the server.') ?>
</div>

<div class="tab-content content-box col-xs-12 __mb">
    <div id="portprobe">
        {{ partial("layout_partials/base_form",['fields':portprobeForm,'id':'frm_PortprobeSettings', 'apply_btn_id':'btn_query'])}}
    </div>
</div>
<div class="tab-content content-box col-xs-12 __mb">
  <table class="table table-condensed" id="portprobe_results" style="display:none;">
    <thead>
      <tr>
          <th>{{ lang._('Response')}}</th>
      </tr>
      <tr>
        <th><i class="fa fa-chevron-right" aria-hidden="true"></i> <span  id="portprobe_response"></span></th>
      </tr>
    </thead>
    <tbody>
    </tbody>
  </table>
</div>
