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

export default class Carp extends BaseTableWidget {
    constructor() {
        super();
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
            sizeToContent: 650
        }
    }

    getMarkup() {
        let $carp_table = this.createTable('carp-table', {
            headerPosition: 'left',
        });

        return $('<div></div>').append($carp_table);
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/diagnostics/interface/get_vip_status');
        if (!data.rows.length) {
            $('#carp-table').html(`<a href="/ui/interfaces/vip">${this.translations.unconfigured}</a>`);
            return;
        }

        $('.carp-status-icon').tooltip('hide');

        let ifs = {};

        data.rows.forEach(({ interface: iface, vhid, status, status_txt, subnet, mode, vhid_txt }) => {
            let key = `${iface}_${vhid}`;
            let obj = { interface: iface, vhid, status, status_txt, subnet: subnet ?? '', mode, vhid_txt };

            if (!ifs[key]) {
                ifs[key] = { primary: null, aliases: [] };
            }

            obj.mode === 'carp' ? ifs[key].primary = obj : ifs[key].aliases.push(obj);
        });

        Object.values(ifs).forEach(({ primary, aliases }) => {
            let $intf = `<div><a href="/ui/interfaces/vip">${primary.interface} @ VHID ${primary.vhid}</a></div>`;
            let vips = [
                `<div>
                    <span class="badge badge-pill carp-status-icon"
                            data-toggle="tooltip"
                            title="${primary.vhid_txt}"
                            style="background-color: ${primary.status == 'MASTER' ? 'green' : 'primary'}">
                        ${primary.status_txt}
                    </span>
                    ${primary.subnet} (${this.translations.carp})
                </div>`
            ];

            aliases.forEach(({ status, status_txt, subnet }) => {
                vips.push(`
                    <div>
                        <span class="badge badge-pill carp-status-icon"
                                data-toggle="tooltip"
                                title="${primary.vhid_txt}"
                                style="background-color: ${status == 'MASTER' ? 'green' : 'primary'}">
                            ${status_txt}
                        </span>
                        ${subnet} (${this.translations.alias})
                    </div>
                `);
            });

            this.updateTable('carp-table', [[$intf, vips]], `carp_${primary.interface}_${primary.vhid}`);
        });

        $('.carp-status-icon').tooltip({container: 'body'});
    }
}
