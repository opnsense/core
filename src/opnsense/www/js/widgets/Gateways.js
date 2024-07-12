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

import BaseTableWidget from "./BaseTableWidget.js";

export default class Gateways extends BaseTableWidget {
    constructor() {
        super();
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
            sizeToContent: 650,
        }
    }

    getMarkup() {
        let $gateway_table = this.createTable('gateway-table', {
            headerPosition: 'left',
        });

        return $('<div></div>').append($gateway_table);
    }

    async onWidgetTick() {
        $('.gateways-status-icon').tooltip('hide');
        const data = await this.ajaxCall('/api/routes/gateway/status');
        if (data.items === undefined) {
            return;
        }

        if (!data.items.length) {
            $('#gateway-table').html(`<a href="/ui/routing/configuration">${this.translations.unconfigured}</a>`);
            return;
        }

        data.items.forEach(({name, address, status, loss, delay, stddev, status_translated}) => {
            let color = "text-success";
            switch (status) {
                case "force_down":
                case "down":
                    color = "text-danger";
                    break;
                case "loss":
                case "delay":
                case "delay+loss":
                    color = "text-warning";
                    break;
            }

            let gw = `<div>
                <i class="fa fa-circle text-muted ${color} gateways-status-icon" style="font-size: 11px; cursor: pointer;"
                    data-toggle="tooltip" title="${status_translated}">
                </i>
                &nbsp;
                <a href="/ui/routing/configuration">${name}</a>
                &nbsp;
                <br/>
                <div style="margin-top: 5px; margin-bottom: 5px; font-size: 15px;">${address}</div>
            </div>`

            let stats = `<div>
                ${delay === '~' ? '' : `<div><b>${this.translations.rtt}</b>: ${delay}</div>`}
                ${delay === '~' ? '' : `<div><b>${this.translations.rttd}</b>: ${stddev}</div>`}
                ${delay === '~' ? '' : `<div><b>${this.translations.loss}</b>: ${loss}</div>`}
            </div>`

            this.updateTable('gateway-table', [[gw, stats]], `gw_${name}`);
        });

        $('.gateways-status-icon').tooltip({container: 'body'});
    }
}
