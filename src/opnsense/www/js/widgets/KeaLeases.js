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
            this.ajaxCall(`/api/kea/dhcpv4/get`),
            this.ajaxCall(`/api/kea/dhcpv6/get`)
        ]);

        const dhcpv4Enabled = settingsV4Response?.dhcpv4?.general?.enabled === '1';
        const dhcpv6Enabled = settingsV6Response?.dhcpv6?.general?.enabled === '1';

        if (!dhcpv4Enabled && !dhcpv6Enabled) {
            this.displayError(this.translations.unconfigured, '/ui/kea/dhcp/v4#settings');
            return;
        }

        const config = await this.getWidgetConfig();
        const limit = parseInt(config.leasesToShow ?? '2');

        const statsRequestBody = JSON.stringify({});
        const leasesRequestBody = JSON.stringify({
            rowCount: limit,
            sort: { expire: 'desc' }
        });

        const [stats4Response, stats6Response, leases4Response, leases6Response] = await Promise.all([
            this.ajaxCall(`/api/kea/leases4/stats`, statsRequestBody, 'GET'),
            this.ajaxCall(`/api/kea/leases6/stats`, statsRequestBody, 'GET'),
            this.ajaxCall(`/api/kea/leases4/search`, leasesRequestBody, 'POST'),
            this.ajaxCall(`/api/kea/leases6/search`, leasesRequestBody, 'POST')
        ]);

        const leases4Rows = leases4Response?.rows ?? [];
        const leases6Rows = leases6Response?.rows ?? [];

        // tag leasefamily for each lease
        leases4Rows.forEach(row => row.leaseFamily = 'v4');
        leases6Rows.forEach(row => row.leaseFamily = 'v6');

        // combine v4 and v6 leases and sort by expire time
        const leaseRows = leases4Rows.concat(leases6Rows).sort((a, b) => b.expire - a.expire);

        // combine v4 and v6 lease counts
        const stats = {
            activeCount: (stats4Response?.active ?? 0) + (stats6Response?.active ?? 0),
            inactiveCount: (stats4Response?.inactive ?? 0) + (stats6Response?.inactive ?? 0),
            totalCount: (stats4Response?.total ?? 0) + (stats6Response?.total ?? 0)
        };

        if (stats.totalCount === 0) {
            this.displayError(this.translations.noleases, '/ui/kea/dhcp/leases4');
            return;
        }

        if (!this.dataChanged('dhcp-leases', leaseRows) && !this.configChanged) {
            return;
        }
        this.configChanged = false;

        $('#keaLeasesTable').empty();
        this.processLeases(leaseRows, stats);
        $('[data-toggle="tooltip"]').tooltip('hide');
    }

    displayError(message, linkHref) {
        $('#keaLeasesTable').empty().append(
            `<div class="error-message">${linkHref ? `<a href="${linkHref}">${message}</a>` : message}</div>`
        );
    }

    processLeases(leaseRows, stats) {
        const summaryRow = `
            <div>
                <span>
                    Active: ${stats.activeCount} |
                    Inactive: ${stats.inactiveCount} |
                    Total: ${stats.totalCount}
                </span>
            </div>
        `;

        super.updateTable('keaLeasesTable', [[summaryRow, '']], 'kea-summary');

        const notAvailable = this.translations.notavailable;

        leaseRows.forEach(lease => {
            const hostname = (lease.hostname === '*' || !lease.hostname) ? notAvailable : lease.hostname;
            const ipAddress = lease.address || notAvailable;
            const hwaddr = lease.hwaddr || notAvailable;
            const leaseFamily = lease.leaseFamily;

            const header = `
                <div style="display:flex;align-items:center;">
                    <i class="fa fa-laptop fa-fw text-primary"
                        style="margin-right:5px;"
                        data-toggle="tooltip"
                        title="${hwaddr}"></i>
                    <span style="font-weight:bold;">${hostname}</span>
                </div>
            `;

            const leasesPath = leaseFamily === 'v4' ? 'leases4' : 'leases6';
            const link = `/ui/kea/dhcp/${leasesPath}#search=${encodeURIComponent(ipAddress)}`;
            const row = `<div><a href="${link}">${ipAddress}</a></div><div></div>`;
            super.updateTable('keaLeasesTable', [[header, row]], `${leaseFamily}-${ipAddress}`);
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