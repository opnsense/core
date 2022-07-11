{#
 # Copyright (c) 2022 Deciso B.V.
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
        let grid_spd = $("#grid-spd").UIBootgrid({
            search:'/api/ipsec/spd/search',
            del:'/api/ipsec/spd/delete/',
            options:{
                formatters:{
                    commands: function (column, row) {
                        return  '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" title="{{ lang._('Drop') }}" data-row-id="' + row.id + '"><span class="fa fa-trash-o fa-fw"></span></button>';
                    },
                    direction: function (column, row) {
                        if (row[column.id] == 'out') {
                            return "<span class=\"fa fa-arrow-left\" title=\"{{lang._('out')}}\" data-toggle=\"tooltip\"></span>";
                        } else {
                            return "<span class=\"fa fa-arrow-right\" title=\"{{lang._('in')}}\" data-toggle=\"tooltip\"></span>";
                        }
                    },
                    address: function (column, row) {
                        let addr_txt = row[column.id];
                        if (row[column.id+"_port"]) {
                            addr_txt = addr_txt + "[" + row[column.id+"_port"] + "]";
                        }
                        return addr_txt;
                    },
                    tunnel: function (column, row) {
                        if (Array.isArray(row[column.id])) {
                            return row[column.id].join('->');
                        }
                        return "";
                    },
                    tunnel_info: function (column, row) {
                        let txt = "";
                        let lbl_field = column.id == "reqid" ? "phase2desc" : "phase1desc";
                        if (row[column.id] != null) {
                            txt += row[column.id];
                            if (row[lbl_field]) {
                                let label = row[lbl_field].replace('"', "'");
                                txt += "<span class=\"fa fa-fw fa-info-circle\" title=\""+label+"\" data-toggle=\"tooltip\"></span>";
                            }
                        }
                        return txt;
                    }
                }
            }
        });
        grid_spd.on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
        });

    });

</script>


<div class="tab-content content-box">
    <table id="grid-spd" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
        <tr>
            <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="direction" data-type="string" data-width="4em" data-formatter="direction">{{ lang._('Dir') }}</th>
            <th data-column-id="src" data-type="string" data-formatter="address">{{ lang._('Source') }}</th>
            <th data-column-id="dst" data-type="string" data-formatter="address">{{ lang._('Destination') }}</th>
            <th data-column-id="upperspec" data-type="string" data-visible="false">{{ lang._('Upperspec') }}</th>
            <th data-column-id="type" data-type="string" data-visible="false">{{ lang._('Type') }}</th>
            <th data-column-id="src-dst" data-formatter="tunnel" data-sortable="false" data-type="string">{{ lang._('Tunnel endpoints') }}</th>
            <th data-column-id="level" data-type="string" data-visible="false">{{ lang._('Level') }}</th>
            <th data-column-id="ikeid" data-type="string" data-formatter="tunnel_info">
              {{ lang._('Ikeid') }}
              <span class="fa fa-fw fa-info-circle" title="{{ lang._('Tunnel phase 1 definition') }}" data-toggle="tooltip"></span>
            </th>
            <th data-column-id="reqid" data-type="string" data-formatter="tunnel_info">
              {{ lang._('Reqid') }}
              <span class="fa fa-fw fa-info-circle" title="{{ lang._('Tunnel phase 2 definition') }}" data-toggle="tooltip"></span>
            </th>
            <th data-column-id="proto" data-type="string">{{ lang._('Protocol') }}</th>
            <th data-column-id="mode" data-type="string" data-visible="false">{{ lang._('Mode') }}</th>
            <th data-column-id="type" data-type="string" data-visible="false">{{ lang._('Type') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        <tr>
            <td></td>
            <td>
                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
