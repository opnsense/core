// endpoint:/api/diagnostics/firewall/pf_states

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

export default class FirewallStates extends BaseWidget {
    constructor() {
        super();

        this.chart = null;
        this.current = null;
        this.limit = null;
    }

    getMarkup() {
        return $(`
            <div class="fw-states-chart-container">
                <div class="canvas-container">
                    <canvas id="fw-states-chart"></canvas>
                </div>
            </div>
        `);
    }

    async onMarkupRendered() {
        let context = document.getElementById("fw-states-chart").getContext("2d");
        let colorMap = ['#D94F00', '#E5E5E5'];
        let config = {
            type: 'doughnut',
            data: {
                labels: [this.translations.current, this.translations.limit],
                datasets: [
                    {
                        data: [],
                        backgroundColor: colorMap,
                        hoverBackgroundColor: colorMap.map((color) => this._setAlpha(color, 0.5)),
                        fill: true
                    }
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
                                return `${tooltipItem.label}: ${tooltipItem.parsed}`;
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'custom_positioned_text',
                beforeDatasetsDraw: (chart, args, options) => {
                    if (chart.config.data.datasets[0].data.length !== 0) {
                        let width = chart.width;
                        let height = chart.height;
                        let ctx = chart.ctx;
                        ctx.restore();

                        let percentage = (this.current / this.limit * 100).toFixed(2);

                        let fontSize = (height / (percentage < 1 ? 135 : 114)).toFixed(2);
                        ctx.font = fontSize + "em SourceSansProSemiBold";
                        ctx.textBaseline = "middle";

                        let text = `${percentage} % `;
                        let textX = Math.round((width - ctx.measureText(text).width) / 2);
                        let textY = (height * 0.66);
                        ctx.fillText(text, textX, textY);

                        if (percentage < 1) {
                            let textB = `(${this.current} / ${this.limit})`;
                            let textBX = Math.round((width - ctx.measureText(textB).width) / 2);
                            let textBY = height * 0.85;
                            ctx.fillText(textB, textBX, textBY);
                        }
                        ctx.save();
                    }
                }
            }]
        }

        this.chart = new Chart(context, config);
    }

    async onWidgetTick() {
        ajaxGet('/api/diagnostics/firewall/pf_states', {}, (data, status) => {
            this.current = parseInt(data.current);
            this.limit = parseInt(data.limit);
            this.chart.config.data.datasets[0].data = [this.current, (this.limit - this.current)];
            this.chart.update();
        });
    }

    onWidgetClose() {
        if (this.chart !== null) {
            this.chart.destroy();
        }
    }
}
