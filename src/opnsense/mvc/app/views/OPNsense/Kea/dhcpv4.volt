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
        const data_get_map = {'frm_generalsettings':"/api/kea/dhcpv4/get"};
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


        $("#{{formGridSubnet['table_id']}}").UIBootgrid(
            {   search:'/api/kea/dhcpv4/search_subnet',
                get:'/api/kea/dhcpv4/get_subnet/',
                set:'/api/kea/dhcpv4/set_subnet/',
                add:'/api/kea/dhcpv4/add_subnet/',
                del:'/api/kea/dhcpv4/del_subnet/'
            }
        );

        const grid_reservations = $("#{{formGridReservation['table_id']}}").UIBootgrid(
            {   search:'/api/kea/dhcpv4/search_reservation',
                get:'/api/kea/dhcpv4/get_reservation/',
                set:'/api/kea/dhcpv4/set_reservation/',
                add:'/api/kea/dhcpv4/add_reservation/',
                del:'/api/kea/dhcpv4/del_reservation/'
            }
        ).on('load.rs.jquery.bootgrid', function() {
            $("#upload_reservations").SimpleFileUploadDlg({
                onAction: function(){
                    grid_reservations.bootgrid('reload');
                }
            });

            $('#download_reservations').click(function(e) {
                e.preventDefault();
                window.open("/api/kea/dhcpv4/download_reservations");
            });
        })

        $("#{{formGridPeer['table_id']}}").UIBootgrid(
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
        $("#dhcpv4\\.general\\.manual_config").change(function(){
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
                if (manual_config && $(this).find('#dhcpv4\\.general\\.manual_config').length == 0) {
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
    <!-- reservations -->
    <div id="reservations" class="tab-pane fade in">
        {{
            partial('layout_partials/base_bootgrid_table', formGridReservation + {
                'grid_commands': {
                    'upload_reservations': {
                        'title': lang._('Import csv'),
                        'class': 'btn btn-xs',
                        'icon_class': 'fa fa-fw fa-upload',
                        'data': {
                            'title': lang._('Import Reservations'),
                            'endpoint': '/api/kea/dhcpv4/upload_reservations',
                            'toggle': 'tooltip'
                        }
                    },
                    'download_reservations': {
                        'title': lang._('Export as csv'),
                        'class': 'btn btn-xs',
                        'icon_class': 'fa fa-fw fa-table',
                        'data': {
                            'toggle': 'tooltip'
                        }
                    }
                }
            })
        }}
    </div>
    <!-- HA - peers -->
    <div id="ha-peers" class="tab-pane fade in">
       {{ partial('layout_partials/base_bootgrid_table', formGridPeer)}}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/kea/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogSubnet,'id':formGridSubnet['edit_dialog_id'],'label':lang._('Edit Subnet')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogReservation,'id':formGridReservation['edit_dialog_id'],'label':lang._('Edit Reservation')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPeer,'id':formGridPeer['edit_dialog_id'],'label':lang._('Edit Peer')])}}
