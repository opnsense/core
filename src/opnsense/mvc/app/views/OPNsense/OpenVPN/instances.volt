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
        let grid_instances = $("#grid-instances").UIBootgrid({
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

        let grid_statickeys = $("#grid-statickeys").UIBootgrid({
            search:'/api/openvpn/instances/search_static_key/',
            get:'/api/openvpn/instances/get_static_key/',
            add:'/api/openvpn/instances/add_static_key/',
            set:'/api/openvpn/instances/set_static_key/',
            del:'/api/openvpn/instances/del_static_key/'
        });

        $("#instance\\.role, #instance\\.dev_type").change(function(){
            let show_advanced = $("#show_advanced_formDialogDialogInstance").hasClass("fa-toggle-on");
            let this_role = $("#instance\\.role").val();
            let this_dev_type = $("#instance\\.dev_type").val();
            $(".role").each(function(){
                let tr = $(this).closest("tr").hide();

                if ((tr.data('advanced') === true && show_advanced) || !tr.data('advanced')) {
                    if ($(this).hasClass('role_' + this_role) || $(this).hasClass('role_' + this_role + '_' + this_dev_type)) {
                        tr.show();
                    }
                }
            });
        });
        $("#show_advanced_formDialogDialogInstance").click(function(){
            $("#instance\\.role").change();
        });

        // move "generate key" inside form dialog

        $("#row_statickey\\.mode > td:eq(1) > div:last").before($("#keygen_div").detach().show());
        $("#keygen").click(function(){
            ajaxGet("/api/openvpn/instances/gen_key", {}, function(data, status){
                if (data.result && data.result === 'ok') {
                    $("#statickey\\.key").val(data.key);
                }
            });
        })

        $("#reconfigureAct").SimpleActionButton();
    });

</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#instances">{{ lang._('Instances') }}</a></li>
    <li><a data-toggle="tab" href="#statickeys">{{ lang._('Static Keys') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="instances" class="tab-pane fade in active">
        <table id="grid-instances" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogInstance" data-editAlert="InstanceChangeMessage">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="role" data-type="string">{{ lang._('Role') }}</th>
                    <th data-column-id="dev_type" data-type="string">{{ lang._('Type') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
          </table>
        <div class="col-md-12">
            <div id="InstanceChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them') }}
            </div>
            <hr/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/openvpn/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring openvpn') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
      </div>
      <div id="statickeys" class="tab-pane fade in">
        <span id="keygen_div" style="display:none" class="pull-right">
            <button id="keygen" type="button" class="btn btn-secondary" title="{{ lang._('Generate new.') }}" data-toggle="tooltip">
              <i class="fa fa-fw fa-gear"></i>
            </button>
        </span>
        <table id="grid-statickeys" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogStaticKey">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
      </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogInstance,'id':'DialogInstance','label':lang._('Edit Instance')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogStaticKey,'id':'DialogStaticKey','label':lang._('Edit Static Key')])}}
