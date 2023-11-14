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
        var data_get_map = {'frm_TracerouteSettings':"/api/diagnostics/traceroute/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_query").click(function () {
            if (!$("#frm_TracerouteSettings_progress").hasClass("fa-spinner")) {
                $("#traceroute_results").hide();
                $("#frm_TracerouteSettings_progress").addClass("fa fa-spinner fa-pulse");
                let callb = function (data) {
                    $("#frm_TracerouteSettings_progress").removeClass("fa fa-spinner fa-pulse");
                    if (data.result === 'ok') {
                          $("#traceroute_results").show();
                          if (data.response.notice){
                                $("#traceroute_notice").html(data.response.notice);
                          }
                          $("#traceroute_results > tbody").empty();
                          data.response.rows.forEach(function(row) {
                              $tr = $("<tr/>");
                              $tr.append($("<td/>").append(row.ttl));
                              $tr.append($("<td/>").append(row.AS));
                              $tr.append($("<td/>").append(row.host));
                              $tr.append($("<td/>").append(row.address));
                              $tr.append($("<td/>").append(row.probes));
                              $("#traceroute_results > tbody").append($tr);
                          });
                          if (data.response.error) {
                            $tr = $("<tr/>");
                            $tr.append($("<td colspan='5'>").append(
                                $('<i class="fa fa-chevron-right" aria-hidden="true"></i>'),
                                '&nbsp;',
                                $("<span>").text(data.response.error)
                            ));
                            $("#traceroute_results > tbody").append($tr);
                          }
                    }
                }
                saveFormToEndpoint("/api/diagnostics/traceroute/set", 'frm_TracerouteSettings', callb, true, callb);
            }
        });

    });
</script>

<div class="tab-content content-box col-xs-12 __mb">
    <div id="traceroute">
        {{ partial("layout_partials/base_form",['fields':tracerouteForm,'id':'frm_TracerouteSettings', 'apply_btn_id':'btn_query'])}}
    </div>
</div>
<div class="tab-content content-box col-xs-12 __mb">
  <table class="table table-condensed" id="traceroute_results" style="display:none;">
    <thead>
      <tr>
          <th colspan="4">{{ lang._('Response')}}</th>
      </tr>
      <tr>
        <th colspan="4"><i class="fa fa-chevron-right" aria-hidden="true"></i> <span  id="traceroute_notice"></span></th>
      </tr>
      <tr>
        <th>{{ lang._('TTL')}}</th>
        <th>{{ lang._('AS#')}}</th>
        <th>{{ lang._('Host')}}</th>
        <th>{{ lang._('Address')}}</th>
        <th>{{ lang._('Probes')}}</th>
      </tr>
    </thead>
    <tbody>
    </tbody>
  </table>
</div>
