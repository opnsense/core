/*
 * Copyright (C) 2024 David Berry.
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

export default class Apcupsd extends BaseTableWidget {
    constructor() {
        super();
        this.dataError = false;
        this.data = null;
        this.statusColor = this.translations['t_statusColorWarning'];
        this.statusText = this.translations['t_statusTextWaiting'];
        this.statusName = this.translations['t_statusNameOffline'];
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
            sizeToContent: 650,
        }
    }
   
    getMarkup() {
        let $container = $('<div></div>');
        let $apcupsdTable = this.createTable('apcupsd-table', {
            headerPosition: 'left',
        });
        $container.append($apcupsdTable);
       
        return $container;
    }

    async onWidgetTick() {

        this.dataError = false;
        this.statusColor = this.translations['t_statusColorSuccess'];
        this.statusText = this.translations['t_statusNameOnline'];
        this.statusName = this.translations['t_statusNameOnline'];

        this.data = await this.ajaxCall('/api/apcupsd/service/getUpsStatus');
        if (!this.data) {
            $(`.${this.id}-chart-container`).html(`
                <a href="/system_advanced_misc.php">${this.translations.t_unconfigured}</a>
            `).css('margin', '2em auto')
            this.dataError = true;
            this.statusColor = this.translations['t_statusColorDanger'];
            this.statusText = this.translations['t_statusTextError'];
            this.statusName = this.translations['t_statusNameOffline'];
        }

        if (this.data.status === null) {
            this.dataError = true;   
            this.statusColor = this.translations['t_statusColorDanger'];
            this.statusText = this.translations['t_statusTextOffline'];
            this.statusName = this.translations['t_statusNameOffline'];
        }
        else
        {
            this.statusName = this.data['status']['MODEL']['value'];
        }

        let rows = [];
       
        let upsStatusLight = `<div>
        <i class="fa fa-circle text-muted ${this.statusColor} ups-status-icon" style="font-size: 11px; cursor: pointer;"
            data-toggle="tooltip" title=${this.statusText}>
        </i>
            &nbsp;
        ${this.statusName}
        &nbsp;
        </div>`
       
        rows.push([upsStatusLight, '']);

        if (this.dataError) {
            rows.push([this.translations['t_unable_to_connect'], this.data['error']]);
         }
        else {
            rows.push([this.translations['t_mode'], this.data['status']['UPSMODE']['value']]);
            rows.push([this.translations['t_status'], this.data['status']['STATUS']['value']]);
            rows.push([this.translations['t_battery_runtime'], this.data['status']['TIMELEFT']['value']]);
            rows.push([this.translations['t_load'], this.data['status']['LOADPCT']['value']]);
            rows.push([this.translations['t_int_temp'], this.data['status']['ITEMP']['value']]);
        }
        super.updateTable('apcupsd-table', rows);
    }
}
