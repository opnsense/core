{#
 # Copyright (c) 2020 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<style>
    .pipe_tr {
        background: rgba(73, 173, 255, 0.1);
    }
</style>

<script>

    $( document ).ready(function() {
        function formatSizeUnits(bytes){
            if      (bytes>=1000000000) {bytes=(bytes/1000000000).toFixed(2)+'G';}
            else if (bytes>=1000000)    {bytes=(bytes/1000000).toFixed(2)+'M';}
            else if (bytes>=1000)       {bytes=(bytes/1000).toFixed(2)+'k';}
            else if (bytes>=1)          {bytes=bytes.toFixed(0);}
            else                        {bytes='0';}
            return bytes;
        }

        function update_stats() {
            ajaxGet('/api/trafficshaper/service/statistics', {}, function(data, status){
                let all_rule_stats = {};
                if (data.status == 'ok') {
                    $("#activityTbl > tbody").empty();
                    $.each(data.items, function (key, record) {
                        let pipe_nr = null;
                        let queue_nr = null;
                        let this_id = record.id.toString().replace('.', '_');
                        let $tr = $("<tr/>");
                        let $nodeprop = $("<div/>");
                        if (record.uuid !== undefined) {
                            $tr.attr('data-uuid', record.uuid);
                        }
                        if (record.type == 'pipe') {
                            $tr.addClass("pipe_tr");
                            $tr.append($("<td/>").append($("<i class='fa fa-square-o'></i>'")));
                            $nodeprop.append(record.bw);
                            if (record.delay != "0") {
                                $nodeprop.append(" ");
                                $nodeprop.append(
                                  $("<i class='fa fa-info-circle' data-html='true' title='{{ lang._('Delay  (ms):') }} " +
                                  record.delay +
                                  "' data-toggle='tooltip'></i>'")
                                );
                            }
                            pipe_nr = record.id;
                            all_rule_stats[pipe_nr] = {
                                queues: {},
                                total_pkts:0,
                                total_bytes:0,
                                accessed: "",
                                accessed_epoch:0,
                                id:this_id
                            };
                        } else if (record.type == 'queue') {
                            $tr.addClass("queue_tr");
                            $tr.append($("<td/>").append($("<i class='fa fa-plus'></i>'")));
                            $nodeprop.append(record.weight);
                            $nodeprop.append(" ");
                            $nodeprop.append(
                              $("<i class='fa fa-info-circle' title='" +
                              record.queue_params +
                              "' data-toggle='tooltip'></i>'")
                            );
                            pipe_nr = record.id.split(".")[0];
                            queue_nr = record.id.split(".")[1];
                        } else {
                            $tr.append($("<td/>").append($("<i class='fa fa-question'></i>'")));
                        }
                        $tr.append($("<td/>").html(record.id));
                        $tr.append($("<td/>").html(record.description));
                        $tr.append($("<td/>").append($nodeprop));
                        $tr.append($("<td/>").attr("id", "pkt_total_" + this_id));
                        $tr.append($("<td/>").attr("id", "bytes_total_" + this_id));
                        $tr.append($("<td/>").attr("id", "accessed_total_" + this_id));

                        $("#activityTbl > tbody").append($tr);
                        // active flows
                        if (record.flows.length > 0) {
                            $tr = $("<tr class='flows_tr'/>");
                            $tr.append($("<td colspan='3'/>"));
                            let $activeflows = $("#active_flows_template").clone();
                            let $flowstbody = $activeflows.find("tbody");
                            for (i=0; i < record.flows.length; i++) {
                                let $flowrec = $("<tr/>");
                                $flowrec.append($("<td/>").text(record.flows[i].Prot));
                                $flowrec.append($("<td/>").text(record.flows[i].Source));
                                $flowrec.append($("<td/>").text(record.flows[i].Destination));
                                $flowrec.append($("<td/>").text(formatSizeUnits(record.flows[i].pkt)));
                                $flowrec.append($("<td/>").text(formatSizeUnits(record.flows[i].bytes)));
                                $flowrec.append($("<td/>").text(formatSizeUnits(record.flows[i].drop_pkt)));
                                $flowrec.append($("<td/>").text(formatSizeUnits(record.flows[i].drop_bytes)));
                                $flowstbody.append($flowrec);
                            }
                            $tr.append($("<td colspan='4'/>").append($activeflows));
                            $("#activityTbl > tbody").append($tr);
                        }
                        // attached rules
                        if (record.rules.length > 0) {
                            let total_pkts = 0;
                            let total_bytes = 0;
                            let accessed = "";
                            let accessed_epoch = 0;
                            for (i=0; i < record.rules.length; i++) {
                                let $rulerec = $("<tr class='rule_tr'/>");
                                $rulerec.append($("<td/>").append($("<i class='fa fa-exchange'></i>'")));
                                //$rulerec.append($("<td/>").text(record.rules[i].rule));
                                $rulerec.append($("<td/>"));
                                $rulerec.append($("<td/>").text(record.rules[i].description));
                                $rulerec.append($("<td/>"));
                                $rulerec.append($("<td/>").text(formatSizeUnits(record.rules[i].pkts)));
                                $rulerec.append($("<td/>").text(formatSizeUnits(record.rules[i].bytes)));
                                $rulerec.append($("<td/>").text(record.rules[i].accessed));
                                total_pkts += record.rules[i].pkts;
                                total_bytes += record.rules[i].bytes;
                                if (record.rules[i].accessed_epoch > accessed_epoch) {
                                    accessed_epoch = record.rules[i].accessed_epoch;
                                    accessed = record.rules[i].accessed;
                                }
                                $("#activityTbl > tbody").append($rulerec);
                            }
                            // traffic always belongs to a pipe, could be divided into queues
                            if (pipe_nr) {
                                all_rule_stats[pipe_nr].total_pkts += total_pkts;
                                all_rule_stats[pipe_nr].total_bytes += total_bytes;
                                if (accessed_epoch > all_rule_stats[pipe_nr].accessed_epoch) {
                                    all_rule_stats[pipe_nr].accessed_epoch = accessed_epoch;
                                    all_rule_stats[pipe_nr].accessed = accessed;
                                }
                                if (queue_nr) {
                                    all_rule_stats[pipe_nr].queues[queue_nr] = {
                                        total_pkts:total_pkts,
                                        total_bytes:total_bytes,
                                        accessed: accessed,
                                        id: this_id
                                    };
                                }
                            }
                        }
                    });
                    $('[data-toggle="tooltip"]').tooltip();
                    // set totals
                    $.each(all_rule_stats, function (key, pipe) {
                        let pipe_pkt_td = $("#pkt_total_"+pipe.id);
                        let pipe_bytes_td = $("#bytes_total_"+pipe.id);
                        if (pipe.total_pkts > 0) {
                            pipe_pkt_td.text(formatSizeUnits(pipe.total_pkts));
                        }
                        if (pipe.total_bytes > 0) {
                            pipe_bytes_td.text(formatSizeUnits(pipe.total_bytes));
                        }
                        $("#accessed_total_"+pipe.id).text(pipe.accessed);
                        $.each(pipe.queues, function (key, queue) {
                            let queue_pkt_td = $("#pkt_total_"+queue.id);
                            let queue_bytes_td = $("#bytes_total_"+queue.id);
                            if (queue.total_pkts > 0) {
                                queue_pkt_td.append($("<span/>").text(formatSizeUnits(queue.total_pkts)));
                                queue_pkt_td.append("&nbsp;");
                                queue_pkt_td.append(
                                  $("<small/>").text("[ "+((queue.total_pkts/pipe.total_pkts)*100.0).toFixed(2)+" %]")
                                );
                            }
                            if (queue.total_bytes > 0) {
                                queue_bytes_td.append($("<span/>").text(formatSizeUnits(queue.total_bytes)));
                                queue_bytes_td.append("&nbsp;");
                                queue_bytes_td.append(
                                  $("<small/>").text("[ "+((queue.total_bytes/pipe.total_bytes)*100.0).toFixed(2)+" %]")
                                );
                            }
                            $("#accessed_total_"+queue.id).text(queue.accessed);
                        });
                    });
                    // trigger option events
                    $("#show_flows").change();
                    $("#show_rules").change();
                }
            });
        }
        update_stats();
        $("#show_rules").change(function(){
            if ($("#show_rules").prop('checked')) {
                $(".rule_tr").show();
            } else {
                $(".rule_tr").hide();
            }
        });
        $("#show_flows").change(function(){
            if ($("#show_flows").prop('checked')) {
                $(".flows_tr").show();
            } else {
                $(".flows_tr").hide();
            }
        });

        $("#refreshStats").click(function(){
            update_stats();
        });
    });

</script>

<!-- Templates -->
<div class='hidden'>
    <table class="table table-condensed" id="active_flows_template">
        <thead>
            <tr>
                <th>{{ lang._('Current Activity') }}</th>
            </tr>
            <tr>
                <th>{{ lang._('Proto') }}</th>
                <th>{{ lang._('Source') }}</th>
                <th>{{ lang._('Destination') }}</th>
                <th>{{ lang._('Pkt') }}</th>
                <th>{{ lang._('Bytes') }}</th>
                <th>{{ lang._('Drop Pkt') }}</th>
                <th>{{ lang._('Drop Bytes') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#activity">{{ lang._('Current Activity') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="activity" class="tab-pane fade in active">
        <div class="content-box-main">
            <div  class="col-xs-12">
                <div class="pull-right">
                    <table>
                        <tr>
                            <td>
                              <input id="show_rules" type="checkbox"> {{ lang._('Show rules') }} <br/>
                              <input id="show_flows" type="checkbox"> {{ lang._('Show active flows') }}
                            </td>
                            <td>&nbsp;</td>
                            <td>
                              <span id="refreshStats" class="btn btn-sm btn-default"><i class="fa fa-refresh fa-fw"></i></span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- tab page "activity" -->
        <table class="table table-condensed" id="activityTbl">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>{{ lang._('#') }}</th>
                    <th>{{ lang._('Description') }}</th>
                    <th>{{ lang._('Bandwidth') }}</th>
                    <th>{{ lang._('Packets') }}</th>
                    <th>{{ lang._('Bytes') }}</th>
                    <th>{{ lang._('Accessed') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7"><i class="fa fa-spinner fa-pulse"></i> {{ lang._('Loading') }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7"><strong>{{ lang._('Legend') }}</strong></td>
                </tr>
                <tr>
                    <td><i class='fa fa-square-o'></i></td>
                    <td>{{ lang._('Pipe') }}</td>
                    <td colspan="5"></td>
                </tr>
                <tr>
                    <td><i class='fa fa-plus'></i></td>
                    <td>{{ lang._('Queue') }}</td>
                    <td colspan="5"></td>
                </tr>
                <tr>
                    <td><i class='fa fa-exchange'></i></td>
                    <td>{{ lang._('Rule') }}</td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
