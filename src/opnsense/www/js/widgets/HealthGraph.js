/*
 * Copyright (C) 2026 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
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

export default class HealthGraphWidget extends BaseWidget {
    constructor(config) {
        super(config);

        this.configurable = true;
        this.tickTimeout = 60;
        this.graph = null;
        this.rrdList = null;
        this.root = null;
        this.editMode = false;
        this.resetZoomOnUpdate = true;
    }

    get dialogTitle() {
        return this.translations.title;
    }

    getGridOptions() {
        return {
            minW: 3,
            w: 4,
            sizeToContent: 450
        };
    }

    getMarkup() {
        return $(
            `<div class="health-graph-widget" style="padding: 5px;">
                <div class="health-graph-actions" style="position: absolute; right: 10px; z-index: 1;">
                    <button type="button" class="btn btn-default btn-xs health-graph-reset"
                            data-toggle="tooltip" title="${this.translations.resetzoom}">
                        <i class="fa fa-search-minus fa-fw"></i>
                    </button>
                    <button type="button" class="btn btn-default btn-xs health-graph-export"
                            data-toggle="tooltip" title="${this.translations.export}">
                        <i class="fa fa-cloud-download fa-fw"></i>
                    </button>
                </div>
                <div style="position: relative; height: 300px;">
                    <canvas class="health-graph-canvas"></canvas>
                </div>
            </div>`
        );
    }

    async onMarkupRendered() {
        this.root = $(`[data-widget-instance-id="${CSS.escape(this.id)}"]`);
        const canvas = this.root.find('.health-graph-canvas')[0];

        this.graph = new HealthGraph(canvas);
        await this.graph.initialize();
        this._setChartInteraction(!this.editMode);
        this.rrdList = this.graph.getRRDList();

        this.root.find('.health-graph-reset')
            .tooltip({placement: 'bottom', container: 'body'})
            .on('click.healthGraph', () => this.graph?.resetZoom());
        this.root.find('.health-graph-export')
            .tooltip({placement: 'bottom', container: 'body'})
            .on('click.healthGraph', () => this.graph?.exportData());

    }

    async onWidgetTick() {
        if (!this.graph) {
            return;
        }
        await this._updateGraph(this.resetZoomOnUpdate);
        this.resetZoomOnUpdate = false;
    }

    onEditModeChanged(enabled) {
        this.editMode = enabled;
        this._setChartInteraction(!enabled);
    }

    async getWidgetOptions() {
        const rrdList = await this._getRRDList();
        const categories = Object.keys(rrdList.data);
        const configuredCategory = this.config?.widget?.category;
        const selectedCategory = categories.includes(configuredCategory) ? configuredCategory : categories[0];
        const subjectsByCategory = {};

        for (const category of categories) {
            subjectsByCategory[category] = rrdList.data[category].map(subject => ({
                value: subject,
                label: rrdList.interfaces?.[subject]?.descr ?? subject
            }));
        }

        return {
            category: {
                title: this.translations.category,
                type: 'select',
                options: categories.map(category => ({
                    value: category,
                    label: this._capitalize(category)
                })),
                default: categories[0],
                required: true
            },
            subject: {
                title: this.translations.subject,
                type: 'select',
                options: subjectsByCategory[selectedCategory] ?? [],
                optionsByValue: subjectsByCategory,
                dependsOn: 'category',
                default: subjectsByCategory[selectedCategory]?.[0]?.value,
                required: true
            },
            granularity: {
                title: this.translations.granularity,
                type: 'select',
                options: [
                    {value: '0', label: this.translations.minute},
                    {value: '1', label: this.translations.minutes5},
                    {value: '2', label: this.translations.hour},
                    {value: '3', label: this.translations.hours24}
                ],
                default: '0',
                required: true
            },
            stacked: {
                title: this.translations.stacked,
                type: 'checkbox',
                default: false
            },
            logarithmic: {
                title: this.translations.logarithmic,
                type: 'checkbox',
                default: false
            }
        };
    }

    async getWidgetConfig(options = null) {
        return this._normalizeConfig(await super.getWidgetConfig(options));
    }

    async onWidgetOptionsChanged() {
        this.resetZoomOnUpdate = true;
    }

    onWidgetClose() {
        super.onWidgetClose();
        if (this.root) {
            this.root.find('.health-graph-reset, .health-graph-export')
                .off('.healthGraph')
                .tooltip('destroy');
        }
        this.graph?.destroy();
        this.graph = null;
        this.root = null;
    }

    async _getRRDList() {
        if (!this.rrdList) {
            this.rrdList = await this.ajaxCall('/api/diagnostics/systemhealth/get_rrd_list');
        }
        if (!this.rrdList?.data || Object.keys(this.rrdList.data).length === 0) {
            throw new Error('No RRD data available');
        }
        return this.rrdList;
    }

    async _normalizeConfig(config) {
        const rrdList = await this._getRRDList();
        const categories = Object.keys(rrdList.data);
        const category = categories.includes(config.category) ? config.category : categories[0];
        const subjects = rrdList.data[category];
        const subject = subjects.includes(config.subject) ? config.subject : subjects[0];
        const granularity = ['0', '1', '2', '3'].includes(`${config.granularity}`)
            ? `${config.granularity}`
            : '0';
        return {
            category,
            subject,
            granularity,
            stacked: config.stacked === true || config.stacked === 'true' || config.stacked === '1',
            logarithmic: config.logarithmic ?? false
        };
    }

    async _updateGraph(resetZoom) {
        const graph = this.graph;
        if (!graph) {
            return;
        }
        const config = await this.getWidgetConfig();
        if (graph !== this.graph) {
            return;
        }
        this.setWidgetConfig(config);
        this._updateTitle(config);
        await graph.update(
            `${config.subject}-${config.category}`,
            Number(config.granularity),
            config.stacked,
            resetZoom,
            config.logarithmic
        );
    }

    _updateTitle(config) {
        const subject = this.rrdList.interfaces?.[config.subject]?.descr ?? config.subject;
        this.config.callbacks.updateTitle(
            `${this.translations.title}: ${this._capitalize(config.category)} — ${subject}`
        );
    }

    _setChartInteraction(enabled) {
        this.root?.find('.health-graph-canvas').css('pointer-events', enabled ? '' : 'none');
    }

    _capitalize(value) {
        return value ? value[0].toUpperCase() + value.slice(1) : '';
    }
}
