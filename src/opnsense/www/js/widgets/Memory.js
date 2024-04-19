// endpoint:/api/core/system/systemResources

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

export default class Memory extends BaseWidget {
    constructor() {
        super();
        this.tickTimeout = 15000;

        this.chart = null;
        this.curMemUsed = null;
        this.curMemTotal = null;
    }

    getMarkup() {
        return $(`
            <div class="memory-chart-container">
                <div class="canvas-container">
                    <canvas id="memory-chart"></canvas>
                </div>
            </div>
        `);
    }

    async onMarkupRendered() {
        let context = document.getElementById("memory-chart").getContext("2d");
        let colorMap = ['#D94F00', '#A8C49B', '#E5E5E5'];

        let config = {
            type: 'doughnut',
            data: {
                labels: [this.translations.used, this.translations.arc, this.translations.free],
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
                                return `${tooltipItem.label}: ${tooltipItem.parsed} MB`;
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
                        let text = (this.curMemUsed / this.curMemTotal * 100).toFixed(2) + "%";
                        let textX = Math.round((width - ctx.measureText(text).width) / 2);
                        let textY = (height / 3) * 2;
                        ctx.fillText(text, textX, textY);
                        ctx.save();
                    }
                }
            }]
        }

        this.chart = new Chart(context, config);
    }

    async onWidgetTick() {
        ajaxGet('/api/core/system/systemResources', {}, (data, status) => {
            if (data.memory.total !== undefined) {
                let used = parseInt(data.memory.used_frmt);
                let arc = data.memory.hasOwnProperty('arc') ? parseInt(data.memory.arc_frmt) : 0;
                let total = parseInt(data.memory.total_frmt);
                let result = [(used - arc), arc, total - used];    
                this.chart.config.data.datasets[0].data = result

                this.curMemUsed = used - arc;
                this.curMemTotal = total;
                this.chart.update();
            }
        });
    }
}
