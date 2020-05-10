{#

OPNsense® is Copyright © 2020 by Deciso B.V.
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
<!-- nvd3 -->
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/nv.d3.css', ui_theme|default('opnsense'))) }}" />

<!-- d3 -->
<script src="{{ cache_safe('/ui/js/d3.min.js') }}"></script>

<!-- nvd3 -->
<script src="{{ cache_safe('/ui/js/nv.d3.min.js') }}"></script>


<script>
    'use strict';

    $( document ).ready(function() {
        function load_chart(group_by) {
            ajaxGet("/api/diagnostics/firewall/stats", {group_by: group_by}, function (data, status) {
                if (status == "success") {
                    var svg = d3.select("svg");
                    svg.selectAll("*").remove();
                    nv.addGraph(function() {
                      // Find all piechart classes to insert the chart
                      $('div[class="piechart"]').each(function(){
                          var selected_id = $(this).prop("id");
                          var chart = nv.models.pieChart()
                              .x(function(d) { return d.label })
                              .y(function(d) { return d.value })
                              .showLabels(true)
                              .labelThreshold(.05)
                              .labelType("percent")
                              .donut(true)
                              .donutRatio(0.35)
                              .legendPosition("right")
                              ;
                          d3.select("[id='chart'].piechart svg")
                              .datum(data)
                              .transition().duration(350)
                              .call(chart);

                          // Update Chart after window resize
                          nv.utils.windowResize(function(){ chart.update(); });

                        return chart;
                      });
                    });
                    $("#stats > tbody").empty();
                    for (let i=0; i < data.length ; ++i) {
                        let tr = $("<tr/>");
                        tr.append($("<td/>").text(data[i].label));
                        tr.append($("<td/>").text(data[i].value));
                        $("#stats > tbody").append(tr)
                    }
                }
            });
        }

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            let group_by = e.target.href.split('#')[1];
            load_chart(group_by);
            $("#heading_label").text(e.target.text);
        });

        let selected_tab = window.location.hash != "" ? window.location.hash : "#action";
        $('a[href="' +selected_tab + '"]').tab('show');
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });

    });
</script>

<style>
    #chart svg {
        height: 400px;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#action">{{ lang._('Actions') }}</i></a></li>
    <li><a data-toggle="tab" href="#interface">{{ lang._('Interfaces') }}</i></a></li>
    <li><a data-toggle="tab" href="#protoname">{{ lang._('Protocols') }}</i></a></li>
    <li><a data-toggle="tab" href="#src">{{ lang._('Source IPs') }}</i></a></li>
    <li><a data-toggle="tab" href="#dst">{{ lang._('Destination IPs') }}</i></a></li>
    <li><a data-toggle="tab" href="#srcport">{{ lang._('Source Ports') }}</i></a></li>
    <li><a data-toggle="tab" href="#dstport">{{ lang._('Destination Ports') }}</i></a></li>
</ul>
<div class="tab-content">
    <div  id="graph" class="tab-pane fade in active">
      <div class="panel panel-default">
        <div class="panel-body">
          <div class="piechart" id="chart">
              <svg></svg>
          </div>
          <table class="table table-striped table-bordered" id="stats">
            <thead>
                <tr>
                  <th id="heading_label">  </th>
                  <th> # </th>
                </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    </div>
</div>
