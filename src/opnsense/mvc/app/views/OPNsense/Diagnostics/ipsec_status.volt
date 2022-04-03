{#
 # Copyright (c) 2022 Manuel Faux <mfaux@conf.at>
 # Copyright (c) 2021 Deciso B.V.
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

    $( document ).ready(function() {
        function format_bytes(str) {
            if (!isNaN(str) && str > 0) {
                let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                let ndx = Math.floor(Math.log(str) / Math.log(1000));
                if (ndx > 0) {
                    return  (str / Math.pow(1000, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                } else {
                    return str;
                }
            } else if (!isNaN(str) && str == 0) {
                return "0";
            } else {
                return "";
            }
        }

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/
        const formatters = {
            direction: function (column, row) {
                if (row[column.id] == 'out') {
                    return "<span class=\"fa fa-arrow-left\" title=\"{{lang._('out')}}\" data-toggle=\"tooltip\"></span>";
                } else {
                    return "<span class=\"fa fa-arrow-right\" title=\"{{lang._('in')}}\" data-toggle=\"tooltip\"></span>";
                }
            },
            address: function (column, row) {
                if (row[column.id+"_addr"]) {
                    let addr_txt = row[column.id+"_addr"];
                    if (addr_txt.includes(":")) {
                        addr_txt = addr_txt + ":[" + row[column.id+"_port"] + "]";
                    } else {
                        addr_txt = addr_txt + ":" + row[column.id+"_port"];
                    }
                    return addr_txt;
                }
                return "";
            },
            bytes: function(column, row) {
                return format_bytes(row[column.id]);
            },
            inoutbytes: function (column, row) {
                if (row[column.id+"-in"] && row[column.id+"-out"]) {
                    return `in: ${format_bytes(row[column.id+"-in"])}<br>out: ${format_bytes(row[column.id+"-out"])}`;
                } else if (row[column.id+"-in"]) {
                    return `in: ${format_bytes(row[column.id+"-in"])}`;
                } else if (row[column.id+"-out"]) {
                    return `out: ${format_bytes(row[column.id+"-out"])}`;
                }
                else {
                    return "";
                }
            },
            datetime: function (column, row) {
                if (row[column.id] == "" || row[column.id] == undefined) {
                    return "{{ lang._('unknown') }}";
                } else {
                    var date = new Date(row[column.id]*1000);
                    return date.toLocaleString();
                }
            },
            endpoints: function (column, row) {
                if (Array.isArray(row[column.id])) {
                    if (row[column.id].length >= 2 && row[column.id][0].length > 0 && row[column.id][1].length > 0) {
                        return row[column.id][0] + " &rarr; " + row[column.id][1];
                    } else {
                        return "";
                    }
                } else {
                    return row[column.id];
                }
            },
            trafficselector: function (column, row) {
                if (Array.isArray(row[column.id])) {
                    return row[column.id].join(", ");
                } else {
                    return row[column.id];
                }
            },
            spi: function (column, row) {
                if (row[column.id+"-in"] && row[column.id+"-out"]) {
                    return `in: ${row[column.id+"-in"]}<br>out: ${row[column.id+"-out"]}`;
                } else if (row[column.id+"-in"]) {
                    return `in: ${row[column.id+"-in"]}`;
                } else if (row[column.id+"-out"]) {
                    return `out: ${row[column.id+"-out"]}`;
                }
                else {
                    return "";
                }
            },
            commands: function (column, row) {
                if (row["sas"]) {
                    return "<button type=\"button\" title=\"{{ lang._('Disconnect') }}\" class=\"btn btn-xs command-disconnect bootgrid-tooltip\" data-row-id=\"" + row.id + "\"><i class=\"fa fa-fw fa-remove\"></i></button> " +
                    "<button type=\"button\" title=\"{{ lang._('Reconnect') }}\" class=\"btn btn-xs command-connect bootgrid-tooltip\" data-row-id=\"" + row.id + "\"><i class=\"fa fa-play fa-fw text-success\"></i></button> ";
                } else {
                    return "<button type=\"button\" title=\"{{ lang._('Connect') }}\" class=\"btn btn-xs command-connect bootgrid-tooltip\" data-row-id=\"" + row.id + "\"><i class=\"fa fa-play fa-fw text-warning\"></i></button>";
                }
            },
        };

        // Overview grids
        const grid_overview = $("#grid-overview").UIBootgrid(
            {
                search:'/api/diagnostics/ipsec/searchConnection',
                connect:'/api/ipsec/connection/connect/',
                disconnect:'/api/ipsec/connection/disconnect/',
                options:{
                    formatters: formatters,
                    multiSelect: false,
                    rowSelect: true,
                    selection: true
                }
            }
        ).on("selected.rs.jquery.bootgrid", function(e, rows) {
            $("#grid-overview-sas").bootgrid('reload');
        }).on("deselected.rs.jquery.bootgrid", function(e, rows) {
            $("#grid-overview-sas").bootgrid('reload');
        }).on("loaded.rs.jquery.bootgrid", function (e) {
            let ids = $("#grid-overview").bootgrid("getCurrentRows");
            if (ids.length > 0) {
                $("#grid-overview").bootgrid('select', [ids[0].id]);
            }

            // disconnect command
            grid_overview.find(".command-disconnect").on("click", function(e) {
                e.stopPropagation();
                var uuid=$(this).data("row-id");
                ajaxCall("/api/ipsec/connection/disconnect/" + uuid, {}, function(data,status) {
                        // reload grid after command
                        grid_overview.bootgrid("reload");
                    });
            });

            // connect command
            grid_overview.find(".command-connect").on("click", function(e) {
                e.stopPropagation();
                var uuid=$(this).data("row-id");
                ajaxCall("/api/ipsec/connection/connect/" + uuid, {},function(data,status) {
                        // reload grid after command
                        grid_overview.bootgrid("reload");
                    });
            });
        });

        $("#grid-overview-sas").UIBootgrid(
                {
                    search:'/api/diagnostics/ipsec/searchSad',
                    options:{
                        formatters: formatters,
                        useRequestHandlerOnGet: true,
                        requestHandler: function(request) {
                            let ids = $("#grid-overview").bootgrid("getSelectedRows");
                            request['connection'] = ids.length > 0 ? ids[0] : "__not_found__";
                            return request;
                        }
                    }
                }
        ).on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
        });

        // SAD grid
        $("#grid-sad").UIBootgrid(
            {
                search:'/api/diagnostics/ipsec/searchSad',
                options:{
                    formatters: formatters,
                }
            }
        ).on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
        });

        // SPD grid
        $("#grid-spd").UIBootgrid(
            {
                search:'/api/diagnostics/ipsec/searchSpd',
                options:{
                    formatters: formatters,
                }
            }
        ).on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
        });

        // Refresh grid upon click on active tab
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $('.tab-icon').removeClass("fa-refresh");

            if ($("#"+e.target.id).data('grid') !== undefined) {
                $("#"+e.target.id).find(".tab-icon").addClass("fa-refresh");
                $("#"+e.target.id).unbind('click').click(function () {
                    if ($("#"+e.target.id + " .tab-icon").hasClass("fa-refresh")) {
                        var grid_id = $("#"+e.target.id).data('grid');
                        $("#" + grid_id).bootgrid("reload");
                    }
                });
            }
        });

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        } else {
            $('#maintabs > li:first-child > a').click();
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });

        // Display service control
        updateServiceControlUI('ipsec');
    });

</script>

<ul class="nav nav-tabs" id="maintabs" data-tabs="tabs">
    <li>
        <a data-toggle="tab" data-grid="grid-overview" href="#overview" id="overview-tab">
            {{ lang._('Status Overview') }} <i class="fa tab-icon "></i>
        </a>
    </li>
    <li>
        <a data-toggle="tab" data-grid="grid-sad" href="#sad" id="sad-tab">
            {{ lang._('Security Associations') }} <i class="fa tab-icon "></i>
        </a>
    </li>
    <li>
        <a data-toggle="tab" data-grid="grid-spd" href="#spd" id="spd-tab">
            {{ lang._('Security Policies') }} <i class="fa tab-icon "></i>
        </a>
    </li>
</ul>

<div class="tab-content content-box">
    <div class="tab-pane" id="overview" role="tabpanel">
        <table id="grid-overview" class="table table-condensed table-hover table-striped table-responsive">
          <thead>
          <tr>
              <th data-column-id="id" data-type="string" data-identifier="true">{{ lang._('Connection ID') }}</th>
              <th data-column-id="name" data-type="string">{{ lang._('Connection Name') }}</th>
              <th data-column-id="version" data-type="string" data-sortable="false" data-visible="false">{{ lang._('IKE Version') }}</th>
              <th data-column-id="local-addrs" data-type="string" data-visible="false">{{ lang._('Local Host') }}</th>
              <th data-column-id="local-id" data-type="string" data-visible="false">{{ lang._('Local ID') }}</th>
              <th data-column-id="remote-addrs" data-type="string">{{ lang._('Remote Host') }}</th>
              <th data-column-id="remote-id" data-type="string">{{ lang._('Remote ID') }}</th>
              <th data-column-id="local-class" data-type="string">{{ lang._('Local Auth.') }}</th>
              <th data-column-id="remote-class" data-type="string">{{ lang._('Remote Auth.') }}</th>
              <th data-column-id="sas" data-type="string">{{ lang._('SAs') }}</th>
              {% if allow_connect %}
              <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
              {% endif %}
          </tr>
          </thead>
          <tbody>
          </tbody>
        </table>

        <table id="grid-overview-sas" class="table table-condensed table-hover table-striped table-responsive">
          <thead>
          <tr>
              <th data-column-id="uniqueid" data-type="string" data-sortable="false" data-identifier="true" data-visible="false" >{{ lang._('SA ID') }}</th>
              <th data-column-id="protocol" data-type="string" data-width="6em">{{ lang._('Proto') }}</th>
              <th data-column-id="mode" data-type="string" data-width="6em">{{ lang._('Mode') }}</th>
              <th data-column-id="local-ts" data-type="string" data-formatter="trafficselector">{{ lang._('Local Networks') }}</th>
              <th data-column-id="remote-ts" data-type="string" data-formatter="trafficselector">{{ lang._('Remote Networks') }}</th>
              <th data-column-id="spi" data-type="string" data-formatter="spi">{{ lang._('SPIs') }}</th>
              <th data-column-id="encr-alg" data-type="string" data-visible="false">{{ lang._('Encr. Alg.') }}</th>
              <th data-column-id="integ-alg" data-type="string" data-visible="false">{{ lang._('Integ. Alg.') }}</th>
              <th data-column-id="dh-group" data-type="string" data-visible="false">{{ lang._('PFS DH Group') }}</th>
              <th data-column-id="state" data-type="string">{{ lang._('State') }}</th>
              <th data-column-id="packets" data-type="numeric" data-formatter="inoutbytes">{{ lang._('Pkts') }}</th>
              <th data-column-id="bytes" data-type="numeric" data-formatter="inoutbytes">{{ lang._('Bytes') }}</th>
          </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
    </div>

    <div class="tab-pane" id="sad" role="tabpanel">
        <table id="grid-sad" class="table table-condensed table-hover table-striped table-responsive">
          <thead>
          <tr>
              <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false" >{{ lang._('SA ID') }}</th>
              <th data-column-id="ikeversion" data-type="string" data-sortable="false" data-identifier="true" data-visible="false" >{{ lang._('IKE Version') }}</th>
              <th data-column-id="dir" data-type="string" data-width="4em" data-formatter="direction">{{ lang._('Dir') }}</th>
              <th data-column-id="proto" data-type="string" data-width="6em">{{ lang._('Proto') }}</th>
              <th data-column-id="src" data-type="string" data-formatter="address">{{ lang._('Source') }}</th>
              <th data-column-id="src_id" data-type="string" data-visible="false">{{ lang._('Source ID') }}</th>
              <th data-column-id="dst" data-type="string" data-formatter="address">{{ lang._('Destination') }}</th>
              <th data-column-id="dst_id" data-type="string" data-visible="false">{{ lang._('Dest. ID') }}</th>
              <th data-column-id="spi" data-type="string">{{ lang._('SPI') }}</th>
              <th data-column-id="encr-alg" data-type="string">{{ lang._('Encr. Alg.') }}</th>
              <th data-column-id="integ-alg" data-type="string">{{ lang._('Integ. Alg.') }}</th>
              <th data-column-id="dh-group" data-type="string" data-visible="false">{{ lang._('PFS DH Group') }}</th>
              <th data-column-id="pkts" data-type="numeric" data-formatter="bytes">{{ lang._('Pkts') }}</th>
              <th data-column-id="bytes" data-type="numeric" data-formatter="bytes">{{ lang._('Bytes') }}</th>
          </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
    </div>

    <div class="tab-pane" id="spd" role="tabpanel">
        <table id="grid-spd" class="table table-condensed table-hover table-striped table-responsive">
          <thead>
          <tr>
              <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false" >{{ lang._('SP ID') }}</th>
              <th data-column-id="dir" data-type="string" data-width="4em" data-formatter="direction">{{ lang._('Dir') }}</th>
              <th data-column-id="proto" data-type="string" data-width="6em">{{ lang._('Proto') }}</th>
              <th data-column-id="mode" data-type="string" data-width="6em" data-visible="false">{{ lang._('Mode') }}</th>
              <th data-column-id="src_addr" data-type="string">{{ lang._('Source') }}</th>
              <th data-column-id="dst_addr" data-type="string">{{ lang._('Destination') }}</th>
              <th data-column-id="endpoints" data-type="string" data-formatter="endpoints">{{ lang._('Endpoints') }}</th>
              <th data-column-id="scope" data-type="string" data-visible="false">{{ lang._('Scope') }}</th>
              <th data-column-id="created" data-type="string" data-formatter="datetime" data-visible="false">{{ lang._('Created') }}</th>
              <th data-column-id="lastused" data-type="string" data-formatter="datetime" data-visible="false">{{ lang._('Last Used') }}</th>
          </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
    </div>
</div>
