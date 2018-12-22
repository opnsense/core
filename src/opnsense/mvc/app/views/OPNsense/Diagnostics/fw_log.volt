{#

OPNsense® is Copyright © 2014 – 2016 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}

<script>
    'use strict';

    $( document ).ready(function() {
        var field_type_icons = {'pass': 'fa-play', 'block': 'fa-ban', 'in': 'fa-arrow-right', 'out': 'fa-arrow-left', 'rdr': 'fa-exchange' }
        var interface_descriptions = {};
        let hostnameMap = {};

        /**
         * reverse lookup address fields (replace adres part for hostname if found)
         */
        function reverse_lookup() {
            let to_fetch = [];
             $(".address").each(function(){
                let address = $(this).data('address')
                if (!hostnameMap.hasOwnProperty(address) && !to_fetch.includes(address)) {
                    to_fetch.push(address);
                }
            });
            let update_grid = function() {
                $(".address").each(function(){
                    if (hostnameMap.hasOwnProperty($(this).data('address'))) {
                          $(this).text($(this).text().replace(
                            $(this).data('address'),
                            hostnameMap[$(this).data('address')]
                          ));
                          $(this).removeClass('address');
                    }
                });
            }
            if (to_fetch.length > 0) {
                ajaxGet('/api/diagnostics/dns/reverse_lookup', { 'address': to_fetch }, function(data, status) {
                    $.each(to_fetch, function(index, address) {
                        if (!data.hasOwnProperty(address) || data[address] === undefined) {
                            hostnameMap[address] = address;
                        } else {
                            hostnameMap[address] = data[address];
                        }
                    });
                    update_grid();
                });
            } else {
                update_grid();
            }
        }

        function fetch_log() {
            var record_spec = [];
            // read heading, contains field specs
            $("#grid-log > thead > tr > th ").each(function () {
                record_spec.push({
                    'column-id': $(this).data('column-id'),
                    'type': $(this).data('type'),
                    'class': $(this).attr('class')
                });
            });
            // read last digest (record hash) from top data row
            var last_digest = $("#grid-log > tbody > tr:first > td:first").text();
            // fetch new log lines and add on top of grid-log
            ajaxGet('/api/diagnostics/firewall/log/', {'digest': last_digest, 'limit': $("#limit").val()}, function(data, status) {
                if (data !== undefined && data.length > 0) {
                    let record;
                    while ((record = data.pop()) != null) {
                        if (record['__digest__'] != last_digest) {
                            var log_tr = $("<tr>");
                            log_tr.data('details', record);
                            log_tr.hide();
                            $.each(record_spec, function(idx, field){
                                var log_td = $('<td>').addClass(field['class']);
                                var column_name = field['column-id'];
                                var content = null;
                                switch (field['type']) {
                                    case 'icon':
                                        var icon = field_type_icons[record[column_name]];
                                        if (icon != undefined) {
                                            log_td.html('<i class="fa '+icon+'" aria-hidden="true"></i><span style="display:none">'+record[column_name]+'</span>');
                                        }
                                        break;
                                    case 'interface':
                                        if (interface_descriptions[record[column_name]] != undefined) {
                                            log_td.text(interface_descriptions[record[column_name]]);
                                        } else {
                                            log_td.text(record[column_name]);
                                        }
                                        break;
                                    case 'address':
                                        log_td.text(record[column_name]);
                                        log_td.addClass('address');
                                        log_td.data('address', record[column_name]);
                                        if (record[column_name+'port'] !== undefined) {
                                            if (record['version'] == 6) {
                                                log_td.text('['+log_td.text()+']:'+record[column_name+'port']);
                                            } else {
                                                log_td.text(log_td.text()+':'+record[column_name+'port']);
                                            }
                                        }
                                        break;
                                    case 'info':
                                        log_td.html('<button class="act_info btn btn-xs fa fa-info-circle" aria-hidden="true"></i>');
                                        break;
                                    default:
                                        if (record[column_name] != undefined) {
                                            log_td.text(record[column_name]);
                                        }
                                }
                                log_tr.append(log_td);
                            });

                            if (record['action'] == 'pass') {
                                log_tr.addClass('fw_pass');
                            } else if (record['action'] == 'block') {
                                log_tr.addClass('fw_block');
                            } else if (record['action'] == 'rdr') {
                                log_tr.addClass('fw_nat');
                            }
                            $("#grid-log > tbody > tr:first").before(log_tr);
                        }
                    }
                    // apply filter after load
                    $("#filter").keyup();

                    // limit output, try to keep max X records on screen.
                    var tr_count = 0;
                    var visible_count = 0;
                    var max_rows = parseInt($("#limit").val());
                    $("#grid-log > tbody > tr").each(function(){
                        if ($(this).is(':visible')) {
                            ++visible_count;
                            if (visible_count > max_rows) {
                               // more then [max_rows] visible, safe to remove the rest
                               $(this).remove();
                            }
                        } else if (tr_count > max_rows) {
                            // invisible rows starting at [max_rows] rownumber
                            $(this).remove();
                        }
                        ++tr_count;
                    });

                    // bind info buttons
                    $(".act_info").unbind('click').click(function(){
                        var sender_tr = $(this).parent().parent();
                        var sender_details = sender_tr.data('details');
                        var hidden_columns = ['__spec__', '__host__', '__digest__'];
                        var map_icon = ['dir', 'action'];
                        var sorted_keys = Object.keys(sender_details).sort();
                        var tbl = $('<table class="table table-condensed table-hover"/>');
                        var tbl_tbody = $("<tbody/>");
                        for (let i=0 ; i < sorted_keys.length; i++) {
                            if (hidden_columns.indexOf(sorted_keys[i]) === -1 ) {
                                var row = $("<tr/>");
                                var icon = null;
                                if (map_icon.indexOf(sorted_keys[i]) !== -1) {
                                    if (field_type_icons[sender_details[sorted_keys[i]]] !== undefined) {
                                        icon = $("<i/>");
                                        icon.addClass("fa").addClass(field_type_icons[sender_details[sorted_keys[i]]]);
                                    }
                                }
                                row.append($("<td/>").text(sorted_keys[i]));
                                if (icon === null) {
                                  row.append($("<td/>").addClass("act_info_fld_"+sorted_keys[i]).text(
                                    sender_details[sorted_keys[i]]
                                  ));
                                } else {
                                  row.append($("<td/>")
                                      .append(icon)
                                      .append($("<span/>").addClass("act_info_fld_"+sorted_keys[i]).text(
                                        " [" + sender_details[sorted_keys[i]] + "]"
                                      ))
                                  );
                                }
                                tbl_tbody.append(row);
                            }
                        }
                        tbl.append(tbl_tbody);
                        BootstrapDialog.show({
                           title: "{{ lang._('Detailed rule info') }}",
                           message: tbl,
                           type: BootstrapDialog.TYPE_INFO,
                           draggable: true,
                           buttons: [{
                             label: '<i class="fa fa-search" aria-hidden="true"></i>',
                             action: function(){
                               $(this).unbind('click');
                               $(".act_info_fld_src, .act_info_fld_dst").each(function(){
                                  var target_field = $(this);
                                  ajaxGet('/api/diagnostics/dns/reverse_lookup', {'address': target_field.text()}, function(data, status) {
                                      if (data[target_field.text()] != undefined) {
                                          var resolv_output = data[target_field.text()];
                                          if (target_field.text() != resolv_output) {
                                              target_field.text(target_field.text() + ' [' + resolv_output + ']');
                                          }
                                      }
                                      target_field.prepend('<i class="fa fa-search" aria-hidden="true"></i>&nbsp;');
                                  });
                               });
                             }
                           },{
                             label: "{{ lang._('Close') }}",
                             action: function(dialogItself){
                               dialogItself.close();
                             }
                           }]
                        });
                    });
                    // reverse lookup when selected
                    if ($('#dolookup').is(':checked')) {
                        reverse_lookup();
                    }
                }
            });
        }

        // live filter
        $("#filter").keyup(function(){
            var search_str = $(this).val().toLowerCase();
            $("#grid-log > tbody > tr").each(function(){
                var selected_tr = $(this);
                var visible_text = $(this).text().toLowerCase();
                try {
                    if (visible_text.match(search_str)) {
                        selected_tr.show();
                    } else {
                        selected_tr.hide();
                    }
                } catch(e) {
                    // ignore regexp errors
                }
            });
        });

        // reset log content on limit change, forces a reload
        $("#limit").change(function(){
            $('#grid-log > tbody').html("<tr></tr>");
        });

        function poller() {
            if ($("#auto_refresh").is(':checked')) {
                fetch_log();
            }
            setTimeout(poller, 1000);
        }

        // fetch interface mappings on load
        ajaxGet('/api/diagnostics/interface/getInterfaceNames', {}, function(data, status) {
            interface_descriptions = data;
        });

        // startup poller
        poller();
    });
</script>
<style>
    .data-center {
        text-align: center !important;
    }
    .table > tbody > tr > td {
        padding-top: 1px !important;
        padding-bottom: 1px !important;
    }
    .act_info {
        cursor: pointer;
    }
    .fw_pass {
        background: rgba(5, 142, 73, 0.3);
    }
    .fw_block {
        background: rgba(235, 9, 9, 0.3);
    }
    .fw_nat {
        background: rgba(73, 173, 255, 0.3);
    }
</style>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-xs-12">
                <div class="checkbox-inline  col-xs-3">
                  <input type="text" id="filter" class="form-control" placeholder="filter">
                </div>
                <div class="checkbox-inline pull-right">
                  <label>
                    <input id="auto_refresh" type="checkbox" checked="checked">
                    <span class="fa fa-refresh"></span> {{ lang._('Auto refresh') }}
                  </label>
                  <br/>
                  <label>
                      <input id="dolookup" type="checkbox">
                      <span class="fa fa-search"></span> {{ lang._('Lookup hostnames') }}
                  </label>
                </div>
                <select id="limit" class="selectpicker pull-right" data-width="100" >
                    <option value="25" selected="selected">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="250">250</option>
                    <option value="500">500</option>
                    <option value="1000">1000</option>
                    <option value="2500">2500</option>
                    <option value="5000">5000</option>
                </select>
            </div>
            <div  class="col-xs-12">
                <hr/>
                <div class="table-responsive">
                    <table id="grid-log" class="table table-condensed table-responsive">
                        <thead>
                          <tr>
                              <th class="hidden" data-column-id="__digest__" data-type="string">{{ lang._('Hash') }}</th>
                              <th class="data-center" data-column-id="action" data-type="icon"></th>
                              <th data-column-id="interface" data-type="interface">{{ lang._('Interface') }}</th>
                              <th data-column-id="dir" data-type="icon"></th>
                              <th data-column-id="__timestamp__" data-type="string">{{ lang._('Time') }}</th>
                              <th data-column-id="src" data-type="address">{{ lang._('Source') }}</th>
                              <th data-column-id="dst" data-type="address">{{ lang._('Destination') }}</th>
                              <th data-column-id="protoname" data-type="string">{{ lang._('Proto') }}</th>
                              <th data-column-id="label" data-type="string">{{ lang._('Label') }}</th>
                              <th data-column-id="" data-type="info" style="width:20px;"></th>
                          </tr>
                        </thead>
                        <tbody>
                        <tr></tr>
                        </tbody>
                    </table>
                    <br/>
                </div>
            </div>
        </div>
    </div>
</div>
