{#
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
        let grid_pftop = $("#grid-pftop").UIBootgrid(
                {   search:'/api/diagnostics/firewall/query_pf_top',
                    options:{
                        formatters:{
                            rule: function (column, row) {
                                if (row.label !== "") {
                                    return "<a target=\"_blank\" href=\"/firewall_rule_lookup.php?rid=" + row.label + "\">"+row[column.id]+"</a>";
                                } else {
                                    return row[column.id];
                                }
                            },
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
                                    let ndx = Math.floor(Math.log(row[column.id]) / Math.log(1000) );
                                    if (ndx > 0) {
                                        return  (row[column.id] / Math.pow(1000, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                                    } else {
                                        return row[column.id].toFixed(2);
                                    }
                                } else {
                                    return "";
                                }
                            }
                        },
                        requestHandler:function(request){
                            if ($("#ruleid").val() != "") {
                                request['ruleid'] = $("#ruleid").val();
                            }
                            return request;
                        },
                    }
                }
        );
        grid_pftop.on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
        });

        // collect rule id's
        ajaxGet("/api/diagnostics/firewall/list_rule_ids", {}, function(data, status){
            if (data.items) {
                for (let i=0; i < data.items.length ; ++i) {
                    $("#ruleid").append($("<option/>").val(data.items[i]['id']).text(data.items[i]['descr']));
                }
                $("#service_status_container").append($("#ruleid"));
                $("#ruleid").selectpicker();
                $("#ruleid").show();
                $("#ruleid").change(function(){
                    $("#grid-pftop").bootgrid("reload");
                });
                let init_state = window.location.hash.substr(1);
                if (init_state) {
                    $("#ruleid").val(init_state);
                    $("#ruleid").change();
                }
            }
        });
    });

</script>

<select id="ruleid" data-size="5" data-live-search="true" style="display:none">
  <option value="">{{ lang._("Select rule") }}</option>
</select>

<div class="tab-content content-box">
  <table id="grid-pftop" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEdit">
      <thead>
      <tr>
          <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false" >{{ lang._('state id') }}</th>
          <th data-column-id="direction" data-type="string" data-width="4em" data-formatter="direction">{{ lang._('Dir') }}</th>
          <th data-column-id="proto" data-type="string" data-width="6em">{{ lang._('Proto') }}</th>
          <th data-column-id="src" data-type="string" data-formatter="address" data-sortable="false">{{ lang._('Source') }}</th>
          <th data-column-id="gw" data-type="string" data-formatter="address" data-sortable="false">{{ lang._('Gateway') }}</th>
          <th data-column-id="dst" data-type="string" data-formatter="address" data-sortable="false">{{ lang._('Destination') }}</th>
          <th data-column-id="state" data-type="string">{{ lang._('State') }}</th>
          <th data-column-id="age" data-type="numeric">{{ lang._('Age (sec)') }}</th>
          <th data-column-id="expire" data-type="numeric">{{ lang._('Expires (sec)') }}</th>
          <th data-column-id="pkts" data-type="numeric" data-formatter="bytes">{{ lang._('Pkts') }}</th>
          <th data-column-id="bytes" data-type="numeric" data-formatter="bytes">{{ lang._('Bytes') }}</th>
          <th data-column-id="avg" data-type="numeric" data-visible="false">{{ lang._('Avg') }}</th>
          <th data-column-id="descr" data-type="string" data-formatter="rule">{{ lang._('Rule') }}</th>
      </tr>
      </thead>
      <tbody>
      </tbody>
  </table>
</div>
