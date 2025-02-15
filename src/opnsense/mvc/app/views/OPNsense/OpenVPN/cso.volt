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
        const grid_cso = $("#{{formGridCSO['table_id']}}").UIBootgrid({
            search:'/api/openvpn/client_overwrites/search/',
            get:'/api/openvpn/client_overwrites/get/',
            add:'/api/openvpn/client_overwrites/add/',
            set:'/api/openvpn/client_overwrites/set/',
            del:'/api/openvpn/client_overwrites/del/',
            toggle:'/api/openvpn/client_overwrites/toggle/',
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

    });

</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#cso">{{ lang._('Overwrites') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="cso" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridCSO)}}
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogCSO,'id':formGridCSO['edit_dialog_id'],'label':lang._('Edit CSO')])}}
