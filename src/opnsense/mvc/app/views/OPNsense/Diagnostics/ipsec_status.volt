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
                if (!isNaN(row[column.id]) && row[column.id] > 0) {
                    let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                    let ndx = Math.floor(Math.log(row[column.id]) / Math.log(1000));
                    if (ndx > 0) {
                        return  (row[column.id] / Math.pow(1000, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                    } else {
                        return row[column.id];
                    }
                } else if (!isNaN(row[column.id]) && row[column.id] == 0) {
                    return "0";
                } else {
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
            }
        };

        const grid_sad = $("#grid-sad").UIBootgrid(
            {
                search:'/api/diagnostics/ipsec/searchSad',
                options:{
                    formatters: formatters,
                }
            }
        ).on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
        });

        const grid_spd = $("#grid-spd").UIBootgrid(
            {
                search:'/api/diagnostics/ipsec/searchSpd',
                options:{
                    formatters: formatters,
                }
            }
        ).on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
        });

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
    });

</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {% if allow_sad %}
    <li class="active"><a data-toggle="tab" role="tab" href="#sad">{{ lang._('Security Associations') }}</i></a></li>
    {% endif %}{% if allow_spd %}
    <li{% if not allow_sad %} class="active"{% endif %}><a data-toggle="tab" role="tab" href="#spd">{{ lang._('Security Policies') }}</i></a></li>
    {% endif %}
</ul>

<div class="tab-content content-box">
    {% if allow_sad %}
    <div class="tab-pane{% if allow_sad %} active{% endif %}" id="sad" role="tabpanel">
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
    {% endif %}

    {% if allow_sad %}
    <div class="tab-pane{% if not allow_sad %} active{% endif %}" id="spd" role="tabpanel">
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
    {% endif %}
</div>
