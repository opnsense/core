// endpoint:/api/diagnostics/cpu_usage/*

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

export default class Cpu extends BaseWidget {
    constructor() {
        super();
        this.resizeHandles = "e, w";
    }

    _createChart(selector, timeSeries) {
        let smoothie = new SmoothieChart({
            responsive: true,
            millisPerPixel:50,
            tooltip: true,
            labels: {
                fillStyle: '#000000',
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

    getMarkup() {
        let $container = $(`
        <div class="canvas-container">
            <div class="smoothie-container">
                <b>${this.translations.total}</b>
                <div><canvas id="cpu-usage" style="width: 80%; height: 50px;"></canvas></div>
            </div>
            <div class="smoothie-container">
                <b>${this.translations.interrupt}</b>
                <div><canvas id="cpu-usage-intr" style="width: 80%; height: 50px;"></canvas></div>
            </div>
            <div class="smoothie-container">
                <b>${this.translations.user}</b>
                <div><canvas id="cpu-usage-user" style="width: 80%; height: 50px;"></canvas></div>
            </div>
            <div class="smoothie-container">
                <b>${this.translations.system}</b>
                <div><canvas id="cpu-usage-sys" style="width: 80%; height: 50px;"></canvas></div>
            </div>
        </div>`);

        return $container;
    }

    async onMarkupRendered() {
        let total_ts = new TimeSeries();
        let intr_ts = new TimeSeries();
        let user_ts = new TimeSeries();
        let sys_ts = new TimeSeries();
        this._createChart('cpu-usage', total_ts);
        this._createChart('cpu-usage-intr', intr_ts);
        this._createChart('cpu-usage-user', user_ts);
        this._createChart('cpu-usage-sys', sys_ts);
        const eventSource = new EventSource('/api/diagnostics/cpu_usage/stream');

        eventSource.onmessage = function(event) {
            if (!event) {
                eventSource.close();
            }
            const data = JSON.parse(event.data);
            let date = Date.now();
            total_ts.append(date, data.total);
            intr_ts.append(date, data.intr);
            user_ts.append(date, data.user);
            sys_ts.append(date, data.sys);
        }

        eventSource.onerror = function(event) {
            eventSource.close();
        };

    }

    onWidgetResize(elem, width, height) {
        let curWidth = document.getElementById('cpu-usage').getBoundingClientRect().width;
        let viewPort = document.getElementsByClassName('page-content-main')[0].getBoundingClientRect().width;
        if (width > (viewPort / 2)) {
            $('.canvas-container').css('flex-direction', 'row');
        } else {
            $('.canvas-container').css('flex-direction', 'column');
        }
    }
}
