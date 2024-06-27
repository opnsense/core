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
    $( document ).ready(function() {
        let data_get_map = {'frm_generalsettings':"/api/kea/dhcpv4/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            try {
                $("#dhcpv4\\.ha\\.this_server_name").attr(
                    "placeholder",
                    data.frm_generalsettings.dhcpv4.this_hostname
                );
            } catch (e) {
                null;
            }
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('kea');
        });


        $("#grid-subnets").UIBootgrid(
            {   search:'/api/kea/dhcpv4/search_subnet',
                get:'/api/kea/dhcpv4/get_subnet/',
                set:'/api/kea/dhcpv4/set_subnet/',
                add:'/api/kea/dhcpv4/add_subnet/',
                del:'/api/kea/dhcpv4/del_subnet/'
            }
        );

        let grid_reservations = $("#grid-reservations").UIBootgrid(
            {   search:'/api/kea/dhcpv4/search_reservation',
                get:'/api/kea/dhcpv4/get_reservation/',
                set:'/api/kea/dhcpv4/set_reservation/',
                add:'/api/kea/dhcpv4/add_reservation/',
                del:'/api/kea/dhcpv4/del_reservation/'
            }
        );

        $("#grid-ha-peers").UIBootgrid(
            {   search:'/api/kea/dhcpv4/search_peer',
                get:'/api/kea/dhcpv4/get_peer/',
                set:'/api/kea/dhcpv4/set_peer/',
                add:'/api/kea/dhcpv4/add_peer/',
                del:'/api/kea/dhcpv4/del_peer/'
            }
        );

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/kea/dhcpv4/set", 'frm_generalsettings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('kea');
            }
        });

        /**
         * Reservations csv download and upload
         */
        $("#download_reservations").click(function(e){
            e.preventDefault();
            window.open("/api/kea/dhcpv4/download_reservations");
        });
        $("#upload_reservations").SimpleFileUploadDlg({
            onAction: function(){
                grid_reservations.bootgrid('reload');
            }
        });

        /**
         *
         */
        $("#subnet4\\.option_data_autocollect").change(function(){
            if ($(this).is(':checked')) {
                $(".option_data_autocollect").closest('tr').hide();
            } else {
                $(".option_data_autocollect").closest('tr').show();
            }
        });

    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings" id="tab_settings">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#subnets" id="tab_pools"> {{ lang._('Subnets') }} </a></li>
    <li><a data-toggle="tab" href="#reservations" id="tab_reservations"> {{ lang._('Reservations') }} </a></li>
    <li><a data-toggle="tab" href="#ha-peers" id="tab_ha-peers"> {{ lang._('HA Peers') }} </a></li>
</ul>
<div class="tab-content content-box">
    <!-- general settings  -->
    <div id="settings"  class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_generalsettings'])}}
    </div>
    <!-- subnets / pools  -->
    <div id="subnets" class="tab-pane fade in">
        <table id="grid-subnets" class="table table-condensed table-hover table-striped" data-editDialog="DialogSubnet">
            <thead>
                <tr>
                  <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                  <th data-column-id="subnet" data-type="string">{{ lang._('Subnet') }}</th>
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
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <!-- reservations -->
    <div id="reservations" class="tab-pane fade in">
        <table id="grid-reservations" class="table table-condensed table-hover table-striped" data-editDialog="DialogReservation">
            <thead>
                <tr>
                  <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                  <th data-column-id="subnet" data-type="string">{{ lang._('Subnet') }}</th>
                  <th data-column-id="ip_address" data-type="string">{{ lang._('IP Address') }}</th>
                  <th data-column-id="hw_address" data-type="string">{{ lang._('MAC') }}</th>
                  <th data-column-id="hostname" data-type="string">{{ lang._('Hostname') }}</th>
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
                        <button
                            id="upload_reservations"
                            type="button"
                            data-title="{{ lang._('Import reservations') }}"
                            data-endpoint='/api/kea/dhcpv4/upload_reservations'
                            title="{{ lang._('Import csv') }}"
                            data-toggle="tooltip"
                            class="btn btn-xs"
                        ><span class="fa fa-fw fa-upload"></span></button>
                        <button id="download_reservations" type="button" title="{{ lang._('Export as csv') }}" data-toggle="tooltip"  class="btn btn-xs"><span class="fa fa-fw fa-table"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <!-- HA - peers -->
    <div id="ha-peers" class="tab-pane fade in">
        <table id="grid-ha-peers" class="table table-condensed table-hover table-striped" data-editDialog="DialogPeer">
            <thead>
                <tr>
                  <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                  <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                  <th data-column-id="role" data-type="string">{{ lang._('Role') }}</th>
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
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/kea/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring DHCPv4') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':formDialogSubnet,'id':'DialogSubnet','label':lang._('Edit Subnet')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogReservation,'id':'DialogReservation','label':lang._('Edit Reservation')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPeer,'id':'DialogPeer','label':lang._('Edit Peer')])}}
