/**
 *    Copyright (C) 2024 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 */

import BaseWidget from "./BaseWidget.js";

export default class BaseTableWidget extends BaseWidget {
    constructor() {
        super();

        this.options = null;
        this.data = [];

        this.curSize = null;
        this.sizeStates = {
            0: {
                '.flextable-row': {'padding': ''},
                '.flextable-header .flex-cell': {'border-bottom': 'solid 1px'},
                '.flex-cell': {'width': '100%'},
                '.column': {'width': '100%'},
                '.flex-subcell': {'width': '100%'},
            },
            400: {
                '.flextable-row': {'padding': '0.5em 0.5em'},
                '.flextable-header .flex-cell': {'border-bottom': ''},
                '.flex-cell': {'width': this._calculateColumnWidth.bind(this)},
                '.column .flex-cell': {'width': '100%'},
                '.column': {'width': ''},
                '.flex-subcell': {'width': ''},
            }
        }
        this.widths = Object.keys(this.sizeStates).sort();

        this.flextableId = Math.random().toString(36).substring(7);
        this.$flextable = null;
        this.$headerContainer = null;
    }

    _calculateColumnWidth() {
        if (this.options !== null && this.data !== null) {
            switch (this.options.headerPosition) {
                case 'none':
                    return `calc(100% / ${this.data[0].length})`;
                case 'left':
                    return `calc(100% / 2)`;
            }
        }

        return '';
    }

    _constructTable() {
        if (this.options === null) {
            console.error('No table options set');
            return null;
        }

        if (this.options.headerPosition === 'top') {
            this.$flextable = $(`<div class="grid-table" id="id_${this.flextableId}" role="table"></div>`)
            this.$headerContainer = $(`<div id="header_${this.flextableId}" class="grid-header-container"></div>`);

            for (const h of this.options.headers) {
                this.$headerContainer.append($(`
                    <div class="grid-item grid-header">${h}</div>
                `));
            }

            this.$flextable.append(this.$headerContainer);
        } else {
            this.$flextable = $(`<div class="flextable-container" id="id_${this.flextableId}" role="table"></div>`)
        }
    }

    _rotate(arr, newElement) {
        arr.unshift(newElement);
        if (arr.length > this.options.rotation) {
            arr.splice(this.options.rotation);
        }

        const divs = document.querySelectorAll(`#id_${this.flextableId} .grid-row`);
        if (divs.length > this.options.rotation) {
            for (let i = this.options.rotation; i < divs.length; i++) {
                $(divs[i]).remove();
            }
        }
    }

    setTableOptions(options = {}) {
        /**
         * headerPosition: top, left or none.
         *  top: headers are on top of the table. Headers are defined in the options. Data layout: 
         *  [
         *      ['x', 'y', 'z'],
         *      ['x', 'y', 'z']
         *  ]
         *
         *  left: headers are on the left of the table (key-value). Data layout:
         *  [
         *      ['x', 'x1'],
         *      ['y', 'y1'],
         *      ['z', ['z1', 'z2']] <-- supports nested columns
         *  ]
         * 
         *  none: no headers, same data layout as 'top', without headers set as an option.
         * 
         * rotation: limit table entries to a certain amount, and rotate them. Only applicable for headerPosition: top.
         * headers: list of headers to display. Only applicable for headerPosition: top.
         */

        this.options = {
            headerPosition: 'top',
            rotation: false,
            ...options // merge and override defaults
        }
    }

    updateTable(data = []) {
        let $table = $(`#id_${this.flextableId}`);

        if (!this.options.rotation) {
            $table.children('.flextable-row').remove();
            this.data = data;
        }

        for (const row of data) {
            switch (this.options.headerPosition) {
                case "none":
                    let $row = $(`<div class="flextable-row"></div>`)
                    for (const item of row) {
                        $row.append($(`
                            <div class="flex-cell" role="cell">${item}</div>
                        `));
                    }
                    $table.append($row);
                break;
                case "top":
                    let $gridRow = $(`<div class="grid-row" style="opacity: 0.4; background-color: #f7e2d6"></div>`);
                    for (const item of row) {
                        $gridRow.append($(`
                            <div class="grid-item">${item}</div>
                        `));
                    }

                    $(`#header_${this.flextableId}`).after($gridRow);
                    if (this.options.rotation) {
                        $gridRow.animate({
                            from: 0,
                            to: 255,
                            opacity: 1,
                        }, {
                            duration: 50,
                            easing: 'linear',
                            step: function(now) {
                                $gridRow.css('background-color',`transparent`)
                            }
                        });
                        this._rotate(this.data, row);
                    }
                break;
                case "left":
                    if (row.length !== 2) {
                        break;
                    }
                    const [h, c] = row;
                    if (Array.isArray(c)) {
                        // nested column
                        let $row = $('<div class="flextable-row"></div>');
                        $row.append($(`
                            <div class="flex-cell rowspan first"><b>${h}</b></div>
                        `));
                        let $column = $('<div class="column"></div>');
                        for (const item of c) {
                            $column.append($(`
                                <div class="flex-cell">
                                    <div class="flex-subcell">${item}</div>
                                </div>
                            `));
                        }
                        $table.append($row.append($column));
                    } else {
                        $table.append($(`
                        <div class="flextable-row">
                            <div class="flex-cell first"><b>${h}</b></div>
                            <div class="flex-cell">${c}</div>
                        </div>
                        `));
                    }
                break;
            }
        }
    }

    getMarkup() {
        this._constructTable();
        return $(this.$flextable);
    }

    async onMarkupRendered() {

    }

    onWidgetResize(elem, width, height) {
        let lowIndex = 0;
        for (let i = 0; i < this.widths.length; i++) {
            if (this.widths[i] <= width) {
                lowIndex = i;
            } else {
                break;
            }
        }

        const lowIndexWidth = this.widths[lowIndex];
        if (lowIndexWidth !== this.curSize) {
            for (const [selector, styles] of Object.entries(this.sizeStates[lowIndexWidth])) {
                $(elem).find(selector).css(styles);
            }
            this.curSize = lowIndexWidth;
            return true;
        }

        return false;
    }
}
