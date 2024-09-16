/*
 * Copyright (C) 2024 Wogglesoft
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

export default class Leases extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 10;
    }

    getGridOptions() {
        return {
            // Trigger overflow-y:scroll after 650px height
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $table = this.createTable('leaseTable', {
            headerPosition: 'top',
            headers: [
                this.translations.ip,
                this.translations.hostname,
                this.translations.mac
            ]
        });

        $container.append($table);
        return $container;
    }

    async onWidgetTick() {
        // Check if DHCP is enabled
        const statusData = await this.ajaxCall('/api/dhcpv4/service/status');
        if (!statusData || statusData.status !== "running") {
            this.displayError(this.translations.servicedisabled);
            return;
        }

        // Fetch lease information
        let eparams =  {
                    current:1,
                    inactive:false,
                    rowCount:-1,
                    searchPhrase:"",
                    selected_interfaces:[],
                    sort: {}
                };
        const leaseData = await this.ajaxCall('/api/dhcpv4/leases/searchLease', JSON.stringify(eparams),'POST');
        if (!leaseData || !leaseData.rows || leaseData.rows.length === 0) {
            this.displayError(this.translations.nolease);
            return;
        }

        this.processLeases(leaseData.rows);
    }

    // Utility function to display errors within the widget
    displayError(message) {
        const $error = $(`
            <div class="error-message">
                <a href="/ui/dhcpv4/leases" target="_blank">${message}</a>
            </div>
        `);
        $('#leaseTable').empty().append($error);
    }

    processLeases(leases) {
        if (!this.dataChanged('leases', leases)) {
            return;
        }

        $('.dhcp-tooltip').tooltip('hide');

        let rows = [];
        leases.forEach(lease => {
            let colorClass = lease.status === "online" ? 'text-success' : 'text-danger';
            let tooltipText = lease.status === "online" ? this.translations.enabled : this.translations.disabled;

            let currentIp = lease.address || this.translations.undefined;
            let currentMac = lease.mac || this.translations.undefined;
            let currentHostname = lease.hostname || this.translations.undefined;

            let row = [
                `
                    <div class="leases-ip" style="white-space: nowrap;">
                    <a href="/ui/dhcpv4/leases" target="_blank"><i class="fa fa-circle ${colorClass} dhcp-tooltip" style="cursor: pointer;" title="${tooltipText}"></i></a>&nbsp;${lease.address}
                    </div>`,
                    `<div class="leases-host">${currentHostname}</div>`,
                    `<div class="leases-mac"><small>${currentMac}</small></div>`
            ];

            rows.splice(0,0,row);
        });

        // Update table with rows
        super.updateTable('leaseTable', rows);

        // Initialize tooltips
        $('.dhcp-tooltip').tooltip({container: 'body'});
    }

    onWidgetResize(elem, width, height) {
        if (width < 320) {
            $('#header_leaseTable').hide();
            $('.leases-mac').parent().hide();
        } else {
            $('#header_leaseTable').show();
            $('.leases-mac').parent().show();
        }
        return true; // Return true to force the grid to update its layout
    }
}
