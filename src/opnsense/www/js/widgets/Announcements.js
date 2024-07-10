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

export default class Announcements extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 3600;
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
            sizeToContent: 350
        }
    }

    getMarkup() {
        let $announcements_table = this.createTable('announcements-table', {
            headerPosition: 'none',
        });

        return $('<div></div>').append($announcements_table);
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/core/dashboard/product_info_feed');

        if (!data.items.length) {
            $('#announcements-table').html(`${this.translations.no_feed}`);
            return;
        }
        let rows = [];
        data.items.forEach(({ title, description, link, pubDate, guid }) => {
            description = $('<div/>').html(description).text();
            rows.push(`
                    <div>
                        <a href="${link}" target='_new'">${title}</a>
                    </div>
                    <div>
                        ${description}
                    </div>
                `);
        });
        this.updateTable('announcements-table', rows.map(row => [row]));
    }
}
