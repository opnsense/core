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

import BaseTableWidget from "./BaseTableWidget.js";

export default class Wireguard extends BaseTableWidget {
    constructor() {
        super();
        this.resizeHandles = "e, w";
        this.currentTunnels = {};
    }

    getGridOptions() {
        return {
            // Automatically triggers vertical scrolling after reaching 650px in height
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $wgTunnelTable = this.createTable('wgTunnelTable', {
            headerPosition: 'none'
        });

        $container.append($wgTunnelTable);
        return $container;
    }

    async onWidgetTick() {
        const wg = await this.ajaxCall('/api/wireguard/general/get');
        if (!wg.general || !wg.general.enabled) {
            this.displayError(`${this.translations.unconfigured}`);
            return;
        }

        const response = await this.ajaxCall('/api/wireguard/service/show');

        if (!response || !response.rows || response.rows.length === 0) {
            this.displayError(`${this.translations.notunnels}`);
            return;
        }

        this.processTunnels(response.rows);
    }

    displayError(message) {
        $('#wgTunnelTable'). empty().append(
            $(`<div class="error-message"><a href="/ui/wireguard/general">${message}</a></div>`)
        );
    }

    processTunnels(newTunnels) {
        $('.wireguard-interface').tooltip('hide');
        let tunnels = newTunnels.filter(row => row.type == 'peer').map(row => ({
            ifname: row.ifname ? row.if + ' (' + row.ifname + ') ' : row.if,
            name: row.name,
            rx: row['transfer-rx'] ? this._formatBytes(row['transfer-rx']) : '-',
            tx: row['transfer-tx'] ? this._formatBytes(row['transfer-tx']) : '-',
            pubkey: row['public-key'],
            latest_handhake: row['latest-handshake'],
            latest_handhake_fmt: row['latest-handshake'] ? moment.unix(row['latest-handshake']).local().format('YYYY-MM-DD HH:mm:ss') : '-'
        }));

        tunnels.sort((a, b) => a.latest_handhake === b.latest_handhake ? 0 : a.latest_handhake ? -1 : 1);

        let rows = [];
        // Generate HTML for each tunnel
        tunnels.forEach(tunnel => {
            let row = `
                <div>
                    <div data-toggle="tooltip" class="wireguard-interface" title="${tunnel.pubkey}">
                        <b>${tunnel.ifname}</b>
                        <i class="fa fa-arrows-h " aria-hidden="true"></i>
                        <b>${tunnel.name}</b>
                    </div>
                    <div>
                        ${this.translations.rx} : ${tunnel.rx}
                        ${this.translations.tx} : ${tunnel.tx}
                    </div>
                    <div>
                        ${tunnel.latest_handhake_fmt}
                    </div>
                </div>`;
            rows.push(row);
        });

        // Update the HTML table with the sorted rows
        super.updateTable('wgTunnelTable', rows.map(row => [row]));

        // Activate tooltips for new dynamic elements
        $('.wireguard-interface').tooltip();
    }
}
