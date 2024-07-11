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

export default class OpenVPNClients extends BaseTableWidget {
    constructor() {
        super();
        this.resizeHandles = "e, w";

        this.locked = false;
    }

    getGridOptions() {
        return {
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div id="ovpn-client-table-container"></div>');
        let $clientTable = this.createTable('ovpn-client-table', {
            headerPosition: 'left'
        });

        $container.append($clientTable);
        return $container;
    }

    async _killClient(id, commonName) {
        let split = id.split('_');
        let params = {server_id: split[0], session_id: split[1]};
        await this.ajaxCall('/api/openvpn/service/kill_session/', JSON.stringify(params), 'POST').then(async (data) => {
            if (data && data.status === 'not_found') {
                // kill by common name
                params.session_id = commonName;
                await this.ajaxCall('/api/openvpn/service/kill_session/', JSON.stringify(params), 'POST');
            }
        });
    }

    async updateClients() {
        const sessions = await this.ajaxCall('/api/openvpn/service/search_sessions', JSON.stringify({'type': ['server']}), 'POST');

        if (!sessions || !sessions.rows || sessions.rows.length === 0) {
            $('#ovpn-client-table-container').html(`
                <div class="error-message">
                    <a href="/ui/openvpn/instances">${this.translations.noclients}</a>
                </div>
            `);
            return;
        }

        let servers = {};
        sessions.rows.forEach((session) => {
            let id = session.id.toString().split("_")[0];
            if (!servers[id]) {
                servers[id] = {
                    description: session.description || '',
                    clients: []
                };
            }

            if (!session.is_client) {
                servers[id] = session;
                servers[id].clients = null;
            } else {

                servers[id].clients.push(session);
            }
        });

        for (const [server_id, server] of Object.entries(servers)) {
            let $clients = $('<div></div>');

            if (server.clients) {
                // sort the sessions per server
                server.clients.sort((a, b) => (b.bytes_received + b.bytes_sent) - (a.bytes_received + a.bytes_sent));
                server.clients.forEach((client) => {
                    let color = "text-success";
                    // disabled servers are not included in the list. Stopped servers have no "status" property
                    if (client.status) {
                        // server active, status may either be 'ok' or 'failed'
                        if (client.status === 'failed') {
                            color = "text-danger";
                        } else {
                            color = "text-success";
                        }
                    } else {
                        // server is stopped
                        color = "text-muted";
                    }
                    $clients.append($(`
                        <div class="ovpn-client-container">
                            <div class="ovpn-common-name">
                                <i class="fa fa-circle ${color}" style="font-size: 11px;">
                                </i>
                                &nbsp;
                                <a href="/ui/openvpn/status">${client.common_name}</a>

                                <span class="ovpn-client-command ovpn-command-kill"
                                  data-row-id="${client.id}"
                                  data-common-name="${client.common_name}"
                                  style="cursor: pointer; float:right; margin-left: auto;"
                                  data-toggle="tooltip"
                                  title="${this.translations.kill}">
                                    <i class="fa fa-times" style="font-size: 13px;"></i>
                                </span>
                            </div>
                            <div>
                                ${client.real_address} / ${client.virtual_address}
                            </div>
                            <div>
                                ${client.connected_since}
                            </div>
                            <div style="padding-bottom: 10px;">
                                RX: ${this._formatBytes(client.bytes_received, 0)} / TX: ${this._formatBytes(client.bytes_sent, 0)}
                            </div>
                        </div>
                    `));
                });
            } else {
                $clients = $(`<a href="/ui/openvpn/status">${this.translations.noclients}</a>`);
            }

            this.updateTable('ovpn-client-table', [[`${this.translations.server} ${server.description}`, $clients.prop('outerHTML')]], `server_${server_id}`);

            $('.ovpn-command-kill').on('click', async (event) => {
                this.locked = true;

                let $target = $(event.currentTarget);
                let rowId = $target.data('row-id');
                let commonName = $target.data('common-name');

                this.startCommandTransition(rowId, $target);
                await this._killClient(rowId, commonName);
                await this.endCommandTransition(rowId, $target);
                await this.updateClients();
                this.locked = false;

            });

            $('.ovpn-client-command').tooltip({container: 'body'});
        };
    }

    async onWidgetTick() {
        if (!this.locked) {
            await this.updateClients();
        }
    }
}
