/*
 * Copyright (C) 2024 Cedrik Pischem
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

export default class Certificates extends BaseTableWidget {

    constructor(config) {
        super(config);
        this.tickTimeout = 30;
        this.configurable = true;
        this.configChanged = false;
    }

    getGridOptions() {
        return {
            sizeToContent: 650
        };
    }

    getMarkup() {
        const $container = $('<div></div>');
        const $certificateTable = this.createTable('certificateTable', {
            headerPosition: 'none'
        });

        $container.append($certificateTable);
        return $container;
    }

    // Do not trigger danger modal if endpoint does not exist
    async endpointExists(url) {
        return new Promise((resolve) => {
            $.ajax({
                url: url,
                type: 'HEAD',
                success: function() {
                    resolve(true);
                },
                error: function() {
                    resolve(false);
                }
            });
        });
    }

    async onWidgetTick() {
        const cas = (await this.ajaxCall('/api/trust/ca/search')).rows || [];
        const certs = (await this.ajaxCall('/api/trust/cert/search')).rows || [];
        let apacheCerts = [];

        // This endpoint only exists when os-OPNWAF is installed
        if (await this.endpointExists('/api/apache/gateway/certs')) {
            apacheCerts = (await this.ajaxCall('/api/apache/gateway/certs')).rows || [];
        }

        if (cas.length === 0 && certs.length === 0 && apacheCerts.length === 0) {
            this.displayError(`${this.translations.noitems}`);
            return;
        }

        this.clearError();
        await this.processCertificates(cas, certs, apacheCerts);
    }

    displayError(message) {
        const $error = $(`<div class="error-message">${message}</div>`);
        $('#certificateTable').empty().append($error);
    }

    clearError() {
        $('#certificateTable .error-message').remove();
    }

    processItems(items, type, hiddenItems, rows) {
        items.forEach(item => {
            if (!hiddenItems.includes(item.descr)) {
                const validTo = new Date(parseInt(item.valid_to) * 1000);
                const now = new Date();
                const remainingDays = Math.max(0, Math.floor((validTo - now) / (1000 * 60 * 60 * 24)));

                const colorClass = remainingDays === 0
                    ? 'text-danger'
                    : remainingDays < 14
                        ? 'text-warning'
                        : 'text-success';

                const statusText = remainingDays === 0 ? this.translations.expired : this.translations.valid;
                const iconClass = remainingDays === 0 ? 'fa fa-unlock' : 'fa fa-lock';

                const row = `
                    <div>
                        <i class="${iconClass} ${colorClass} certificate-tooltip" style="cursor: pointer;"
                            data-tooltip="${type}-${item.descr}" title="${statusText}">
                        </i>
                        &nbsp;
                        <span><b>${item.descr}</b></span>
                        <br/>
                        <div style="margin-top: 5px; margin-bottom: 5px;">
                            <i>${this.translations.expires}</i> ${remainingDays} ${this.translations.days},
                            ${validTo.toLocaleString()}
                        </div>
                    </div>`;
                rows.push({ html: row, expirationDate: validTo });
            }
        });
    }

    async processCertificates(cas, certs, apacheCerts) {
        const config = await this.getWidgetConfig() || {};

        if (!this.dataChanged('certificates', { cas, certs, apacheCerts }) && !this.configChanged) {
            return;
        }

        if (this.configChanged) {
            this.configChanged = false;
        }

        $('.certificate-tooltip').tooltip('hide');

        const hiddenItems = config.hiddenItems || [];
        const rows = [];

        if (cas.length > 0) {
            this.processItems(cas, 'ca', hiddenItems, rows);
        }

        if (certs.length > 0) {
            this.processItems(certs, 'cert', hiddenItems, rows);
        }

        if (apacheCerts.length > 0) {
            this.processItems(apacheCerts, 'apache', hiddenItems, rows);
        }

        if (rows.length === 0) {
            this.displayError(`${this.translations.noitems}`);
            return;
        }

        // Sort rows by expiration date from earliest to latest
        rows.sort((a, b) => a.expirationDate - b.expirationDate);

        const sortedRows = rows.map(row => [row.html]);
        super.updateTable('certificateTable', sortedRows);

        $('.certificate-tooltip').tooltip({ container: 'body' });
    }

    async getWidgetOptions() {
        const [caResponse, certResponse] = await Promise.all([
            this.ajaxCall('/api/trust/ca/search'),
            this.ajaxCall('/api/trust/cert/search')
        ]);

        let apacheRows = [];

        // Use endpointExists to check for Apache endpoint availability
        if (await this.endpointExists('/api/apache/gateway/certs')) {
            const apacheResponse = await this.ajaxCall('/api/apache/gateway/certs');
            apacheRows = apacheResponse.rows || [];
        }

        const hiddenItemOptions = [];

        if (caResponse.rows) {
            caResponse.rows.forEach(ca => {
                hiddenItemOptions.push({ value: `ca-${ca.descr}`, label: ca.descr });
            });
        }

        if (certResponse.rows) {
            certResponse.rows.forEach(cert => {
                hiddenItemOptions.push({ value: `cert-${cert.descr}`, label: cert.descr });
            });
        }

        if (apacheRows.length > 0) {
            apacheRows.forEach(cert => {
                hiddenItemOptions.push({ value: `apache-${cert.descr}`, label: cert.descr });
            });
        }

        return {
            hiddenItems: {
                title: this.translations.hiddenitems,
                type: 'select_multiple',
                options: hiddenItemOptions,
                default: [],
                required: false
            }
        };
    }

    async onWidgetOptionsChanged() {
        this.configChanged = true;
    }
}

