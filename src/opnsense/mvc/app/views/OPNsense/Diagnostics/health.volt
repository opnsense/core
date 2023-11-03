<!--
/*
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
    var chart;
    var data = [];
    var fetching_data = true;
    var current_selection_from = 0;
    var current_selection_to = 0;
    var disabled = [];
    var resizeTimer;
    var current_detail = 0;
    var csvData = [];
    var zoom_buttons;
    var rrd="";


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
                    return d3.time.format('%x')(new Date(d))
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

    // Some options have changed, check and fetch data
    function UpdateOptions() {
        window.onresize = null; // clear any pending resize events
        var inverse = false;
        var detail = 0;
        var resolution = 120;
        if ($('input:radio[name=inverse]:checked').val() == 1) {
            inverse = true;
        }

        detail = $('input:radio[name=detail]:checked').val();
        resolution = $('input:radio[name=resolution]:checked').val();
        if (detail != current_detail) {
            chart.brushExtent([0, 0]);
            getdata(rrd, 0, 0, resolution, detail);
            current_detail = detail;
        } else {
            getdata(rrd, current_selection_from, current_selection_to, resolution, detail);
            current_detail = detail;
        }
    }

    function getRRDlist() {
        ajaxGet("/api/diagnostics/systemhealth/getRRDlist/", {}, function (data, status) {
            if (status == "success") {
                if (data.data.length == 0 ) {
                    $(".page-content-head").removeClass("hidden");
                    $('#info_tab').toggleClass('active');
                    return;
                }
                var category;
                var tabs="";
                var subitem="";
                var active_category=Object.keys(data["data"])[0];
                var active_subitem=data["data"][active_category][0];
                var rrd_name="";
                for ( category in data["data"]) {
                    if (category == active_category) {
                        tabs += '<li role="presentation" class="dropdown active">';
                    } else {
                        tabs += '<li role="presentation" class="dropdown">';
                    }

                    subitem=data["data"][category][0]; // first sub item
                    rrd_name = subitem + '-' + category;

                    // create dropdown menu
                    tabs+='<a data-toggle="dropdown" href="#" class="dropdown-toggle pull-right visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" role="button">';
                    tabs+='<b><span class="caret"></span></b>';
                    tabs+='</a>';
                    tabs+='<a data-toggle="tab" onclick="$(\'#'+rrd_name+'\').click();" class="visible-lg-inline-block visible-md-inline-block visible-xs-inline-block visible-sm-inline-block" style="border-right:0px;"><b>'+category[0].toUpperCase() + category.slice(1)+'</b></a>';
                    tabs+='<ul class="dropdown-menu" role="menu">';
                    rrd_name="";

                    // add subtabs
                    for (var count=0;  count<data["data"][category].length;++count ) {
                        subitem=data["data"][category][count];
                        rrd_name = subitem + '-' + category;

                        if (subitem==active_subitem && category==active_category) {
                            tabs += '<li class="active"><a data-toggle="tab" class="rrd-item" onclick="getdata(\''+rrd_name+'\',0,0,120,0);" id="'+rrd_name+'">' + subitem[0].toUpperCase() + subitem.slice(1) + '</a></li>';
                            rrd=rrd_name;
                            getdata(rrd_name,0,0,120,false,0); // load initial data
                        } else {
                            tabs += '<li><a data-toggle="tab" class="rrd-item"  onclick="getdata(\''+rrd_name+'\',0,0,120,0);" id="'+rrd_name+'">' + subitem[0].toUpperCase() + subitem.slice(1) + '</a></li>';
                        }
                    }
                    tabs+='</ul>';
                    tabs+='</li>';
                }
                $('#maintabs').html(tabs);
                $('#tab_1').toggleClass('active');
                // map interface descriptions
                $(".rrd-item").each(function(){
                    var rrd_item = $(this);
                    var rrd_item_name = $(this).attr('id').split('-')[0].toLowerCase();
                    $.map(data['interfaces'], function(value, key){
                        if (key.toLowerCase() == rrd_item_name) {
                            rrd_item.html(value['descr']);
                        }
                    });
                });
            } else {
                alert("Error while fetching RRD list : "+status);
            }
        });
    }

    function getdata(rrd_name, from, to, maxitems, detail) {

        if (zoom_buttons===undefined) {
            zoom_buttons="";
        }

        // Set defaults if not specified
        if (rrd_name === undefined) {
            rrd_name = rrd;
            disabled = [];  // clear disabled stream data

        } else {
            if ( rrd_name!=rrd ) {
                rrd = rrd_name; // set global rrd name to current rrd
                disabled = [];  // clear disabled stream data
                zoom_buttons=""; // clear zoom_buttons
                chart.brushExtent([0, 0]); // clear focus area
                $('#res0').parent().click(); // reset resolution
            }
        }

        if (from === undefined) {
            from = 0;
        }
        if (to === undefined) {
            to = 0;

        }
        if (maxitems === undefined) {
            maxitems = 120;

        }

        let inverse = false;
        if ($('input:radio[name=inverse]:checked').val() == 1) {
            inverse = true;
        }
        if (detail === undefined) {
            detail = 0;
        }

        // Remember selected area
        current_selection_from = from;
        current_selection_to = to;

        // Flag to know when we are fetching data
        fetching_data = true;

        // Used to set render the zoom/detail buttons
        //zoom_buttons = "";

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
        ajaxGet("/api/diagnostics/systemhealth/getSystemHealth/" + rrd_name + "/" + String(from) + "/" + String(to) + "/" + String(maxitems) + "/" + String(inverse) + "/" + String(detail), {}, function (data, status) {
            if (status == "success") {
                var stepsize = data["d3"]["stepSize"];
                var scale = "{{ lang._('seconds') }}";
                var dtformat = '%m/%d %H:%M';
                var visible_time=to-from;

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

                // if we have a focus area then change the x-scale to reflect current view
                if (visible_time >= (86400*7)) { // one week
                    dtformat = '\'%y w%U%';
                } else if (visible_time >= (3600*48)) { // 48 hours
                    dtformat = '\'%y d%j%';
                } else if (visible_time >= (60*maxitems)) { // max minutes
                    dtformat = '%H:%M';
                }

                // Add zoomlevel buttons/options
                if ($('input:radio[name=detail]:checked').val() == undefined || zoom_buttons==="") {
                    for (let setcount = 0; setcount < data["sets"].length; ++setcount) {
                        const recordedtime = data["sets"][setcount]["recorded_time"];
                        let detail_text = '';
                        // Find out what text matches best
                        if (recordedtime >= 31536000) {
                            detail_text = Math.floor(recordedtime / 31536000).toString() + " {{ lang._('Year(s)') }}";
                        } else if (recordedtime >= 259200) {
                            detail_text = Math.floor(recordedtime / 86400).toString() + " {{ lang._('Days') }}";
                        } else if (recordedtime > 3600) {
                            detail_text = Math.floor(recordedtime / 3600).toString() + " {{ lang._('Hours') }}";
                        } else {
                            detail_text = Math.floor(recordedtime / 60).toString() + " {{ lang._('Minutes') }}";
                        }
                        if (setcount == 0) {
                            zoom_buttons += '<label class="btn btn-default active"> <input type="radio" id="d' + setcount.toString() + '" name="detail" checked="checked" value="' + setcount.toString() + '" /> ' + detail_text + ' </label>';
                        } else {
                            zoom_buttons += '<label class="btn btn-default"> <input type="radio" id="d' + setcount.toString() + '" name="detail" value="' + setcount.toString() + '" /> ' + detail_text + ' </label>';
                        }

                    }
                    if (zoom_buttons === "") {
                        zoom_buttons = "<b>No data available</b>";
                    }
                    // insert zoom buttons html code
                    $('#zoom').html(zoom_buttons);
                }
                $('#stepsize').text(stepsize + " " + scale);

                // Check for enabled or disabled stream, to make sure that same set stays selected after update
                for (let index = 0; index < disabled.length; ++index) {
                    window.resize = null;
                    data["d3"]["data"][index]["disabled"] = disabled[index]; // disable stream if it was disabled before updating dataset
                }

                // Create tables (general and detail)
                if ($('input:radio[name=show_table]:checked').val() == 1) { // check if toggle table is on
                    table_head = "<th>#</th>";
                    if ($('input:radio[name=toggle_time]:checked').val() == 1) {
                        table_head += "<th>{{ lang._('full date & time') }}</th>";
                    } else {
                        table_head += "<th>{{ lang._('timestamp') }}</th>";
                    }

                    // Setup variables for table data
                    var table_head; // used for table headings in html format
                    var table_row_data = {}; // holds row data for table
                    var table_view_rows = ""; // holds row data in html format

                    var keyname = ""; // used for name of key
                    var rowcounter = 0;// general row counter
                    var min; // holds calculated minimum value
                    var max; // holds calculated maximum value
                    var average; // holds calculated average

                    var t; // general date/time variable
                    var item; // used for name of key

                    var counter = 1; // used for row count

                    for (let index = 0; index < data["d3"]["data"].length; ++index) {
                        rowcounter = 0;
                        min = 0;
                        max = 0;
                        average = 0;
                        if (data["d3"]["data"][index]["disabled"] != true) {
                            table_head += '<th>' + data["d3"]["data"][index]["key"] + '</th>';
                            keyname = data["d3"]["data"][index]["key"].toString();
                            for (var value_index = 0; value_index < data["d3"]["data"][index]["values"].length; ++value_index) {

                                if (data["d3"]["data"][index]["values"][value_index][0] >= (from * 1000) && data["d3"]["data"][index]["values"][value_index][0] <= (to * 1000) || ( from == 0 && to == 0 )) {

                                    if (table_row_data[data["d3"]["data"][index]["values"][value_index][0]] === undefined) {
                                        table_row_data[data["d3"]["data"][index]["values"][value_index][0]] = {};
                                    }
                                    if (table_row_data[data["d3"]["data"][index]["values"][value_index][0]][data["d3"]["data"][index]["key"]] === undefined) {
                                        table_row_data[data["d3"]["data"][index]["values"][value_index][0]][data["d3"]["data"][index]["key"]] = data["d3"]["data"][index]["values"][value_index][1];
                                    }
                                    if (csvData[rowcounter] === undefined) {
                                        csvData[rowcounter] = {};
                                    }
                                    if (csvData[rowcounter]["timestamp"] === undefined) {
                                        t = new Date(parseInt(data["d3"]["data"][index]["values"][value_index][0]));
                                        csvData[rowcounter]["timestamp"] = data["d3"]["data"][index]["values"][value_index][0] / 1000;
                                        csvData[rowcounter]["date_time"] = t.toString();
                                    }
                                    csvData[rowcounter][keyname] = data["d3"]["data"][index]["values"][value_index][1];
                                    if (data["d3"]["data"][index]["values"][value_index][1] < min) {
                                        min = data["d3"]["data"][index]["values"][value_index][1];
                                    }
                                    if (data["d3"]["data"][index]["values"][value_index][1] > max) {
                                        max = data["d3"]["data"][index]["values"][value_index][1];
                                    }
                                    average += data["d3"]["data"][index]["values"][value_index][1];
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
                        for (var value in table_row_data[item]) {
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
                chart.useInteractiveGuideline(true);
                chart.interactive(true);

                d3.select('#chart svg')
                        .datum(data["d3"]["data"])
                        .transition().duration(0)
                        .call(chart);


                chart.update();
                window.onresize = null; // clear any pending resize events


                fetching_data = false;
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
                alert("Error while fetching data : "+status);
            }
        });
    }

    // convert a data Array to CSV format
    function convertToCSV(args) {
        var result, ctr, keys, columnDelimiter, lineDelimiter, data;

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
        var data, filename, link;
        var csv = convertToCSV({
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
                            <b>{{ lang._('Zoom level') }}:</b>
                            <form onChange="UpdateOptions()">
                                <div class="btn-group btn-group-xs" data-toggle="buttons" id="zoom">
                                    <!-- The zoom buttons are generated based upon the current dataset -->
                                </div>
                            </form>
                        </div>
                        <div class="col-md-2">
                            <b>{{ lang._('Inverse') }}:</b>
                            <form onChange="UpdateOptions()">
                                <div class="btn-group btn-group-xs" data-toggle="buttons">
                                    <label class="btn btn-default active">
                                        <input type="radio" id="in0" name="inverse" checked="checked" value="0"/> {{
                                        lang._('Off') }}
                                    </label>
                                    <label class="btn btn-default">
                                        <input type="radio" id="in1" name="inverse" value="1"/> {{ lang._('On') }}
                                    </label>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <b>{{ lang._('Resolution') }}:</b>
                            <form onChange="UpdateOptions()">
                                <div class="btn-group btn-group-xs" data-toggle="buttons">
                                    <label class="btn btn-default active">
                                        <input type="radio" id="res0" name="resolution" checked="checked" value="120"/>
                                        {{ lang._('Standard') }}
                                    </label>
                                    <label class="btn btn-default">
                                        <input type="radio" id="res1" name="resolution" value="240"/> {{
                                        lang._('Medium') }}
                                    </label>
                                    <label class="btn btn-default">
                                        <input type="radio" id="res2" name="resolution" value="600"/> {{ lang._('High')
                                        }}
                                    </label>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-2">
                            <b>{{ lang._('Show Tables') }}:</b>
                            <form onChange="UpdateOptions()">
                                <div class="btn-group btn-group-xs" data-toggle="buttons">
                                    <label class="btn btn-default active">
                                        <input type="radio" id="tab0" name="show_table" checked="checked" value="0"/> {{
                                        lang._('Off') }}
                                    </label>
                                    <label class="btn btn-default">
                                        <input type="radio" id="tab1" name="show_table" value="1"/> {{ lang._('On') }}
                                    </label>
                                </div>
                            </form>
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

                    <form onChange="UpdateOptions();">
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-xs btn-default active">
                                <input type="radio" id="time0" name="toggle_time" checked="checked" value="0"/> {{
                                lang._('Timestamp') }}
                            </label>
                            <label class="btn btn-xs btn-default">
                                <input type="radio" id="time1" name="toggle_time" value="1"/> {{ lang._('Full Date & Time') }}
                            </label>
                        </div>
                    </form>
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
