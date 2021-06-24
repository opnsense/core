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
        let grid_states = $("#grid-states").UIBootgrid(
                {   search:'/api/diagnostics/firewall/query_states',
                    del:'/api/diagnostics/firewall/del_state/',
                    options:{
                        formatters:{
                            commands: function (column, row) {
                                return  '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" title="{{ lang._('Drop') }}" data-row-id="' + row.id + '"><span class="fa fa-trash-o fa-fw"></span></button>';
                            },
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
        grid_states.on('loaded.rs.jquery.bootgrid', function() {
            $('[data-toggle="tooltip"]').tooltip();
            if ($(".search-field").val() !== "") {
                $("#actKillStates").show();
            } else {
                $("#actKillStates").hide();
            }
        });

        // collect rule id's
        ajaxGet("/api/diagnostics/firewall/list_rule_ids", {}, function(data, status){
            if (data.items) {
                for (let i=0; i < data.items.length ; ++i) {
                    $("#ruleid").append($("<option/>").val(data.items[i]['id']).text(data.items[i]['descr']));
                }
                $("#service_status_container").append($("#ruleid"));
                $("#ruleid").show();
                $("#ruleid").change(function(){
                    $("#grid-states").bootgrid("reload");
                });
                let init_state = window.location.hash.substr(1);
                if (init_state) {
                    $("#ruleid").val(init_state);
                    $("#ruleid").change();
                }
            }
        });

        $("#actKillStates").click(function(){
          BootstrapDialog.show({
              type:BootstrapDialog.TYPE_DANGER,
              title: "{{ lang._('Delete all filtered states') }}",
              message: "{{ lang._('Are you sure you do want to kill all states matching the provided search criteria?') }}",
              buttons: [{
                        label: "{{ lang._('No') }}",
                        action: function(dialogRef) {
                            dialogRef.close();
                        }}, {
                        label: "{{ lang._('Yes') }}",
                        action: function(dialogRef) {
                            let params = {'filter': $(".search-field").val()};
                            if ($("#ruleid").val() != "") {
                                params['ruleid'] = $("#ruleid").val();
                            }
                            ajaxCall('/api/diagnostics/firewall/kill_states/', params, function(data, status){
                                $("#grid-states").bootgrid("reload");
                                dialogRef.close();
                            });
                      }
                  }]
          });
        });
        // move kill states button
        $("div.search.form-group").before($("#actKillStates"));

        $("#reset_states").click(function(){
          BootstrapDialog.show({
              type:BootstrapDialog.TYPE_DANGER,
              title: $("#reset_states").html(),
              message: $("#msg_statetable").html(),
              buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function(dialogRef) {
                            dialogRef.close();
                        }}, {
                        label: "{{ lang._('Reset') }}",
                        action: function(dialogRef) {
                            ajaxCall('/api/diagnostics/firewall/flush_states/', {}, function(data, status){
                            });
                            dialogRef.close();
                      }
                  }]
          });
        });

        $("#reset_sources").click(function(){
          BootstrapDialog.show({
              type:BootstrapDialog.TYPE_DANGER,
              title: $("#reset_sources").html(),
              message: $("#msg_sourcetracking").html(),
              buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function(dialogRef) {
                            dialogRef.close();
                        }}, {
                        label: "{{ lang._('Reset') }}",
                        action: function(dialogRef) {
                            ajaxCall('/api/diagnostics/firewall/flush_sources/', {}, function(data, status){
                            });
                            dialogRef.close();
                      }
                  }]
          });
        });
    });

</script>

<div id="msg_statetable" style="display:none;">
  <?=gettext("Resetting the state tables will remove all entries from " .
  "the corresponding tables. This means that all open connections " .
  "will be broken and will have to be re-established. This " .
  "may be necessary after making substantial changes to the " .
  "firewall and/or NAT rules, especially if there are IP protocol " .
  "mappings (e.g. for PPTP or IPv6) with open connections."); ?>
  <br />
  <?=gettext("The firewall will normally leave " .
  "the state tables intact when changing rules."); ?>
  <br />
  <?=gettext('Note: If you reset the firewall state table, the browser ' .
  'session may appear to be hung after clicking "Reset". ' .
  'Simply refresh the page to continue.'); ?>
</div>

<div id="msg_sourcetracking" style="display:none;">
  <?=gettext("Resetting the source tracking table will remove all source/destination associations. " .
  "This means that the \"sticky\" source/destination association " .
  "will be cleared for all clients."); ?><br/><br/>
  <?=gettext("This does not clear active connection states, only source tracking."); ?>
</div>

<div style="display:none">
  <div class="btn-group" id="actKillStates" style="margin-right:20px; display:none;">
    <button class="btn btn-danger" style="cursor: pointer;" data-toggle="tooltip"  title="{{ lang._('kill all matched states') }}">
        <span class="fa fa-remove"></span>
    </button>
  </div>
</div>

<select id="ruleid" style="display:none">
  <option value="">{{ lang._("Select rule") }}</option>
</select>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#categories">{{ lang._('States') }}</a></li>
    <li><a data-toggle="tab" href="#actions">{{ lang._('Actions') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="categories" class="tab-pane fade in active">
        <table id="grid-states" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEdit">
            <thead>
            <tr>
                <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false" >{{ lang._('state id') }}</th>
                <th data-column-id="interface" data-type="string" data-width="6em">{{ lang._('Int') }}</th>
                <th data-column-id="direction" data-type="string" data-width="4em" data-formatter="direction">{{ lang._('Dir') }}</th>
                <th data-column-id="proto" data-type="string" data-width="6em">{{ lang._('Proto') }}</th>
                <th data-column-id="src" data-type="string" data-formatter="address">{{ lang._('Source') }}</th>
                <th data-column-id="nat" data-type="string" data-formatter="address">{{ lang._('Nat') }}</th>
                <th data-column-id="dst" data-type="string" data-formatter="address">{{ lang._('Destination') }}</th>
                <th data-column-id="state" data-type="string">{{ lang._('State') }}</th>
                <th data-column-id="descr" data-type="string" data-formatter="rule">{{ lang._('Rule') }}</th>
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
    <div id="actions" class="tab-pane fade in">
        <table class="table table-condensed">
            <thead>
                <tr>
                  <th>{{ lang._('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                      <button id="reset_states" type="button" class="btn btn-primary"><span class="fa fa-trash-o fa-fw"></span> {{ lang._('Reset state table') }}</button>
                    </td>
                </tr>
                <tr>
                    <td>
                      <button id="reset_sources" type="button" class="btn btn-primary"><span class="fa fa-trash-o fa-fw"></span> {{ lang._('Reset source tracking') }}</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
