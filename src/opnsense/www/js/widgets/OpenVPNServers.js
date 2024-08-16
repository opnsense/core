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

export default class OpenVPNServers extends BaseTableWidget {
    constructor() {
        super();
    }

    getGridOptions() {
        return {
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div id="ovpn-server-table-container"></div>');
        let $clientTable = this.createTable('ovpn-server-table', {
            headerPosition: 'left'
        });

        $container.append($clientTable);
        return $container;
    }

    async updateServers() {
        const servers = await this.ajaxCall('/api/openvpn/service/search_sessions', JSON.stringify({type: ['client']}), 'POST');

        if (!servers || !servers.rows || servers.rows.length === 0) {
            $('#ovpn-server-table-container').html(`
                <div class="error-message">
                    <a href="/ui/openvpn/instances">${this.translations.noservers}</a>
                </div>
            `);
            return;
        }

        servers.rows.forEach((server) => {
            let stopped = false;
            let color = "text-muted";
            // disabled servers are not included in the list. Stopped servers have no "status" property
            if (server.status) {
                switch (server.status) {
                    case 'connected':
                    case 'ok':
                        color = "text-success";
                        break;
                    case 'failed':
                        color = "text-danger";
                        break;
                    default:
                        color = "text-warning";
                        break;
                }
            } else {
                stopped = true;
            }

            let $header = $(`
                <div>
                    <i class="ovpn-server-status fa fa-circle ${color}"
                        style="font-size: 11px; cursor: pointer;"
                        data-toggle="tooltip"
                        title="${server.status || this.translations.stopped}">
                    </i>
                    &nbsp;
                    <a href="/ui/openvpn/status">${server.description || this.translations.client}</a>
                    <i class="fa fa-arrows-h" style="font-size: 13px;"></i>
                    ${server.real_address || ''}
                </div>
            `);

            let $row = null;
            if (!stopped) {
                $row = $(`
                    <div>
                        <div>
                            ${server.virtual_address || ''}
                        </div>
                        <div>
                            ${server.connected_since || ''}
                        </div>
                        <div style="padding-bottom: 10px;">
                            <i class="fa fa-arrow-down" style="font-size: 13px;"></i>
                            ${this._formatBytes(server.bytes_received, 0)}
                            |
                            <i class="fa fa-arrow-up" style="font-size: 13px;"></i>
                            ${this._formatBytes(server.bytes_sent, 0)}
                        </div>
                    </div>
                `);
            }

            this.updateTable('ovpn-server-table', [[$header.prop('outerHTML'), $row ? $row.prop('outerHTML') : '']], server.id);
        });
    }

    async onWidgetTick() {
        $('.ovpn-server-status').tooltip('hide');
        await this.updateServers();

        $('.ovpn-server-status').tooltip({container: 'body'});
    }
}
