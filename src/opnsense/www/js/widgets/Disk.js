// endpoint:/api/core/system/systemDisk

/**
 *    Copyright (C) 2024 Deciso B.V.
 *
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

import BaseWidget from "./BaseWidget.js";

export default class Disk extends BaseWidget {
    constructor() {
        super();

        this.simple_chart = null;
        this.detailed_chart = null;
    }

    _convertToBytes(sizeString) {
        // intentionally multiply by 1000 to retain original data format
        const units = {
            'K': 1000,
            'M': 1000 * 1000,
            'G': 1000 * 1000 * 1000,
            'T': 1000 * 1000 * 1000 * 1000
        };

        const match = sizeString.match(/^(\d+(?:\.\d+)?)([BKMGT])$/i);

        if (!match) {
            throw new Error("Invalid size format");
        }

        const size = parseFloat(match[1]);
        const unit = match[2].toUpperCase();

        if (!units[unit]) {
            throw new Error("Invalid unit");
        }

        return size * units[unit];
    }

    getMarkup() {
        return $(`
            <div class="disk-chart-container">
                <div class="canvas-container">
                    <canvas id="disk-chart"></canvas>
                </div>
                <div class="canvas-container">
                    <canvas id="disk-detailed-chart"></canvas>
                </div>
                </div>
            </div>
        `);
    }

    async onMarkupRendered() {
        let context_simple = document.getElementById("disk-chart").getContext("2d");
        let colorMap = ['#D94F00', '#E5E5E5'];
        let config_simple = {
            type: 'doughnut',
            data: {
                labels: [this.translations.used, this.translations.free],
                datasets: [
                    {
                        data: [],
                        backgroundColor: colorMap,
                        hoverBackgroundColor: colorMap.map((color) => this._setAlpha(color, 0.5)),
                        hoveroffset: 50,
                        fill: true
                    },
              ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                layout: {
                    padding: 10
                },
                cutout: '64%',
                rotation: 270,
                circumference: 180,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (tooltipItem) => {
                                let pct = tooltipItem.dataset.pct[tooltipItem.dataIndex];
                                return `${tooltipItem.label}: ${pct}%`;
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'custom_positioned_text',
                beforeDatasetsDraw: (chart, args, options) => {
                    // custom plugin: draw text at 2/3 y position of chart
                    if (chart.config.data.datasets[0].data.length !== 0) {
                        let width = chart.width;
                        let height = chart.height;
                        let ctx = chart.ctx;
                        ctx.restore();
                        let fontSize = (height / 114).toFixed(2);
                        ctx.font = fontSize + "em SourceSansProSemibold";
                        ctx.textBaseline = "middle";
                        let text = this.simple_chart.config.data.datasets[0].pct[0] + '%';
                        let textX = Math.round((width - ctx.measureText(text).width) / 2);
                        let textY = (height / 3) * 2;
                        ctx.fillText(text, textX, textY);
                        ctx.save();
                    }
                }
            }]
        }

        this.simple_chart = new Chart(context_simple, config_simple);
        let context_detailed = document.getElementById("disk-detailed-chart").getContext("2d");
        let config = {
            type: 'bar',
            data: {
                labels: [],
                types: [],
                datasets: [
                    {
                        // used
                        data: [],
                        backgroundColor: ['#D94F00'],
                        hoverBackgroundColor: [this._setAlpha('#D94F00', 0.5)],
                        hoveroffset: 50,
                        fill: false,
                        descr: this.translations.used
                    },
                    {
                        // free
                        data: [],
                        backgroundColor: ['#E5E5E5'],
                        hoverBackgroundColor: [this._setAlpha('#E5E5E5', 0.5)],
                        hoveroffset: 50,
                        fill: false,
                        descr: this.translations.free
                    },
              ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                layout: {
                    padding: 10
                },
                scales: {
                    x: {
                        stacked: true,
                        display: false,
                    },
                    y: {
                        stacked: true,
                    }
                },
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: (tooltipItem) => {
                                let type = this.detailed_chart.config.data.types[tooltipItem[0].dataIndex];
                                return `${tooltipItem[0].label} [${type}]`;
                            },
                            label: (tooltipItem) => {
                                return `${tooltipItem.dataset.descr}: ${this._formatBytes(tooltipItem.raw)}`;
                            }
                        }
                    }
                }
            },
        }

        this.detailed_chart = new Chart(context_detailed, config);
    }

    async onWidgetTick() {
        ajaxGet('/api/core/system/systemDisk', {}, (data, status) => {
            if (data.devices !== undefined) {
                let set = this.detailed_chart.config.data;
                let init = set.labels.length === 0;
                this.detailed_chart.config.data.datasets[0].data = [];
                this.detailed_chart.config.data.datasets[1].data = [];
                let totals = [];
                for (const device of data.devices) {
                    let used = this._convertToBytes(device.used);
                    let total = this._convertToBytes(device.blocks);
                    let free = total - used;
                    if (device.mountpoint === '/') {
                        this.simple_chart.config.data.datasets[0].data = [used, free];
                        this.simple_chart.config.data.datasets[0].pct = [device.used_pct, (100 - device.used_pct)];
                        this.simple_chart.update();
                    }
                    totals.push(total);

                    if (init) {
                        this.detailed_chart.config.data.types.push(device.type);
                        this.detailed_chart.config.data.labels.push(device.mountpoint);
                    }
                    this.detailed_chart.config.data.datasets[0].data.push(used);
                    this.detailed_chart.config.data.datasets[1].data.push(free);
                }

                this.detailed_chart.config.options.scales.x.max = Math.max(...totals);
                this.detailed_chart.update();
            }
        });
    }

    onWidgetResize(elem, width, height) {
        if (width < 500) {
            $('#disk-chart').show();
            $('#disk-detailed-chart').hide();
        } else {
            $('#disk-chart').hide();
            $('#disk-detailed-chart').show();
        }
    }

}
