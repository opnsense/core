{#
 # Copyright (c) 2023-2025 Deciso B.V.
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
$( document ).ready(function() {
    let data_get_map = {'frm_AclSettings': '/api/unbound/settings/get'};
    mapDataToFormUI(data_get_map).done(function() {
        $('.selectpicker').selectpicker('refresh');

        $("#unbound\\.acls\\.default_action").change(function() {
            saveFormToEndpoint(url="/api/unbound/settings/set", formid="frm_AclSettings", function() {
                /* Mimic bootgrid behaviour to nudge users towards reconfiguring */
                $("#AclChangeMessage").slideDown(1000, function() {
                    setTimeout(function() {
                        $("#AclChangeMessage").slideUp(2000);
                    }, 2000);
                });
            });
        });
    });

    $("#{{formGridAcl['table_id']}}").UIBootgrid({
        search:'/api/unbound/settings/search_acl',
        get:'/api/unbound/settings/get_acl/',
        set:'/api/unbound/settings/set_acl/',
        add:'/api/unbound/settings/add_acl/',
        del:'/api/unbound/settings/del_acl/',
        toggle:'/api/unbound/settings/toggle_acl/'
    });

    $("div.actionBar").parent().prepend($('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Access Control Lists') }}</div>'));

    $("#reconfigureAct").SimpleActionButton();
    updateServiceControlUI('unbound');
});
</script>

<style>
    .theading-text {
        font-weight: 800;
        font-style: italic;
    }
</style>

<div class="content-box __mb">
    {{ partial("layout_partials/base_form",['fields':aclForm,'id':'frm_AclSettings'])}}
</div>
<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridAcl)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/reconfigure', 'data_service_widget': 'unbound'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogAcl,'id':formGridAcl['edit_dialog_id'],'label':lang._('Edit ACL')])}}
