import BaseWidget from "./BaseWidget.js";

export default class Gateways extends BaseWidget {
    constructor() {
        super();
        this.title = 'Gateways';
    }

    onWidgetResize(elem, width, height) {
        if (width > 500) {
            $('.gateway-detail-container').show();
            $('.gateway-graph').show();
        } else {
            $('.gateway-detail-container').hide();
            $('.gateway-graph').hide();
        }
    }

    async getHtml() {
        let $container = $(`
<div class="gateways-container">
    <div class="flex-container">
        <div class="gateway-info"><i class="fa fa-circle text-success" style="margin-right: 5px;"></i>WAN_GW<br/><small>10.100.1.1</small></div>
        <div class="container gateway-detail-container">
            <div class="row flex-nowrap vertical-center-row">
                <div class="text-center col-xs-4 info-detail"> RTT: 0.3ms</div>
                <div class="text-center col-xs-4 info-detail"> RTTd: 0.0ms</div>
                <div class="text-center col-xs-4 info-detail"> Loss: 0.0%</div>
            </div>
        </div>
        <div class="gateway-info" style="margin-left: auto;">Uptime: 99.89%</div>
    </div>
    <div style="">
        <div class="gateway-graph" style="flex-grow: 1; height: 45px;">
                <canvas id="gateway-wan-gw"></canvas>
        </div>
    </div>
    <div class="flex-container">
        <div class="gateway-info"><i class="fa fa-circle text-success" style="margin-right: 5px;"></i>ADSL_PPPOE<br/><small>172.29.50.1</small></div>
        <div class="container gateway-detail-container">
            <div class="row flex-nowrap vertical-center-row">
                <div class="text-center col-xs-4 info-detail"> RTT: 0.3ms</div>
                <div class="text-center col-xs-4 info-detail"> RTTd: 0.0ms</div>
                <div class="text-center col-xs-4 info-detail"> Loss: 0.0%</div>
            </div>
        </div>
        <div class="gateway-info" style="margin-left: auto">Uptime: 99.89%</div>
    </div>
    <div style="">
        <div class="gateway-graph" style="flex-grow: 1; height: 45px;">
            <canvas id="gateway-adsl-pppoe"></canvas>
        </div>
    </div>
</div>
        `);
        return $container;
    }

    async onMarkupRendered() {
        let $target = $('#gateway-wan-gw');
        let $target_two = $('#gateway-adsl-pppoe');
        let ctx = $target[0].getContext('2d');
        let ctx2 = $target_two[0].getContext('2d');

        const matrix_data = {
            datasets: [{
              data: generateData(),
              backgroundColor({raw}) {
                //const alpha = (10 + raw.v) / 60;
                const alpha = 0.3;

                if (raw.v > 40) {
                    return `rgba(255, 0, 0, ${alpha})`;
                }
                if (raw.v > 30) {
                    return `rgba(255, 165, 0, ${alpha})`;
                }
                return `rgba(0, 128, 0, ${alpha})`;
              },
              borderColor({raw}) {
                //const alpha = (10 + raw.v) / 60;
                const alpha = 0.3;
                return `rgba(0, 128, 0, ${alpha})`;
              },
              borderWidth: 1,
              hoverBackgroundColor: 'yellow',
              hoverBorderColor: 'yellowgreen',
              width: ({chart}) => (chart.chartArea || {}).width / chart.scales.x.ticks.length - 3,
              height: ({chart}) => 10
            }]
          };

        const config = {
            type: 'matrix',
            data: matrix_data,
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: false,
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            title() {
                                return '';
                            },
                            label(context) {
                                const v = context.dataset.data[context.dataIndex];
                                return ['d: ' + v.d, 'v: ' + v.v.toFixed(2)];
                            }
                        }
                    },
                },
                layout: {
                    padding: {
                        top: 0,
                        bottom: 0
                    }
                },
                scales: {
                    y: {
                        display: false,
                        ticks: {
                            stepSize: 1,
                        },
                        grid: {
                            display: false,
                            drawBorder: false,
                        },
                    },
                    x: {
                        display: false,
                        ticks: {
                            stepSize: 1,
                            padding: 0
                        },
                        grid: {
                            display: false,
                            drawBorder: false,
                        }
                    }
                }
            }
        };

        let chart = new Chart(ctx, config);

        function generateData() {
            const data = [];
            let dt = moment().subtract(30, 'days').startOf('day'); // Subtract 6 days to include the last 7 days
            const end = moment();
            let i = 0;
            while (dt <= end) {
                const iso = dt.format('YYYY-MM-DD');
                data.push({
                    x: i,
                    y: 1,
                    d: iso,
                    v: Math.floor(Math.random() * 50)
                });
                dt.add(1, 'day');
                i++;
            }
        
            return data;
        }

        const config2 = {
            type: 'matrix',
            data: matrix_data,
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: false,
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            title() {
                                return '';
                            },
                            label(context) {
                                const v = context.dataset.data[context.dataIndex];
                                return ['d: ' + v.d, 'v: ' + v.v.toFixed(2)];
                            }
                        }
                    },
                },
                layout: {
                    padding: {
                        top: 0,
                        bottom: 0
                    }
                },
                scales: {
                    y: {
                        display: false,
                        ticks: {
                            stepSize: 1,
                        },
                        grid: {
                            display: false,
                            drawBorder: false,
                        },
                    },
                    x: {
                        display: false,
                        ticks: {
                            stepSize: 1,
                            padding: 0
                        },
                        grid: {
                            display: false,
                            drawBorder: false,
                        }
                    }
                }
            }
        };

        let chart2 = new Chart(ctx2, config2);
    }
}
