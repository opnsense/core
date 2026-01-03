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

export default class Gateways extends BaseTableWidget {
    constructor(config) {
        super(config);

        this.configurable = true;
        this.cachedGateways = []; // prevent fetch when loading options
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
            sizeToContent: 650,
        };
    }

    getMarkup() {
        let $gateway_table = this.createTable('gateway-table', {
            headerPosition: 'left',
        });

        return $('<div></div>').append($gateway_table);
    }

    async _fetchGateways() {
        const data = await this.ajaxCall('/api/routing/settings/search_gateway');
        if (!data.rows || !data.rows.length) {
            return false;
        }

        return data.rows;
    }

    async onWidgetOptionsChanged(options) {
        // Intentionally not awaited to avoid blocking dialog close
        this._updateGateways();
    }

    async getWidgetOptions() {
        const gateways = this.cachedGateways.length > 0 ? this.cachedGateways : await this._fetchGateways();

        return {
            gateways: {
                title: this.translations.title,
                type: 'select_multiple',
                options: gateways.map(({ name, uuid }) => {
                    return {
                        value: uuid,
                        label: name,
                    };
                }),
                default: gateways.map(({ uuid }) => uuid),
            },
        };
    }

    async onWidgetTick() {
        await this._updateGateways();
    }

    async _updateGateways() {
        $('.gateways-status-icon').tooltip('hide');

        const gateways = await this._fetchGateways();
        if (!gateways) {
            $('#gateway-table').html(`<a href="/ui/routing/configuration">${this.translations.unconfigured}</a>`);
            return;
        }

        this.cachedGateways = gateways;

        const config = await this.getWidgetConfig();

        let data = [];
        gateways.forEach(({ uuid, name, gateway: address, status, loss, delay, stddev, defaultgw }) => {
            if (!config.gateways.includes(uuid)) {
                return;
            }

            let color = "text-success";

            if (status.toLowerCase().includes("offline")) {
                color = "text-danger";
            } else if (status.toLowerCase() !== "online") {
                color = "text-warning";
            }

            let gw = `<div>
                <i class="fa fa-circle text-muted ${color} gateways-status-icon" style="font-size: 11px; cursor: pointer;"
                    data-toggle="tooltip" title="${status}">
                </i>
                &nbsp;
                <a href="/ui/routing/configuration#edit=${encodeURIComponent(uuid)}" target="_blank" rel="noopener noreferrer">
                    ${name}
                </a>
                &nbsp;
                ${defaultgw ? `(${this.translations.active})` : ''}
                <br/>
                <div style="margin-top: 5px; margin-bottom: 5px; font-size: 15px;">${address}</div>
            </div>`;

            let stats = `<div>
                ${delay === '~' ? '' : `<div><b>${this.translations.rtt}</b>: ${delay}</div>`}
                ${stddev === '~' ? '' : `<div><b>${this.translations.rttd}</b>: ${stddev}</div>`}
                ${loss === '~' ? '' : `<div><b>${this.translations.loss}</b>: ${loss}</div>`}
            </div>`;

            data.push([gw, stats]);
        });

        this.updateTable('gateway-table', data);

        $('.gateways-status-icon').tooltip({container: 'body'});
    }
}
