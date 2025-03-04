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

export default class Wireguard extends BaseTableWidget {
    constructor() {
        super();
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
            headerPosition: 'left'
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

        if (!this.dataChanged('wg-tunnels', response.rows)) {
            return; // No changes detected, do not update the UI
        }

        this.processTunnels(response.rows);
    }

    displayError(message) {
        $('#wgTunnelTable').empty().append(
            $(`<div class="error-message"><a href="/ui/wireguard/general">${message}</a></div>`)
        );
    }

    processTunnels(newTunnels) {
        $('.wireguard-interface').tooltip('hide');

        let tunnels = newTunnels
            .filter(row => row.type == 'peer')
            .map(row => ({
                if: row.if,

                name: row.name,
                allowed_ips: row['allowed-ips'] || this.translations.notavailable,

                rx: row['transfer-rx']
                    ? this._formatBytes(row['transfer-rx'])
                    : this.translations.notavailable,

                tx: row['transfer-tx']
                    ? this._formatBytes(row['transfer-tx'])
                    : this.translations.notavailable,

                // No fallback since we handle if null
                latest_handshake_epoch: row['latest-handshake-epoch'],

                peerStatus: row['peer-status'],

                statusIcon: row['peer-status'] === 'online'
                    ? 'fa-check-circle fa-fw text-success'
                    : row['peer-status'] === 'stale'
                        ? 'fa-question-circle fa-fw'
                        : 'fa-times-circle fa-fw text-danger',

                statusTooltip: row['peer-status'] === 'online'
                    ? this.translations.online
                    : row['peer-status'] === 'stale'
                        ? this.translations.stale
                        : this.translations.offline,

                publicKey: row['public-key'],
                uniqueId: row.if + row['public-key']
            }));

        tunnels.sort((a, b) => {
            if (a.peerStatus === b.peerStatus) return 0;
            if (a.peerStatus === 'online') return -1;
            if (a.peerStatus === 'stale' && b.peerStatus !== 'online') return -1;
            return 1;
        });

        let onlineCount = tunnels.filter(tunnel => tunnel.peerStatus === 'online').length;
        let staleCount = tunnels.filter(tunnel => tunnel.peerStatus === 'stale').length;
        let offlineCount = tunnels.length - onlineCount - staleCount;

        let summaryRow = `
            <div>
                <span>
                    ${this.translations.online}: ${onlineCount} |
                    ${this.translations.stale}: ${staleCount} |
                    ${this.translations.offline}: ${offlineCount}
                </span>
            </div>`;

        super.updateTable('wgTunnelTable', [[summaryRow, '']], 'wg-summary');

        // Generate HTML for each tunnel
        tunnels.forEach(tunnel => {
            let header = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <i class="fa ${tunnel.statusIcon} wireguard-interface" style="cursor: pointer;"
                            data-toggle="tooltip" title="${tunnel.statusTooltip}">
                        </i>
                        &nbsp;
                        <a href="/ui/wireguard/general#peers&search=${encodeURIComponent(tunnel.name)}" target="_blank" rel="noopener noreferrer">
                            ${tunnel.if} | ${tunnel.name}
                        </a>
                    </div>
                </div>`;
            let row = `
                <div>
                    <span>
                        ${tunnel.allowed_ips}
                    </span>
                </div>
                <div>
                    ${tunnel.latest_handshake_epoch
                        ? `<span>${tunnel.latest_handshake_epoch}</span>
                           <div style="padding-bottom: 10px;">
                               <i class="fa fa-arrow-down" style="font-size: 13px;"></i>
                               ${tunnel.rx}
                               |
                               <i class="fa fa-arrow-up" style="font-size: 13px;"></i>
                               ${tunnel.tx}
                           </div>`
                        : ''}
                </div>`;

            // Update the HTML table with the sorted rows
            super.updateTable('wgTunnelTable', [[header, row]], tunnel.uniqueId);
        });

        // Activate tooltips for new dynamic elements
        $('.wireguard-interface').tooltip({container: 'body'});
    }
}
