/*
 * Copyright (C) 2026 Deciso B.V.
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

export default class KeaLeases extends BaseTableWidget {

    constructor(config) {
        super(config);
        this.configurable = true;
        this.configChanged = false;
    }

    getGridOptions() {
        return {
            sizeToContent: 650
        };
    }

    getMarkup() {
        return this.createTable('keaLeasesTable', {
            headerPosition: 'left'
        });
    }

    async onWidgetTick() {
        $('[data-toggle="tooltip"]').tooltip({container: 'body'});

        const [settingsV4Response, settingsV6Response] = await Promise.all([
            this.ajaxCall(`/api/kea/dhcpv4/${'get'}`),
            this.ajaxCall(`/api/kea/dhcpv6/${'get'}`)
        ]);
        const dhcpv4Enabled = settingsV4Response?.dhcpv4?.general?.enabled === '1';
        const dhcpv6Enabled = settingsV6Response?.dhcpv6?.general?.enabled === '1';
        if (!dhcpv4Enabled && !dhcpv6Enabled) {
            this.displayError(this.translations.unconfigured, '/ui/kea/dhcp/v4#settings');
            return;
        }

        const config = await this.getWidgetConfig() || {};
        const limit = parseInt(config.leasesToShow ?? '2', 10) || 2;

        const requestBody = JSON.stringify({
            rowCount: Math.max(1, limit),
            sort: { expire: 'desc' }
        });

        const [leases4Response, leases6Response] = await Promise.all([
            this.ajaxCall(`/api/kea/leases4/${'search'}`, requestBody, 'POST'),
            this.ajaxCall(`/api/kea/leases6/${'search'}`, requestBody, 'POST')
        ]);

        const leases4Rows = leases4Response?.rows ?? [];
        const leases6Rows = leases6Response?.rows ?? [];

        const leases4Tag = leases4Rows.map(row => Object.assign({}, row, { leaseFamily: 'v4' }));
        const leases6Tag = leases6Rows.map(row => Object.assign({}, row, { leaseFamily: 'v6' }));

        const expireValue = n => Number(n?.expire) || 0;
        // combine v4 and v6 leases, sort by expire time and limit to the configured number of leases to show
        const leaseRows = leases4Tag.concat(leases6Tag).sort((a, b) => expireValue(b) - expireValue(a)).slice(0, Math.max(1, limit));

        const leases4TotalCount = Number.isFinite(leases4Response?.total) ? leases4Response.total : leases4Rows.length;
        const leases6TotalCount = Number.isFinite(leases6Response?.total) ? leases6Response.total : leases6Rows.length;
        const leaseTotalCount = leases4TotalCount + leases6TotalCount;

        if (leaseTotalCount === 0) {
            this.displayError(this.translations.noleases, '/ui/kea/dhcp/leases4');
            return;
        }

        if (!this.dataChanged('dhcp-leases', leaseRows) && !this.configChanged) {
            return;
        }
        this.configChanged = false;

        $('#keaLeasesTable').empty();
        this.processLeases(leaseRows.slice(0, Math.max(1, limit)));
        $('[data-toggle="tooltip"]').tooltip('hide');
    }

    displayError(message, linkHref) {
        $('#keaLeasesTable').empty().append(
            `<div class="error-message">${linkHref ? `<a href="${linkHref}">${message}</a>` : message}</div>`
        );
    }

    processLeases(leaseRows) {
        const stateLabels = ['assigned', 'declined', 'assigned expired', 'declined expired', 'expired-reclaimed'];
        const normalizeState = row => {
            const rawState = row?.state ?? '';
            const numericState = Number(rawState);
            if (Number.isInteger(numericState) && stateLabels[numericState]) {
                return stateLabels[numericState];
            }
            return String(rawState);
        };

        let activeCount = 0;
        let inactiveCount = 0

        leaseRows.forEach(row => {
            const state = normalizeState(row);
            if (state === 'assigned') {
                activeCount++;
            } else {
                inactiveCount++;
            }
        });

        const summaryRow = `
            <div>
                <span>
                    Active: ${activeCount} |
                    Inactive: ${inactiveCount} |
                    Total: ${leaseRows.length}
                </span>
            </div>
        `;

        super.updateTable('keaLeasesTable', [[summaryRow, '']], 'kea-summary');

        leaseRows
            .map(row => ({
                hostname: row.hostname || this.translations.notavailable,
                ipAddress: row.address || this.translations.notavailable,
                hwaddr: row.hwaddr || this.translations.notavailable,
                leaseFamily: row.leaseFamily || (String(row.address || '').includes(':') ? 'v6' : 'v4')
            }))
            .forEach(lease => {
                const hostname = (lease.hostname === '*') ? this.translations.notavailable : lease.hostname;
                const header = `
                    <div style="display:flex;align-items:center;">
                        <i class="fa fa-laptop fa-fw text-primary"
                           style="margin-right:5px;"
                           data-toggle="tooltip"
                           title="${lease.hwaddr}"></i>
                        <span style="font-weight:bold;">${hostname}</span>
                    </div>
                `;
                const leasesPath = lease.leaseFamily === 'v6' ? 'leases6' : 'leases4';
                const link = `/ui/kea/dhcp/${leasesPath}#search=${encodeURIComponent(lease.ipAddress)}`;
                const row = `<div><a href="${link}">${lease.ipAddress}</a></div><div></div>`;
                super.updateTable('keaLeasesTable', [[header, row]], `${lease.leaseFamily}-${lease.ipAddress}`);
            });

    }

    async getWidgetOptions() {
        return {
            leasesToShow: {
                id: 'leasesToShow',
                title: this.translations.leasesselect,
                type: 'select',
                options: [
                    { value: '2',  label: '2'  },
                    { value: '5',  label: '5'  },
                    { value: '10', label: '10' },
                    { value: '20', label: '20' },
                    { value: '30', label: '30' },
                    { value: '40', label: '40' },
                    { value: '50', label: '50' }
                ],
                default: '2',
                required: true
            }
        };
    }

    async onWidgetOptionsChanged() {
        this.configChanged = true;
        await this.onWidgetTick();
    }
}