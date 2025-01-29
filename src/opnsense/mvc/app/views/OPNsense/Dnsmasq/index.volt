{#
 # Copyright (c) 2025 Deciso B.V.
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
        let data_get_map = {'frm_settings':"/api/dnsmasq/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('dnsmasq');
        });

        $("#{{formGridHostOverride['table_id']}}").UIBootgrid({
            'search':'/api/dnsmasq/settings/search_host',
            'get':'/api/dnsmasq/settings/get_host/',
            'set':'/api/dnsmasq/settings/set_host/',
            'add':'/api/dnsmasq/settings/add_host/',
            'del':'/api/dnsmasq/settings/del_host/'
        });

        $("#{{formGridDomainOverride['table_id']}}").UIBootgrid({
            'search':'/api/dnsmasq/settings/search_domain',
            'get':'/api/dnsmasq/settings/get_domain/',
            'set':'/api/dnsmasq/settings/set_domain/',
            'add':'/api/dnsmasq/settings/add_domain/',
            'del':'/api/dnsmasq/settings/del_domain/'
        });


        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/dnsmasq/settings/set", 'frm_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('dnsmasq');
            }
        });
    });
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#hosts">{{ lang._('Hosts') }}</a></li>
    <li><a data-toggle="tab" href="#domains">{{ lang._('Domains') }}</a></li>
</ul>

<div class="tab-content content-box">
    <!-- general settings  -->
    <div id="general"  class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_settings'])}}
    </div>
    <!-- Tab: Hosts -->
    <div id="hosts" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridHostOverride)}}
    </div>
    <!-- Tab: Domains -->
    <div id="domains" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDomainOverride)}}
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/dnsmasq/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring Dnsmasq') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>


{{ partial("layout_partials/base_dialog",['fields':formDialogEditHostOverride,'id':formGridHostOverride['edit_dialog_id'],'label':lang._('Edit Host Override')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDomainOverride,'id':formGridDomainOverride['edit_dialog_id'],'label':lang._('Edit Domain Override')])}}
