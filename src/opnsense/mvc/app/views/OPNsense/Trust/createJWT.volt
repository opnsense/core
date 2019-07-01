{#

Copyright 2019 Fabian Franz

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

<script>
    $(function () {

        let form_id = 'frm_jwt_create';
        let data_get_map = {'frm_jwt_create':"/api/trust/jwt/create_template"};

        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        $("#createAct").click(function () {
            saveFormToEndpoint("/api/trust/jwt/create_token", form_id, function (data) {
                if (data.result == 'success') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_SUCCESS,
                        title: '{{ lang._('Token') }}',
                        message: data.token
                    });
                } else if (data.result == 'failed') {
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_DANGER,
                        title: '{{ lang._('Error') }}',
                        message: "{{ lang._('The token cannot be generated.') }}"
                    });
                }
            });
        });
    });
</script>

<div id="general" class="tab-pane fade in active">
    <div class="content-box" style="padding-bottom: 1.5em;">
        {{ partial("layout_partials/base_form",['fields':formDialogCreate,'id':'frm_jwt_create'])}}
        <div class="col-md-12">
            <hr />
            <button class="btn btn-primary" id="createAct" type="button"><b>{{ lang._('Create') }}</b></button>
        </div>
    </div>
</div>