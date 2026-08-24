{#
 # Copyright (c) 2014-2026 Deciso B.V.
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
        let configbuilder_reference = null;
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
            options: {
                initialSearchPhrase: getUrlHash('search'),
                requestHandler: function(request){
                    if ( $('#server_filter').val().length > 0) {
                        request['servers'] = $('#server_filter').val();
                    }
                    return request;
                }
            },
            commands: {
                show_peer: {
                    filter: (cell) => !!cell.getData().privkey,
                    classname: "fa fa-fw fa-qrcode",
                    title: "{{ lang._('Open in peer generator') }}",
                    sequence: 1000,
                    method: function(event, cell) {
                        configbuilder_reference = cell.getData();
                        $('a[href="#configbuilder"]').tab('show');
                    }
                }
            },
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
         * Build the peer config for the peer generator
         */
        function buildPeerConfig(data)
        {
            let rows = [];
            rows.push('[Interface]');
            rows.push('PrivateKey = ' + data.privkey);
            if (data.address) {
                rows.push('Address = ' + data.address);
            }
            if (data.peer_dns) {
                rows.push('DNS = ' + data.peer_dns);
            }
            if (data.mtu) {
                rows.push('MTU = ' + data.mtu);
            }
            rows.push('');
            rows.push('[Peer]');
            rows.push('PublicKey = ' + data.pubkey);
            if (data.psk) {
                rows.push('PresharedKey = ' + data.psk);
            }
            rows.push('Endpoint = ' + data.endpoint);
            rows.push('AllowedIPs = ' + data.allowedips);
            if (data.keepalive) {
                rows.push('PersistentKeepalive = ' + data.keepalive);
            }
            return rows.join("\n");
        }

        /**
         * Load instance information for the peer generator
         */
        function configbuilder_load_server(server_id, callback, update_address = true)
        {
            ajaxGet('/api/wireguard/client/get_server_info/' + server_id, {}, function(data, status) {
                if (data.status === 'ok') {
                    let endpoint = $("#configbuilder\\.endpoint");
                    let peer_dns = $("#configbuilder\\.peer_dns");
                    let allowed_ips = $("#configbuilder\\.tunneladdress");

                    if (update_address) {
                        $("#configbuilder\\.address").val(data.address);
                    }

                    peer_dns
                        .val(data.peer_dns)
                        .data('org-value', data.peer_dns);

                    endpoint
                        .val(data.endpoint)
                        .data('org-value', data.endpoint)
                        .data('mtu', data.mtu)
                        .data('pubkey', data.pubkey);

                    const allowed_ips_value = data.allowed_ips || "0.0.0.0/0,::/0";
                    allowed_ips
                        .val(allowed_ips_value)
                        .data('org-value', allowed_ips_value);

                    if (callback) {
                        callback();
                    }

                    endpoint.change();
                }
            });
        }

        /**
         * Toggle peer generator reference mode
         */
        function configbuilder_set_reference_mode(enabled)
        {
            $("#configbuilder\\.name").prop("disabled", enabled);

            [
                "servers",
                "endpoint",
                "peer_dns",
                "tunneladdress",
                "keepalive",
                "pubkey",
                "privkey",
                "psk",
                "address",
                "store_privkey",
                "actions"
            ].forEach(function(field) {
                $("#row_configbuilder\\." + field).toggle(!enabled);
            });

            $("#pskgen_cb").toggle(!enabled);
        }

        /**
         * Load an existing peer into the peer generator
         */
        function configbuilder_load_reference(row)
        {
            const server_id = row.servers;

            $("#configbuilder\\.servers")
                .val(server_id)
                .selectpicker('refresh');

            $("#configbuilder\\.name").val(row.name);
            $("#configbuilder\\.pubkey").val(row.pubkey);
            $("#configbuilder\\.privkey").val(row.privkey);
            $("#configbuilder\\.psk").val(row.psk);
            $("#configbuilder\\.address").val(row.tunneladdress);
            $("#configbuilder\\.keepalive").val(row.keepalive);

            configbuilder_load_server(server_id, function() {
                configbuilder_set_reference_mode(true);
            }, false);
        }

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
        $("#configbuilder\\.output").css('max-width', '100%');
        $("#configbuilder\\.output").css('height', '256px');
        $("#configbuilder\\.output").on('input', function() {
            $("#configbuilder\\.qrcode").empty().qrcode($(this).val());
        });

        $("#configbuilder\\.servers").change(function(){
            configbuilder_load_server($(this).val());
        });

        $("#btn_configbuilder_cancel").click(function() {
            $('a[href="#peers"]').tab('show');
        });

        $("#btn_configbuilder_save").click(function(){
            if (configbuilder_reference !== null) {
                return;
            }
            let instance_id = $("#configbuilder\\.servers").val();
            let endpoint = $("#configbuilder\\.endpoint");
            let peer_dns = $("#configbuilder\\.peer_dns");
            let allowed_ips = $("#configbuilder\\.tunneladdress");
            let peer = {
                configbuilder: {
                    enabled: '1',
                    name: $("#configbuilder\\.name").val(),
                    pubkey: $("#configbuilder\\.pubkey").val(),
                    privkey: $("#configbuilder\\.store_privkey").prop("checked")
                        ? $("#configbuilder\\.privkey").val()
                        : '',
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
                         * tunnel address for the client is this peers address, since we remap these
                         * in the form, we should remap the errors as well.
                         */
                        data.validations['configbuilder.address'] = data.validations['configbuilder.tunneladdress'];
                        delete data.validations['configbuilder.tunneladdress'];
                    }
                    handleFormValidation("frm_config_builder", data.validations);
                } else {
                    $(document).trigger("settings-changed");
                    if (
                        endpoint.val() != endpoint.data('org-value') ||
                        peer_dns.val() != peer_dns.data('org-value') ||
                        allowed_ips.val() != allowed_ips.data('org-value')
                    ) {
                        let param = {
                            'server': {
                                'endpoint': endpoint.val(),
                                'peer_dns': peer_dns.val(),
                                'allowed_ips': allowed_ips.val()
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
            configbuilder_reference = null;
            configbuilder_set_reference_mode(false);

            mapDataToFormUI({'frm_config_builder':"/api/wireguard/client/get_client_builder"}).done(function(data){
                formatTokenizersUI();
                $('.selectpicker').selectpicker('refresh');
                // Private key storage is intentionally opt-in to preserve existing behavior.
                $("#configbuilder\\.store_privkey").prop("checked", false);
                ajaxGet("/api/wireguard/server/key_pair", {}, function(data, status){
                    if (data.status && data.status === 'ok') {
                        $("#configbuilder\\.pubkey").val(data.pubkey);
                        $("#configbuilder\\.privkey").val(data.privkey).change();
                    }
                });
                clearFormValidation("frm_config_builder");
            });
        }

        function configbuilder_update_config()
        {
            const config = buildPeerConfig({
                privkey: $("#configbuilder\\.privkey").val(),
                address: $("#configbuilder\\.address").val(),
                peer_dns: $("#configbuilder\\.peer_dns").val(),
                mtu: $("#configbuilder\\.endpoint").data('mtu'),
                pubkey: $("#configbuilder\\.endpoint").data('pubkey'),
                psk: $("#configbuilder\\.psk").val(),
                endpoint: $("#configbuilder\\.endpoint").val(),
                allowedips: $("#configbuilder\\.tunneladdress").val(),
                keepalive: $("#configbuilder\\.keepalive").val()
            });

            $("#configbuilder\\.output").val(config).trigger('input');
        }

        const $apply_container = $("#reconfigureAct").closest(".alert.content-box");

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id == 'tab_configbuilder') {
                if (configbuilder_reference !== null) {
                    configbuilder_load_reference(configbuilder_reference);
                    $apply_container.hide();
                } else {
                    configbuilder_new();
                    $apply_container.show();
                }
                $('#frm_general_settings').hide();
            } else if (e.target.id == 'tab_peers') {
                configbuilder_reference = null;
                $apply_container.show();
                $('#{{formGridWireguardClient['table_id']}}').bootgrid('reload');
                $('#frm_general_settings').show();
            } else if (e.target.id == 'tab_instances') {
                configbuilder_reference = null;
                $apply_container.show();
                $('#{{formGridWireguardServer['table_id']}}').bootgrid('reload');
                $('#frm_general_settings').show();
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
        {{ partial('layout_partials/base_bootgrid_table', formGridWireguardClient + {'command_width':'120'}) }}
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
        {{ partial("layout_partials/base_form",['fields':formDialogConfigBuilder,'id':'frm_config_builder'])}}
    </div>
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/wireguard/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditWireguardClient,'id':formGridWireguardClient['edit_dialog_id'],'label':lang._('Edit peer')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditWireguardServer,'id':formGridWireguardServer['edit_dialog_id'],'label':lang._('Edit instance')])}}
