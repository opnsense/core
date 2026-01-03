/*
 * Copyright (C) 2025 Deciso B.V.
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


class HealthGraph {
    constructor(canvasId, chartConfig = {}, datasetOptions = {}) {
        this.ctx = $(`#${canvasId}`)[0].getContext('2d');
        this.chartConfig = { ...this._defaultChartOptions(), ...chartConfig };
        this.datasetOptions = { ...this._defaultDatasetOptions(), ...datasetOptions };
        this.chart = null;
        this.rrdList = null;

        this.currentSystem = null;
        this.currentDetailLevel = 0;
        this.currentStacked = false;
    }

    async initialize() {
        this.rrdList = await this._fetchRRDList();
        if (Object.keys(this.rrdList.data).length === 0) {
            throw new Error('No RRD data available');
        }

        const firstKey = Object.keys(this.rrdList.data)[0];
        const firstValue = this.rrdList.data[firstKey]?.[0] || "";
        this.currentSystem = `${firstValue}-${firstKey}`;

        this.chart = new Chart(this.ctx, this.chartConfig);
    }

    getRRDList() {
        return this.rrdList;
    }

    async update(system = null, detailLevel = null, stacked = null) {
        if (system === null) {
            system = this.currentSystem;
        } else {
            this.currentSystem = system;
        }
        if (detailLevel === null) {
            detailLevel = this.currentDetailLevel;
        } else {
            this.currentDetailLevel = detailLevel;
        }
        if (stacked === null) {
            stacked = this.currentStacked;
        } else {
            this.currentStacked = stacked;
        }

        const data = await this._fetchData();
        const formatted = this._formatData(data.set);

        const stepSize = data.set.step_size;

        this.chart.data.datasets = formatted;
        this.chart.options.scales.y.title.text = data['y-axis_label'];
        this.chart.options.scales.y.stacked = stacked;
        this.chart.options.plugins.title.text = data['title'];
        this.chart.options.plugins.zoom.limits.x.minRange = this._getMinRange(stepSize * 1000);
        this.chart.update();
        this.chart.resetZoom();
    }

    resetZoom() {
        this.chart.resetZoom();
    }

    exportData() {
        window.open(`/api/diagnostics/systemhealth/export_as_csv/${this.currentSystem}/${this.currentDetailLevel}`);
    }

    async _fetchRRDList() {
        const list = await fetch(`/api/diagnostics/systemhealth/get_rrd_list`).then(response => response.json());
        return list;
    }

    async _fetchData() {
        const data = await fetch(`/api/diagnostics/systemhealth/get_system_health/${this.currentSystem}/${this.currentDetailLevel}`)
            .then(response => response.json());
        return data;
    }

    _getMinRange(stepSize) {
        const ONE_MINUTE = 60 * 1000;
        const FIVE_MINUTES = 5 * ONE_MINUTE;
        const ONE_HOUR = 60 * ONE_MINUTE;
        const ONE_DAY = 24 * ONE_HOUR;

        if (stepSize <= ONE_MINUTE) {
            return 5 * ONE_MINUTE;
        } else if (stepSize <= FIVE_MINUTES) {
            return 30 * ONE_MINUTE;
        } else if (stepSize <= ONE_HOUR) {
            return 6 * ONE_HOUR;
        } else if (stepSize <= ONE_DAY) {
            return 7 * ONE_DAY;
        } else {
            return 30 * ONE_DAY;
        }
    }

    _formatData(data) {
        let datasets = [];
        for (const item of data.data) {
            const dataset = {
                ...this.datasetOptions,
                label: item.key,
                data: []
            };
            for (const [x, y] of item.values) {
                // timestack formatter expects array of x,y objects
                dataset.data.push({ x: x, y: y });
            }
            datasets.push(dataset);
        }
        return datasets;
    }

    _defaultChartOptions() {
        const verticalHoverLine = {
            id: 'verticalHoverLine',
            beforeDatasetsDraw(chart, args, pluginOptions) {
                if (chart.getDatasetMeta(0).data.length == 0) {
                    // data may not have loaded yet
                    return;
                }

                const { ctx, chartArea: { top, bottom } } = chart;
                ctx.save();

                chart.getDatasetMeta(0).data.forEach((dataPoint, index) => {
                    if (dataPoint.active === true) {
                        ctx.beginPath();
                        ctx.strokeStyle = 'gray'; // XXX theming concern
                        ctx.moveTo(dataPoint.x, top);
                        ctx.lineTo(dataPoint.x, bottom);
                        ctx.stroke();
                    }
                });
            }
        };

        const config = {
            type: 'line',
            data: {},
            options: {
                normalized: true,
                responsive: true,
                maintainAspectRatio: false,
                parsing: false,
                animation: {
                    duration: 500,
                },
                scales: {
                    x: {
                        type: 'timestack'
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            padding: 8
                        },
                        ticks: {
                            callback: function(value, index, ticks) {
                                const kb = 1000;
                                const ndx = value === 0 ? 0 : Math.floor(Math.log(Math.abs(value)) / Math.log(kb));
                                const fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                                if (Math.abs(value) > 1000 && fileSizeTypes[ndx]) {
                                    return (value / Math.pow(kb, ndx)).toFixed(0) + ' ' + fileSizeTypes[ndx];
                                } else {
                                    return value;
                                }
                            }
                        }
                    },
                },
                plugins: {
                    zoom: {
                        limits: {
                            x: { min: 'original', max: 'original', minRange: 1800 * 1000 },
                        },
                        pan: {
                            enabled: true,
                            mode: 'x',
                            modifierKey: 'ctrl',
                        },
                        zoom: {
                            wheel: {
                                speed: 0.3,
                                enabled: true,
                            },
                            drag: {
                                enabled: true,
                            },
                            pinch: {
                                enabled: true
                            },
                            mode: 'x',
                        }
                    },
                    title: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        caretPadding: 15,
                    },
                },
                transitions: {
                    zoom: {
                        animation: {
                            duration: 500
                        }
                    }
                },
                interaction: {
                    // complementary to the vertical line on hover plugin
                    mode: 'index',
                    intersect: false
                }
            },
            plugins: [verticalHoverLine]
        };

        return config;
    }

    _defaultDatasetOptions() {
        return {
            spanGaps: true,
            pointRadius: 0,
            pointHoverRadius: 7,
            borderWidth: 1,
            stepped: true,
            pointHoverBackgroundColor: (ctx) => ctx.element.options.borderColor,
        }
    }

}
