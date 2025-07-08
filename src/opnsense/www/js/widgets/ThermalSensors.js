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

export default class ThermalSensors extends BaseWidget {
    constructor(config) {
        super(config);

        this.chart = null;
        this.width = null;
        this.height = null;
        this.colors = [];

        this.configurable = true;
        this.cachedSensors = []; // prevent fetch when loading options
    }

    getMarkup() {
        return $(`
            <div class="${this.id}-chart-container" style="margin-left: 10px; margin-right: 10px;">
                <div class="canvas-container-noaspectratio">
                    <canvas id="${this.id}-chart"></canvas>
                </div>
            </div>
        `);
    }

    async onMarkupRendered() {
        let context = document.getElementById(`${this.id}-chart`).getContext("2d");

        const data = {
            datasets: [
                {
                    data: [],
                    metadata: [],
                    backgroundColor: (context) => {
                        const {chartArea} = context.chart;

                        if (!chartArea || this.colors.length === 0) {
                            return;
                        }

                        let dataIndex = context.dataIndex;
                        let value = parseInt(context.raw);
                        if (value >= 80) {
                            this.colors[dataIndex] = '#dc3545';
                        } else if (value >= 70) {
                            this.colors[dataIndex] = '#ffc107';
                        } else {
                            this.colors[dataIndex] = '#28a745';
                        }
                        return this.colors;
                    },
                    categoryPercentage: 1.0,
                    barPercentage: 0.8,
                    borderWidth: 1,
                    borderSkipped: false,
                    borderRadius: 20,
                },
                {
                    categoryPercentage: 1.0,
                    data: [],
                    backgroundColor: ['#E5E5E5'],
                    borderRadius: 20,
                    barPercentage: 0.5,
                    borderWidth: 1,
                    borderSkipped: true,
                    pointHitRadius: 0
                }
            ]
        }

        const lines = {
            id: 'lines',
            afterDatasetsDraw: (chart, args, plugins) => {
                const {ctx, data, chartArea} = chart;
                if (data.datasets[0].data.length === 0) {
                    return;
                }
                let count = data.datasets[0].data.length;
                ctx.save();

                const margin = 10;
                ctx.textBaseline = 'middle';
                for (let i = 0; i < count; i++) {
                    const meta = chart.getDatasetMeta(0);
                    const xPos = meta.data[i].x;
                    const yPos = meta.data[i].y;
                    const barHeight = meta.data[i].height;

                    const textX = Math.max(chartArea.left + margin, xPos - 50);

                    ctx.font = 'semibold 12px sans-serif';
                    ctx.fillStyle = '#ffffff';
                    ctx.fillText(`${data.datasets[0].data[i]}°C`, textX, yPos);
                }
                ctx.restore();
            }
        }

        const config = {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: {
                    colorschemes: false,
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        filter: function(tooltipItem) {
                            return tooltipItem.datasetIndex === 0;
                        },
                        bodyFont: {
                           size: 9
                        },
                        callbacks: {
                            label: (tooltipItem) => {
                                let idx = tooltipItem.dataIndex;
                                if (!tooltipItem.dataset.metadata) {
                                    return;
                                }
                                let meta = tooltipItem.dataset.metadata[idx];
                                return `${meta.device}: ${meta.temperature}°C / ${meta.temperature_fahrenheit}°F`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        grid: {
                            display: false,
                            drawBorder: false,
                        },
                        ticks: {
                            display: false,
                        }
                    },
                    y: {
                        autoSkip: false,
                        stacked: true,
                        grid: {
                            display: false,
                            drawBorder: false,
                        },
                        ticks: {
                            autoSkip: false,
                        }
                    }
                }
            },
            plugins: [
                lines
            ]
        }

        this.chart = new Chart(context, config);

        $(`#${this.id}-title`).append(`&nbsp;<i class="fa fa-question-circle thermalsensors-info-icon" data-toggle="tooltip" title="${this.translations.help}"></i>`);
        $('.thermalsensors-info-icon').tooltip({container: 'body'});
    }

    async onWidgetOptionsChanged(options) {
        // Intentionally not awaited to avoid blocking dialog close
        this._updateSensors();
    }

    async getWidgetOptions() {
        const data = this.cachedSensors.length > 0 ? this.cachedSensors : await this._fetchSensors();

        return {
            sensors: {
                title: this.translations.title,
                type: 'select_multiple',
                options: data.map(({ device, device_seq, type_translated }) => {
                    return {
                        value: device,
                        label: `${type_translated} ${device_seq}`,
                    };
                }),
                default:data.map(({ device }) => device),
            },
        };
    }

    async onWidgetTick() {
        const data = await this._fetchSensors();
        this.cachedSensors = this._parseSensors(data);
        this._updateSensors();
    }

    async _fetchSensors() {
        const data = await this.ajaxCall('/api/diagnostics/system/system_temperature');
        return data;
    }

    async _updateSensors() {
        const data = this.cachedSensors;

        if (!this.chart || data.length === 0) {
            $(`.${this.id}-chart-container`).html(`
                <a href="/system_advanced_misc.php">${this.translations.unconfigured}</a>
            `).css('margin', '2em auto')
            return;
        }

        const config = await this.getWidgetConfig();

        this.colors = new Array(data.length).fill(0);

        this.chart.data.labels = [];
        this.chart.data.datasets[0].data = [];
        this.chart.data.datasets[0].metadata = [];
        this.chart.data.datasets[1].data = [];

        data.forEach((value) => {
            if (!config.sensors.includes(value.device)) {
                return;
            }
            this.chart.data.labels.push(`${value.type_translated} ${value.device_seq}`);
            this.chart.data.datasets[0].data.push(Math.max(1, Math.min(100, value.temperature)));
            this.chart.data.datasets[0].metadata.push(value);
            this.chart.data.datasets[1].data.push(100 - value.temperature);
        });
        this.chart.canvas.parentNode.style.height = `${30 + (this.chart.data.datasets[0].data.length * 30)}px`;
        this.chart.update();

        // Since we are modifying the chart height based on the data length,
        // make sure we force the manager to recalculate the widget size.
        this.config.callbacks.updateGrid();
    }

    _parseSensors(data) {
        const toFahrenheit = (celsius) => (celsius * 9 / 5) + 32;
        data.forEach(item => {
            item.temperature_fahrenheit = toFahrenheit(parseFloat(item.temperature)).toFixed(1);
        });

        return data;
    }

    onWidgetClose() {
        if (this.chart !== null) {
            this.chart.destroy();
        }
    }
}
