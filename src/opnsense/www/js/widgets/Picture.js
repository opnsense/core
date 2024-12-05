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

export default class Picture extends BaseWidget {
    constructor(config) {
        super(config);
    }

    getMarkup() {
        return $('<div id="picture-container" style="padding: 5px;"><img id="picture" class="img-responsive center-block"></div>');
    }

    async onMarkupRendered() {
        const data = await this.ajaxCall('/api/core/dashboard/picture');
        if (data.result !== 'ok') {
            this._showError();
            return;
        }

        $('#picture').attr('src', `data:${data.mime};base64,${data.picture}`);
        $('#picture').on('load', () => {
            this.config.callbacks.updateGrid();
        });
        $('#picture').on('error', () => {
            this._showError();
        });
    }

    _showError() {
        $('#picture-container').html(`
            <div class="error-message">
                <a href="/system_general.php">${this.translations.nopicture}</a>
            </div>
        `);
    }
}
