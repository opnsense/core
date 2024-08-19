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

export default class InterfaceStatistics extends BaseTableWidget {
    constructor() {
        super();

        this.chart = null;
        this.labels = [];
        this.rawData = {};
        this.dataset = {};
        this.sortedLabels = [];
        this.sortedData = [];
    }

    getMarkup() {
        let $container = $('<div id="if-stats-container"></div>');
        let $table = this.createTable('interface-statistics-table', {
            headerPosition: 'top',
            headers: [
                this.translations.interface,
                this.translations.bytesin,
                this.translations.bytesout,
                this.translations.packetsin,
                this.translations.packetsout,
                this.translations.errorsin,
                this.translations.errorsout,
                this.translations.collisions
            ]
        });
        let $chartContainer = $(`
            <div class="interface-statistics-chart-container">
                <div class="canvas-container">
                    <canvas id="intf-stats"></canvas>
                </div>
            </div>`
        );

        $container.append($table);
        $container.append($chartContainer);
        return $container;
    }

    _getIndexedData(data) {
        let indexedData = Array(this.labels.length).fill(null);
        let indexedColors = Array(this.labels.length).fill(null);
        for (const item in data) {
            let obj = data[item];
            let idx = this.labels.indexOf(obj.name);
            indexedData[idx] = obj.data;
            indexedColors[idx] = obj.color;
        }
        return {
            data: indexedData,
            colors: indexedColors
        };
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/diagnostics/traffic/interface');

        for (const [id, obj] of Object.entries(data.interfaces)) {
            super.updateTable('interface-statistics-table', [
                [
                    $(`<a href="/interfaces.php?if=${id}">${obj.name}</a>`).prop('outerHTML'),
                    this._formatBytes(parseInt(obj["bytes received"])) || "0",
                    this._formatBytes(parseInt(obj["bytes transmitted"])) || "0",
                    parseInt(obj["packets received"]).toLocaleString(),
                    parseInt(obj["packets transmitted"]).toLocaleString(),
                    parseInt(obj["input errors"]).toLocaleString(),
                    parseInt(obj["output errors"]).toLocaleString(),
                    parseInt(obj["collisions"]).toLocaleString()
                ]
            ], id);
        }

        let sortedSet = {};
        let i = 0;
        let colors = Chart.colorschemes.tableau.Classic10;
        for (const intf in data.interfaces) {
            const obj = data.interfaces[intf];
            this.labels.indexOf(obj.name) === -1 && this.labels.push(obj.name);
            obj.data = parseInt(obj["packets received"]) + parseInt(obj["packets transmitted"]);
            obj.color = colors[i % colors.length]
            this.rawData[obj.name] = obj;

            sortedSet[i] = {'name': obj.name, 'data': obj.data};
            i++;
        }

        this.sortedLabels = [];
        this.sortedData = [];
        Object.values(sortedSet).sort((a, b) => b.data - a.data).forEach(item => {
            this.sortedLabels.push(item.name);
            this.sortedData.push(item.data);
        });

        let formattedData = this._getIndexedData(data.interfaces);
        this.dataset = {
            label: 'statistics',
            data:  formattedData.data,
            backgroundColor: formattedData.colors,
            hoverBackgroundColor: formattedData.colors.map((color) => this._setAlpha(color, 0.5)),
            fill: true,
            borderWidth: 2,
            hoverOffset: 10,
        }

        if (this.chart.config.data.datasets.length > 0) {
            this.chart.config.data.datasets[0].data = this.dataset.data;
        } else {
            this.chart.config.data.labels = this.labels;
            this.chart.config.data.datasets.push(this.dataset);
        }

        this.chart.update();
    }

    async onMarkupRendered() {
        let context = $(`#intf-stats`)[0].getContext('2d');

        let config = {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                cutout: '40%',
                maintainAspectRatio: true,
                responsive: true,
                aspectRatio: 2,
                layout: {
                    padding: 10
                },
                normalized: true,
                parsing: false,
                plugins: {
                    legend: {
                        display: false,
                        position: 'left',
                        title: 'Traffic',
                        onHover: (event, legendItem) => {
                            const activeElement = {
                              datasetIndex: 0,
                              index: legendItem.index
                            };
                            this.chart.setActiveElements([activeElement]);
                            this.chart.tooltip.setActiveElements([activeElement]);
                            this.chart.update();
                        },
                        labels: {
                            sort: (a, b, chartData) => {
                                return this.sortedLabels.indexOf(a.text) - this.sortedLabels.indexOf(b.text);
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                           label: (tooltipItem) => {
                                let obj = this.rawData[tooltipItem.label];
                                let result = [
                                    `${tooltipItem.label}`,
                                    `${this.translations.bytesin}: ${byteFormat(parseInt(obj["bytes received"]))}`,
                                    `${this.translations.bytesout}: ${byteFormat(parseInt(obj["bytes transmitted"]))}`,
                                    `${this.translations.packetsin}: ${parseInt(obj["packets received"]).toLocaleString()}`,
                                    `${this.translations.packetsout}: ${parseInt(obj["packets transmitted"]).toLocaleString()}`,
                                    `${this.translations.errorsin}: ${parseInt(obj["input errors"]).toLocaleString()}`,
                                    `${this.translations.errorsout}: ${parseInt(obj["output errors"]).toLocaleString()}`,
                                    `${this.translations.collisions}: ${parseInt(obj["collisions"]).toLocaleString()}`,
                                ];
                                return result;
                            }
                        }
                    },
                }
            }
        }

        this.chart = new Chart(context, config);
    }

    onWidgetResize(elem, width, height) {
        if (this.chart !== null) {
            if (width > 450) {
                this.chart.options.plugins.legend.display = true;
            } else {
                this.chart.options.plugins.legend.display = false;
            }
        }

        if (width < 700) {
            $('#intf-stats').show();
            $('#interface-statistics-table').hide();
        } else {
            $('#intf-stats').hide();
            $('#interface-statistics-table').show();
        }

        return true;
    }

    onWidgetClose() {
        if (this.chart !== null) {
            this.chart.destroy();
        }
    }
}
