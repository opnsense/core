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
        let data_get_map = {'frm_settings':"/api/dnsmasq/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            try {
                $("#dnsmasq\\.dhcp\\.domain").attr(
                    "placeholder",
                    data.frm_settings.dnsmasq.dhcp.this_domain
                );
            } catch (e) {
                null;
            }
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('dnsmasq');
        });

        let all_grids = {};
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            let grid_ids = null;
            switch (e.target.hash) {
                case '#hosts':
                    grid_ids = ["{{formGridHostOverride['table_id']}}"];
                    break;
                case '#domains':
                    grid_ids = ["{{formGridDomainOverride['table_id']}}"];
                    break;
                case '#dhcptags':
                    grid_ids = ["{{formGridDHCPtag['table_id']}}"];
                    break;
                case '#dhcpranges':
                    grid_ids = ["{{formGridDHCPrange['table_id']}}"];
                    break;
                case '#dhcpoptions':
                    grid_ids = ["{{formGridDHCPoption['table_id']}}"];
                    break;
                case '#dhcpboot':
                    grid_ids = ["{{formGridDHCPboot['table_id']}}"];
                    break;
            }
            /* grid action selected, load or refresh target grid */
            if (grid_ids !== null) {
                grid_ids.forEach(function (grid_id, index) {
                    if (all_grids[grid_id] === undefined) {
                        const isGroupedGrid = [
                            "{{formGridDHCPrange['table_id']}}",
                            "{{formGridDHCPoption['table_id']}}",
                            "{{formGridDHCPboot['table_id']}}"
                        ].includes(grid_id);
                        all_grids[grid_id] = $("#"+grid_id).UIBootgrid({
                            'search':'/api/dnsmasq/settings/search_' + grid_id,
                            'get':'/api/dnsmasq/settings/get_' + grid_id + '/',
                            'set':'/api/dnsmasq/settings/set_' + grid_id + '/',
                            'add':'/api/dnsmasq/settings/add_' + grid_id + '/',
                            'del':'/api/dnsmasq/settings/del_' + grid_id + '/',
                            tabulatorOptions: {
                                groupBy: isGroupedGrid ? "%interface" : false,
                                groupHeader: (value, count, data, group) => {
                                    // Show "Any" when interface is empty
                                    const displayValue = !value ? "{{ lang._('Any') }}" : value;

                                    const icons = {
                                        interface: '<i class="fa fa-fw fa-ethernet fa-sm text-info"></i>',
                                    };

                                    const countValue = `<span class="badge chip">${count}</span>`;

                                    return `${icons.interface} ${displayValue} ${countValue}`;
                                },
                            },
                            options: {
                                triggerEditFor: getUrlHash('edit'),
                                initialSearchPhrase: getUrlHash('search'),
                                requestHandler: function(request) {
                                    const selectedTags = $('#tag_select').val();
                                    if (selectedTags && selectedTags.length > 0) {
                                        request['tags'] = selectedTags;
                                    }
                                    return request;
                                },
                                headerFormatters: {
                                    interface: function (column) {
                                        return '<i class="fa fa-fw fa-ethernet text-info"></i> {{ lang._("Interface") }}';
                                    },
                                    tag: function (column) {
                                        return '<i class="fa fa-fw fa-tag text-primary"></i> {{ lang._("Tag") }}';
                                    },
                                    set_tag: function (column) {
                                        return '<i class="fa fa-fw fa-tag text-primary"></i> {{ lang._("Tag [set]") }}';
                                    },
                                },
                            }
                        });
                    } else {
                        all_grids[grid_id].bootgrid('reload');

                    }
                    // insert tag selectpicker in all grids that use tags or interfaces, boot excluded cause two grids in same tab
                    if (!['domain'].includes(grid_id)) {
                        let header = $("#" + grid_id + "-header");
                        let $actionBar = header.find('.actionBar');
                        if ($actionBar.length) {
                            $('#tag_select_container').detach().insertAfter($actionBar.find('.search'));
                            $('#tag_select_container').show();
                        }
                    }

                    // host grid needs custom commands (upload/download)
                    if (['host'].includes(grid_id)) {
                        all_grids[grid_id].on('load.rs.jquery.bootgrid', function() {
                            $("#upload_hosts").SimpleFileUploadDlg({
                                onAction: function(){
                                    $("#{{formGridHostOverride['table_id']}}").bootgrid('reload');
                                }
                            });

                            $('#download_hosts').click(function(e) {
                                e.preventDefault();
                                window.open("/api/dnsmasq/settings/download_hosts");
                            });
                        })
                    }
                });
            }
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/dnsmasq/settings/set", 'frm_settings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('dnsmasq');
            }
        });

        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        // We use two kinds of url hashes appended to the tab hash: & to search a host and ? to create a host
        $(window).on('hashchange', function() {
            $('a[href="' + (window.location.hash.split(/[?&]/)[0] || '') + '"]').click();
        });

        $('a[href="' + (window.location.hash.split(/[?&]/)[0] || '#general') + '"]').click();

        $("#range\\.start_addr, #range\\.ra_mode, #option\\.type").on("keyup change", function () {
            const addr = $("#range\\.start_addr").val() || "";
            const ra_mode = String($("#range\\.ra_mode").val() || "").trim();
            const option_type = String($("#option\\.type").val() || "")

            const styleVisibility = [
                {
                    class: "style_dhcpv4",
                    visible: !addr.includes(":")
                },
                {
                    class: "style_dhcpv6",
                    visible: addr.includes(":")
                },
                {
                    class: "style_ra",
                    visible: ra_mode !== ""
                },
                {
                    class: "style_set",
                    visible: option_type == "set"
                },
                {
                    class: "style_match",
                    visible: option_type == "match"
                },
            ];

            styleVisibility.forEach(style => {
                const elements = $("." + style.class).closest("tr");
                style.visible ? elements.show() : elements.hide();
            });
        });

        // Populate tag selectpicker
        $('#tag_select').fetch_options('/api/dnsmasq/settings/get_tag_list');

        $('#tag_select').change(function () {
            Object.keys(all_grids).forEach(function (grid_id) {
                if (!['domain'].includes(grid_id)) {
                    all_grids[grid_id].bootgrid('reload');
                }
            });

            $('#tag_select_icon')
                .toggleClass('text-success fa-filter-circle-xmark', ($(this).val() || []).length > 0)
                .toggleClass('fa-filter', !($(this).val() || []).length);

        });

        // Clear tag selectpicker
        $('#tag_select_clear').on('click', function () {
            $('#tag_select').selectpicker('val', []);
            $('#tag_select').selectpicker('refresh');
            $('#tag_select').trigger('change');
        });

        // Autofill interface/tag when add dialog is opened
        $(
            '#{{ formGridHostOverride["edit_dialog_id"] }}, ' +
            '#{{ formGridDHCPrange["edit_dialog_id"] }}, ' +
            '#{{ formGridDHCPoption["edit_dialog_id"] }}, ' +
            '#{{ formGridDHCPboot["edit_dialog_id"] }}'
        ).on('opnsense_bootgrid_mapped', function(e, actionType) {
            if (actionType === 'add') {
                const selectedTags = $('#tag_select').val();

                if (selectedTags && selectedTags.length > 0) {
                    $(
                        '#host\\.set_tag, ' +
                        '#range\\.interface, #range\\.set_tag, ' +
                        '#option\\.interface, #option\\.tag, ' +
                        '#boot\\.interface, #boot\\.tag'
                    )
                    .selectpicker('val', selectedTags)
                    .selectpicker('refresh');
                }
            }
        });

        // Autofill dhcp reservation with URL hash
        if (window.location.hash.startsWith('#hosts?')) {
            const params = new URLSearchParams(window.location.hash.split('?')[1]);

            // Switch to hosts tab
            $('a[href="#hosts"]').one('shown.bs.tab', () => {
                // Wait for grid to be ready
                $('#{{ formGridHostOverride["table_id"] }}').one('loaded.rs.jquery.bootgrid', function () {
                    // Wait for dialog to be ready
                    $('#{{ formGridHostOverride["edit_dialog_id"] }}').one('opnsense_bootgrid_mapped', () => {
                        if (params.has('host')) $('#host\\.host').val(params.get('host'));
                        if (params.has('ip')) $('#host\\.ip').trigger('tokenize:tokens:add', [params.get('ip'), params.get('ip')]);
                        if (params.has('client_id')) $('#host\\.client_id').val(params.get('client_id'));
                        if (params.has('hwaddr')) $('#host\\.hwaddr').trigger('tokenize:tokens:add', [params.get('hwaddr'), params.get('hwaddr')]);
                        history.replaceState(null, null, window.location.pathname + '#hosts');
                    });

                    $(this).find('.command-add').trigger('click');
                });
            }).tab('show');
        }

    });
</script>

<style>
    tbody.collapsible > tr > td:first-child {
        padding-left: 30px;
    }
</style>

<div id="tag_select_container" class="btn-group" style="display: none;">
    <button type="button" id="tag_select_clear" class="btn btn-default" title="{{ lang._('Clear Selection') }}">
        <i id="tag_select_icon" class="fa fa-fw fa-filter"></i>
    </button>
    <select id="tag_select" class="selectpicker" multiple data-title="{{ lang._('Tags & Interfaces') }}" data-show-subtext="true" data-live-search="true" data-size="15" data-width="200px" data-container="body">
    </select>
</div>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#domains">{{ lang._('Domains') }}</a></li>
    <li><a data-toggle="tab" href="#hosts">{{ lang._('Hosts') }}</a></li>
    <li><a data-toggle="tab" href="#dhcpranges">{{ lang._('DHCP ranges') }}</a></li>
    <li><a data-toggle="tab" href="#dhcpoptions">{{ lang._('DHCP options') }}</a></li>
    <li><a data-toggle="tab" href="#dhcpboot">{{ lang._('DHCP boot') }}</a></li>
    <li><a data-toggle="tab" href="#dhcptags">{{ lang._('DHCP tags') }}</a></li>
</ul>

<div class="tab-content content-box">
    <!-- general settings  -->
    <div id="general"  class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_settings'])}}
    </div>
    <!-- Tab: Hosts -->
    <div id="hosts" class="tab-pane fade in">
        {{
            partial('layout_partials/base_bootgrid_table', formGridHostOverride + {
                'grid_commands': {
                    'upload_hosts': {
                        'title': lang._('Import csv'),
                        'class': 'btn btn-xs',
                        'icon_class': 'fa fa-fw fa-upload',
                        'data': {
                            'title': lang._('Import hosts'),
                            'endpoint': '/api/dnsmasq/settings/upload_hosts',
                            'toggle': 'tooltip'
                        }
                    },
                    'download_hosts': {
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
    <!-- Tab: Domains -->
    <div id="domains" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDomainOverride)}}
    </div>
    <!-- Tab: DHCP Ranges -->
    <div id="dhcpranges" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDHCPrange)}}
    </div>
    <!-- Tab: DHCP Options -->
    <div id="dhcpoptions" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDHCPoption)}}
    </div>
    <!-- Tab: DHCP Boot -->
    <div id="dhcpboot" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDHCPboot)}}
    </div>
    <!-- Tab: DHCP Tags -->
    <div id="dhcptags" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDHCPtag)}}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/dnsmasq/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditHostOverride,'id':formGridHostOverride['edit_dialog_id'],'label':lang._('Edit Host Override')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDomainOverride,'id':formGridDomainOverride['edit_dialog_id'],'label':lang._('Edit Domain Override')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDHCPtag,'id':formGridDHCPtag['edit_dialog_id'],'label':lang._('Edit DHCP tag')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDHCPrange,'id':formGridDHCPrange['edit_dialog_id'],'label':lang._('Edit DHCP range')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDHCPoption,'id':formGridDHCPoption['edit_dialog_id'],'label':lang._('Edit DHCP option')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditDHCPboot,'id':formGridDHCPboot['edit_dialog_id'],'label':lang._('Edit DHCP boot')])}}
