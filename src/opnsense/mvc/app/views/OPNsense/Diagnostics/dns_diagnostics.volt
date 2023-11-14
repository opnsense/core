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
        var data_get_map = {'frm_DNSSettings':"/api/diagnostics/dns_diagnostics/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_query").click(function () {
            if (!$("#frm_DNSSettings_progress").hasClass("fa-spinner")) {
                $("#dns_results").hide();
                $("#frm_DNSSettings_progress").addClass("fa fa-spinner fa-pulse");
                let callb = function (data) {
                    $("#frm_DNSSettings_progress").removeClass("fa fa-spinner fa-pulse");
                    if (data.result === 'ok') {
                      if (data.response.error_message !== undefined) {
                          BootstrapDialog.show({
                              type:BootstrapDialog.TYPE_WARNING,
                              title: "{{ lang._('Query failure') }}",
                              message: data.response.error_message,
                              buttons: [{
                                  label: "{{ lang._('Close') }}",
                                  cssClass: 'btn-warning',
                                  action: function(dialogRef){
                                      dialogRef.close();
                                  }
                              }]
                          });
                      } else {
                          $("#dns_results").show();
                          $("#dns_results > tbody").empty();
                          Object.keys(data.response).forEach(function(qtype) {
                              $tr = $("<tr/>");
                              $tr.append($("<td/>").append(qtype));
                              $tr.append($("<td/>").append(data.response[qtype].answers.join('<br>')));
                              $tr.append($("<td/>").append(data.response[qtype].server));
                              $tr.append($("<td/>").append(data.response[qtype].query_time));
                              $("#dns_results > tbody").append($tr);
                          });
                      }
                    }
                }
                saveFormToEndpoint("/api/diagnostics/dns_diagnostics/set", 'frm_DNSSettings', callb, true, callb);
            }
        });

    });
</script>

<div class="tab-content content-box col-xs-12 __mb">
    <div id="capture">
        {{ partial("layout_partials/base_form",['fields':captureForm,'id':'frm_DNSSettings', 'apply_btn_id':'btn_query'])}}
    </div>
</div>
<div class="tab-content content-box col-xs-12 __mb">
  <table class="table table-condensed" id="dns_results" style="display:none;">
    <thead>
      <tr>
          <th colspan="4">{{ lang._('Response')}}</th>
      </tr>
      <tr>
        <th>{{ lang._('Type')}}</th>
        <th>{{ lang._('Answer')}}</th>
        <th>{{ lang._('Server')}}</th>
        <th>{{ lang._('Query time')}}</th>
      </tr>
    </thead>
    <tbody>
    </tbody>
  </table>
</div>
