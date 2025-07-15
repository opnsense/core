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
        const data_get_map = {'frm_generalsettings':"/api/kea/dhcpv6/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            try {
                $("#dhcpv6\\.ha\\.this_server_name").attr(
                    "placeholder",
                    data.frm_generalsettings.dhcpv6.this_hostname
                );
            } catch (e) {
                null;
            }
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('kea');
        });


        $("#{{formGridSubnet['table_id']}}").UIBootgrid(
            {   search:'/api/kea/dhcpv6/search_subnet',
                get:'/api/kea/dhcpv6/get_subnet/',
                set:'/api/kea/dhcpv6/set_subnet/',
                add:'/api/kea/dhcpv6/add_subnet/',
                del:'/api/kea/dhcpv6/del_subnet/'
            }
        );

        $("#{{formGridPDPool['table_id']}}").UIBootgrid(
            {   search:'/api/kea/dhcpv6/search_pd_pool',
                get:'/api/kea/dhcpv6/get_pd_pool/',
                set:'/api/kea/dhcpv6/set_pd_pool/',
                add:'/api/kea/dhcpv6/add_pd_pool/',
                del:'/api/kea/dhcpv6/del_pd_pool/'
            }
        );

        const grid_reservations = $("#{{formGridReservation['table_id']}}").UIBootgrid(
            {   search:'/api/kea/dhcpv6/search_reservation',
                get:'/api/kea/dhcpv6/get_reservation/',
                set:'/api/kea/dhcpv6/set_reservation/',
                add:'/api/kea/dhcpv6/add_reservation/',
                del:'/api/kea/dhcpv6/del_reservation/'
            }
        );

        $("#{{formGridPeer['table_id']}}").UIBootgrid(
            {   search:'/api/kea/dhcpv6/search_peer',
                get:'/api/kea/dhcpv6/get_peer/',
                set:'/api/kea/dhcpv6/set_peer/',
                add:'/api/kea/dhcpv6/add_peer/',
                del:'/api/kea/dhcpv6/del_peer/'
            }
        );

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/kea/dhcpv6/set", 'frm_generalsettings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('kea');
            }
        });

        /**
         * Reservations csv download and upload
         */
        const $tfoot = grid_reservations.find("tfoot td:last");
        $tfoot.append(`
            <button
                id="upload_reservations"
                type="button"
                data-title="{{ lang._('Import reservations') }}"
                data-endpoint='/api/kea/dhcpv6/upload_reservations'
                title="{{ lang._('Import csv') }}"
                data-toggle="tooltip"
                class="btn btn-xs"
            >
                <span class="fa fa-fw fa-upload"></span>
            </button>
        `);
        $tfoot.append(`
            <button
                id="download_reservations"
                type="button"
                title="{{ lang._('Export as csv') }}"
                data-toggle="tooltip"
                class="btn btn-xs"
            >
                <span class="fa fa-fw fa-table"></span>
            </button>
        `);

        $("#download_reservations").click(function(e){
            e.preventDefault();
            window.open("/api/kea/dhcpv6/download_reservations");
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

        /* Manual configuration, hide all config elements except the service section*/
        $("#dhcpv6\\.general\\.manual_config").change(function(){
            let manual_config = $(this).is(':checked');
            if (manual_config) {
                if (!$("#show_advanced_frm_generalsettings").hasClass('fa-toggle-on')) {
                    /* enforce advanced mode so the user notices the checkbox */
                    $("#show_advanced_frm_generalsettings").click();
                }
                $(".is_managed").hide();
            } else {
                $(".is_managed").show();
            }
            $("#settings").find('table').each(function(){
                if (manual_config && $(this).find('#dhcpv6\\.general\\.manual_config').length == 0) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
            });
        });


    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings" id="tab_settings">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#subnets" id="tab_pools" class="is_managed"> {{ lang._('Subnets') }} </a></li>
    <li><a data-toggle="tab" href="#pdpools" id="tab_reservations" class="is_managed"> {{ lang._('PD Pools') }} </a></li>
    <li><a data-toggle="tab" href="#reservations" id="tab_reservations" class="is_managed"> {{ lang._('Reservations') }} </a></li>
    <li><a data-toggle="tab" href="#ha-peers" id="tab_ha-peers" class="is_managed"> {{ lang._('HA Peers') }} </a></li>
</ul>
<div class="tab-content content-box">
    <!-- general settings  -->
    <div id="settings"  class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_generalsettings'])}}
    </div>
    <!-- subnets / pools  -->
    <div id="subnets" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridSubnet)}}
    </div>
    <!-- prefix delegation pools  -->
    <div id="pdpools" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridPDPool)}}
    </div>
    <!-- reservations -->
    <div id="reservations" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridReservation)}}
    </div>
    <!-- HA - peers -->
    <div id="ha-peers" class="tab-pane fade in">
       {{ partial('layout_partials/base_bootgrid_table', formGridPeer)}}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/kea/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogSubnet,'id':formGridSubnet['edit_dialog_id'],'label':lang._('Edit Subnet')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPDPool,'id':formGridPDPool['edit_dialog_id'],'label':lang._('Edit PD Pool')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogReservation,'id':formGridReservation['edit_dialog_id'],'label':lang._('Edit Reservation')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPeer,'id':formGridPeer['edit_dialog_id'],'label':lang._('Edit Peer')])}}
