/*
 * Copyright (C) 2024 Cedrik Pischem
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

export default class IpsecTunnels extends BaseTableWidget {
    constructor() {
        super();
        this.locked = false; // Add a lock mechanism
    }

    getGridOptions() {
        return {
            // Automatically triggers vertical scrolling after reaching 650px in height
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $ipsecTunnelTable = this.createTable('ipsecTunnelTable', {
            headerPosition: 'left'
        });

        $container.append($ipsecTunnelTable);
        return $container;
    }

    async onWidgetTick() {
        if (!this.locked) { // Check if the widget is locked
            const ipsecStatusResponse = await this.ajaxCall('/api/ipsec/connections/is_enabled');

            if (!ipsecStatusResponse.enabled) {
                this.displayError(`${this.translations.unconfigured}`);
                return;
            }

            const response = await this.ajaxCall('/api/ipsec/sessions/search_phase1');

            if (!response || !response.rows || response.rows.length === 0) {
                this.displayError(`${this.translations.notunnels}`);
                return;
            }

            if (!this.dataChanged('ipsec-tunnels', response.rows)) {
                return; // No changes detected, do not update the UI
            }

            this.processTunnels(response.rows);
        }
    }

    // Utility function to display errors within the widget
    displayError(message) {
        const $error = $(`<div class="error-message"><a href="/ui/ipsec/connections">${message}</a></div>`);
        $('#ipsecTunnelTable').empty().append($error);
    }

    async connectTunnel(ikeid) {
        await this.ajaxCall(`/api/ipsec/sessions/connect/${ikeid}`, JSON.stringify({ikeid: ikeid}), 'POST');
        const response = await this.ajaxCall('/api/ipsec/sessions/search_phase1');
        this.processTunnels(response.rows); // Refresh the tunnels
    }

    async disconnectTunnel(ikeid) {
        await this.ajaxCall(`/api/ipsec/sessions/disconnect/${ikeid}`, JSON.stringify({ikeid: ikeid}), 'POST');
        const response = await this.ajaxCall('/api/ipsec/sessions/search_phase1');
        this.processTunnels(response.rows); // Refresh the tunnels
    }

    processTunnels(newTunnels) {
        $('[data-toggle="tooltip"]').tooltip('hide');
        $('.ipsectunnels-status-icon').tooltip('hide');

        let tunnels = newTunnels.map(tunnel => ({
            phase1desc: tunnel.phase1desc || this.translations.notavailable,
            localAddrs: tunnel['local-addrs'] || this.translations.notavailable,
            remoteAddrs: tunnel['remote-addrs'] || this.translations.notavailable,
            installTime: tunnel['install-time'], // No fallback since we check if it is null
            bytesIn: tunnel['bytes-in'] != null && tunnel['bytes-in'] !== 0 ? this._formatBytes(tunnel['bytes-in']) : this.translations.notavailable,
            bytesOut: tunnel['bytes-out'] != null && tunnel['bytes-out'] !== 0 ? this._formatBytes(tunnel['bytes-out']) : this.translations.notavailable,
            connected: tunnel.connected,
            ikeid: tunnel.ikeid,
            name: tunnel.name,
            statusIcon: tunnel.connected ? 'fa-exchange text-success' : 'fa-exchange text-danger'
        }));

        // Sort by connected status, offline first then online
        tunnels.sort((a, b) => a.connected === b.connected ? 0 : a.connected ? -1 : 1);

        let onlineCount = tunnels.filter(tunnel => tunnel.connected).length;
        let offlineCount = tunnels.length - onlineCount;

        // Summary row for tunnel counts
        let summaryRow = `
            <div>
                <span>${this.translations.total}: ${tunnels.length} | ${this.translations.online}: ${onlineCount} | ${this.translations.offline}: ${offlineCount}</span>
            </div>`;

        super.updateTable('ipsecTunnelTable', [[summaryRow, '']], 'ipsec-summary');

        // Generate HTML for each tunnel
        tunnels.forEach(tunnel => {
            let installTimeInfo = tunnel.installTime === null
                ? `<span>${this.translations.nophase2connected}</span>`
                : `<span>${this.translations.installtime}: ${tunnel.installTime}s</span>`;

            let bytesInfo = tunnel.installTime !== null
                ? `<div style="padding-bottom: 10px;">
                       <i class="fa fa-arrow-down" style="font-size: 13px;"></i>
                       ${tunnel.bytesIn}
                       |
                       <i class="fa fa-arrow-up" style="font-size: 13px;"></i>
                       ${tunnel.bytesOut}
                   </div>`
                : '';

            let connectDisconnectButton = tunnel.connected
                ? `<span class="ipsec-disconnect" data-ikeid="${tunnel.ikeid}" style="cursor: pointer; float: right; margin-left: auto; margin-right: 10px;" data-toggle="tooltip" title="${this.translations.disconnect}">
                        <i class="fa fa-times" style="font-size: 13px;"></i>
                   </span>`
                : `<span class="ipsec-connect" data-ikeid="${tunnel.ikeid}" style="cursor: pointer; float: right; margin-left: auto; margin-right: 10px;" data-toggle="tooltip" title="${this.translations.connect}">
                        <i class="fa fa-play" style="font-size: 13px;"></i>
                   </span>`;

            let header = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <i class="fa ${tunnel.statusIcon} ipsectunnels-status-icon" style="cursor: pointer;"
                            data-toggle="tooltip" title="${tunnel.connected ? this.translations.online : this.translations.offline}">
                        </i>
                        &nbsp;
                        <span><b>${tunnel.phase1desc}</b></span>
                    </div>
                </div>`;

            let row = `
                <div style="display: flex; justify-content: center; align-items: center;">
                <span>
                    <a href="/ui/ipsec/sessions#search=${encodeURIComponent(tunnel.name)}" target="_blank" rel="noopener noreferrer">
                        ${tunnel.localAddrs} | ${tunnel.remoteAddrs}
                    </a>
                </span>
                    ${connectDisconnectButton}
                </div>
                <div>
                    ${installTimeInfo}
                </div>
                <div>
                    ${bytesInfo}
                </div>
            `;

            super.updateTable('ipsecTunnelTable', [[header, row]], tunnel.ikeid);
        });

        // Activate tooltips for new dynamic elements
        $('.ipsectunnels-status-icon').tooltip({container: 'body'});
        $('[data-toggle="tooltip"]').tooltip({container: 'body'});

        // Event listeners for the connect and disconnect buttons
        $('.ipsec-connect').on('click', async (event) => {
            this.locked = true;

            let $target = $(event.currentTarget);
            let ikeid = $target.data('ikeid');

            this.startCommandTransition(ikeid, $target);
            await this.connectTunnel(ikeid);
            this.locked = false;
        });

        $('.ipsec-disconnect').on('click', async (event) => {
            this.locked = true;

            let $target = $(event.currentTarget);
            let ikeid = $target.data('ikeid');

            this.startCommandTransition(ikeid, $target);
            await this.disconnectTunnel(ikeid);
            this.locked = false;
        });
    }
}
