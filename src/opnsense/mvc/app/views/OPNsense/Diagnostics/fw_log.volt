{#
 #
 # Copyright (c) 2014-2016 Deciso B.V.
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
        var field_type_icons = {
          'pass': 'fa-play', 'block': 'fa-ban', 'in': 'fa-arrow-right',
          'out': 'fa-arrow-left', 'rdr': 'fa-exchange', 'nat': 'fa-exchange'
        };
        var interface_descriptions = {};
        let hostnameMap = {};

        /**
         * reverse lookup address fields (replace adres part for hostname if found)
         */
        function reverse_lookup() {
            let to_fetch = [];
             $(".address").each(function(){
                let address = $(this).data('address');
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
            };
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
                if (status == 'error') {
                    // stop poller on failure
                    $("#auto_refresh").prop('checked', false);
                } else if (data !== undefined && data.length > 0) {
                    let record;
                    while ((record = data.pop()) != null) {
                        if (record['__digest__'] != last_digest) {
                            var log_tr = $("<tr>");
                            if (record.interface !== undefined && interface_descriptions[record.interface] !== undefined) {
                                record['interface_name'] = interface_descriptions[record.interface];
                            } else {
                                record['interface_name'] = record.interface;
                            }
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
                            } else if (record['action'] == 'rdr' || record['action'] == 'nat') {
                                log_tr.addClass('fw_nat');
                            }
                            $("#grid-log > tbody > tr:first").before(log_tr);
                        }
                    }
                    // apply filter after load
                    apply_filter();

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
                                        icon.addClass("fa fa-fw").addClass(field_type_icons[sender_details[sorted_keys[i]]]);
                                    }
                                }
                                row.append($("<td/>").text(sorted_keys[i]));
                                if (sorted_keys[i] == 'rid') {
                                  // rid field, links to rule origin
                                  var rid_td = $("<td/>").addClass("act_info_fld_"+sorted_keys[i]);
                                  var rid = sender_details[sorted_keys[i]];

                                  var rid_link = $("<a target='_blank' href='/firewall_rule_lookup.php?rid=" + rid + "'/>");
                                  rid_link.text(rid);
                                  rid_td.append($("<i/>").addClass('fa fa-fw fa-search'));
                                  rid_td.append(rid_link);
                                  row.append(rid_td);
                                } else if (icon === null) {
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
        function apply_filter()
        {
            let filters = [];
            $("#filters > span.badge").each(function(){
                filters.push($(this).data('filter'));
            });
            $("#grid-log > tbody > tr").each(function(){
                let selected_tr = $(this);
                let this_data = $(this).data('details');
                if (this_data === undefined) {
                    return;
                }
                let is_matched = true;
                for (let i=0; i < filters.length; i++) {
                    let filter_tag = filters[i].tag;
                    let filter_value = filters[i].value.toLowerCase();
                    let filter_condition = filters[i].condition;
                    if (this_data[filter_tag] === undefined) {
                        is_matched = false;
                        break;
                    } else if (filter_condition === '=' && this_data[filter_tag].toLowerCase() != filter_value) {
                        is_matched = false;
                        break;
                    } else if (filter_condition === '~' && !this_data[filter_tag].toLowerCase().match(filter_value)) {
                        is_matched = false;
                        break;
                    }
                }
                if (is_matched) {
                    selected_tr.show();
                } else {
                    selected_tr.hide();
                }
            });
        }

        $("#add_filter_condition").click(function(){
            if ($("#filter_value").val() === "") {
                return;
            }
            let $new_filter = $("<span/>").addClass("badge");
            $new_filter.data('filter', {
                tag:$("#filter_tag").val(),
                condition:$("#filter_condition").val(),
                value:$("#filter_value").val(),
              }
            );
            $new_filter.text($("#filter_tag").val() + $("#filter_condition").val() + $("#filter_value").val());
            $new_filter.click(function(){
                $("#filter_tag").val($(this).data('filter').tag);
                $("#filter_condition").val($(this).data('filter').condition);
                $("#filter_value").val($(this).data('filter').value);
                $(this).remove();
                if ($("#filters > span.badge").length == 0) {
                    $("#filters_help").hide();
                }
                $('.selectpicker').selectpicker('refresh');
                $("#filter_tag").change();
                apply_filter();
            });
            $("#filters").append($new_filter);
            $("#filter_value").val("");
            $("#filters_help").show();
            apply_filter();
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
            /**
             * fetch log "static" dropdown filters and add logic
             */
            ajaxGet('/api/diagnostics/firewall/log_filters', {}, function(data, status) {
                if (data.action !== undefined) {
                    let filter_value_items = $("#filter_value_items");
                    let filter_value = $("#filter_value");
                    filter_value_items.data('filters', data);
                    $("#filter_tag").change(function(){
                        let filters = filter_value_items.data('filters');
                        let filter = $("#filter_tag").val();
                        filter_value.hide();
                        filter_value_items.parent().hide();
                        if (filters[filter] !== undefined) {
                            filter_value_items.parent().show();
                            filter_value_items.empty();
                            for (let i = 0 ; i < filters[filter].length ; ++i) {
                                let filter_opt = $("<option/>").text(filters[filter][i]);
                                if (filter_value.val() == filters[filter][i]) {
                                    filter_opt.prop('selected', true);
                                }
                                filter_value_items.append(filter_opt);
                            }
                            filter_value_items.selectpicker('refresh');
                            filter_value_items.change();
                        } else {
                            filter_value.show();
                        }
                    }).change();
                    filter_value_items.change(function(){
                        filter_value.val($(this).val())
                    });
                }
            });
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
                <div class="col-lg-6 col-sm-12">
                  <table class="table table-condensed">
                      <tbody>
                          <tr>
                              <td style="width:125px;">
                                <select id="filter_tag" class="selectpicker" data-width="120px">
                                    <option value="action">{{ lang._('action') }}</option>
                                    <option value="interface_name">{{ lang._('interface') }}</option>
                                    <option value="dir">{{ lang._('dir') }}</option>
                                    <option value="__timestamp__">{{ lang._('Time') }}</option>
                                    <option value="src">{{ lang._('src') }}</option>
                                    <option value="srcport">{{ lang._('src_port') }}</option>
                                    <option value="dst">{{ lang._('dst') }}</option>
                                    <option value="dstport">{{ lang._('dst_port') }}</option>
                                    <option value="protoname">{{ lang._('protoname') }}</option>
                                    <option value="label">{{ lang._('label') }}</option>
                                    <option value="dst">{{ lang._('dst') }}</option>
                                    <option value="rid">{{ lang._('rule id') }}</option>
                                    <option value="tcpflags">{{ lang._('tcpflags') }}</option>
                                </select>
                              </td>
                              <td style="width:125px;">
                                <select id="filter_condition" class="condition"  data-width="120px">
                                    <option value="~" selected=selected>{{ lang._('contains') }}</option>
                                    <option value="=">{{ lang._('is') }}</option>
                                </select>
                              </td>
                              <td>
                                <input type="text" id="filter_value"></input>
                                <div>
                                <select id="filter_value_items" class="selectpicker" data-width="250px"></select>
                                </div>
                              </td>
                              <td>
                                  <button class="btn" id="add_filter_condition" aria-hidden="true">
                                      <i class="fa fa-plus"></i>
                                  </button>
                              </td>
                          </tr>
                          <tr>
                              <td colspan="4">
                                <div id="filters">
                                </div>
                                <div id="filters_help" style="display:none; padding-top:5px">
                                  <small>{{ lang._('click on badge to remove filter') }}</small>
                                </div>
                              </td>
                          </tr>
                      </tbody>
                  </table>
                </div>
                <div class="col-lg-6 col-sm-12">
                  <div class="pull-right">
                    <div class="checkbox-inline">
                      <label>
                        <input id="auto_refresh" type="checkbox" checked="checked">
                        <span class="fa fa-refresh"></span> {{ lang._('Auto refresh') }}
                      </label>
                      <br/>
                      <label>
                          <input id="dolookup" type="checkbox">
                          <span class="fa fa-search"></span> {{ lang._('Lookup hostnames') }}
                      </label>
                    </div><br/>
                    <select id="limit" class="selectpicker" data-width="150" >
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
                </div>
            </div>
            <div  class="col-xs-12">
                <hr/>
                <div class="table-responsive">
                    <table id="grid-log" class="table table-condensed table-responsive">
                        <thead>
                          <tr>
                              <th class="hidden" data-column-id="__digest__" data-type="string">{{ lang._('Hash') }}</th>
                              <th class="data-center" data-column-id="action" data-type="icon"></th>
                              <th data-column-id="interface_name" data-type="interface">{{ lang._('Interface') }}</th>
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
