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

/*
 * Leases widget
 * @author lucaspalomodevelop <lucas.palomo@t-online.de>
 */

export default class Leases extends BaseTableWidget {
  constructor() {
    super();
  }

  addHTML(list) {
    // Iterate through each row in the list
    for (let i = 0; i < list.length; i++) {
      let row = list[i]; // Extract the current row
      let mac = row[0]; // MAC address
      let address = row[1]; // IP address
      let hostname = row[2]; // Hostname

      // Determine the type (dynamic or static) and apply corresponding HTML styling
      let type =
        row[3] == "dynamic"
          ? `<p style="color:blue;" > ${this.translations.dynamic} </p>` // Dynamic type in blue
          : `<p style="color:orange" > ${this.translations.static} </p>`; // Static type in orange

      // Determine the state (active or inactive) and apply corresponding HTML styling
      let state =
        row[4] == "active"
          ? `<p style="color:green" > ${this.translations.active} </p>` // Active state in green
          : `<p style="color:red"> ${this.translations.inactive} </p>`; // Inactive state in red

      // Determine the status (online or offline) and apply corresponding HTML styling
      let status =
        row[5] == "online"
          ? `<p style="color:green" > ${this.translations.online} </p>` // Online status in green
          : `<p style="color:red"> ${this.translations.offline} </p>`; // Offline status in red

      // Replace the current row with the updated values, including the styled HTML
      list[i] = [mac, address, hostname, type, state, status];
    }

    // Return the updated list with HTML formatting applied
    return list;
  }

  getMarkup() {
    let $container = $("<div></div>");
    let $table = this.createTable("leases-table", {
      headerPosition: "top",
      headers: [
        this.translations.mac,
        this.translations.address,
        this.translations.hostname,
        this.translations.type,
        this.translations.state,
        this.translations.status,
      ],
    });
    $container.append($table);
    return $container;
  }

  async onWidgetTick() {
    const datav4 = await this.ajaxCall("/api/dhcpv4/leases/searchLease/");
    const datav6 = await this.ajaxCall("/api/dhcpv6/leases/searchLease/");
    let rows = [];
    for (let lease of datav4.rows) {
      rows.push([
        lease.mac,
        lease.address,
        lease.hostname,
        lease.type,
        lease.state,
        lease.status,
      ]);
    }
    for (let lease of datav6.rows) {
      rows.push([
        lease.duid,
        lease.address,
        lease.hostname || "",
        lease.type,
        lease.state,
        lease.status,
      ]);
    }

    if (!rows || rows.length === 0) {
      this.displayError(this.translations.noitems);
      return;
    }

    rows.sort((a, b) => {
      return a[1].localeCompare(b[1]);
    });

    rows = this.addHTML(rows);

    super.updateTable("leases-table", rows);
  }
}
