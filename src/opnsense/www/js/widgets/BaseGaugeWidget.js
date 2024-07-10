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

import BaseWidget from "./BaseWidget.js";

export default class BaseGaugeWidget extends BaseWidget {
    constructor() {
        super();

        this.chart = null;
    }

    getMarkup() {
        return $(`
            <div class="${this.id}-chart-container">
                <div class="canvas-container">
                    <canvas id="${this.id}-chart"></canvas>
                </div>
            </div>
        `);
    }

    getGridOptions() {
        return {
            minW: 1
        };
    }

    createGaugeChart(options) {
        let _options = {
            colorMap: ['#D94F00', '#E5E5E5'],
            labels: [],
            tooltipLabelCallback: (tooltipItem) => {
                return `${tooltipItem.label}: ${tooltipItem.parsed}`;
            },
            primaryText: (data) => {
                return `${(data[0] / (data[0] + data[1]) * 100).toFixed(2)}%`;
            },
            secondaryText: (data) => false,
            ...options
        }


        let context = document.getElementById(`${this.id}-chart`).getContext("2d");
        let config = {
            type: 'doughnut',
            data: {
                labels: _options.labels,
                datasets: [
                    {
                        data: [],
                        backgroundColor: _options.colorMap,
                        hoverBackgroundColor: _options.colorMap.map((color) => this._setAlpha(color, 0.5)),
                        hoverOffset: 10,
                        fill: true

                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                cutout: '60%',
                rotation: 270,
                circumference: 180,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: _options.tooltipLabelCallback
                        }
                    }
                }
            },
            plugins: [{
                id: 'custom_positioned_text',
                beforeDatasetsDraw: (chart, _, __) => {
                    let data = chart.config.data.datasets[0].data;
                    if (data.length !== 0) {
                        let width = chart.width;
                        let height = chart.height;
                        let ctx = chart.ctx;
                        ctx.restore();

                        let divisor = 60;
                        let primaryText = _options.primaryText(data, chart);
                        let secondaryText = _options.secondaryText(data, chart);

                        let fontSize = (height / divisor).toFixed(2);
                        ctx.font = fontSize + "em SourceSansProSemiBold";
                        ctx.textBaseline = "middle";
                        ctx.fillStyle = Chart.defaults.color;

                        let textX = Math.round((width - ctx.measureText(primaryText).width) / 2);
                        let textY = (height * (secondaryText ? 0.70 : 0.75));

                        ctx.fillText(primaryText, textX, textY);

                        if (secondaryText) {
                            fontSize = (height / 90).toFixed(2);
                            ctx.font = fontSize + "em SourceSansProRegular";
                            ctx.textBaseline = "middle";
                            ctx.fillStyle = Chart.defaults.color;

                            let textBX = Math.round((width - ctx.measureText(secondaryText).width) / 2);
                            let textBY = height * 0.90;
                            ctx.fillText(secondaryText, textBX, textBY);
                        }

                        ctx.save();
                    }
                }
            }]
        }

        this.chart = new Chart(context, config);
    }

    updateChart(data) {
        if (this.chart) {
            this.chart.data.datasets[0].data = data;
            this.chart.update();
        }
    }

    onWidgetClose() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
}
