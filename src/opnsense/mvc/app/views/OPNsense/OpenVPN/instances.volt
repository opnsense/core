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
    'use strict';

    $( document ).ready(function () {
        const grid_instances = $("#{{formGridInstance['table_id']}}").UIBootgrid({
            search:'/api/openvpn/instances/search/',
            get:'/api/openvpn/instances/get/',
            add:'/api/openvpn/instances/add/',
            set:'/api/openvpn/instances/set/',
            del:'/api/openvpn/instances/del/',
            toggle:'/api/openvpn/instances/toggle/',
            options:{
                selection: false,
                formatters:{
                    tunnel: function (column, row) {
                        let items = [];
                        if (row.tunnel_network) {
                            items.push(row.tunnel_network);
                        }
                        if (row.tunnel_networkv6) {
                            items.push(row.tunnel_networkv6);
                        }
                        return items.join('<br/>');
                    }
                }
            }
        });

        const grid_statickeys = $("#{{formGridStaticKey['table_id']}}").UIBootgrid({
            search:'/api/openvpn/instances/search_static_key/',
            get:'/api/openvpn/instances/get_static_key/',
            add:'/api/openvpn/instances/add_static_key/',
            set:'/api/openvpn/instances/set_static_key/',
            del:'/api/openvpn/instances/del_static_key/'
        });

        $("#instance\\.role, #instance\\.dev_type").change(function(){
            const show_advanced = $("#show_advanced_formDialogdialog_dialogInstance").hasClass("fa-toggle-on");
            const this_role = $("#instance\\.role").val();
            const this_dev_type = $("#instance\\.dev_type").val();
            $(".role").each(function(){
                const tr = $(this).closest("tr").hide();
                if ((tr.data('advanced') === true && show_advanced) || !tr.data('advanced')) {
                    if ($(this).hasClass('role_' + this_role) || $(this).hasClass('role_' + this_role + '_' + this_dev_type)) {
                        tr.show();
                    }
                }
            });
        });
        $("#show_advanced_formDialogdialog_dialogInstance").click(function(){
            $("#instance\\.role").change();
        });

        // move "generate key" inside form dialog
        $("#row_statickey\\.mode > td:eq(1) > div:last").before($("#keygen_div").detach().show());
        $("#control_label_instance\\.auth-gen-token-secret").before($("#keygen_auth_token_div").detach().show());

        $("#keygen").click(function(){
            ajaxGet("/api/openvpn/instances/gen_key/secret", {}, function(data, status){
                if (data.result && data.result === 'ok') {
                    $("#statickey\\.key").val(data.key);
                }
            });
        });

        $("#keygen_auth_token").click(function(){
            ajaxGet("/api/openvpn/instances/gen_key/auth-token", {}, function(data, status){
                if (data.result && data.result === 'ok') {
                    $("#instance\\.auth-gen-token-secret").val(data.key);
                }
            });
        });


        $("#reconfigureAct").SimpleActionButton();
    });

</script>

<style>
    /* The instances grid column dropdown has many items */
    .actions .dropdown-menu.pull-right {
        max-height: 400px;
        min-width: max-content;
        overflow-y: auto;
        overflow-x: hidden;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#instances">{{ lang._('Instances') }}</a></li>
    <li><a data-toggle="tab" href="#statickeys">{{ lang._('Static Keys') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="instances" class="tab-pane fade in active">
        <span id="keygen_auth_token_div" style="display:none" class="pull-right">
            <button id="keygen_auth_token" type="button" class="btn btn-secondary" title="{{ lang._('Generate new auth-token.') }}" data-toggle="tooltip">
              <i class="fa fa-fw fa-gear"></i>
            </button>
        </span>
        {{ partial('layout_partials/base_bootgrid_table', formGridInstance)}}
    </div>
    <div id="statickeys" class="tab-pane fade in">
        <span id="keygen_div" style="display:none" class="pull-right">
            <button id="keygen" type="button" class="btn btn-secondary" title="{{ lang._('Generate new.') }}" data-toggle="tooltip">
                <i class="fa fa-fw fa-gear"></i>
            </button>
        </span>
        {{ partial('layout_partials/base_bootgrid_table', formGridStaticKey)}}
    </div>
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/openvpn/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogInstance,'id':formGridInstance['edit_dialog_id'],'label':lang._('Edit Instance')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogStaticKey,'id':formGridStaticKey['edit_dialog_id'],'label':lang._('Edit Static Key')])}}
