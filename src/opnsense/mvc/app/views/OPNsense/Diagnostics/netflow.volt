{#

OPNsense® is Copyright © 2016 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}

<script type="text/javascript">
    $( document ).ready(function() {
        var data_get_map = {'frm_CaptureSettings':"/api/diagnostics/netflow/getconfig"};
        mapDataToFormUI(data_get_map).done(function(data){
            // place actions to run after load, for example update form styles.
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        // link save button to API set action
        $("#btn_save_capture").click(function(){
            saveFormToEndpoint(url="/api/diagnostics/netflow/setconfig",formid='frm_CaptureSettings',callback_ok=function(){
            });
        });

    });
</script>



<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#capture">{{ lang._('Capture') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="capture" class="tab-pane fade in active">
        <!-- tab page capture -->
        {{ partial("layout_partials/base_form",['fields':captureForm,'id':'frm_CaptureSettings', 'apply_btn_id':'btn_save_capture'])}}
    </div>
</div>
