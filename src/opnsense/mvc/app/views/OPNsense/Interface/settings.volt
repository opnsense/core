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
    $(document).ready(function() {
        const getMap = {'frm_settings':'/api/interfaces/settings/get'};
        mapDataToFormUI(getMap).done(function(data) {
            const duids = data.frm_settings.duids;
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            const $container = $(`
                <div>
                    <a id="current" href="#">{{ lang._('Insert the currently configured DUID') }}</a><br/>
                    <a id="llt" href="#">{{ lang._('Insert a new LLT DUID') }}</a><br/>
                    <a id="ll" href="#">{{ lang._('Insert a new LL DUID') }}</a><br/>
                    <a id="uuid" href="#">{{ lang._('Insert a new UUID DUID') }}</a><br/>
                    <a id="en" href="#">{{ lang._('Insert a new EN DUID') }}</a><br/>
                    <a id="clear" class="text-danger" href="#">
                        <i class="fa fa-times-circle"></i>
                        <small>{{ lang._('Clear') }}</small>
                    </a>
                </div>
            `);
            $container.insertAfter($('#settings\\.ipv6duid'));
            ['current', 'llt', 'll', 'uuid', 'en'].forEach(id => {
                $(`#${id}`).click(function() {
                    $('#settings\\.ipv6duid').val(duids[id] ?? '');
                });
            });
            $('#clear').click(function() {
                $('#settings\\.ipv6duid').val('');
            })
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/interfaces/settings/set", 'frm_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
        });

    });
</script>

<div class="content-box">
        {{ partial("layout_partials/base_form",['fields':formDialogSettings,'id':'frm_settings'])}}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/settings/reconfigure'}) }}
