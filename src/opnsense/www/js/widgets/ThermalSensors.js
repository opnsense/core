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
    constructor() {
        super();

        this.chart = null;
        this.width = null;
        this.height = null;
        this.colors = [];
    }

    getMarkup() {
        return $(`
            <div class="${this.id}-chart-container" style="margin-left: 10px; margin-right: 10px;">
                <div class="canvas-container" style="position: relative;">
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
                    barPercentage: 0.8,
                    borderWidth: 1,
                    borderSkipped: false,
                    borderRadius: 20,
                    barThickness: 20
                },
                {
                    data: [],
                    backgroundColor: ['#E5E5E5'],
                    borderRadius: 20,
                    barPercentage: 0.5,
                    borderWidth: 1,
                    borderSkipped: true,
                    barThickness: 10,
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
                for (let i = 0; i < count; i++) {
                    const meta = chart.getDatasetMeta(0);
                    const xPos = meta.data[i].x;
                    const yPos = meta.data[i].y;
                    const barHeight = meta.data[i].height;

                    ctx.font = 'semibold 12px sans-serif';
                    ctx.fillStyle = '#ffffff';
                    ctx.fillText(`${data.datasets[0].data[i]}°C`, xPos - 50, yPos + barHeight / 4);
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
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        filter: function(tooltipItem) {
                            return tooltipItem.datasetIndex === 0;
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

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/diagnostics/system/systemTemperature');
        if (!data || !data.length) {
            $(`.${this.id}-chart-container`).html(`
                <a href="/system_advanced_misc.php">${this.translations.unconfigured}</a>
            `).css('margin', '2em auto')
            return;
        }
        let parsed = this._parseSensors(data);
        this._update(parsed);
    }

    _update(data = []) {
        if (!this.chart || data.length === 0) {
            return;
        }

        this.colors = new Array(data.length).fill(0);

        data.forEach((value, index) => {
            this.chart.data.labels[index] = `${value.type_translated} ${value.device_seq}`;
            this.chart.data.datasets[0].data[index] = Math.max(1, Math.min(100, value.temperature));
            this.chart.data.datasets[0].metadata[index] = value;
            this.chart.data.datasets[1].data[index] = 100 - value.temperature;
        });
        this.chart.canvas.parentNode.style.height = `${30 + (data.length * 30)}px`;
        this.chart.update();
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
