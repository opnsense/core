{#
 #
 # Copyright (c) 2014-2021 Deciso B.V.
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
         * reverse lookup address fields (replace address part for hostname if found)
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

        /**
         * set new selection
         * @param items list of lexical expressions
         * @param operator enable or disable global OR operator
         */
        function set_selection(items, operator)
        {
            // remove old selection
            $("#filters > span.badge").click();
            // collect valid condition types
            let conditions = [];
            $("#filter_condition > option").each(function(){
                conditions.push($(this).val());
            });
            items.forEach(function(value) {
                let parts = value.split(new RegExp("("+conditions.join("|")+")(.+)$"));
                if (parts.length >= 3 && $("#filter_tag").val(parts[0]).val() === parts[0] ) {
                    $("#filter_tag").val(parts[0]);
                    $("#filter_condition").val(parts[1]);
                    $("#filter_value").val(parts[2]);
                    $("#add_filter_condition").click();
                } else if (value.toLowerCase() == "or=1") {
                    operator = "1";
                }
            });
            $("#filter_or_type").prop('checked', operator === "1" ? true : false);
            $(".selectpicker").selectpicker('refresh');
            $("#filter_tag").change();
        }

        /**
         * add new filters template
         * @param t_data template's parameters
         */
        function addTemplate(t_data) {
            ajaxCall('/api/diagnostics/lvtemplate/addItem/', t_data, function(data, status) {
                if (data.result == "saved") {
                    fetchTemplates(data.uuid);
                } else {
                    BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Add filters template') }}",
                    message: "{{ lang._('Template save failed. Message: ') }}" + data.result,
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function (dialogRef) {
                            dialogRef.close();
                        }
                        }]
                    });
                    fetchTemplates("00000");
                }
            })
        }

        /**
         * set template new values
         * @param t_id template uuid
         * @param t_data template's parameters
         */
        function editTemplate(t_id, t_data) {
            ajaxCall('/api/diagnostics/lvtemplate/setItem/' + t_id, t_data, function(data, status) {
                if (data.result == "saved") {
                    fetchTemplates(t_id);
                } else {
                    BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Filters template edit') }}",
                    message: "{{ lang._('Template edit failed. Message: ') }}" + data.result,
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function (dialogRef) {
                            dialogRef.close();
                        }
                        }]
                    });
                    fetchTemplates(t_id);
                }
            })
        }

        /**
         * delete filters template
         * @param t_id template uuid
         */
        function delTemplate(t_id) {
            ajaxCall('/api/diagnostics/lvtemplate/delItem/' + t_id, {}, function(data, status) {
                if (data.result == "deleted") {
                    //don't reset current filters so template can be restored right after delete
                    $("#templates option[value=" + t_id + "]").remove();
                    $("#templates").val("").selectpicker('refresh');
                } else {
                    BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Filters template delete') }}",
                    message: "{{ lang._('Template delete failed. Result: ') }}" + data.result,
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function (dialogRef) {
                            dialogRef.close();
                        }
                        }]
                    });
                }
            })
        }

        /**
         * fetch templates from config
         * @param opt select value to make :selected and apply
         */
        function fetchTemplates(opt) {
            const t_fetched = new $.Deferred();
            opt = opt || "00000";
            //apply = apply || true;
            $('#templ_name').val("");
            $('#templates').empty();
            $('#templates').append($('<option/>', {value: "00000", text: "None"}).data('template', {'filters': "0", 'or': "0"}).addClass("disp_none_opt templates"));
            $('#templates').append($('<option/>', {value: "00001", text: "New"}).data('template', {'filters': "0", 'or': "0"}).data('icon','glyphicon-file').addClass("add_new_opt templ_save"));
            $('#templates').selectpicker('refresh');
            $('.templates').show();
            $('.templ_save').hide();
            ajaxGet('/api/diagnostics/lvtemplate/searchItem/', {}, function(data, status) {
                let templates = data.rows;
                $.each(templates, function(i, template) {
                    $('#templates').append(template.uuid == opt ? $('<option/>', {value:template.uuid, text:template.name, selected: "selected" }).data('template', template) : $('<option/>', {value:template.uuid, text:template.name }).data('template', template));
                });
                $('#templates').selectpicker('refresh');
                $('.badge').click();
                $("#templates").change();
                t_fetched.resolve();
            });
            return t_fetched;
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

        // matcher
        function match_filter(value, condition, data)
        {
            if (data === undefined) {
                return false;
            }

            data = data.toLowerCase();

            return (condition === '=' && data == value) ||
                (condition === '~' && data.match(value)) ||
                (condition === '!=' && data != value) ||
                (condition === '!~' && !data.match(value));
        }

        // live filter
        function apply_filter()
        {
            let filters = [];
            $("#filters > span.badge").each(function(){
                filters.push($(this).data('filter'));
            });
            let filter_or_type = $("#filter_or_type").is(':checked');
            $("#grid-log > tbody > tr").each(function(){
                let selected_tr = $(this);
                let this_data = $(this).data('details');
                if (this_data === undefined) {
                    return;
                }
                let is_matched = (filters.length > 0) ? !filter_or_type : true;
                for (let i=0; i < filters.length; i++) {
                    let filter_value = filters[i].value.toLowerCase();
                    let filter_condition = filters[i].condition;
                    let this_condition_match = false;
                    let filter_tag = filters[i].tag;

                    if (filter_tag === '__addr__') {
                        let src_match = match_filter(filter_value, filter_condition, this_data['src']);
                        let dst_match = match_filter(filter_value, filter_condition, this_data['dst']);
                        if (!filter_condition.match('!')) {
                            this_condition_match = src_match || dst_match;
                        } else {
                            this_condition_match = src_match && dst_match;
                        }
                    } else if (filter_tag === '__port__') {
                        let srcport_match = match_filter(filter_value, filter_condition, this_data['srcport']);
                        let dstport_match = match_filter(filter_value, filter_condition, this_data['dstport']);
                        if (!filter_condition.match('!')) {
                            this_condition_match = srcport_match || dstport_match;
                        } else {
                            this_condition_match = srcport_match && dstport_match;
                        }
                    } else {
                        this_condition_match = match_filter(filter_value, filter_condition, this_data[filter_tag]);
                    }

                    if (!this_condition_match && !filter_or_type) {
                        // normal AND condition, exit when one of the criteria is not met
                        is_matched = this_condition_match;
                        break;
                    } else if (filter_or_type) {
                        // or condition is deselected by default
                        is_matched = is_matched || this_condition_match;
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

        $("#filter_or_type").click(function(){
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
        // manual refresh
        $("#refresh").click(function(){
            fetch_log();
        });

        // templates actions
        $("#templates").change(function () {
            if ($('#templ_save_start').is(':visible')) {
                //apply chosen template
                let t_data = $(this).find('option:selected').data('template') ? $(this).find('option:selected').data('template') : {'filters': "0", 'or': "0"};
                set_selection(t_data.filters.split(','), t_data.or);
            } else {
                //choose template to modify or create new one. Show Name input if New option clicked
                if ($('#templates').val() === "00001") {
                    $('#templates').selectpicker('hide');
                    $('#templ_name').show().focus();
                }
            }
        });

        $('#templ_save_start').click(function () {
            if ($(".badge").text() == '') {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_DANGER,
                    title: "{{ lang._('Save filters template') }}",
                    message: "{{ lang._('Filters not set') }}",
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function (dialogRef) {
                            dialogRef.close();
                        }
                    }]
                });
            } else {
                $('.templates').hide();
                $('.templ_save').show();
                $('#templ_name').focus();
                if ($("#templates option").length == 3){
                    //no stored templates. skip to new template name
                    $('#templates').val("00001").selectpicker('refresh').change();
                }
            }
        });

        $("#templ_save_cancel").click(function () {
            $('#templ_name').val("").hide();
            $('.templ_save').hide();
            $('.templates').show();
            $('#templates').val('').selectpicker('refresh').selectpicker('show');
        });

        $("#templ_name").on('keyup', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                $('#templ_save_apply').click();
            } else if (e.keyCode === 27) {
                $('#templ_name').val("").hide();
                $('#templates').val('').selectpicker('refresh').selectpicker('show');
            }
        });

        $("#templ_save_apply").click(function () {
            let fltrs = "";
            $('.badge').each(function () {
                fltrs += $(this).text() + ",";
            });
            fltrs = fltrs.slice(0, -1);
            let or = $('#filter_or_type').prop("checked") ? "1" : "0";
            let t_data = {
                'template': {
                    'filters': fltrs,
                    'or': or
                }
            };
            $('#templates').selectpicker('refresh').selectpicker('show');
            if ($("#templ_name").val().length >= 1 && $("#templ_name").is(':visible')) {
                //new template
                t_data.template.name = $("#templ_name").val();
                $('#templ_name').val("").hide();
                addTemplate(t_data);
            } else if ($("#templ_name").val().length == 0 && $("#templ_name").is(':hidden') && $("#templates").val().length == 36) {
                //edit template
                let t_id = $("#templates").val();
                t_data.template.name = $("#templates option:selected").text();
                editTemplate(t_id, t_data);
            }
        });

        $("#template_delete").click(function () {
            let t_id = $('#templates').val();
            if (t_id.length == 36) {
                delTemplate(t_id);
            }
        });

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
                    filter_value_items.change(function(){
                        filter_value.val($(this).val())
                    });
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
                    fetchTemplates("00000").done(function() {
                        // get and apply url params. ie11 compat
                        set_selection(window.location.search.substring(1).split("&"), "0");
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
                                    <option value="__addr__">{{ lang._('address') }}</option>
                                    <option value="__port__">{{ lang._('port') }}</option>
                                    <option value="protoname">{{ lang._('protoname') }}</option>
                                    <option value="label">{{ lang._('label') }}</option>
                                    <option value="rid">{{ lang._('rule id') }}</option>
                                    <option value="tcpflags">{{ lang._('tcpflags') }}</option>
                                </select>
                              </td>
                              <td style="width:125px;">
                                <select id="filter_condition" class="selectpicker"  data-width="120px">
                                    <option value="~" selected=selected>{{ lang._('contains') }}</option>
                                    <option value="=">{{ lang._('is') }}</option>
                                    <option value="!~">{{ lang._('does not contain') }}</option>
                                    <option value="!=">{{ lang._('is not') }}</option>
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
                      <tfoot>
                        <tr>
                            <td colspan="4">
                                <label>
                                    <input id="filter_or_type" type="checkbox">
                                    {{ lang._('Select any of given criteria (or)') }}
                                </label>
                            </td>
                        </tr>
                      </tfoot>
                  </table>
                </div>
                <div class="col-lg-4 col-sm-12">
                    <div class="pull-right">
                        <button type="button" class="btn btn-default templates"
                            title="Save the current set of filters" id="templ_save_start"><span
                                class="fa fa-angle-double-right"></span></button>
                            <button type="button" class="btn btn-default templ_save" title="Cancel" id="templ_save_cancel"><span
                                    class="fa fa-times"></span></button>
                            <div style="display: inline-block;vertical-align: top;"><select id="templates" class="selectpicker" title="Choose template" data-width="200"></select>
                                <input type="text" id="templ_name" placeholder="Template name" style="width:200px;vertical-align:middle;display:none;">
                            </div>
                            <button type="button" class="btn btn-default templ_save" title="Save template" id="templ_save_apply"><span class="fa fa-save"></span></button>
                        <span class="templates">
                            <button id="template_delete" type="button" class="btn btn-default" title="Deleted selected template" if="templ_del">
                                <span class="fa fa-trash"></span>
                            </button>
                        </span>
                    </div>
                </div>
                <div class="col-lg-2 col-sm-12">
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
                        <option value="10000">10000</option>
                        <option value="20000">20000</option>
                    </select>
                    <button id="refresh" type="button" class="btn btn-default">
                        <span class="fa fa-refresh"></span>
                    </button>
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
