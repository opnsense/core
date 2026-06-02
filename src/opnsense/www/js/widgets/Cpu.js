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

export default class Cpu extends BaseWidget {
    constructor(config) {
        super(config);
        this.resizeHandles = "e, w";
        this.configurable = true;
        this.graphs = ['total', 'intr', 'user', 'sys'];
    }

    _createChart(selector, timeSeries) {
        let smoothie = new SmoothieChart({
            responsive: true,
            millisPerPixel:50,
            tooltip: true,
            labels: {
                fillStyle: Chart.defaults.color,
                precision: 0,
                fontSize: 11
            },
            grid: {
                strokeStyle:'rgba(119,119,119,0.12)',
                verticalSections:4,
                millisPerLine:1000,
                fillStyle: 'transparent'
            }
        });

        smoothie.streamTo(document.getElementById(selector), 1000);
        smoothie.addTimeSeries(timeSeries, {
            lineWidth: 3,
            strokeStyle: '#d94f00'
        });
    }

    async getWidgetOptions() {
        return {
            graphs: {
                title: this.translations.graphs,
                type: 'select_multiple',
                options: ['total', 'intr', 'user', 'sys'].map((value) => {
                    return {
                        value: value,
                        label: this.translations[value]
                    }
                }),
                default: ['total']
            }
        }
    }

    async onWidgetOptionsChanged(options) {
        this.graphs.filter(x => !options.graphs.includes(x)).forEach(graph => $(`#cpu-${graph}`).hide());
        const config = await this.getWidgetConfig();
        this.graphs = config.graphs
        this.graphs.forEach(graph => $(`#cpu-${graph}`).show());
    }

    getMarkup() {
        let $container = $(`
        <div class="cpu-type"></div>
        <div class="cpu-canvas-container">
            <div id="cpu-total" class="smoothie-container">
                <b>${this.translations.total}</b>
                <div><canvas id="cpu-usage-total" style="width: 100%; height: 50px;"></canvas></div>
            </div>
            <div id="cpu-intr" class="smoothie-container">
                <b>${this.translations.intr}</b>
                <div><canvas id="cpu-usage-intr" style="width: 100%; height: 50px;"></canvas></div>
            </div>
            <div id="cpu-user" class="smoothie-container">
                <b>${this.translations.user}</b>
                <div><canvas id="cpu-usage-user" style="width: 100%; height: 50px;"></canvas></div>
            </div>
            <div id="cpu-sys" class="smoothie-container">
                <b>${this.translations.sys}</b>
                <div><canvas id="cpu-usage-sys" style="width: 100%; height: 50px;"></canvas></div>
            </div>
        </div>`);

        return $container;
    }

    async onMarkupRendered() {
        const data = await this.ajaxCall(`/api/diagnostics/cpu_usage/${'getcputype'}`);
        $('.cpu-type').text(data);

        const config = await this.getWidgetConfig();

        let ts = {};
        this.graphs.forEach((graph) => {
            let timeSeries = new TimeSeries();
            this._createChart(`cpu-usage-${graph}`, timeSeries);
            ts[graph] = timeSeries;

            if (!config.graphs.includes(graph)) {
                // hide canvas container
                $(`#cpu-${graph}`).hide();
            }
        });

        super.openEventSource(`/api/diagnostics/cpu_usage/${'stream'}`, (event) => {
            if (!event) {
                super.closeEventSource();
            }
            const data = JSON.parse(event.data);
            let date = Date.now();
            this.graphs.forEach((graph) => {
                ts[graph].append(date, data[graph]);
            });
        });
    }

    onWidgetResize(elem, width, height) {
        let viewPort = document.getElementsByClassName('page-content-main')[0].getBoundingClientRect().width;
        if (width > (viewPort / 2)) {
            $('.cpu-canvas-container').css('flex-direction', 'row');
            $('.smoothie-container').css('margin', '0px 10px 0px 10px');
        } else {
            $('.cpu-canvas-container').css('flex-direction', 'column');
            $('.smoothie-container').css('margin', '0px');
        }

        return true;
    }
}
