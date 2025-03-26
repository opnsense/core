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

export default class OpenVPNClients extends BaseTableWidget {
    constructor(config) {
        super(config);

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
        await this.ajaxCall('/api/openvpn/service/kill_session', JSON.stringify(params), 'POST').then(async (data) => {
            if (data && data.status === 'not_found') {
                // kill by common name
                params.session_id = commonName;
                await this.ajaxCall('/api/openvpn/service/kill_session', JSON.stringify(params), 'POST');
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
                    let color = "text-muted";
                    // disabled servers are not included in the list. Stopped servers have no "status" property
                    if (client.status) {
                        if (client.status === 'failed') {
                            color = "text-danger";
                        } else {
                            color = "text-success";
                        }
                    }

                    // store all ip addresses
                    let ip_list = [
                        client.real_address,
                        client.virtual_address,
                        client.virtual_ipv6_address
                    ];

                    // filter out empty values
                    let ip_list_view = ip_list.filter((value) => value).join(" | ");

                    $clients.append($(`
                        <div class="ovpn-client-container">
                            <div class="ovpn-common-name">
                                <i class="ovpn-client-status fa fa-circle ${color}"
                                   style="font-size: 11px; cursor: pointer;"
                                   data-toggle="tooltip"
                                   title="${client.status}">
                                </i>
                                &nbsp;
                                <a href="/ui/openvpn/status#search=${encodeURIComponent(client.common_name)}" target="_blank" rel="noopener noreferrer">
                                    ${client.common_name}
                                </a>

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
                                ${ip_list_view}
                            </div>
                            <div>
                                ${client.connected_since}
                            </div>
                            <div style="padding-bottom: 10px;">
                                <i class="fa fa-arrow-down" style="font-size: 13px;"></i>
                                ${this._formatBytes(client.bytes_received, 0)}
                                |
                                <i class="fa fa-arrow-up" style="font-size: 13px;"></i>
                                ${this._formatBytes(client.bytes_sent, 0)}
                            </div>
                        </div>
                    `));
                });
            } else {
                $clients = $(`<a href="/ui/openvpn/status#search=${encodeURIComponent(server.description)}" target="_blank" rel="noopener noreferrer">
                    ${this.translations.noclients}
                </a>`);
            }

            let clientInfo = server.clients ? `
                <br/>
                <div style="padding-bottom: 5px; padding-top: 5px; font-size: 12px;">
                    ${this.translations.clients}: ${server.clients.length}
                </div>` : '';

            let $serverHeader = $(`
                <div>
                    ${this.translations.server} ${server.description}
                    ${clientInfo}
                </div>
            `);

            this.updateTable('ovpn-client-table', [[$serverHeader.prop('outerHTML'), $clients.prop('outerHTML')]], `server_${server_id}`);

            $('.ovpn-command-kill').on('click', async (event) => {
                this.locked = true;

                let $target = $(event.currentTarget);
                let rowId = $target.data('row-id');
                let commonName = $target.data('common-name');

                this.startCommandTransition(rowId, $target);
                await this._killClient(rowId, commonName);
                await this.endCommandTransition(rowId, $target, true, true);
                await this.updateClients();
                this.config.callbacks.updateGrid();
                this.locked = false;
            });

        };
    }

    async onWidgetTick() {
        if (!this.locked) {
            $('.ovpn-client-command').tooltip('hide');
            $('.ovpn-client-status').tooltip('hide');
            await this.updateClients();
        }

        $('.ovpn-client-command').tooltip({container: 'body'});
        $('.ovpn-client-status').tooltip({container: 'body'});
    }
}
