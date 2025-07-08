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

export default class SystemInformation extends BaseTableWidget {
    constructor() {
        super();
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $sysinfotable = this.createTable('sysinfo-table', {
            headerPosition: 'left',
        });
        $container.append($sysinfotable);
        return $container;
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/diagnostics/system/system_time');
        $('#uptime').text(data['uptime']);
        $('#loadavg').text(data['loadavg']);
        $('#datetime').text(data['datetime']);
        $('#config').text(data['config']);
    }

    async onMarkupRendered() {
        const data = await this.ajaxCall('/api/diagnostics/system/system_information');
        let rows = [];
        for (let [key, value] of Object.entries(data)) {
            if (!key in this.translations) {
                console.error('Missing translation for ' + key);
                continue;
            }

            if (key === 'updates') {
                value = $('<a>').attr('href', '/ui/core/firmware#checkupdate').text(value).prop('outerHTML');
            }

            rows.push([[this.translations[key]], value]);
        }

        rows.push([[this.translations['uptime']], $('<span id="uptime">').prop('outerHTML')]);
        rows.push([[this.translations['loadavg']], $('<span id="loadavg">').prop('outerHTML')]);
        rows.push([[this.translations['datetime']], $('<span id="datetime">').prop('outerHTML')]);
        rows.push([[this.translations['config']], $('<span id="config">').prop('outerHTML')]);
        super.updateTable('sysinfo-table', rows);
    }
}
