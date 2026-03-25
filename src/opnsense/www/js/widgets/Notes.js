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

    }

    getMarkup() {
        let $container = $(`
        <div id="notes-container-${this.id}" class="widget-content">
            <div style="padding: 10px;">
                <textarea
                    id="notes-text-${this.id}" maxlength="8192"
                    style="
                        min-width: 0;
                        resize: none;
                        min-height: 150px;
                        margin-bottom: 10px;
                        max-width: 100%;
                        max-height: 100%;
                        flex-grow: 1;
                        box-sizing: border-box;
                    ">
                </textarea>
                <div style="display: flex; justify-content: flex-end; align-items: center;">
                    <span id="notes-saved-msg-${this.id}" style="color: green; margin-right: 10px; display: none;">
                        <i class="fa fa-check"></i> ${this.translations.saved}
                    </span>
                    <button id="notes-save-btn-${this.id}" class="btn btn-primary btn-sm">
                        ${this.translations.save}
                    </button>
                </div>
            </div>
        </div>
        `);
        return $container;
    }

    async onMarkupRendered() {
        const textElement = $(`#notes-text-${this.id}`);
        const saveButton = $(`#notes-save-btn-${this.id}`);
        const savedMsg = $(`#notes-saved-msg-${this.id}`);

        const data = await this.ajaxCall('/api/core/dashboard/getNote');
        if (data.result === 'ok') {
            textElement.val(data.note);
        }

        $(saveButton).on('click', async () => {
            $(saveButton).prop('disabled', true);
            const result = await this.ajaxCall('/api/core/dashboard/saveNote', JSON.stringify({
                note: textElement.val()
            }), 'POST');

            $(saveButton).prop('disabled', false);
            if (result.result === 'saved') {
                $(savedMsg).fadeIn().delay(2000).fadeOut();
            }
        });
    }
}
