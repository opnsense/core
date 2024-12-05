<!--
/*
*    Copyright (C) 2023 Deciso B.V.
*    Copyright (C) 2015 Jos Schellevis <jos@opnsense.org>
*    All rights reserved.
*
*    Redistribution and use in source and binary forms, with or without
*    modification, are permitted provided that the following conditions are met:
*
*    1. Redistributions of source code must retain the above copyright notice,
*       this list of conditions and the following disclaimer.
*
*    2. Redistributions in binary form must reproduce the above copyright
*       notice, this list of conditions and the following disclaimer in the
*       documentation and/or other materials provided with the distribution.
*
*    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
*    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
*    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
*    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
*    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
*    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
*    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
*    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
*    POSSIBILITY OF SUCH DAMAGE.
*/
-->

<style type="text/css">
.panel-heading-sm{
    height: 28px;
    padding: 4px 10px;
}
</style>

<!-- nvd3 -->
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/nv.d3.css', ui_theme|default('opnsense'))) }}" />

<!-- d3 -->
<script src="{{ cache_safe('/ui/js/d3.min.js') }}"></script>

<!-- nvd3 -->
<script src="{{ cache_safe('/ui/js/nv.d3.min.js') }}"></script>

<!-- System Health -->
<style>

    #chart svg {
        height: 500px;
    }

</style>


<script>
    let chart;
    let data = [];
    let disabled = [];
    let resizeTimer;
    let current_detail = 0;
    let csvData = [];
    let zoom_buttons;
    let rrd="";


    // create our chart
    nv.addGraph(function () {
        chart = nv.models.lineWithFocusChart()
                .margin( {left:70})
                .x(function (d) {
                    return d[0]
                })
                .y(function (d) {
                    return d[1]
                });
        chart.xAxis
                .tickFormat(function (d) {
                    return d3.time.format('%b %e %H:%M')(new Date(d))
                });

        chart.x2Axis
                .tickFormat(function (d) {
                    return d3.time.format('%Y-%m-%d')(new Date(d))
                });

        chart.yAxis
                .tickFormat(d3.format(',.2s'));

        chart.y2Axis
                .tickFormat(d3.format(',.1s'));

        chart.focusHeight(80);
        chart.interpolate('step-before');

        // dispatch when one of the streams is enabled/disabled
        chart.dispatch.on('stateChange', function (e) {
            disabled = e['disabled'];
        });

        // dispatch on window resize - delay action with 500ms timer
        nv.utils.windowResize(function () {
            if (resizeTimer) {
                clearTimeout(resizeTimer);
            }
            resizeTimer = setTimeout(function () {
                chart.update();
                resizeTimer = null;
            }, 500);
        });

        return chart;
    });

    function getRRDlist() {
        ajaxGet("/api/diagnostics/systemhealth/getRRDlist/", {}, function (data, status) {
            if (status == "success") {
                let category;
                let tabs = "";
                let subitem = "";
                let active_category = Object.keys(data["data"])[0];
                let active_subitem = data["data"][active_category][0];
                let rrd_name = "";
                for ( category in data["data"]) {
                    if (category == active_category) {
                        tabs += '<li role="presentation" class="dropdown active">';
                    } else {
                        tabs += '<li role="presentation" class="dropdown">';
                    }

                    subitem = data["data"][category][0]; // first sub item
                    rrd_name = subitem + '-' + category;

                    // create dropdown menu
                    tabs+='<a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">';
                    tabs+='<b><span class="caret"></span></b>';
                    tabs+='</a>';
                    tabs+='<a data-toggle="tab" onclick="$(\'#'+rrd_name+'\').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>'+category[0].toUpperCase() + category.slice(1)+'</b></a>';
                    tabs+='<ul class="dropdown-menu" role="menu">';

                    // add subtabs
                    for (let count=0;  count<data["data"][category].length;++count ) {
                        subitem=data["data"][category][count];
                        rrd_name = subitem + '-' + category;

                        if (subitem==active_subitem && category==active_category) {
                            tabs += '<li class="active"><a data-toggle="tab" class="rrd_item" id="'+rrd_name+'">' + subitem[0].toUpperCase() + subitem.slice(1) + '</a></li>';
                        } else {
                            tabs += '<li><a data-toggle="tab" class="rrd_item"  id="'+rrd_name+'">' + subitem[0].toUpperCase() + subitem.slice(1) + '</a></li>';
                        }
                    }
                    tabs+='</ul>';
                    tabs+='</li>';
                }
                $('#maintabs').html(tabs);
                $('#tab_1').toggleClass('active');

                // map interface descriptions
                $(".rrd_item").each(function(){
                    let rrd_item = $(this);
                    let rrd_item_name = $(this).attr('id').split('-')[0].toLowerCase();
                    $.map(data['interfaces'], function(value, key){
                        if (key.toLowerCase() == rrd_item_name) {
                            rrd_item.html(value['descr']);
                        }
                    });
                });
                $(".rrd_item").click(function(){
                    // switch between rrd graphs
                    $('#zoom').empty();
                    disabled = [];  // clear disabled stream data
                    chart.brushExtent([0, 0]); // clear focus area
                    getdata($(this).attr('id'));
                });
                $(".update_options").change(function(){
                    window.onresize = null; // clear any pending resize events
                    let rrd = $(".dropdown-menu > li.active > a").attr('id');
                    if ($(this).attr('id') == 'zoom') {
                        chart.brushExtent([0, 0]); // clear focus area
                    }
                    getdata(rrd);
                });
                $("#"+active_subitem+"-"+active_category).click();
            }
        });
    }

    function getdata(rrd_name) {

        let from = 0;
        let to = 0;
        let maxitems = 120;

        let detail = $('input:radio[name=detail]:checked').val() ?? '0';
        let inverse = $('input:radio[name=inverse]:checked').val() == 1 ? '1' : '0';

        // array used for cvs export option
        csvData = [];

        // array used for min/max/average table when shown
        let min_max_average = {};

        // info bar - hide averages info bar while refreshing data
        $('#averages').hide();
        $('#chart_title').hide();
        // info bar - show loading info bar while refreshing data
        $('#loading').show();
        // API call to request data
        ajaxGet("/api/diagnostics/systemhealth/getSystemHealth/" + rrd_name + "/" + inverse + "/" + detail, {}, function (data, status) {
            if (status == "success") {
                let stepsize = data["set"]["step_size"];
                let scale = "{{ lang._('seconds') }}";
                let dtformat = '%m-%d %H:%M';

                // set defaults based on stepsize
                if (stepsize >= 86400) {
                    stepsize = stepsize / 86400;
                    scale = "{{ lang._('days') }}";
                    dtformat = '\'%y w%U%';
                } else if (stepsize >= 3600) {
                    stepsize = stepsize / 3600;
                    scale = "{{ lang._('hours') }}";
                    dtformat = '\'%y d%j%';
                } else if (stepsize >= 60) {
                    stepsize = stepsize / 60;
                    scale = "{{ lang._('minutes') }}";
                    dtformat = '%H:%M';
                }

                // Add zoomlevel buttons/options
                if ($('input:radio[name=detail]:checked').val() == undefined) {
                    $('#zoom').html("<b>No data available</b>");
                    for (let setcount = 0; setcount < data["sets"].length; ++setcount) {
                        const set_stepsize = data["sets"][setcount]["step_size"];
                        let detail_text = '';
                        // Find out what text matches best
                        if (set_stepsize >= 31536000) {
                            detail_text = Math.floor(set_stepsize / 31536000).toString() + " {{ lang._('Year(s)') }}";
                        } else if (set_stepsize >= 259200) {
                            detail_text = Math.floor(set_stepsize / 86400).toString() + " {{ lang._('Days') }}";
                        } else if (set_stepsize > 3600) {
                            detail_text = Math.floor(set_stepsize / 3600).toString() + " {{ lang._('Hours') }}";
                        } else {
                            detail_text = Math.floor(set_stepsize / 60).toString() + " {{ lang._('Minute(s)') }}";
                        }
                        if (setcount == 0) {
                            $('#zoom').empty();
                            $('#zoom').append('<label class="btn btn-default active"> <input type="radio" id="d' + setcount.toString() + '" name="detail" checked="checked" value="' + setcount.toString() + '" /> ' + detail_text + ' </label>');
                        } else {
                            $('#zoom').append('<label class="btn btn-default"> <input type="radio" id="d' + setcount.toString() + '" name="detail" value="' + setcount.toString() + '" /> ' + detail_text + ' </label>');
                        }

                    }
                }
                $('#stepsize').text(stepsize + " " + scale);

                // Check for enabled or disabled stream, to make sure that same set stays selected after update
                for (let index = 0; index < disabled.length; ++index) {
                    window.resize = null;
                    data["set"]["data"][index]["disabled"] = disabled[index]; // disable stream if it was disabled before updating dataset
                }

                // Create tables (general and detail)
                if ($('input:radio[name=show_table]:checked').val() == 1) { // check if toggle table is on
                    // Setup variables for table data
                    let table_head; // used for table headings in html format
                    let table_row_data = {}; // holds row data for table
                    let table_view_rows = ""; // holds row data in html format

                    let keyname = ""; // used for name of key
                    let rowcounter = 0;// general row counter
                    let min; // holds calculated minimum value
                    let max; // holds calculated maximum value
                    let average; // holds calculated average

                    let t; // general date/time variable
                    let item; // used for name of key

                    let counter = 1; // used for row count

                    table_head = "<th>#</th>";
                    if ($('input:radio[name=toggle_time]:checked').val() == 1) {
                        table_head += "<th>{{ lang._('full date & time') }}</th>";
                    } else {
                        table_head += "<th>{{ lang._('timestamp') }}</th>";
                    }


                    for (let index = 0; index < data["set"]["data"].length; ++index) {
                        rowcounter = 0;
                        min = 0;
                        max = 0;
                        average = 0;
                        if (data["set"]["data"][index]["disabled"] != true) {
                            table_head += '<th>' + data["set"]["data"][index]["key"] + '</th>';
                            keyname = data["set"]["data"][index]["key"].toString();
                            for (let value_index = 0; value_index < data["set"]["data"][index]["values"].length; ++value_index) {

                                if (data["set"]["data"][index]["values"][value_index][0] >= (from * 1000) && data["set"]["data"][index]["values"][value_index][0] <= (to * 1000) || ( from == 0 && to == 0 )) {

                                    if (table_row_data[data["set"]["data"][index]["values"][value_index][0]] === undefined) {
                                        table_row_data[data["set"]["data"][index]["values"][value_index][0]] = {};
                                    }
                                    if (table_row_data[data["set"]["data"][index]["values"][value_index][0]][data["set"]["data"][index]["key"]] === undefined) {
                                        table_row_data[data["set"]["data"][index]["values"][value_index][0]][data["set"]["data"][index]["key"]] = data["set"]["data"][index]["values"][value_index][1];
                                    }
                                    if (csvData[rowcounter] === undefined) {
                                        csvData[rowcounter] = {};
                                    }
                                    if (csvData[rowcounter]["timestamp"] === undefined) {
                                        t = new Date(parseInt(data["set"]["data"][index]["values"][value_index][0]));
                                        csvData[rowcounter]["timestamp"] = data["set"]["data"][index]["values"][value_index][0] / 1000;
                                        csvData[rowcounter]["date_time"] = t.toString();
                                    }
                                    csvData[rowcounter][keyname] = data["set"]["data"][index]["values"][value_index][1];
                                    if (data["set"]["data"][index]["values"][value_index][1] < min) {
                                        min = data["set"]["data"][index]["values"][value_index][1];
                                    }
                                    if (data["set"]["data"][index]["values"][value_index][1] > max) {
                                        max = data["set"]["data"][index]["values"][value_index][1];
                                    }
                                    average += data["set"]["data"][index]["values"][value_index][1];
                                    ++rowcounter;
                                }
                            }
                            if (min_max_average[keyname] === undefined) {
                                min_max_average[keyname] = {};
                                min_max_average[keyname]["min"] = min;
                                min_max_average[keyname]["max"] = max;
                                min_max_average[keyname]["average"] = average / rowcounter;
                            }

                        }
                    }

                    for ( item in min_max_average) {
                        table_view_rows += "<tr>";
                        table_view_rows += "<td>" + item + "</td>";
                        table_view_rows += "<td>" + min_max_average[item]["min"].toString() + "</td>";
                        table_view_rows += "<td>" + min_max_average[item]["max"].toString() + "</td>";
                        table_view_rows += "<td>" + min_max_average[item]["average"].toString() + "</td>";
                        table_view_rows += "</tr>";
                    }
                    $('#table_view_general_heading').html('<th>item</th><th>min</th><th>max</th><th>average</th>');
                    $('#table_view_general_rows').html(table_view_rows);
                    table_view_rows = "";

                    for ( item in table_row_data) {
                        if ($('input:radio[name=toggle_time]:checked').val() == 1) {
                            t = new Date(parseInt(item));
                            table_view_rows += "<tr><td>" + counter.toString() + "</td><td>" + t.toString() + "</td>";
                        } else {
                            table_view_rows += "<tr><td>" + counter.toString() + "</td><td>" + parseInt(item / 1000).toString() + "</td>";
                        }
                        for (let value in table_row_data[item]) {
                            table_view_rows += "<td>" + table_row_data[item][value] + "</td>";
                        }
                        ++counter;
                        table_view_rows += "</tr>";
                    }

                    $('#table_view_heading').html(table_head);
                    $('#table_view_rows').html(table_view_rows);
                    $('#chart_details_table').show();
                    $('#chart_general_table').show();
                } else {
                    $('#chart_details_table').hide();
                    $('#chart_general_table').hide();
                }
                chart.xAxis
                        .tickFormat(function (d) {
                            return d3.time.format(dtformat)(new Date(d))
                        });
                chart.yAxis.axisLabel(data["y-axis_label"]);
                chart.forceY([0]);
                chart.useInteractiveGuideline(true);
                chart.interactive(true);

                d3.select('#chart svg')
                        .datum(data["set"]["data"])
                        .transition().duration(0)
                        .call(chart);


                chart.update();
                window.onresize = null; // clear any pending resize events


                $('#loading').hide(); // Data has been found and chart will be drawn
                $('#averages').show();
                $('#chart_title').show();
                if (data["title"]!="") {
                    $('#chart_title').show();
                    $('#chart_title').text(data["title"]);
                } else
                {
                    $('#chart_title').hide();
                }

            } else {
                $('#loading').hide();
                $('#chart_title').show();
                $('#chart_title').text("{{ lang._('Unable to load data') }}");
            }
        });
    }

    // convert a data Array to CSV format
    function convertToCSV(args) {
        let result, ctr, keys, columnDelimiter, lineDelimiter, data;

        data = args.data || null;
        if (data == null || !data.length) {
            return null;
        }

        columnDelimiter = args.columnDelimiter || ';';
        lineDelimiter = args.lineDelimiter || '\n';

        keys = Object.keys(data[0]);

        result = '';
        result += keys.join(columnDelimiter);
        result += lineDelimiter;

        data.forEach(function (item) {
            ctr = 0;
            keys.forEach(function (key) {
                if (ctr > 0) result += columnDelimiter;

                result += item[key];
                ctr++;
            });
            result += lineDelimiter;
        });

        return result;
    }

    // download CVS file
    function downloadCSV(args) {
        let data, filename, link;
        let csv = convertToCSV({
            data: csvData
        });
        if (csv == null) return;
        filename = args.filename || 'export.csv';

        if (!csv.match(/^data:text\/csv/i)) {
            csv = 'data:text/csv;charset=utf-8,' + csv;
        }
        data = encodeURI(csv);

        link = document.createElement('a');
        link.href = data;
        link.target = '_blank';
        link.download = filename;
        document.body.appendChild(link);
        link.click();
    }

    $(document).ready(function() {
        $("#options").collapse('show');
        // hide title row
        $(".page-content-head").addClass("hidden");
        // Load data when document is ready
        getRRDlist();
    });

</script>

<div class="tab-content">
    <div id="info_tab" class="tab-pane fade in">
      <div class="panel panel-primary">
          <div class="panel-heading">
              <h3 class="panel-title">
                  <b>{{ lang._('Information') }}</b>
              </h3>
          </div>
          <div class="panel-body">
            {{ lang._('Local data collection is not enabled at the moment') }}
            <a href="/reporting_settings.php">{{ lang._('Go to reporting settings') }} </a>
          </div>
      </div>
    </div>
    <div id="tab_1" class="tab-pane fade in">
        <div class="panel panel-primary">
            <div class="panel-heading panel-heading-sm">
              <i class="fa fa-chevron-down" style="cursor: pointer;" data-toggle="collapse" data-target="#options"></i>
              <b>{{ lang._('Options') }}</b>
            </div>
            <div class="panel-body collapse" id="options">
                <div class="container-fluid">
                <ul class="nav nav-tabs" role="tablist" id="maintabs">
                {# Tab Content #}
                </ul>
                    <div class="row">
                        <div class="col-md-12"></div>
                        <div class="col-md-4">
                            <b>{{ lang._('Granularity') }}:</b>
                            <div class="btn-group btn-group-xs update_options" data-toggle="buttons" id="zoom">
                                <!-- The zoom buttons are generated based upon the current dataset -->
                            </div>
                        </div>
                        <div class="col-md-2">
                            <b>{{ lang._('Inverse') }}:</b>
                            <div class="btn-group btn-group-xs update_options" data-toggle="buttons">
                                <label class="btn btn-default active">
                                    <input type="radio" id="in0" name="inverse" checked="checked" value="0"/>
                                    {{lang._('Off') }}
                                </label>
                                <label class="btn btn-default">
                                    <input type="radio" id="in1" name="inverse" value="1"/> {{ lang._('On') }}
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                        </div>
                        <div class="col-md-2">
                            <b>{{ lang._('Show Tables') }}:</b>
                            <div class="btn-group btn-group-xs update_options" data-toggle="buttons">
                                <label class="btn btn-default active">
                                    <input type="radio" id="tab0" name="show_table" checked="checked" value="0"/> {{
                                    lang._('Off') }}
                                </label>
                                <label class="btn btn-default">
                                    <input type="radio" id="tab1" name="show_table" value="1"/> {{ lang._('On') }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- place holder for the chart itself -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                 <span id="loading">
                     <i id="loading" class="fa fa-spinner fa-spin"></i>
                     <b>{{ lang._('Please wait while loading data...') }}</b>
                 </span>
                <span id="chart_title"> </span>
                <span id="averages">
                    <small>
                    (<b>{{ lang._('Current detail is showing') }} <span id="stepsize"></span> {{ lang._('averages') }}.</b>)
                    </small>
                </span>
                </h3>
            </div>
            <div class="panel-body">
                <div id="chart">
                    <svg></svg>
                </div>
            </div>
        </div>

        <!-- place holder for the general table with min/max/averages, is hidden by default -->
        <div id="chart_general_table" class="col-md-12" style="display: none;">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"> {{ lang._('Current View - Overview') }}</h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-condensed table-hover table-striped">
                            <thead>
                                <tr id="table_view_general_heading" class="active">
                                    <!-- Dynamic data -->
                                </tr>
                            </thead>
                            <tbody id="table_view_general_rows">
                                <!-- Dynamic data -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="chart_details_table" class="col-md-12" style="display: none;">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                  {{ lang._('Current View - Details') }}
                </h3>
            </div>
            <div class="panel-body">
                <div class="btn-toolbar" role="toolbar">
                    <i>{{ lang._('Toggle Timeview') }}:</i>

                    <div class="btn-group update_options" data-toggle="buttons">
                        <label class="btn btn-xs btn-default active">
                            <input type="radio" id="time0" name="toggle_time" checked="checked" value="0"/> {{
                            lang._('Timestamp') }}
                        </label>
                        <label class="btn btn-xs btn-default">
                            <input type="radio" id="time1" name="toggle_time" value="1"/> {{ lang._('Full Date & Time') }}
                        </label>
                    </div>
                    <div class="btn btn-xs btn-primary inline" onclick='downloadCSV({ filename: rrd+".csv" });'>
                        <i class="fa fa-download"></i> {{ lang._('Download as CSV') }}
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-condensed table-hover table-striped">
                        <thead>
                        <tr id="table_view_heading" class="active">
                            <!-- Dynamic data -->
                        </tr>
                        </thead>
                        <tbody id="table_view_rows">
                        <!-- Dynamic data -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
