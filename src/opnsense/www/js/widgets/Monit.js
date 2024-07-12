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

export default class Monit extends BaseTableWidget {
    constructor() {
        super();

        this.tickTimeout = 25;
    }

    getMarkup() {
        let $table = this.createTable('monit-table', {
            headerPosition: 'left'
        });
        return $(`<div id="monit-container"></div>`).append($table);
    }

    async onMarkupRendered() {
        this.serviceIcons = {
            "0": "fa-hdd-o",
            "1": "fa-folder",
            "2": "fa-file",
            "3": "fa-cogs",
            "4": "fa-desktop",
            "5": "fa-server",
            "6": "fa-sort-amount-asc",
            "7": "fa-cube",
            "8": "fa-globe"
        };

        this.statusColors = {
            "1": "text-danger",
            "2": "text-warning",
            "3": "text-warning"
        };

       this.serviceMap = [
            this.translations.filesystem,
            this.translations.directory,
            this.translations.file,
            this.translations.process,
            this.translations.host,
            this.translations.system,
            this.translations.fifo,
            this.translations.custom,
            this.translations.network
        ];

        this.statusMap = [
            this.translations.ok,
            this.translations.failed,
            this.translations.changed,
            this.translations.unchanged
        ];
    }

    async onWidgetTick() {
        const data =  await this.ajaxCall('/api/monit/status/get/xml');
        if (data['result'] !== 'ok') {
            $('#monit-table').html(`<a href="/ui/monit">${this.translations.unconfigured}</a>`);
            return;
        }

        $('.monit-status-icon').tooltip('hide');
        $('.monit-type-icon').tooltip('hide');

        let rows = [];
        $.each(data['status']['service'], (index, service) => {
            let color = this.statusColors[service['status']] || "text-success";
            let icon = this.serviceIcons[service['@attributes']['type']] || "fa-circle";

            let $header = $(`
                <div>
                    <i class="fa fa-circle text-muted ${color} monit-status-icon" style="font-size: 11px; cursor: pointer;"
                        data-toggle="tooltip" title="${this.statusMap[service['status']]}">
                    </i>
                    &nbsp;
                    <i class="fa ${icon} monit-type-icon" style="font-size: 11px;"
                        data-toggle="tooltip" title="${this.serviceMap[service['@attributes']['type']]}">
                    </i>
                    &nbsp;
                    <a href="/ui/monit/status">${service['name']}</a>
                </div>
            `);

            rows.push([$header.prop('outerHTML'), '']);

        });

        this.updateTable('monit-table', rows);

        $('.monit-status-icon').tooltip({container: 'body'});
        $('.monit-type-icon').tooltip({container: 'body'});
    }
}
