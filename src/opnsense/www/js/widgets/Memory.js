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

import BaseGaugeWidget from "./BaseGaugeWidget.js";

export default class Memory extends BaseGaugeWidget {
    constructor() {
        super();

        this.tickTimeout = 60;
    }

    async onMarkupRendered() {
        let colorMap = ['#D94F00', '#A8C49B', '#E5E5E5'];

        super.createGaugeChart({
            colorMap: colorMap,
            labels: [this.translations.used, this.translations.arc, this.translations.free],
            tooltipLabelCallback: (tooltipItem) => {
                return `${tooltipItem.label}: ${tooltipItem.parsed} MB`;
            },
            primaryText: (data) => {
                return `${(data[0] / (data[0] + data[1] + data[2]) * 100).toFixed(2)}%`;
            },
            secondaryText: (data) => {
                return `(${data[0]} / ${data[0] + data[1] + data[2]}) MB`;
            }
        });
    }

    async onWidgetTick() {
        const data = await this.ajaxCall('/api/diagnostics/system/systemResources');
        if (data.memory.total !== undefined) {
            let used = parseInt(data.memory.used_frmt);
            let arc = data.memory.hasOwnProperty('arc') ? parseInt(data.memory.arc_frmt) : 0;
            let total = parseInt(data.memory.total_frmt);
            super.updateChart([(used - arc), arc, total - used]);
        }
    }
}
