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

export default class Firewall extends BaseTableWidget {
    constructor(config) {
        super(config);
        this.ifMap = {};
        this.counters = {};
        this.chart = null;
        this.rotation = 5;
        // Tableau 10 base hues with deterministic shade variants per action
        this.palettes = {
            block:  ['#e15759', '#c44e52', '#a83a3d', '#ff6b6b', '#d94f4f', '#b33636', '#e87272', '#cf5c5c', '#a04040', '#ff8585'],
            pass:   ['#59a14f', '#4c8c43', '#3d7a36', '#6fbf65', '#52a347', '#448c3a', '#7acc70', '#5db352', '#4a9c42', '#88d680'],
            rdr:    ['#4e79a7', '#3f6a98', '#335b85', '#6090b8', '#5080a8', '#436f95', '#729fc5', '#5a8ab5', '#4878a2', '#82afd0'],
            nat:    ['#4e79a7', '#3f6a98', '#335b85', '#6090b8', '#5080a8', '#436f95', '#729fc5', '#5a8ab5', '#4878a2', '#82afd0'],
            binat:    ['#4e79a7', '#3f6a98', '#335b85', '#6090b8', '#5080a8', '#436f95', '#729fc5', '#5a8ab5', '#4878a2', '#82afd0'],
            _default: ['#999999', '#888888', '#777777', '#aaaaaa', '#b0b0b0', '#969696', '#a3a3a3', '#8c8c8c', '#7a7a7a', '#b5b5b5']
        };
    }

    _getColor(action, rid) {
        let palette = this.palettes[action] ?? this.palettes['_default'];
        return palette[parseInt(rid.slice(0, 8), 16) % palette.length];
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $tableContainer = $(`<div id="fw-table-container"><b>${this.translations.livelog}</b></div>`);
        let $top_table = this.createTable('fw-top-table', {
            headerPosition: 'top',
            rotation: this.rotation,
            headers: [
                this.translations.action,
                this.translations.time,
                this.translations.interface,
                this.translations.source,
                this.translations.destination,
                this.translations.port
            ],
        });

        let $rule_table = this.createTable('fw-rule-table', {
            headerPosition: 'top',
            rotation: this.rotation,
            headers: [
                this.translations.label,
                this.translations.count
            ],
            sortIndex: 1,
            sortOrder: 'desc'
        });

        $tableContainer.append($top_table);
        $tableContainer.append(`<div style="margin-top: 2em"><b>${this.translations.events}</b><div>`);
        $tableContainer.append($rule_table);

        $container.append($tableContainer);

        $container.append($(`
            <div class="fw-chart-container">
                <div class="canvas-container">
                    <canvas id="fw-chart" style="display: inline-block"></canvas>
                </div>
            </div>
        `));

        return $container;
    }

    _onMessage(event) {
        if (!event) {
            super.closeEventSource();
        }

        $('.ip-tooltip').tooltip('hide');

        let actIcons = {
            'pass': '<i class="fa fa-play text-success"></i>',
            'block': '<i class="fa fa-minus-circle text-danger"></i>',
            'rdr': '<i class="fa fa-exchange text-info"></i>',
            'nat': '<i class="fa fa-exchange text-info"></i>',
        }

        const data = JSON.parse(event.data);

        // increase counters
        if (!this.counters[data.rid]) {
            this.counters[data.rid] = {
                count: data.counter,
                label: data.label ?? ''
            }
        } else {
            this.counters[data.rid].count = data.counter;
        }

        let popContent = $(`
            <p>
                @${data.rulenr}
                ${data.label.length > 0 ? 'Label: ' + data.label : ''}
                <br>
                <sub>${this.translations.click}</sub>
            </p>
        `).prop('outerHTML');
        let popover = $(`
            <a target="_blank" href="/ui/diagnostics/firewall/log?rid=${data.rid}" type="button"
                data-toggle="popover" data-trigger="hover" data-html="true" data-title="${this.translations.matchedrule}"
                data-content="${popContent}">
                ${actIcons[data.action]}
            </a>
        `);

        super.updateTable('fw-top-table', [
            [
                popover.prop('outerHTML'),
                /* Format time based on client browser locale */
                (new Intl.DateTimeFormat(undefined, {hour: 'numeric', minute: 'numeric'})).format(new Date(data.__timestamp__)),
                this.ifMap[data.interface] ?? data.interface,
                `<span class="ip-tooltip" style="cursor: pointer; data-toggle="tooltip" title="${data.src}">${data.src}</span>`,
                `<span class="ip-tooltip" style="cursor: pointer; data-toggle="tooltip" title="${data.dst}">${data.dst}</span>`,
                data.dstport ?? ''
            ]
        ]);

        $('.ip-tooltip').tooltip({container: 'body'});

        super.updateTable('fw-rule-table', [
            [
                popover.html($(`<div style="text-align: left;">${this.counters[data.rid].label}</div>`)).prop('outerHTML'),
                this.counters[data.rid].count
            ]
        ], data.rid);

        $('[data-toggle="popover"]').popover('hide');
        $('[data-toggle="popover"]').popover({
            container: 'body'
        }).on('show.bs.popover', function() {
            $(this).data("bs.popover").tip().css("max-width", "100%")
        });

        let iface = this.ifMap[data.interface] ?? data.interface;
        this._updateChart(data.rid, this.counters[data.rid].label, this.counters[data.rid].count, data.action, iface);

        if (Object.keys(this.counters).length < this.rotation) {
            this.config.callbacks.updateGrid();
        }
    }

    _updateChart(rid, label, count, action, iface) {
        let labels = this.chart.data.labels;
        let data = this.chart.data.datasets[0].data;
        let rids = this.chart.data.datasets[0].rids;
        let colors = this.chart.data.datasets[0].backgroundColor;

        let idx = rids.findIndex(x => x === rid);
        if (idx === -1) {
            let decodedLabel = $("<textarea/>").html(label).text();
            labels.push(iface ? `${decodedLabel} (${iface})` : decodedLabel);
            data.push(count);
            rids.push(rid);
            colors.push(this._getColor(action, rid));
        } else {
            data[idx] = count;
        }

        this.chart.update();
    }

    async onMarkupRendered() {
        const data = await this.ajaxCall('/api/diagnostics/interface/get_interface_names');
        this.ifMap = data;

        super.openEventSource('/api/diagnostics/firewall/stream_log', this._onMessage.bind(this));

        let context = document.getElementById('fw-chart').getContext('2d');
        let config = {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [
                    {
                        data: [],
                        rids: [],
                        backgroundColor: [],
                    }
                ]
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
                onClick: (event, elements, chart) => {
                    const i = elements[0].index;
                    const rid = chart.data.datasets[0].rids[i];
                    window.open(`/ui/diagnostics/firewall/log?rid=${rid}`);
                },
                onHover: (event, elements) => {
                    event.native.target.style.cursor = elements[0] ? 'pointer' : 'grab';
                },
                plugins: {
                    colorschemes: false,
                    legend: {
                        display: true,
                        position: 'left',
                        onHover: (event, legendItem) => {
                            const activeElement = {
                              datasetIndex: 0,
                              index: legendItem.index
                            };
                            this.chart.setActiveElements([activeElement]);
                            this.chart.tooltip.setActiveElements([activeElement]);
                        },
                        labels: {
                            filter: (ds, data) => {
                                /* clamp amount of legend labels to a max of 10 (sorted) */
                                const sortable = [];
                                data.labels.forEach((l, i) => {
                                    sortable.push([l, data.datasets[0].data[i]]);
                                });
                                sortable.sort((a, b) => (b[1] - a[1]));
                                const sorted = sortable.slice(0, 10).map(e => (e[0]));

                                return sorted.includes(ds.text)
                            },
                        }
                    },
                    tooltip: {
                        callbacks: {
                            labels: (tooltipItem) => {
                                let obj = this.counters[tooltipItem.label];
                                return `${obj.label} (${obj.count})`;
                            }
                        }
                    },
                }
            },
            plugins: [
                {
                    // display a placeholder if no data is available
                    id: 'nodata_placeholder',
                    afterDraw: (chart, args, options) => {
                        if (chart.data.datasets[0].data.length === 0) {
                            let ctx = chart.ctx;
                            let width = chart.width;
                            let height = chart.height;

                            chart.clear();
                            ctx.save();
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(this.translations.nodata + '...', width / 2, height / 2);
                            ctx.restore();
                        }
                    }
                }
            ]
        }

        this.chart = new Chart(context, config);
    }

    onWidgetClose() {
        super.onWidgetClose();

        if (this.chart !== null) {
            this.chart.destroy();
        }
    }

    onWidgetResize(elem, width, height) {
        if (width < 700) {
            $('#fw-chart').show();
            $('#fw-table-container').hide();
        } else {
            $('#fw-chart').hide();
            $('#fw-table-container').show();
        }
    }
}
