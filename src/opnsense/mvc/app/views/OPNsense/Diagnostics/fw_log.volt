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

<script type="text/javascript">
    $( document ).ready(function() {
        var field_type_icons = {'pass': 'fa-play', 'block': 'fa-ban'}
        var interface_descriptions = {};
        function fetch_log(){
            var record_spec = [];
            // read heading, contains field specs
            $("#grid-log > thead > tr > th ").each(function(){
                record_spec.push({'column-id': $(this).data('column-id'),
                                  'type': $(this).data('type'),
                                  'class': $(this).attr('class')
                                 });
            });
            // read last digest (record hash) from top data row
            var last_digest = $("#grid-log > tbody > tr:first > td:first").text();
            // fetch new log lines and add on top of grid-log
            ajaxGet(url='/api/diagnostics/firewall/log/', {'digest': last_digest, 'limit': $("#limit").val()}, callback=function(data, status) {
                if (data != undefined && data.length > 0) {
                    while ((record=data.pop()) != null) {
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
                                            log_td.html('<i class="fa '+icon+'" aria-hidden="true"></i>');
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
                                        if (record[column_name+'port'] != undefined) {
                                            log_td.text(log_td.text()+':'+record[column_name+'port']);
                                        }
                                        break;
                                    case 'info':
                                        log_td.html('<button class="act_info btn btn-xs fa fa-info-circle" aria-hidden="true"></i>');
                                    default:
                                        if (record[column_name] != undefined) {
                                            log_td.text(record[column_name]);
                                        }
                                }
                                log_tr.append(log_td);
                            });

                            if (record['action'] == 'pass') {
                                log_tr.css('background', 'rgba(5, 142, 73, 0.3)');
                            } else if (record['action'] == 'block') {
                                log_tr.css('background', 'rgba(235, 9, 9, 0.3)');
                            }
                            $("#grid-log > tbody > tr:first").before(log_tr);
                        }
                    }
                    // limit output
                    $("#grid-log > tbody > tr:gt("+(parseInt($("#limit").val())-1)+")").remove();
                    // apply filter after load
                    $("#filter").keyup();
                    // bind info buttons
                    $(".act_info").unbind('click').click(function(){
                        var sender_tr = $(this).parent().parent();
                        var sender_details = sender_tr.data('details');
                        var hidden_columns = ['__spec__', '__host__', '__digest__'];
                        var sorted_keys = Object.keys(sender_details).sort();
                        var tbl = $('<table class="table table-condensed table-hover"/>');
                        for (i=0 ; i < sorted_keys.length; i++) {
                            if (hidden_columns.indexOf(sorted_keys[i]) === -1 ) {
                                var row = $("<tr/>");
                                row.append($("<td/>").text(sorted_keys[i]));
                                row.append($("<td/>").text(sender_details[sorted_keys[i]]));
                                tbl.append(row);
                            }
                        }
                        BootstrapDialog.show({
                           title: "{{ lang._('Detailed rule info') }}",
                           message: tbl,
                           type: BootstrapDialog.TYPE_INFO,
                           draggable: true
                        });
                    });
                }
            });
        };

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
                    null; // ignore regexp errors
                }
            });
        });

        // reset log content on limit change, forces a reload
        $("#limit").change(function(){
            $("#grid-log > tbody").html("<tr/>");
        });

        function poller() {
            if ($("#auto_refresh").is(':checked')) {
                fetch_log();
            }
            setTimeout(poller, 1000);
        }

        // fetch interface mappings on load
        ajaxGet(url='/api/diagnostics/interface/getInterfaceNames', {}, callback=function(data, status) {
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
                </div>
                <select id="limit" class="selectpicker pull-right" data-width="100" >
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="250" selected="selected">250</option>
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
                              <th data-column-id="__timestamp__" data-type="string">{{ lang._('Time') }}</th>
                              <th data-column-id="src" data-type="address">{{ lang._('Source') }}</th>
                              <th data-column-id="dst" data-type="address">{{ lang._('Destination') }}</th>
                              <th data-column-id="protoname" data-type="string">{{ lang._('Proto') }}</th>
                              <th data-column-id="label" data-type="string">{{ lang._('Label') }}</th>
                              <th data-column-id="" data-type="info" style="width:20px;"></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr/>
                        </tbody>
                    </table>
                    <br/>
                </div>
            </div>
        </div>
    </div>
</div>
