import BaseWidget from "./BaseWidget.js";

export default class TrafficWidget extends BaseWidget {
    constructor() {
        super();
        this.title = 'Traffic Statistics';

        this.chart = null;
        this.chart2 = null;
        this.interfaces = ['LAN', 'WAN'];
    }

    async getHtml() {
        let $div = $(`<div class="chart-container-trafficwidget"></div>`);
        $div.append($(`<div><h3>Traffic In</h3><div class="canvas-container"><canvas id="traffic-chart"></canvas></div></div>`));
        $div.append($(`<div><h3>Traffic Out</h3><div class="canvas-container"><canvas id="traffic-chart-two"></canvas></div></div>`));
        return $div;
    }

    async onWidgetTick() {
        let charts = [this.chart, this.chart2];
        // assignment necessary, we lose "this" in function scope
        let interfaces = this.interfaces;
        charts.forEach(function(c) {
            for (const intf of interfaces) {
                c.config.data.datasets.forEach(function(dataset) {
                    if (dataset.intf == intf) {
                        let min = 0;
                        let max = 1000000;
                        dataset.data.push({
                            x: Date.now(),
                            y: Math.floor(Math.random() * (max - min + 1) + min)
                        });
                        dataset.last_time = Date.now();
                        return;
                    }
                });
            }
            c.update('quiet');
        });
    }

    onWidgetClose() {
        this.chart.destroy();
        this.chart2.destroy();
    }

    async onMarkupRendered() {
        let $target = $('#traffic-chart');
        let $target_two = $('#traffic-chart-two');
        let ctx = $target[0].getContext('2d');
        let ctx2 = $target_two[0].getContext('2d');

        function set_alpha(color, opacity) {
            const op = Math.round(Math.min(Math.max(opacity || 1, 0), 1) * 255);
            return color + op.toString(16).toUpperCase();
        }

        function format_field(value) {
            if (!isNaN(value) && value > 0) {
                let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                let ndx = Math.floor(Math.log(value) / Math.log(1000) );
                if (ndx > 0) {
                    return  (value / Math.pow(1000, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                } else {
                    return value.toFixed(2);
                }
            } else {
                return "";
            }
        }
        let all_datasets = [];
        let i = 1;

        for (const intf of this.interfaces) {
            let colors = Chart.colorschemes.tableau.Classic10.length;
            let colorIdx = i - parseInt(i / colors) * colors;
            let color = Chart.colorschemes.tableau.Classic10[colorIdx];
            all_datasets.push({
                label: intf,
                borderColor: color,
                backgroundColor: set_alpha(color, 0.5),
                pointHoverBackgroundColor: color,
                pointHoverBorderColor: color,
                pointBackgroundColor: color,
                pointBorderColor: color,
                last_time: Date.now(),
                intf: intf,
                data: []
            });
            i++;
        };

        const config = {
            type: 'line',
            data: {
                datasets: all_datasets
            },
            options: {
                bezierCurve: false,
                maintainAspectRatio: false,
                scaleShowLabels: false,
                tooltipEvents: [],
                pointDot: false,
                scaleShowGridLines: true,
                responsive: true,
                elements: {
                    line: {
                        fill: true,
                        cubicInterpolationMode: 'monotone',
                        clip: 0
                    }
                  },
                scales: {
                    x: {
                        display: false,
                        time: {
                            tooltipFormat:'HH:mm:ss',
                            unit: 'second',
                            stepSize: 10,
                            minUnit: 'second',
                            displayFormats: {
                                second: 'HH:mm:ss',
                                minute: 'HH:mm:ss'
                            }
                        },
                        type: 'realtime',
                        realtime: {
                            duration: 20000,
                            refresh: 5000,
                            delay: 5000,
                        },
                    },
                    y: {
                        ticks: {
                            callback: function (value, index, values) {
                                return format_field(value);
                            }
                        }
                    }
                },
                hover: {
                    mode: 'nearest',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'nearest',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ": " + format_field(context.dataset.data[context.dataIndex].y).toString();
                            }
                        }
                      },
                    streaming: {
                        frameRate: 30
                    },
                    colorschemes: {
                        scheme: 'tableau.Classic10'
                    }
                }
            },
        };

        this.chart = new Chart(ctx, config);
        this.chart2 = new Chart(ctx2, config);
    }
}
