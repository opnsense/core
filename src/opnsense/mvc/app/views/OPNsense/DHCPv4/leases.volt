<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">

            <ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
                <li class="active"><a data-toggle="tab" id="leases_tab" href="#leases">{{ lang._('Leases') }}</a></li>
                <li><a data-toggle="tab" id="pools_tab" href="#pools">{{ lang._('Pools') }}</a></li>
            </ul>

            <div class="tab-content content-box">
                <div id="leases" class="tab-pane fade in active">
                    <section class="col-xs-12">
                        <div class="content-box">
                            <table id="grid-leases" class="table table-condensed table-hover table-striped table-responsive">
                                <thead>
                                    <tr>
                                        <th data-column-id="if_friendly" data-type="string">{{ lang._("Interface") }}</th>
                                        <th data-column-id="ip" data-type="string">{{ lang._("IP address") }}</th>
                                        <th data-column-id="mac" data-formatter="macman">{{ lang._("MAC address") }}</th>
                                        <th data-column-id="hostname" data-type="string">{{ lang._("Hostname") }}</th>
                                        <th data-column-id="descr" data-type="string">{{ lang._("Description") }}</th>
                                        <th data-column-id="start" data-type="string">{{ lang._("Start") }}</th>
                                        <th data-column-id="end" data-type="string">{{ lang._("End") }}</th>
                                        <th data-column-id="online" data-type="string">{{ lang._("Status") }}</th>
                                        <th data-column-id="act" data-type="string">{{ lang._("Lease type") }}</th>
                                        <th data-column-id="commands" data-formatter="commands"
                                            data-sortable="false">{{ lang._('Commands') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>

                            <div class="checkbox-inline pull-right">
                                <label>
                                    <input id="show_all" type="checkbox"> {{ lang._('Show all configured leases') }}
                                </label>
                            </div>
                        </div>
                    </section>
                </div>

                <div id="pools" class="tab-pane fade in">
                    <section class="col-xs-12">
                        <div class="content-box">
                            <table id="grid-pools" class="table table-condensed table-hover table-striped table-responsive">
                                <thead>
                                    <tr>
                                        <th data-column-id="name" data-type="string">{{ lang._("Failover Group") }}</th>
                                        <th data-column-id="mystate" data-type="string">{{ lang._("My State") }}</th>
                                        <th data-column-id="mydate" data-type="string">{{ lang._("Since") }}</th>
                                        <th data-column-id="peerstate" data-type="string">{{ lang._("Peer State") }}</th>
                                        <th data-column-id="peerdate" data-type="string">{{ lang._("Since") }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
    'use strict';

    $(document).ready(function () {
        createLeasesGrid();
        createPoolsGrid();

        $('#show_all').on('change', function() {
            $('#grid-leases').bootgrid('destroy');
            createLeasesGrid();
        });
    });

    function createLeasesGrid() {
        let searchUrl = '/api/dhcpv4/lease/searchItem';
        if ($('#show_all').is(':checked')) {
            searchUrl += '/showAll'
        }

        let grid = $('#grid-leases').UIBootgrid({
            search: searchUrl,
            del: '/api/dhcpv4/lease/delItem/',
            options: {
                rowCount: 20,
                formatters: {
                    macman: function(column, row) {
                        let retVal = row.mac;
                        if (row.manu !== '') {
                            retVal += '<br><small>' + row.manu + '</small>';
                        }
                        return retVal;
                    },
                    commands: function (column, row) {
                        let retVal = '';
                        if (row.type === 'dynamic' && row.if !== undefined && row.mac !== undefined) {
                            const hostname = (row.hostname !== undefined) ? row.hostname : '';
                            retVal +=
                                '<a class="btn btn-xs btn-default command-add-static" href="/services_dhcp_edit.php?if=' + row.if + '&amp;mac=' + row.mac + '&amp;hostname=' + hostname + '" title="Add static mapping for this MAC address">' +
                                    '<span class="fa fa-plus"></span>' +
                                '</a>';
                        }
                        if (row.online !== 'online') {
                            retVal += '<button type="button" class="btn btn-xs btn-default command-delete" data-type="' + row.type + '" data-row-id="' + row.encodedIp + '"><span class="fa fa-trash"></span></button>';
                        }
                        return retVal;
                    },
                }
            }
        });
    }

    function createPoolsGrid() {
        $('#grid-pools').UIBootgrid({
            search: '/api/dhcpv4/pool/searchItem',
            options: {
                rowCount: 20,
                formatters: {
                    commands: function (column, row) {
                        return '';
                    },
                }
            }
        });
    }
</script>