{#
 # Copyright (c) 2022 Deciso B.V.
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

{% set theme_name = ui_theme|default('opnsense') %}
<script src="{{ cache_safe('/ui/js/chart.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-streaming.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-colorschemes.js') }}"></script>
<script src="{{ cache_safe('/ui/js/moment-with-locales.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-adapter-moment.js') }}"></script>
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/chart.css', theme_name)) }}" rel="stylesheet" />
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/dns-overview.css', theme_name)) }}" rel="stylesheet"/>

<script>
    $(document).ready(function() {
        $('#info').hide();

        function create_chart(target, stepsize, feed_data) {
            const ctx = target[0].getContext('2d');
            const config = {
                type: 'line',
                data: {
                    datasets: [{
                        label: 'Total queries',
                        data: feed_data,
                        borderWidth: 1,
                        parsing: {
                            yAxisKey: 'y.total'
                        }
                    }, {
                        label: 'Blocked',
                        data: feed_data,
                        borderWidth: 1,
                        parsing: {
                            yAxisKey: 'y.blocked'
                        }
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    aspectRatio: 1,
                    elements: {
                        line: {
                            fill: false,
                            cubicInterpolationMode: 'monotone',
                            clip: 0,
                        },
                        point: {
                            radius: 0
                        }
                    },
                    layout: {
                        padding: {
                            left: 40,
                            right: 50,
                            bottom: 20
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                tooltipFormat:'HH:mm',
                                unit:'minute',
                                stepSize: stepsize,
                                minUnit: 'minute',
                                displayFormats: {
                                    minute: 'HH:mm'
                                }
                            }
                        },
                        y: {
                            ticks: {
                                callback: function (value, index, values) {
                                    /* Don't show decimal values on the y-axis */
                                    if (Math.floor(value) == value) {
                                        return value;
                                    }
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'nearest',
                            intersect: false
                        },
                        legend: {
                            display: true
                        },
                        colorschemes: {
                            scheme: 'brewer.DarkTwo8'
                        }
                    }
                }
            };

            return new Chart(ctx, config);
        }

        function formatQueryData(data) {
            let formatted = [];
            Object.keys(data).forEach((key, index) => {
                formatted.push({
                    x: key * 1000,
                    y: data[key]
                });
            });

            /* Add a redundant data step to end the chart time axis properly */
            if (formatted.length > 0) {
                let lastVal = formatted[formatted.length - 1];
                let interval = $("#timeperiod").val() == 1 ? 60 : 300;
                formatted.push({
                    x: lastVal.x + (interval * 1000),
                    y: null
                });
            }

            return formatted;
        }

        function updateQueryChart() {
            ajaxGet('/api/unbound/overview/rolling/' + $("#timeperiod").val(), {}, function(data, status) {
                let formatted = formatQueryData(data);
                g_queryChart.config.options.scales.x.time.stepSize = $("#timeperiod").val() == 1 ? 5 : 60;
                g_queryChart.config.data.datasets.forEach(function(dataset) {
                    dataset.data = formatted;
                });
                g_queryChart.update();
            });
        }

        function formatTitle() {
            let h = $("#timeperiod").val() == 1 ? "hour" : $("#timeperiod").val() + " hours";
            return "Queries over the last " + h;
        }

        function createTopList(id, data) {
            let idx = 1;
            for (const [domain, statObj] of Object.entries(data)) {
                let bl = statObj.hasOwnProperty('blocklist') ? '(' + statObj.blocklist + ')' : '';
                $('#' + id).append(
                    '<li class="list-group-item list-group-item-border">' +
                    idx + '. ' + domain + ' ' + bl +
                    '<span class="counter">'+ statObj.total +' (' + statObj.pcnt +'%)</span>' +
                    '</li>'
                )
                idx++;
            }
        }

        g_queryChart = null;

        /* Initial page load */
        function do_startup() {
            let def = new $.Deferred();
            ajaxGet('/api/unbound/overview/isEnabled', {}, function(is_enabled, status) {
                if (is_enabled.enabled == 0) {
                    def.reject();
                    return;
                }

                if (window.localStorage && window.localStorage.getItem("api.unbound.overview.timeperiod") !== null) {
                    $("#timeperiod").val(window.localStorage.getItem("api.unbound.overview.timeperiod"));
                }
                $('#timeperiod').selectpicker('refresh');

                ajaxGet('/api/unbound/overview/totals/10', {}, function(data, status) {
                    $('#totalCounter').html(data.total);
                    $('#blockedCounter').html(data.blocked.total + " (" + data.blocked.pcnt + "%)");
                    $('#cachedCounter').html(data.cached.total + " (" + data.cached.pcnt + "%)");
                    $('#localCounter').html(data.local.total + " (" + data.local.pcnt + "%)");

                    createTopList('top', data.top);
                    createTopList('top-blocked', data.top_blocked);

                    $('#top li:nth-child(even)').addClass('odd-bg');
                    $('#top-blocked li:nth-child(even)').addClass('odd-bg');

                    $('#bannersub').html("Starting from " + (new Date(data.start_time * 1000)).toLocaleString());

                    ajaxGet('/api/unbound/overview/rolling/' + $("#timeperiod").val(), {}, function(data, status) {
                        def.resolve();
                        let formatted = formatQueryData(data);
                        let stepSize = $("#timeperiod").val() == 1 ? 5 : 60;
                        g_queryChart = create_chart($("#rollingChart"), stepSize, formatted);
                    });
                });
            });

            return def;
        }

        $("#timeperiod").change(function() {
            if (window.localStorage) {
                window.localStorage.setItem("api.unbound.overview.timeperiod", $(this).val());
            }
            updateQueryChart();
        });

        do_startup().done(function() {
            $('.content-box').show();
        }).fail(function() {
            $('.content-box').hide();
            $('#info').show();
        });

        updateServiceControlUI('unbound');
    });

</script>

<div id="info" class="alert alert-warning" role="alert">
    {{ lang._('Local gathering of statistics is not enabled. Enable it in the Unbound General page.') }}
    <br />
    <a href="/services_unbound.php">{{ lang._('Go the Unbound configuration') }}</a>
</div>
<div class="content-box" style="margin-bottom: 10px;">
    <div id="counters" class="container-fluid">
        <div class="col-md-12">
            <h3 id="bannersub"></h3>
        </div>
        <div class="row" style="margin-bottom: 20px; margin-top: 20px;">
            <div class="banner col-xs-3 justify-content-center">
                <div class="stats-element">
                    <div class="stats-icon">
                        <i class="icon fa fa-cogs text-success" aria-hidden="true"></i>
                    </div>
                    <div class="stats-text">
                        <h2 id="totalCounter" class="stats-counter-text"></h2>
                        <p class="stats-inner-text">{{ lang._('Total')}}</p>
                    </div>
                </div>
            </div>
            <div class="banner col-xs-3 justify-content-center">
                <div class="stats-element">
                    <div class="stats-icon">
                        <i class="icon fa fa-ban text-danger" aria-hidden="true"></i>
                    </div>
                    <div class="stats-text">
                        <h2 id="blockedCounter" class="stats-counter-text"></h2>
                        <p class="stats-inner-text pull-right">{{ lang._('Blocked') }}</p>
                    </div>
                </div>
            </div>
            <div class="banner col-xs-3 justify-content-center">
                <div class="stats-element">
                    <div class="stats-icon">
                        <i class="icon fa fa-database text-primary" aria-hidden="true"></i>
                    </div>
                    <div class="stats-text">
                        <h2 id="cachedCounter" class="stats-counter-text"></h2>
                        <p class="stats-inner-text">{{ lang._('From Cache') }}</p>
                    </div>
                </div>
            </div>
            <div class="banner col-xs-3 justify-content-center">
                <div class="stats-element">
                    <div class="stats-icon">
                        <i class="icon fa fa-database text-info" aria-hidden="true"></i>
                    </div>
                    <div class="stats-text">
                        <h2 id="localCounter" class="stats-counter-text"></h2>
                        <p class="stats-inner-text">{{ lang._('From Local-data') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="content-box" style="margin-bottom: 10px;">
    <div id="graph" class="container-fluid">
        <div class="row d-flex justify-content-center">
            <div class="col-md-4"></div>
            <div class="col-md-4 text-center" style="padding: 10px;">
                <span id="qGraphTitle" style="padding: 5px;"><b>{{ lang._('Queries over the last ') }}</b></span>
                <select class="selectpicker" id="timeperiod" data-width="auto">
                    <option value="24">{{ lang._('24 Hours') }}</option>
                    <option value="12">{{ lang._('12 Hours') }}</option>
                    <option value="1">{{ lang._('1 Hour') }}</option>
                </select>
            </div>
            <div class="col-md-4"></div>
        </div>
        <div class="row">
            <div class="col-2"></div>
            <div class="col-8">
                <div class="chart-container">
                    <canvas id="rollingChart"></canvas>
                </div>
            </div>
            <div class="col-2"></div>
        </div>
    </div>
</div>
<div class="content-box">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <div class="top-list">
                    <ul class="list-group" id="top">
                      <li class="list-group-item list-group-item-border">
                        <b>{{ lang._('Top passed domains') }}</b>
                      </li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="top-list">
                    <ul class="list-group" id="top-blocked">
                      <li class="list-group-item list-group-item-border">
                        <b>{{ lang._('Top blocked domains') }}</b>
                      </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
