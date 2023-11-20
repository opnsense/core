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
 #  INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
        function format_linerate(value) {
            if (!isNaN(value) && value > 0) {
                let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                let ndx = Math.floor(Math.log(value) / Math.log(1000) );
                if (ndx > 0) {
                    return  (value / Math.pow(1000, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx] + 'bit/s';
                } else {
                    return value.toFixed(2).toString();
                }
            } else {
                return "";
            }
        }

        function iterate_ips(obj) {
            let $elements = $('<div></div>');
            obj.forEach(function (ip) {
                $span = $('<span></span><br/>').text(ip['ipaddr'] + ' ');
                if ('vhid' in ip) {
                    $carp = $('<span></span>').text('vhid ' + ip['vhid']);
                    $carp.attr('class', 'bootgrid-tooltip badge badge-pill');
                    $carp.css('background-color', ip['status'] == 'MASTER' ? 'green' : 'primary');
                    $carp.attr('data-toggle', 'tooltip');
                    $carp.attr('title', ip['status']);
                    $span.append($carp);
                }
                $elements.append($span);
            });
            return $elements.prop('outerHTML');
        }

        $("#grid-overview").UIBootgrid(
            {
                search: '/api/interfaces/overview/interfacesInfo',
                options: {
                    selection: false,
                    formatters: {
                        "interface": function (column, row) {
                            let descr = row.description;
                            if (row.identifier) {
                                descr += ' (' + row.identifier + ')';
                            }
                            return descr;
                        },
                        "routes": function (column, row) {
                            let $elements = $('<div></div>').attr('class', 'route-container');
                            if (row.routes) {
                                let i = 0;
                                row.routes.forEach(function (route) {
                                    let $route = $('<span></span>').attr('class', 'route-content').text(route);
                                    if (route == 'default') {
                                        $route.css('color', 'green');
                                    }
                                    if (i > 1) {
                                        $route.css("display", "none");
                                    }
                                    $elements.append($route.append($('<br/>')));
                                    i++;
                                });
                                $elements.append($('<button></button>')
                                    .attr('class', 'route-expand btn btn-primary btn-xs')
                                    .text('Expand'));
                            }
                            return $elements.prop('outerHTML');
                        
                        },
                        "status": function (column, row) {
                            let connected = row.status == 'up' ? 'text-success' : 'text-danger';

                            if (!row.enabled) {
                                row.status += ' (disabled)';
                            }

                            return '<i class="fa fa-plug ' + connected + '" title="' + row.status + '" data-toggle="tooltip"></i>';
                        },
                        "ipv4": function (column, row) {
                            if (row.ipv4) {
                                return iterate_ips(row.ipv4);
                            }

                            return '';
                        },
                        "ipv6": function (column, row) {
                            if (row.ipv6) {
                                return iterate_ips(row.ipv6);
                            }

                            return '';
                        },
                        "gateways": function (column, row) {
                            let $elements = $('<div></div>');
                            if (row.gateways) {
                                row.gateways.forEach(function (gw) {
                                    let $span = $('<span></span><br/>').text(gw);
                                    $elements.append($span);
                                });
                            }
                            return $elements.prop('outerHTML');
                        },
                        "commands": function (column, row) {
                            let $commands = $('<div></div>');
                            let $btn = $('<button type="button" class="btn btn-xs btn-default" data-toggle="tooltip"">\
                                            <i></i></button>');

                            if ('link_type' in row) {
                                if (["dhcp", "pppoe", "pptp", "l2tp", "ppp"].includes(row.link_type)) {
                                    let $command = $btn.clone();
                                    $command.addClass('interface-reload').attr('title', 'Reload').attr('data-device-id', row.identifier);
                                    $command.find('i').addClass('fa fa-fw fa-refresh');
                                    $commands.append($command);
                                }
                            }
                            $btn.addClass('interface-info').attr('title', 'Info').attr('data-row-id', row.device);
                            $btn.find('i').addClass('fa fa-fw fa-search');
                            $commands.append($btn);
                            return $commands.prop('outerHTML');
                        }
                    }
                }
            }
        ).on("loaded.rs.jquery.bootgrid", function (e) {
            $('[data-toggle="tooltip"]').tooltip();

            /* attach event handler to reload buttons */
            $('.interface-reload').each(function () {
                $(this).click(function () {
                    let $element = $(this);
                    let device = $(this).data("device-id");
                    $element.remove('i').html('<i class="fa fa-spinner fa-spin"></i>');
                    ajaxCall('/api/interfaces/overview/reloadInterface/' + device, {}, function (data, status) {
                        /* delay slightly to allow the interface to come up */
                        setTimeout(function() {
                            $element.remove('i').html('<i class="fa fa-fw fa-refresh"></i>');
                            $("#grid-overview").bootgrid('reload');
                        }, 1000);
                    });
                });
            });

            /* attach event handler to the command-info button */
            $(".interface-info").each(function () {
                $(this).click(function () {
                    let $element = $(this);
                    let device = $(this).data("row-id");

                    ajaxGet('/api/interfaces/overview/getInterface/' + device, {}, function(data, status) {
                        data = data['message'];
                        let $table = $('<table class="table table-bordered table-condensed table-hover table-striped"></table>');
                        let $table_body = $('<tbody/>');

                        for (let key in data) {
                            let $row = $('<tr/>');
                            let value = data[key]['value'];
                            if (['ipaddr', 'ipaddrv6', 'subnet', 'subnetv6', 'linklocal'].includes(key)) {
                                continue;
                            }
                            if (key === 'line rate') {
                                value = format_linerate(value.split(" ")[0]);
                            }
                            if (key === 'ipv4' || key === 'ipv6') {
                                value = iterate_ips(value);
                            }
                            key = data[key]['translation'];
                            if (typeof value === 'string' || Array.isArray(value)) {
                                value = value.toString().split(",").join("<br/>");
                            }
                            $row.append($('<td/>').text(key));
                            $row.append($('<td/>').html(value));
                            $table_body.append($row);
                        }

                        $table.append($table_body);
                        $('[data-toggle="tooltip"]').tooltip();
                        BootstrapDialog.show({
                            title: data['description']['value'],
                            message: $table.prop('outerHTML'),
                            type: BootstrapDialog.TYPE_INFO,
                            draggable: true,
                            cssClass: 'details-dialog',
                            buttons: [{
                                label: "{{ lang._('Close') }}",
                                action: function (dialogRef) {
                                    dialogRef.close();
                                }
                            }]
                        });
                    });
                });
            });

            $(".route-container").each(function () {
                let $route_container = $(this);
                let count = $(this).children('.route-content').length;
                let $expand = $(this).find(".route-expand");

                if (count > 2) {
                    $expand.show();
                }

                $expand.click(function () {
                    let $collapsed = $route_container.children('.route-content').filter(function() {
                        return $(this).css('display').toLowerCase().indexOf('none') > -1;
                    });
                    if ($collapsed.length > 0) {
                        $collapsed.show();
                        $expand.html('Collapse');
                    } else {
                        $collapse = $route_container.children('.route-content').slice(2);
                        $collapse.hide();
                        $expand.html('Expand');
                    }
                });
            });
        });
    });
</script>

<style>
    .route-content {
        white-space: pre-line;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    .route-expand {
        display: none;
    }

    .bootgrid-table {
        table-layout: auto;
    }

    .bootgrid-table td {
        text-align: center;
        vertical-align: middle;
    }

    .bootgrid-table th {
        text-align: center;
        vertical-align: middle;
    }

    .details-dialog .modal-dialog{
        position: relative;
        display: table;
        overflow-y: auto;
        overflow-x: auto;
        width: auto;
        min-width: 600px;
    }

    .details-dialog .modal-body {
        height: 60vh;
        overflow-y: auto;
    }
</style>

<div class="tab-content content-box">
    <table id="grid-overview" class="table table-bordered table-condensed table-hover table-striped">
        <thead>
            <tr>
                <th data-column-id="status" data-width="5em" data-formatter="status" data-type="string">{{ lang._('Status') }}</th>
                <th data-column-id="description" data-formatter="interface" data-type="string">{{ lang._('Interface') }}</th>
                <th data-column-id="device" data-identifier="true" data-width="5em" data-type="string">{{ lang._('Device') }}</th>
                <th data-column-id="link_type" data-type="string">{{ lang._('Link Type') }}</th>
                <th data-column-id="ipv4" data-formatter="ipv4" data-type="string">{{ lang._('IPv4') }}</th>
                <th data-column-id="ipv6" data-formatter="ipv6" data-type="string">{{ lang._('IPv6') }}</th>
                <th data-column-id="gateways" data-formatter="gateways" data-type="string">{{ lang._('Gateway') }}</th>
                <th data-column-id="routes" data-formatter="routes" data-type="string">{{ lang._('Routes') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        </tfoot>
    </table>
</div>
