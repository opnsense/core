{#
 # Copyright (c) 2014-2023 Deciso B.V.
 # Copyright (c) 2018 Michael Muenz <m.muenz@gmail.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
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
        const data_get_map = {'frm_general_settings':"/api/wireguard/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });

        const grid_peers = $("#{{formGridWireguardClient['table_id']}}").UIBootgrid({
                search: '/api/wireguard/client/search_client',
                get: '/api/wireguard/client/get_client/',
                set: '/api/wireguard/client/set_client/',
                add: '/api/wireguard/client/add_client/',
                del: '/api/wireguard/client/del_client/',
                toggle: '/api/wireguard/client/toggle_client/',
                options:{
                    initialSearchPhrase: getUrlHash('search'),
                    requestHandler: function(request){
                        if ( $('#server_filter').val().length > 0) {
                            request['servers'] = $('#server_filter').val();
                        }
                        return request;
                    }
            }
        });
        grid_peers.on("loaded.rs.jquery.bootgrid", function (e){
            // reload servers before grid load
            if ($("#server_filter > option").length == 0) {
                ajaxGet('/api/wireguard/client/list_servers', {}, function(data, status){
                    if (data.rows !== undefined) {
                        for (let i=0; i < data.rows.length ; ++i) {
                            let row = data.rows[i];
                            $("#server_filter").append($("<option/>").val(row.uuid).html(row.name));
                        }
                        $("#server_filter").selectpicker('refresh');
                    }
                });
            }
        });

        $("#{{formGridWireguardServer['table_id']}}").UIBootgrid({
            search: '/api/wireguard/server/search_server',
            get: '/api/wireguard/server/get_server/',
            set: '/api/wireguard/server/set_server/',
            add: '/api/wireguard/server/add_server/',
            del: '/api/wireguard/server/del_server/',
            toggle: '/api/wireguard/server/toggle_server/'
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/wireguard/general/set", 'frm_general_settings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            }
        });

        /**
         * Move keypair generation button inside the instance form and hook api event
         */
        $("#control_label_server\\.pubkey").append($("#keygen_div").detach().show());
        $("#keygen").click(function(){
            ajaxGet("/api/wireguard/server/key_pair", {}, function(data, status){
                if (data.status && data.status === 'ok') {
                    $("#server\\.pubkey").val(data.pubkey);
                    $("#server\\.privkey").val(data.privkey);
                }
            });
        })
        $("#control_label_client\\.psk").append($("#pskgen_div").detach().show());
        $("#pskgen").click(function(){
            ajaxGet("/api/wireguard/client/psk", {}, function(data, status){
                if (data.status && data.status === 'ok') {
                    $("#client\\.psk").val(data.psk);
                }
            });
        })

        /**
         * Quick instance filter on top
         */
        $("#filter_container").detach().insertAfter('#{{formGridWireguardClient["table_id"]}}-header .search');
        $("#server_filter").change(function(){
            $('#{{formGridWireguardClient['table_id']}}').bootgrid('reload');
        });

        /**
         * Peer generator tab hooks
         */
        $("#control_label_configbuilder\\.psk").append($("#pskgen_cb_div").detach().show());
        $("#pskgen_cb").click(function(){
            ajaxGet("/api/wireguard/client/psk", {}, function(data, status){
                if (data.status && data.status === 'ok') {
                    $("#configbuilder\\.psk").val(data.psk).change();
                }
            });
        })
        let tmp = $("#configbuilder\\.output").closest('tr');
        tmp.find('td:eq(2)').empty().append($("<div id='qrcode'/>"));
        $("#configbuilder\\.output").css('max-width', '100%');
        $("#configbuilder\\.output").css('height', '256px');
        $("#configbuilder\\.output").change(function(){
            $('#qrcode').empty().qrcode($(this).val());
        });

        $("#configbuilder\\.servers").change(function(){
            ajaxGet('/api/wireguard/client/get_server_info/' + $(this).val(), {}, function(data, status) {
                if (data.status === 'ok') {
                    let endpoint = $("#configbuilder\\.endpoint");
                    let peer_dns = $("#configbuilder\\.peer_dns");
                    $("#configbuilder\\.address").val(data.address);
                    peer_dns
                        .val(data.peer_dns)
                        .data('org-value', data.peer_dns);

                    endpoint
                        .val(data.endpoint)
                        .data('org-value', data.endpoint)
                        .data('mtu', data.mtu)
                        .data('pubkey', data.pubkey)
                        .change();
                }
            });
        });

        $("#configbuilder\\.store_btn").replaceWith($("#btn_configbuilder_save"));

        $("#btn_configbuilder_save").click(function(){
            let instance_id = $("#configbuilder\\.servers").val();
            let endpoint = $("#configbuilder\\.endpoint");
            let peer_dns = $("#configbuilder\\.peer_dns");
            let peer = {
                configbuilder: {
                    enabled: '1',
                    name: $("#configbuilder\\.name").val(),
                    pubkey: $("#configbuilder\\.pubkey").val(),
                    psk: $("#configbuilder\\.psk").val(),
                    tunneladdress: $("#configbuilder\\.address").val(),
                    keepalive: $("#configbuilder\\.keepalive").val(),
                    server: instance_id,
                    endpoint: endpoint.val()
                }
            };
            ajaxCall('/api/wireguard/client/add_client_builder', peer, function(data, status) {
                if (data.validations) {
                    if (data.validations['configbuilder.tunneladdress']) {
                        /*
                            tunnel address for the client is this peers address, since we remap these
                            in the form, we should remap the errors as well.
                        */
                        data.validations['configbuilder.address'] = data.validations['configbuilder.tunneladdress'];
                        delete data.validations['configbuilder.tunneladdress'];
                    }
                    handleFormValidation("frm_config_builder", data.validations);
                } else {
                    if (endpoint.val() != endpoint.data('org-value') || peer_dns.val() != peer_dns.data('org-value')) {
                        let param = {
                            'server': {
                                'endpoint': endpoint.val(),
                                'peer_dns': peer_dns.val()
                            }
                        };
                        ajaxCall('/api/wireguard/server/set_server/' + instance_id, param, function(data, status){
                            configbuilder_new();
                        });
                    } else {
                        configbuilder_new();
                    }
                }
            });
        });
        $('input[id ^= "configbuilder\\."]').change(configbuilder_update_config);
        $('select[id ^= "configbuilder\\."]').change(configbuilder_update_config);

        function configbuilder_new()
        {
            mapDataToFormUI({'frm_config_builder':"/api/wireguard/client/get_client_builder"}).done(function(data){
                    formatTokenizersUI();
                    $('.selectpicker').selectpicker('refresh');
                    ajaxGet("/api/wireguard/server/key_pair", {}, function(data, status){
                    if (data.status && data.status === 'ok') {
                            $("#configbuilder\\.pubkey").val(data.pubkey);
                            $("#configbuilder\\.privkey").val(data.privkey).change();
                        }
                    });
                    $("#configbuilder\\.tunneladdress").val("0.0.0.0/0,::/0");
                    clearFormValidation("frm_config_builder");
                });
        }

        function configbuilder_update_config()
        {
            let rows = [];
            rows.push('[Interface]');
            rows.push('PrivateKey = ' + $("#configbuilder\\.privkey").val());
            if ($("#configbuilder\\.address").val()) {
                rows.push('Address = ' + $("#configbuilder\\.address").val());
            }
            if ($("#configbuilder\\.peer_dns").val()) {
                rows.push('DNS = ' + $("#configbuilder\\.peer_dns").val());
            }
            if ($("#configbuilder\\.endpoint").data('mtu')) {
                rows.push('MTU = ' + $("#configbuilder\\.endpoint").data('mtu'));
            }
            rows.push('');
            rows.push('[Peer]');
            rows.push('PublicKey = ' + $("#configbuilder\\.endpoint").data('pubkey'));
            if ($("#configbuilder\\.psk").val()) {
                rows.push('PresharedKey = ' + $("#configbuilder\\.psk").val());
            }
            rows.push('Endpoint = ' + $("#configbuilder\\.endpoint").val());
            rows.push('AllowedIPs = ' + $("#configbuilder\\.tunneladdress").val());
            if ($("#configbuilder\\.keepalive").val()) {
                rows.push('PersistentKeepalive = ' + $("#configbuilder\\.keepalive").val());
            }
            $("#configbuilder\\.output").val(rows.join("\n")).change();
        }

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id == 'tab_configbuilder'){
                configbuilder_new();
            } else if (e.target.id == 'tab_peers') {
                $('#{{formGridWireguardClient['table_id']}}').bootgrid('reload');
            } else if (e.target.id == 'tab_instances') {
                $('#{{formGridWireguardServer['table_id']}}').bootgrid('reload');
            }
        });

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
    });
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="tab_instances" href="#instances">{{ lang._('Instances') }}</a></li>
    <li><a data-toggle="tab" id="tab_peers" href="#peers">{{ lang._('Peers') }}</a></li>
    <li><a data-toggle="tab" id="tab_configbuilder" href="#configbuilder">{{ lang._('Peer generator') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="peers" class="tab-pane fade in">
        <span id="pskgen_div" style="display:none" class="pull-right">
            <button id="pskgen" type="button" class="btn btn-secondary" title="{{ lang._('Generate new psk.') }}" data-toggle="tooltip">
              <i class="fa fa-fw fa-gear"></i>
            </button>
        </span>
        <div class="hidden">
            <!-- filter per server container -->
            <div id="filter_container" class="btn-group">
                <select id="server_filter"  data-title="{{ lang._('Instances') }}" class="selectpicker" data-live-search="true" data-size="5"  multiple data-width="200px">
                </select>
            </div>
        </div>
        {{ partial('layout_partials/base_bootgrid_table', formGridWireguardClient)}}
    </div>
    <div id="instances" class="tab-pane fade in active">
        <span id="keygen_div" style="display:none" class="pull-right">
            <button id="keygen" type="button" class="btn btn-secondary" title="{{ lang._('Generate new keypair.') }}" data-toggle="tooltip">
              <i class="fa fa-fw fa-gear"></i>
            </button>
        </span>
        {{ partial('layout_partials/base_bootgrid_table', formGridWireguardServer)}}
    </div>
    <div id="configbuilder" class="tab-pane fade in">
        <span id="pskgen_cb_div" style="display:none" class="pull-right">
            <button id="pskgen_cb" type="button" class="btn btn-secondary" title="{{ lang._('Generate new psk.') }}" data-toggle="tooltip">
              <i class="fa fa-fw fa-gear"></i>
            </button>
        </span>
        <span id="configbuilder_div" style="display:none">
            <button id="btn_configbuilder_save" type="button" class="btn btn-primary">
                <i class="fa fa-fw fa-check"></i>
              </button>
        </span>
        {{ partial("layout_partials/base_form",['fields':formDialogConfigBuilder,'id':'frm_config_builder'])}}
    </div>
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/wireguard/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditWireguardClient,'id':formGridWireguardClient['edit_dialog_id'],'label':lang._('Edit peer')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditWireguardServer,'id':formGridWireguardServer['edit_dialog_id'],'label':lang._('Edit instance')])}}
