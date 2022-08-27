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
        var data_get_map = {'frm_CaptureSettings':"/api/diagnostics/packet_capture/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        $("#btn_start > b").text("{{ lang._('Start') }}");

        $("#btn_start").click(function () {
            if (!$("#frm_CaptureSettings_progress").hasClass("fa-spinner")) {
                $("#dns_results").hide();
                $("#frm_CaptureSettings_progress").addClass("fa fa-spinner fa-pulse");
                let callb = function (data) {
                    $("#frm_CaptureSettings_progress").removeClass("fa fa-spinner fa-pulse");
                    return [];
                }
                saveFormToEndpoint("/api/diagnostics/packet_capture/set", 'frm_CaptureSettings', callb, false, callb);
            }
        });

    });
</script>

<div class="tab-content content-box col-xs-12 __mb">
    <div id="capture">
        {{ partial("layout_partials/base_form",['fields':captureForm,'id':'frm_CaptureSettings', 'apply_btn_id':'btn_start'])}}
    </div>
</div>
<div class="tab-content content-box col-xs-12 __mb">
</div>
