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

import BaseWidget from "./BaseWidget.js";

export default class BaseTableWidget extends BaseWidget {
    constructor() {
        super();

        this.tables = {};
        this.curSize = null;
        this.sizeStates = {
            0: {
                '.flextable-row': {'padding': ''},
                '.flextable-header .flex-cell': {'border-bottom': 'solid 1px'},
                '.flex-cell': {'width': '100%'},
                '.column': {'width': '100%'},
                '.flex-subcell': {'width': '100%'},
            },
            450: {
                '.flextable-row': {'padding': '0.5em 0.5em'},
                '.flextable-header .flex-cell': {'border-bottom': ''},
                '.flex-cell': {'width': this._calculateColumnWidth.bind(this)},
                '.column .flex-cell': {'width': '100%'},
                '.column': {'width': ''},
                '.flex-subcell': {'width': ''},
            }
        }
        this.widths = Object.keys(this.sizeStates).sort();
    }

    _calculateColumnWidth() {
        for (const [id, tableObj] of Object.entries(this.tables)) {
            if (tableObj.options.headerPosition === 'left') {
                return `calc(100% / 2)`;
            }

            if (tableObj.options.headerPosition === 'none') {
                let first = $(`#${tableObj.table.attr('id')} > .flextable-row`).first();
                let count = first.children().filter(function() {
                    return $(this).css('display') !== 'none';
                }).length;
                return `calc(100% / ${count})`;
            }
        }

        return '';
    }

    _rotate(id, newElement) {
        let opts = this.tables[id].options;
        let data = this.tables[id].data;

        data.unshift(newElement);
        if (data.length > opts.rotation) {
            data.splice(opts.rotation);
        }

        const divs = document.querySelectorAll(`#${id} .grid-row`);
        if (divs.length > opts.rotation) {
            for (let i = opts.rotation; i < divs.length; i++) {
                $(divs[i]).remove();
            }
        }
    }

    createTable(id, options) {
        /**
         * Options:
         *
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
         * rotation: limit table entries to a certain amount and rotate them. Only applicable for headerPosition: top.
         * headers: list of headers to display. Only applicable for headerPosition: top.
         * sortIndex: index of the column to sort on. Only applicable for headerPosition: top.
         * sortOrder: 'asc' or 'desc'. Only applicable for headerPosition: top.
         *
         */
        if (this.options === null) {
            console.error('No table options set');
            return null;
        }

        let mergedOpts = {
            headerPosition: 'top',
            rotation: false,
            sortIndex: null,
            sortOrder: 'desc',
            ...options
        }

        let $table = null;
        let $headerContainer = null;

        if (mergedOpts.headerPosition === 'top') {
            /* CSS grid implementation */
            $table = $(`<div class="grid-table" id="${id}" role="table"></div>`);
            $headerContainer = $(`<div id="header_${id}" class="grid-header-container"></div>`);

            for (const h of mergedOpts.headers) {
                $headerContainer.append($(`
                    <div class="grid-item grid-header">${h}</div>
                `));
            }

            $table.append($headerContainer);
        } else {
            /* flextable implementation */
            $table = $(`<div class="flextable-container" id="${id}" role="table"></div>`);
        }

        this.tables[id] = {
            'table': $table,
            'options': mergedOpts,
            'headerContainer': $headerContainer,
            'data': [],
        };

        return $table;
    }

    updateTable(id, data = [], rowIdentifier = null) {
        let $table = $(`#${id}`);
        let options = this.tables[id].options;

        if (!options.rotation) {
            $table.children('.flextable-row').remove();
            this.tables[id].data = data;
        }

        for (const row of data) {
            switch (options.headerPosition) {
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
                    let $gridRow = $(`<div class="grid-row"></div>`);
                    let newElement = true;
                    if (rowIdentifier !== null) {
                        $gridRow = $(`#id_${rowIdentifier}`);
                        if ($gridRow.length === 0) {
                            $gridRow = $(`<div class="grid-row" id="id_${rowIdentifier}"></div>`);
                        } else {
                            newElement = false;
                            $gridRow.empty();
                        }
                    }

                    let i = 0;
                    for (const item of row) {
                        $gridRow.append($(`
                            <div class="grid-item ${(options.sortIndex !== null && options.sortIndex == i) ? 'sort' : ''}">${item}</div>
                        `));
                        i++;
                    }

                    if (newElement) {
                        $(`#header_${id}`).after($gridRow);
                    }

                    if (options.sortIndex !== null) {
                        let items = $table.children('.grid-row').toArray().sort(function(a, b) {
                            let vA = parseInt($(a).children('.sort').first().text());
                            let vB = parseInt($(b).children('.sort').first().text());

                            if (options.sortOrder === 'asc')
                                return (vA < vB) ? -1 : (vA > vB) ? 1 : 0;
                            else {
                                return (vA > vB) ? -1 : (vA < vB) ? 1 : 0;
                            }
                        });
                        $table.append(items);
                    }

                    if (options.rotation) {
                        $gridRow.animate({
                            from: 0,
                            to: 255,
                            opacity: 1,
                        }, {
                            duration: 50,
                            easing: 'linear',
                            step: function(now) {
                                $gridRow.css('background-color','initial')
                            }
                        });
                        this._rotate(id, row);
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
