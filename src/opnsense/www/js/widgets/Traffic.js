/*
 * Copyright (C) 2024 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

export default class Traffic extends BaseWidget {
    constructor(config) {
        super(config);

        this.charts = {
            trafficIn: null,
            trafficOut: null
        };
        this.initialized = false;
        this.datasets = {inbytes: [], outbytes: []};
        this.configurable = true;
        this.configChanged = false;
    }

    _set_alpha(color, opacity) {
        const op = Math.round(Math.min(Math.max(opacity || 1, 0), 1) * 255);
        return color + op.toString(16).toUpperCase();
    }

    _chartConfig(dataset) {
        return {
            type: 'line',
            data: {
                datasets: dataset
            },
            options: {
                bezierCurve: false,
                maintainAspectRatio: false,
                scaleShowLabels: false,
                tooltipEvents: [],
                pointDot: true,
                scaleShowGridLines: true,
                responsive: true,
                normalized: true,
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
                            delay: 2000,
                        },
                    },
                    y: {
                        ticks: {
                            callback: (value, index, values) => {
                                return this._formatBits(value);
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
                            label: (context) => {
                                return context.dataset.label + ": " + this._formatBits(context.dataset.data[context.dataIndex].y).toString();
                            }
                        }
                      },
                    streaming: {
                        frameRate: 30,
                        ttl: 30000
                    },
                    colorschemes: {
                        scheme: 'tableau.Classic10'
                    }
                }
            },
        };
    }

    async _initialize(data) {
        const config = await this.getWidgetConfig();
        this.datasets = {inbytes: [], outbytes: []};
        for (const dir of ['inbytes', 'outbytes']) {
            let colors = Chart.colorschemes.tableau.Classic10;
            let i = 0;
            Object.keys(data.interfaces).forEach((intf) => {
                let idx = i % colors.length;
                i++;
                this.datasets[dir].push({
                    label: data.interfaces[intf].name,
                    hidden: !config.interfaces.includes(intf),
                    borderColor: colors[idx],
                    backgroundColor: this._set_alpha(colors[idx], 0.5),
                    pointHoverBackgroundColor: colors[idx],
                    pointHoverBorderColor: colors[idx],
                    pointBackgroundColor: colors[idx],
                    pointBorderColor: colors[idx],
                    pointRadius: 0,
                    intf: intf,
                    last_time: data.time,
                    src_field: dir,
                    data: [],
                });
            });
        }

        this.charts.trafficIn = new Chart($('#traffic-in')[0].getContext('2d'), this._chartConfig(this.datasets.inbytes));
        this.charts.trafficOut = new Chart($('#traffic-out')[0].getContext('2d'), this._chartConfig(this.datasets.outbytes));
    }

    async _onMessage(event) {
        if (!event) {
            super.closeEventSource();
        }

        const data = JSON.parse(event.data);

        if (!this.initialized) {
            await this._initialize(data);
            this.initialized = true;
        }

        let config = null;
        if (this.configChanged) {
            config = await this.getWidgetConfig();
        }

        for (let chart of Object.values(this.charts)) {
            Object.keys(data.interfaces).forEach((intf) => {
                chart.config.data.datasets.forEach((dataset) => {
                    if (dataset.intf === intf) {
                        let elapsed_time = data.time - dataset.last_time;
                        if (this.configChanged) {
                            // check hidden status of dataset
                            dataset.hidden = !config.interfaces.includes(intf);
                        }
                        dataset.data.push({
                            x: Date.now(),
                            y: Math.round(((data.interfaces[intf][dataset.src_field]) / elapsed_time) * 8, 0)
                        });
                        dataset.last_time = data.time;
                        return;
                    }
                });
            });
            chart.update('quiet');
        }

        if (this.configChanged) {
            this.configChanged = false;
        }
    }

    getMarkup() {
        return $(`
            <div class="traffic-charts-container">
                <h3>${this.translations.trafficin}</h3>
                <div class="canvas-container-noaspectratio">
                    <canvas id="traffic-in"></canvas>
                </div>
                <h3>${this.translations.trafficout}</h3>
                <div class="canvas-container-noaspectratio">
                    <canvas id="traffic-out"></canvas>
                </div>
            </div>
        `);
    }

    async onMarkupRendered() {
        super.openEventSource(`/api/diagnostics/traffic/stream/${'1'}`, this._onMessage.bind(this));
    }

    async getWidgetOptions() {
        const data = await this.ajaxCall('/api/diagnostics/traffic/interface');

        const interfaces = Object.entries(data.interfaces).map(([key, intf]) => {
            return [key, intf.name]
        });

        return {
            interfaces: {
                title: this.translations.interfaces,
                type: 'select_multiple',
                options: interfaces.map(([key,intf]) => {
                    return {
                        value: key,
                        label: intf,
                    };
                }),
                default: interfaces
                    .filter(([key]) => key === 'lan' || key === 'wan')
                    .map(([key]) => (key))
            }
        };
    }

    async onWidgetOptionsChanged(options) {
        this.configChanged = true;
    }

    onWidgetClose() {
        super.onWidgetClose();
        if (this.charts.trafficIn !== null) {
            this.charts.trafficIn.destroy();
        }
        if (this.charts.trafficOut !== null) {
            this.charts.trafficOut.destroy();
        }
    }
}
