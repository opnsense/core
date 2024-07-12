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

export default class Interfaces extends BaseTableWidget {
    constructor() {
        super();
        this.resizeHandles = "e, w";
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
            sizeToContent: 350
        }
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $if_table = this.createTable('if-table', {
            headerPosition: 'none'
        });

        $container.append($if_table);
        return $container;
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/interfaces/overview/interfacesInfo');
        if (!this.dataChanged('interfaces', data)) {
            return;
        }

        $('.if-status-icon').tooltip('hide');

        let rows = [];
        data.rows.map((intf_data) => {
            if (!intf_data.hasOwnProperty('config') || intf_data.enabled == false) {
                return;
            }

            if (intf_data.config.hasOwnProperty('virtual') && intf_data.config.virtual == '1') {
                return;
            }

            let row = [];

            row.push($(`
                <div class="interface-info if-name">
                    <i class="fa fa-plug text-${intf_data.status === 'up' ? 'success' : 'danger'} if-status-icon" title="" data-toggle="tooltip" data-original-title="${intf_data.status}"></i>
                    <b class="interface-descr" onclick="location.href='/interfaces.php?if=${intf_data.identifier}'">
                        ${intf_data.description}
                    </b>
                </div>
            `).prop('outerHTML'));

            let media = (!'media' in intf_data ? intf_data.cell_mode : intf_data.media) ?? '';
            row.push($(`
                <div class="interface-info-detail">
                    <div>${media}</div>
                </div>
            `).prop('outerHTML'));

            let ipv4 = '';
            let ipv6 = '';
            if ('ipv4' in intf_data && intf_data.ipv4.length > 0) {
                ipv4 = intf_data.ipv4[0].ipaddr;
            }

            if ('ipv6' in intf_data && intf_data.ipv6.length > 0) {
                ipv6 = intf_data.ipv6[0].ipaddr;
            }

            row.push($(`
                <div class="interface-info">
                    ${ipv4}
                    <div style="flex-basis: 100%; height: 0;"></div>
                    ${ipv6}
                </div>
            `).prop('outerHTML'));

            rows.push(row);
        });

        super.updateTable('if-table', rows);

        $('.if-status-icon').tooltip({container: 'body'});
    }

    onWidgetResize(elem, width, height) {
        if (width > 450) {
            $('.interface-info-detail').parent().show();
        } else {
            $('.interface-info-detail').parent().hide();
        }

        return super.onWidgetResize(elem, width, height);
    }
}
