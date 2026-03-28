/*
 * Copyright (C) 2026 Konstantinos Spartalis (cspartalis@potatonetworks.com)
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

export default class Notes extends BaseWidget {
    constructor(config) {
        super(config);
        this.titleVisible = false;
        this.configurable = true;
        this.dialogTitle = `${this.translations.titleedit}`;
    }

    getMarkup() {
        return $(`
        <div id="notes-container-${this.id}" class="widget-content">
            <div id="notes-text-${this.id}" style="padding: 10px; white-space: pre-wrap; word-wrap: break-word;"></div>
        </div>
        `);
    }

    async getWidgetOptions() {
        return {
            note: {
                title: this.translations.title,
                type: 'textarea',
                id: `notes-option-${this.id}`,
                default: '',
            },
        };
    }

    async onWidgetOptionsChanged(options) {
        $(`#notes-text-${this.id}`).text(options.note || '');
        this.config.callbacks.updateGrid();
    }

    async onMarkupRendered() {
        const config = await this.getWidgetConfig();
        const note = config.note || '';

        $(`#notes-text-${this.id}`).text(note);
    }
}
