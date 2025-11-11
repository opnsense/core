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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
        let data_get_map = {'frm_settings':"/api/hostdiscovery/settings/get"};
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('hostdiscovery');
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/hostdiscovery/settings/set", 'frm_settings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            }
        });

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            switch (e.target.hash) {
                case '#hosts':
                    if (!$("#grid-hosts").hasClass('tabulator')) {
                        $("#grid-hosts").UIBootgrid({
                        search:'/api/hostdiscovery/service/search',
                        options: {
                            responseHandler: function (response) {
                                if (response.rows.length > 0 && response.rows[0].source == 'discovery') {
                                    $("#legacy_alert").hide();
                                } else {
                                    $("#legacy_alert").show();
                                }
                                return response;
                            }
                        }
                        });
                    } else {
                        $("#grid-hosts").bootgrid('reload');
                    }
                    break;
            }
        });

        let selected_tab = window.location.hash != "" ? window.location.hash : "#settings";
        $('a[href="' +selected_tab + '"]').tab('show');
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#settings" id="settings_tab">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#hosts">{{ lang._('Discovered Hosts') }}</a></li>
</ul>
<div class="tab-content content-box">
    <!-- Tab: General settings -->
    <div id="settings"  class="tab-pane fade in">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_settings'])}}
    </div>
    <!-- Tab: Hosts -->
    <div id="hosts" class="tab-pane fade in">
        <div style="padding: 10px;">
            <div id="legacy_alert" class="alert alert-warning" role="alert" style="display: none;">
                {{ lang._('Host discovery service is disabled, below the hosts currently known by this firewall via ARP and NDP') }}
            </div>
        </div>
        <table id="grid-hosts" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="interface_name" data-type="string">{{ lang._('Interface') }}</th>
                    <th data-column-id="ip_address">{{ lang._('MAC Address') }}</th>
                    <th data-column-id="ether_address" data-type="string">{{ lang._('IP Address') }}</th>
                    <th data-column-id="organization_name" data-type="string">{{ lang._('Organization') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            </tfoot>
        </table>
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/hostdiscovery/service/reconfigure', 'data_service_widget': 'hostdiscovery'}) }}
