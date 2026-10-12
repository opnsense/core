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

export default class IpsecLeases extends BaseTableWidget {
    constructor() {
        super();
        this.tickTimeout = 4;
    }

    getGridOptions() {
        return {
            // Automatically triggers vertical scrolling after reaching 650px in height
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $ipsecLeaseTable = this.createTable('ipsecLeaseTable', {
            headerPosition: 'none'
        });

        $container.append($ipsecLeaseTable);
        return $container;
    }

    async onWidgetTick() {
        const ipsecStatusResponse = await this.ajaxCall('/api/ipsec/connections/is_enabled');

        if (!ipsecStatusResponse.enabled) {
            this.displayError(`${this.translations.unconfigured}`);
            return;
        }

        const data = await this.ajaxCall('/api/ipsec/leases/pools');

        if (!data || !data.leases || data.leases.length === 0) {
            this.displayError(`${this.translations.noleases}`);
            return;
        }

        if (!this.dataChanged('ipsecleases', data.leases)) {
            return; // No changes detected, do not update the UI
        }

        this.processLeases(data.leases);
    }

    // Utility function to display errors within the widget
    displayError(message) {
        const $error = $(`<div class="error-message"><a href="/ui/ipsec/connections">${message}</a></div>`);
        $('#ipsecLeaseTable').empty().append($error);
    }

    // Function to process leases data and update the UI accordingly
    processLeases(newLeases) {
        $('.ipsecleases-status-icon').tooltip('hide');

        let users = {}; // Initialize an object to store user data indexed by user names

        // Organize leases by user
        newLeases.forEach(lease => {
            if (!users[lease.user]) {
                users[lease.user] = {
                    ipAddresses: [], // List of IP addresses assigned to the user
                    online: false    // Online status initially false
                };
            }
            users[lease.user].ipAddresses.push({ address: lease.address, online: lease.online });
            // Set the user's status to online if any of their IP addresses is online
            if (lease.online) {
                users[lease.user].online = true;
            }
        });

        // Sort users by online status, placing online users first
        let sortedUsers = Object.keys(users).sort((a, b) => {
            return users[a].online === users[b].online ? 0 : users[a].online ? -1 : 1;
        });

        // Calculate and display the number of users currently online and total users
        let onlineUsersCount = Object.values(users).filter(user => user.online).length;
        let totalUsersCount = Object.keys(users).length;
        let offlineUsersCount = totalUsersCount - onlineUsersCount;

        // Prepare a summary row for user counts
        let userCountsRow = `
            <div>
                <span>${this.translations.users}: ${totalUsersCount} | ${this.translations.online}: ${onlineUsersCount} | ${this.translations.offline}: ${offlineUsersCount}</span>
            </div>`;

        let rows = [userCountsRow];
        // Prepare HTML content for each user showing their status and IP addresses
        sortedUsers.forEach(user => { // Use sortedUsers instead of Object.keys(users)
            let userStatusClass = users[user].online ? 'text-success' : 'text-danger';
            let userStatusTitle = users[user].online ? this.translations.online : this.translations.offline;

            let row = `
                <div>
                    <i class="fa fa-user ${userStatusClass} ipsecleases-status-icon" style="cursor: pointer;"
                        data-toggle="tooltip" title="${userStatusTitle}">
                    </i>
                    &nbsp;
                    <span><b>${user}</b></span>
                    <br/>
                    <div style="margin-top: 5px; margin-bottom: 5px;">
                        ${users[user].ipAddresses.map(ip => `<div>${ip.address}</div>`).join('')}
                    </div>
                </div>`;
            rows.push(row);
        });

        // Update the HTML table with the sorted rows
        super.updateTable('ipsecLeaseTable', rows.map(row => [row]));

        // Activate tooltips for new dynamic elements
        $('.ipsecleases-status-icon').tooltip({container: 'body'});
    }
}
