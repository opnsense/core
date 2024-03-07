import BaseWidget from "./BaseWidget.js";

export default class BaseTableWidget extends BaseWidget {
    constructor() {
        super();

        this.options = null;
        this.data = null;
        this.table = null;

        this.curSize = null;
        this.sizeStates = {
            0: {
                '.flextable-row': {'padding': ''},
                '.header .flex-row': {'border-bottom': 'solid 1px'},
                '.flex-row': {'width': '100%'},
                '.column': {'width': '100%'},
                '.flex-cell': {'width': '100%'},
            },
            430: {
                '.flextable-row': {'padding': '0.5em 0.5em'},
                '.header .flex-row': {'border-bottom': ''},
                '.flex-row': {'width': this._calculateColumnWidth.bind(this)},
                '.column': {'width': ''},
                '.flex-cell': {'width': ''},
            }
        }
        this.widths = Object.keys(this.sizeStates).sort();
    }

    _calculateColumnWidth() {
        if (this.options !== null && this.data !== null) {
            switch (this.options.headerPosition) {
                case 'none':
                    return `calc(100% / ${this.data[0].length})`;
                case 'top':
                    return `calc(100% / ${Object.keys(this.data[0]).length})`;
                case 'left':
                    return `calc(100% / 2)`;
            }
        }

        return '';
    }

    setTableData(options = {}, data = []) {
        /**
         * headerPosition: top, left or none.
         *  top: headers are on top of the table. Data layout: [{header1: value1}, {header1: value2}, ...]
         *  left: headers are on the left of the table (key-value). Data layout: [{header1: value1, header2: value2}, ...]
         *  none: no headers. Data layout: [[value1, value2], ...]
         */
        this.options = {
            headerPosition: 'top',
            // TODO:
            // overflowLimit (how many items to show before y-overflow)
            // overflowScroll (scroll or hide overflow)
            ...options // merge and override defaults
        }
        this.data = data;
    }

    _constructTable() {
        if (this.data === null || this.options === null) {
            console.error('No table data or options set');
            return null;
        }

        let $flextable = $(`<div class="flextable-container" role="table"></div>`)

        let headers = new Set();
        let $headerContainer = null;
        if (this.options.headerPosition === 'top') {
            $headerContainer = $(`<div class="flextable-header"></div>`);
            $flextable.append($headerContainer);
        }        

        for (const row of this.data) {
            let rowType = Array.isArray(row) && row !== null ? 'flat' : 'nested';
            if (rowType === 'flat' && this.options.headerPosition !== 'none') {
                console.error('Flat data is not supported with headers');
                return null;
            }

            if (rowType === 'nested' && this.options.headerPosition === 'none') {
                console.error('Nested data requires headers');
                return null;
            }

            if (rowType === 'flat') {
                let $row = $(`<div class="flextable-row"></div>`)
                for (const item of row) {
                    $row.append($(`
                        <div class="flex-row" role="cell">${item}</div>
                    `));
                }
                $flextable.append($row);
            } else {
                if (this.options.headerPosition === 'top') {
                    let $flextableRow = $(`<div class="flextable-row"></div>`);
                    for (const [h, c] of Object.entries(row)) {
                        if (!headers.has(h)) {
                            $headerContainer.append($(`
                                <div class="flex-row">${h}</div>
                            `));
                            headers.add(h);
                        }
                        if (Array.isArray(c)) {
                            let $column = $('<div class="column"></div>');
                            for (const item of c) {
                                $column.append($(`
                                    <div class="flex-row">
                                        <div class="flex-cell">${item}</div>
                                    </div>
                                `));
                            }
                            $flextableRow.append($column);
                        } else {
                            $flextableRow.append($(`
                                <div class="flex-row">${c}</div>
                            `));
                        }
                    }
                    $flextable.append($flextableRow);
                } else if (this.options.headerPosition === 'left') {
                    for (const [h, c] of Object.entries(row)) {
                        if (Array.isArray(c)) {
                            // nested column
                            let $row = $('<div class="flextable-row" role="rowgroup"></div>');
                            $row.append($(`
                                <div class="flex-row rowspan first" role="cell"><b>${h}</b></div>
                            `));
                            let $column = $('<div class="column"></div>');
                            for (const item of c) {
                                $column.append($(`
                                    <div class="flex-row">
                                        <div class="flex-cell" role="cell">${item}</div>
                                    </div>
                                `));
                            }
                            $flextable.append($row.append($column));
                        } else {
                            $flextable.append($(`
                            <div class="flextable-row" role="rowgroup">
                                <div class="flex-row first" role="cell"><b>${h}</b></div>
                                <div class="flex-row" role="cell">${c}</div>
                            </div>
                        `));
                        }
                    }
                }
            }
            
        }

        return $flextable;
    }

    async getHtml() {
        let $flextable = this._constructTable();        
        this.table = $flextable;
        return $('<div></div>').append($flextable);
    }

    async onMarkupRendered() {
        $('.flextable-header .flex-row:first-child').addClass('first');
        $('.flextable-row .flex-row:first-child').addClass('first');
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
    }
}
