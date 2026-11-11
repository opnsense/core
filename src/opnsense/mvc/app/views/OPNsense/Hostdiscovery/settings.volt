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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
        const accessibleColumns = new Set("{{ accessibleColumns }}".split(','));
        const hostnames = new Map();
        const stateTraffic = new Map();

        function updateAccessibleColumns(grid) {
            ['hostname', 'statistics'].forEach(function(column) {
                grid.bootgrid(accessibleColumns.has(column) ? 'setColumns' : 'unsetColumns', [column]);
            });
        }

        function renderCache(grid, cache, column) {
            grid.getTable().getRows().forEach(function(row) {
                const address = row.getData().ip_address;
                if (cache.has(address)) {
                    row.getCell(column).setValue(cache.get(address));
                }
            });
        }

        function resolveHostnames(grid) {
            if (!accessibleColumns.has('hostname')) {
                return;
            }

            const addresses = [];
            grid.getTable().getRows().forEach(function(row) {
                const address = row.getData().ip_address;
                if (address) {
                    if (!hostnames.has(address)) {
                        hostnames.set(address, null);
                        addresses.push(address);
                        // null explicitly renders the in-progress spinner.
                        row.getCell('hostname').setValue(null);
                    } else {
                        row.getCell('hostname').setValue(hostnames.get(address));
                    }
                }
            });

            for (let i = 0; i < addresses.length; i += 50) {
                const batch = addresses.slice(i, i + 50);
                $.ajax({
                    type: 'GET',
                    url: '/api/diagnostics/dns/reverse_lookup',
                    dataType: 'json',
                    data: {address: batch},
                    success: function(data) {
                        batch.forEach(function(address) {
                            const hostname = data && data[address];
                            hostnames.set(address, hostname && hostname !== address ? hostname : '');
                        });
                        renderCache(grid, hostnames, 'hostname');
                    },
                    error: function() {
                        batch.forEach(function(address) {
                            hostnames.set(address, '');
                        });
                        renderCache(grid, hostnames, 'hostname');
                    }
                });
            }
        }

        function fetchStateTraffic(grid) {
            if (!accessibleColumns.has('statistics')) {
                return;
            }

            const addresses = [];
            grid.getTable().getRows().forEach(function(row) {
                const address = row.getData().ip_address;
                if (!address) {
                    return;
                }
                if (!stateTraffic.has(address)) {
                    stateTraffic.set(address, null);
                    addresses.push(address);
                }
                row.getCell('statistics').setValue(stateTraffic.get(address));
            });

            if (addresses.length === 0) {
                return;
            }

            ajaxCall('/api/diagnostics/firewall/query_pf_top_summary', {
                addresses: addresses.join(',')
            }, function(data) {
                addresses.forEach(function(address) {
                    stateTraffic.set(address, data?.items?.[address] || {bytes: 0, states: 0});
                });
                renderCache(grid, stateTraffic, 'statistics');
            });
        }

        let data_get_map = {'frm_settings':"/api/hostdiscovery/settings/get"};
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('hostdiscovery');
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/hostdiscovery/settings/set", 'frm_settings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            }
        });

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            switch (e.target.hash) {
                case '#hosts':
                    if (!$("#grid-hosts").hasClass('tabulator')) {
                        const grid = $("#grid-hosts").UIBootgrid({
                        search:'/api/hostdiscovery/service/search',
                        options: {
                            requestHandler: function (params) {
                                // sort on last_seen by default
                                if ($.isEmptyObject(params.sort)) {
                                    params.sort = {'last_seen': 'desc'};
                                }
                                return params;
                            },
                            headerFormatters: {
                                statistics: function() {
                                    const element = $(`
                                        <span>
                                            {{ lang._('Statistics') }}
                                            <span class="statistics-cache-flush"
                                                style="cursor:pointer; margin-left:4px;"
                                                data-toggle="tooltip"
                                                title="{{ lang._('Refresh') }}">
                                                <i class="fa-solid fa-fw fa-rotate-right"></i>
                                            </span>
                                        </span>
                                    `);

                                    element.find('.statistics-cache-flush').on('click', function(event) {
                                        event.stopPropagation();
                                        if (!accessibleColumns.has('statistics')) {
                                            return;
                                        }
                                        const bootgrid = grid.data('UIBootgrid');
                                        // Show the loading state while configd refreshes its cache.
                                        stateTraffic.clear();
                                        bootgrid.getTable().getRows().forEach(function(row) {
                                            row.getCell('statistics').setValue(null);
                                        });
                                        ajaxCall(
                                            '/api/diagnostics/firewall/flush_pf_top_cache',
                                            {},
                                            function() {
                                                fetchStateTraffic(bootgrid);
                                            }
                                        );
                                    });

                                    return element[0];
                                }
                            },
                            formatters: {
                                hostname: function(column, row) {
                                    if (row.hostname === null) {
                                        return '<span class="fa fa-spinner fa-pulse"></span>';
                                    }
                                    return $('<span>').text(row.hostname || '')[0];
                                },
                                statistics: function(column, row) {
                                    const statistics = row.statistics;
                                    if (statistics === null) {
                                        return '<span class="fa fa-spinner fa-pulse"></span>';
                                    }

                                    if (!statistics?.states) {
                                        return "";
                                    }

                                    const {states, bytes} = statistics;
                                    const statesUrl = '/ui/diagnostics/firewall/states#search=' +
                                        encodeURIComponent(row.ip_address);
                                    const bytesContent = bytes > 0 ? `
                                        <span data-toggle="tooltip" title="{{ lang._('Bytes') }}: ${bytes.toLocaleString()}">
                                            <i class="fa fa-fw fa-database"></i> ${byteFormat(bytes, 1)}
                                        </span>
                                    ` : '';

                                    return `
                                        <div class="stats-cell">
                                            <span data-toggle="tooltip" title="{{ lang._('States') }}: ${states.toLocaleString()}">
                                                <a href="${statesUrl}" target="_blank" rel="noopener noreferrer">
                                                    <i class="fa fa-fw fa-chart-line"></i> ${byteFormat(states, 1, true)}
                                                </a>
                                            </span>
                                            ${bytesContent}
                                        </div>
                                    `;
                                }
                            }
                        }
                        });
                        grid.on('loaded.rs.jquery.bootgrid', function() {
                            updateAccessibleColumns(grid);
                            const bootgrid = grid.data('UIBootgrid');
                            resolveHostnames(bootgrid);
                            fetchStateTraffic(bootgrid);
                        });
                    } else {
                        $("#grid-hosts").bootgrid('reload');
                    }
                    break;
            }
        });

        let selected_tab = window.location.hash != "" ? window.location.hash : "#settings";
        $('a[href="' +selected_tab + '"]').tab('show');
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
    });
</script>

<style>
    .stats-cell {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 10px;
        align-items: center;
        container-type: inline-size;
    }

    .stats-cell > span {
        white-space: nowrap;
    }

    @container (max-width: 160px) {
        .stats-cell > span {
            flex: 1 1 50%;
        }
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#settings" id="settings_tab">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#hosts" id="discovered_tab">{{ lang._('Discovered Hosts') }}</a></li>
</ul>
<div class="tab-content content-box">
    <!-- Tab: General settings -->
    <div id="settings"  class="tab-pane fade in">
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_settings'])}}
    </div>
    <!-- Tab: Hosts -->
    <div id="hosts" class="tab-pane fade in">
        <table id="grid-hosts" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="first_seen" data-visible="false" data-type="string" data-width="160px">{{ lang._('First Seen') }}</th>
                    <th data-column-id="last_seen" data-type="string" data-formatter="isodatetime" data-width="160px">{{ lang._('Last Seen') }}</th>
                    <th data-column-id="interface_name" data-type="string">{{ lang._('Interface') }}</th>
                    <th data-column-id="ip_address" data-min-width="260px">{{ lang._('IP Address') }}</th>
                    <th data-column-id="hostname" data-visible="false" data-type="string" data-formatter="hostname" data-sortable="false">{{ lang._('Hostname') }}</th>
                    <th data-column-id="ether_address" data-type="string" data-min-width="160px">{{ lang._('MAC Address') }}</th>
                    <th data-column-id="organization_name" data-type="string">{{ lang._('Organization') }}</th>
                    <th data-column-id="statistics" data-visible="false" data-formatter="statistics" data-sortable="false">{{ lang._('Statistics') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            </tfoot>
        </table>
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/hostdiscovery/service/reconfigure', 'data_exclude_scope': 'discovered_tab', 'data_service_widget': 'hostdiscovery'}) }}
