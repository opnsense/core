/*
 * Copyright (C) 2025 Deciso B.V.
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

export default class DnsmasqLeases extends BaseTableWidget {

    getGridOptions() {
        return {
            sizeToContent: 650
        };
    }

    getMarkup() {
        return this.createTable('dnsmasqLeasesTable', {
            headerPosition: 'left'
        });
    }

    async onWidgetTick() {
        const settingsResponse = await this.ajaxCall(`/api/dnsmasq/settings/${'get'}`);
        if (settingsResponse?.dnsmasq?.enable !== '1') {
            this.displayError(this.translations.unconfigured, '/ui/dnsmasq/settings#general');
            return;
        }

        const leasesResponse = await this.ajaxCall(`/api/dnsmasq/leases/${'search'}`);
        const leaseRows = leasesResponse?.rows ?? [];
        const leaseTotalCount = Number.isFinite(leasesResponse?.total) ? leasesResponse.total : leaseRows.length;

        if (leaseTotalCount === 0) {
            this.displayError(this.translations.noleases, '/ui/dnsmasq/leases');
            return;
        }

        if (!this.dataChanged('dhcp-leases', leaseRows)) {
            return;
        }

        $('#dnsmasqLeasesTable').empty();
        this.renderGauge(settingsResponse, leaseTotalCount);
        this.processLeases(leaseRows);
    }

    displayError(message, linkHref) {
        $('#dnsmasqLeasesTable').empty().append(
            `<div class="error-message">${linkHref ? `<a href="${linkHref}">${message}</a>` : message}</div>`
        );
    }

    renderGauge(settingsResponse, currentLeaseCount) {
        const rawMax = settingsResponse?.dnsmasq?.dhcp?.lease_max ?? '';
        // 1000 leases are dnsmasq default
        const leaseMaxValue = String(rawMax).trim() === '' ? 1000 : parseInt(rawMax, 10);
        const leaseUsedCount = Math.min(currentLeaseCount, leaseMaxValue);
        const leaseUsedPercent = Math.max(0, Math.min(100, Math.round((leaseUsedCount / (leaseMaxValue || 1)) * 100)));

        const gaugeColumnContent = `
            <div style="grid-column:1 / -1;width:100%;">
                <div class="progress" role="progressbar"
                     aria-valuenow="${leaseUsedPercent}" aria-valuemin="0" aria-valuemax="100"
                     style="height:18px; margin-top:3px; position:relative;">
                    <div class="progress-bar" style="width:${leaseUsedPercent}%;"></div>
                    <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                                font-weight:bold; pointer-events:none; z-index:2;">
                        ${leaseUsedCount} / ${leaseMaxValue} (${leaseUsedPercent}%)
                    </div>
                </div>
            </div>
        `;

        super.updateTable('dnsmasqLeasesTable', [[gaugeColumnContent, '']], 'dhcp-header');

        // XXX: Ideally this should be in stylesheet, but this works too
        const headerRowElement = $('#dnsmasqLeasesTable .flextable-row[id$="_dhcp-header"]');
        headerRowElement.find('.flex-cell.first').css({ flex: '0 0 100%', width: '100%', maxWidth: '100%' });
        headerRowElement.find('.flex-cell').eq(1).hide();
    }

    processLeases(leaseRows) {
        leaseRows
            .map(row => ({
                hostname: row.hostname || this.translations.notavailable,
                ipAddress: row.address || this.translations.notavailable,
                expireTimestamp: Number(row.expire) || 0
            }))
            // We sort via the most distant timestamp first, as that is most likely the latest lease
            .sort((a, b) => b.expireTimestamp - a.expireTimestamp)
            // Limit the amount of displayed leases
            .slice(0, 10)
            .forEach(lease => {
                const hostname = (lease.hostname === '*') ? this.translations.notavailable : lease.hostname;
                const header = `
                    <div style="display:flex;align-items:center;">
                        <i class="fa fa-laptop fa-fw text-primary" style="margin-right:5px;"></i>
                        <span style="font-weight:bold;">${hostname}</span>
                    </div>
                `;
                const link = `/ui/dnsmasq/leases#search=${encodeURIComponent(lease.ipAddress)}`;
                const row = `<div><a href="${link}">${lease.ipAddress}</a></div><div></div>`;
                super.updateTable('dnsmasqLeasesTable', [[header, row]], lease.ipAddress);
            });
    }
}
