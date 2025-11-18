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

        let all_grids = {};
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            let grid_ids = null;

            switch (e.target.hash) {
                case '#subnets':
                    grid_ids = ["{{formGridSubnet['table_id']}}"];
                    break;
                case '#pdpools':
                    grid_ids = ["{{formGridPDPool['table_id']}}"];
                    break;
                case '#reservations':
                    grid_ids = ["{{formGridReservation['table_id']}}"];
                    break;
                case '#ha-peers':
                    grid_ids = ["{{formGridPeer['table_id']}}"];
                    break;
            }

            if (grid_ids !== null) {
                grid_ids.forEach(function (grid_id) {
                    if (all_grids[grid_id] === undefined) {
                        const isGroupedGrid = [
                            "{{formGridSubnet['table_id']}}",
                            "{{formGridPDPool['table_id']}}",
                            "{{formGridReservation['table_id']}}"
                        ].includes(grid_id);
                        all_grids[grid_id] = $("#" + grid_id).UIBootgrid({
                            search: '/api/kea/dhcpv6/search_' + grid_id,
                            get:    '/api/kea/dhcpv6/get_' + grid_id + '/',
                            set:    '/api/kea/dhcpv6/set_' + grid_id + '/',
                            add:    '/api/kea/dhcpv6/add_' + grid_id + '/',
                            del:    '/api/kea/dhcpv6/del_' + grid_id + '/',
                            tabulatorOptions: {
                                groupBy: !isGroupedGrid
                                    ? false
                                    : (grid_id === "{{formGridSubnet['table_id']}}"
                                        ? "subnet"
                                        : "%subnet"),
                                groupHeader: (value, count, data, group) => {
                                    const icons = {
                                        subnet: '<i class="fa fa-fw fa-ethernet fa-sm text-info"></i>',
                                    };
                                    const countValue = `<span class="badge chip">${count}</span>`;
                                    return `${icons.subnet} ${value} ${countValue}`;
                                },
                            },
                            options: {
                                triggerEditFor: getUrlHash('edit'),
                                initialSearchPhrase: getUrlHash('search')
                            }
                        });

                        // Reservation-only commands
                        if (grid_id === "{{ formGridReservation['table_id'] }}") {
                            all_grids[grid_id].on('load.rs.jquery.bootgrid', function() {
                                $("#upload_reservations").SimpleFileUploadDlg({
                                    onAction: function(){
                                        all_grids[grid_id].bootgrid('reload');
                                    }
                                });

                                $('#download_reservations').click(function(e){
                                    e.preventDefault();
                                    window.open("/api/kea/dhcpv6/download_reservations");
                                });
                            });
                        }

                    } else {
                        all_grids[grid_id].bootgrid('reload');
                    }
                });
            }
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/kea/dhcpv6/set", 'frm_generalsettings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
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

        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        // We use two kinds of url hashes appended to the tab hash: & to search a reservation and ? to create a reservation
        $(window).on('hashchange', function() {
            $('a[href="' + (window.location.hash.split(/[?&]/)[0] || '') + '"]').click();
        });

        $('a[href="' + (window.location.hash.split(/[?&]/)[0] || '#settings') + '"]').click();

        // Autofill dhcp reservation with URL hash
        if (window.location.hash.startsWith('#reservations?')) {
            const params = new URLSearchParams(window.location.hash.split('?')[1]);

            $('a[href="#reservations"]').one('shown.bs.tab', () => {
                $('#{{ formGridReservation["table_id"] }}').one('loaded.rs.jquery.bootgrid', function () {
                    $('#{{ formGridReservation["edit_dialog_id"] }}').one('opnsense_bootgrid_mapped', () => {
                        if (params.has('hostname')) $('#reservation\\.hostname').val(params.get('hostname'));
                        if (params.has('ip_address')) $('#reservation\\.ip_address').val(params.get('ip_address'));
                        if (params.has('duid')) $('#reservation\\.duid').val(params.get('duid'));
                        history.replaceState(null, null, window.location.pathname + '#reservations');
                    });
                    $(this).find('.command-add').trigger('click');
                });
            }).tab('show');
        }

    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#settings" id="tab_settings">{{ lang._('Settings') }}</a></li>
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
        {{
            partial('layout_partials/base_bootgrid_table', formGridReservation + {
                'grid_commands': {
                    'upload_reservations': {
                        'title': lang._('Import csv'),
                        'class': 'btn btn-xs',
                        'icon_class': 'fa fa-fw fa-upload',
                        'data': {
                            'title': lang._('Import reservations'),
                            'endpoint': '/api/kea/dhcpv6/upload_reservations',
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

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/kea/service/reconfigure', 'data_service_widget': 'kea'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogSubnet,'id':formGridSubnet['edit_dialog_id'],'label':lang._('Edit Subnet')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPDPool,'id':formGridPDPool['edit_dialog_id'],'label':lang._('Edit PD Pool')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogReservation,'id':formGridReservation['edit_dialog_id'],'label':lang._('Edit Reservation')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPeer,'id':formGridPeer['edit_dialog_id'],'label':lang._('Edit Peer')])}}
