// endpoint:/api/ipsec/*

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

import BaseTableWidget from "./BaseTableWidget.js";

export default class IpsecLeases extends BaseTableWidget {
    constructor() {
        super();
        this.resizeHandles = "e, w";
    }

    getGridOptions() {
        return {
            // Set the widget to automatically trigger vertical scrolling after reaching 650px in height
            sizeToContent: 650
        }
    }

    getMarkup() {
        let $container = $('<div></div>');
        // Create a table for displaying IPsec leases and assign it an ID for easy access
        let $ipsecLeaseTable = this.createTable('ipsecLeaseTable', {
            headerPosition: 'none'
        });

        $container.append($ipsecLeaseTable);
        return $container;
    }

    async onWidgetTick() {
        // First, check if IPsec is enabled by fetching the status from the API
        const ipsecStatusResponse = await ajaxGet('/api/ipsec/Connections/isEnabled', {});
        const isIpsecEnabled = ipsecStatusResponse.enabled; // Specifically check the "enabled" property

        if (!isIpsecEnabled) {
            // Display an error message if IPsec is not enabled
            const $error = $('<div class="error-message">IPsec is currently disabled. Please enable it to view lease information.</div>');
            $('#ipsecLeaseTable').empty().append($error);  // Clear the table and show the error message
            return;
        }

        // Proceed with fetching the IPsec leases data if IPsec is enabled
        await ajaxGet('/api/ipsec/leases/pools', {}, (data, status) => {
            let users = {}; // Initialize an object to store user data indexed by user names

            // Process each lease record to organize data by user
            data.leases.forEach(lease => {
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

            // Calculate and display the number of users currently online and total users
            let onlineUsersCount = Object.values(users).filter(user => user.online).length;
            let totalUsersCount = Object.keys(users).length;
            let offlineUsersCount = totalUsersCount - onlineUsersCount;

            // Prepare a summary row displaying total, online, and offline user counts
            let userCountsRow = `
                <div>
                    <span><b>Users:</b> ${totalUsersCount} - <b>Online:</b> ${onlineUsersCount} - <b>Offline:</b> ${offlineUsersCount}</span>
                </div>`;

            let rows = [];
            // Prepare HTML content for each user showing their details and IP addresses
            Object.keys(users).forEach(user => {
                let userStatusClass = users[user].online ? 'text-success' : 'text-danger'; // Set class based on online status
                let userStatusTitle = users[user].online ? 'Online' : 'Offline'; // Tooltip text

                // Construct a detailed row for each user
                let row = `
                    <div>
                        <i class="fa fa-user ${userStatusClass}" style="cursor: pointer;"
                            data-toggle="tooltip" title="${userStatusTitle}">
                        </i>
                        &nbsp;
                        <span><b>${user}</b></span>
                        <br/>
                        <div style="margin-top: 5px; margin-bottom: 5px;">
                            ${users[user].ipAddresses.map(ip => `<div>${ip.address}</div>`).join('')}
                        </div>
                    </div>`;

                rows.push({ html: row, online: users[user].online });
            });

            // Sort rows so that online users appear first
            rows.sort((a, b) => b.online - a.online);
            // Add the user count summary at the beginning of the rows
            rows.unshift({ html: userCountsRow });
            // Update the HTML table with the sorted rows
            super.updateTable('ipsecLeaseTable', rows.map(row => [row.html]));
            // Activate tooltips for new dynamic elements
            $('[data-toggle="tooltip"]').tooltip();
        });
    }

    onWidgetResize(elem, width, height) {
        // Adjust visibility of details based on the width of the widget
        if (width > 450) {
            $('.user-info-detail').show();
        } else {
            $('.user-info-detail').hide();
        }

        return super.onWidgetResize(elem, width, height);
    }
}
