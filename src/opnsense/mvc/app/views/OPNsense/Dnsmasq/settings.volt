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
                    grid_ids = ["{{formGridDHCPoption['table_id']}}", "{{formGridDHCPboot['table_id']}}"];
                    break;
            }
            /* grid action selected, load or refresh target grid */
            if (grid_ids !== null) {
                grid_ids.forEach(function (grid_id, index) {
                    if (all_grids[grid_id] === undefined) {
                        all_grids[grid_id] = $("#"+grid_id).UIBootgrid({
                            'search':'/api/dnsmasq/settings/search_' + grid_id,
                            'get':'/api/dnsmasq/settings/get_' + grid_id + '/',
                            'set':'/api/dnsmasq/settings/set_' + grid_id + '/',
                            'add':'/api/dnsmasq/settings/add_' + grid_id + '/',
                            'del':'/api/dnsmasq/settings/del_' + grid_id + '/',
                            options: {
                                triggerEditFor: getUrlHash('edit'),
                                initialSearchPhrase: getUrlHash('search'),
                                requestHandler: function(request) {
                                    const selectedTags = $('#tag_select').val();
                                    if (selectedTags && selectedTags.length > 0) {
                                        request['tags'] = selectedTags;
                                    }
                                    return request;
                                }
                            }
                        });
                        /* insert headers when multiple grids exist on a single tab */
                        let header = $("#" + grid_id + "-header");
                        if (grid_id === 'option' ) {
                            header.find('div.actionBar').parent().prepend(
                                $('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Options') }}</div>')
                            );
                        } else if (grid_id == 'boot') {
                            header.find('div.actionBar').parent().prepend(
                                $('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Boot') }}</div>')
                            );
                        } else if (grid_id == 'host') {
                            all_grids[grid_id].find("tfoot td:last").append($("#hosts_tfoot_append > button").detach());
                        }
                    } else {
                        all_grids[grid_id].bootgrid('reload');

                    }
                    // insert tag selectpicker in all grids that use tags or interfaces, boot excluded cause two grids in same tab
                    if (!['domain', 'boot'].includes(grid_id)) {
                        let header = $("#" + grid_id + "-header");
                        let $actionBar = header.find('.actionBar');
                        if ($actionBar.length) {
                            $('#tag_select_container').detach().insertBefore($actionBar.find('.search'));
                            $('#tag_select_container').show();
                        }
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

        $("#download_hosts").click(function(e){
            e.preventDefault();
            window.open("/api/dnsmasq/settings/download_hosts");
        });
        $("#upload_hosts").SimpleFileUploadDlg({
            onAction: function(){
                $("#{{formGridHostOverride['table_id']}}").bootgrid('reload');
            }
        });

        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
        let selected_tab = window.location.hash != "" ? window.location.hash : "#general";
        $('a[href="' +selected_tab + '"]').click();

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
                // boot is not excluded here, as it reloads in same tab as options
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
                        '#boot\\.tag'
                    )
                    .selectpicker('val', selectedTags)
                    .selectpicker('refresh');
                }
            }
        });

    });
</script>

<style>
    tbody.collapsible > tr > td:first-child {
        padding-left: 30px;
    }
    #tag_select_clear {
        border-right: none;
    }
    #tag_select_container {
        margin-right: 20px;
    }
    #tag_select_container .bootstrap-select > .dropdown-toggle {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
</style>

<div style="display: none;" id="hosts_tfoot_append">
    <button
        id="upload_hosts"
        type="button"
        data-title="{{ lang._('Import hosts') }}"
        data-endpoint='/api/dnsmasq/settings/upload_hosts'
        title="{{ lang._('Import csv') }}"
        data-toggle="tooltip"
        class="btn btn-xs"
    ><span class="fa fa-fw fa-upload"></span></button>
    <button id="download_hosts" type="button" title="{{ lang._('Export as csv') }}" data-toggle="tooltip"  class="btn btn-xs"><span class="fa fa-fw fa-table"></span></button>
</div>

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
    <li><a data-toggle="tab" href="#hosts">{{ lang._('Hosts') }}</a></li>
    <li><a data-toggle="tab" href="#domains">{{ lang._('Domains') }}</a></li>
    <li><a data-toggle="tab" href="#dhcpranges">{{ lang._('DHCP ranges') }}</a></li>
    <li><a data-toggle="tab" href="#dhcpoptions">{{ lang._('DHCP options') }}</a></li>
    <li><a data-toggle="tab" href="#dhcptags">{{ lang._('DHCP tags') }}</a></li>
</ul>

<div class="tab-content content-box">
    <!-- general settings  -->
    <div id="general"  class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_settings'])}}
    </div>
    <!-- Tab: Hosts -->
    <div id="hosts" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridHostOverride + {'command_width': '8em'} )}}
    </div>
    <!-- Tab: Domains -->
    <div id="domains" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDomainOverride)}}
    </div>
    <!-- Tab: DHCP Ranges -->
    <div id="dhcpranges" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDHCPrange)}}
    </div>
    <!-- Tab: DHCP [boot] Options -->
    <div id="dhcpoptions" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridDHCPoption)}}
        <hr/>
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
