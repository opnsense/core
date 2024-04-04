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
                '.header .flex-row': {'border-bottom': 'solid 1px'},
                '.flex-row': {'width': '100%'},
                '.column': {'width': '100%'},
                '.flex-cell': {'width': '100%'},
            },
            500: {
                '.flextable-row': {'padding': '0.5em 0.5em'},
                '.header .flex-row': {'border-bottom': ''},
                '.flex-row': {'width': this._calculateColumnWidth.bind(this)},
                '.column .flex-row': {'width': '100%'},
                '.column': {'width': ''},
                '.flex-cell': {'width': ''},
            }
        }
        this.widths = Object.keys(this.sizeStates).sort();

        this.flextableId = Math.random().toString(36).substring(7);
        this.$flextable = null;
        this.$headerContainer = null;
        this.headers = new Set();
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

    _constructTable() {
        if (this.options === null) {
            console.error('No table options set');
            return null;
        }

        this.$flextable = $(`<div class="flextable-container" id="${this.flextableId}" role="table"></div>`)

        if (this.options.headerPosition === 'top') {
            this.$headerContainer = $(`<div class="flextable-header"></div>`);
            this.$flextable.append(this.$headerContainer);
        }
    }

    setTableOptions(options = {}) {
        /**
         * headerPosition: top, left or none.
         *  top: headers are on top of the table. Data layout: [{header1: value1}, {header1: value2}, ...]
         *  left: headers are on the left of the table (key-value). Data layout: [{header1: value1}, {header2: value2}, ...].
         *        Supports nested columns (e.g. {header1: [value1, value2...]})
         *  none: no headers. Data layout: [[value1, value2], ...]
         */
        this.options = {
            headerPosition: 'top',
            ...options // merge and override defaults
        }
    }

    updateTable(data = []) {
        let $table = $(`#${this.flextableId}`);

        $table.children('.flextable-row').remove();

        for (const row of data) {
            let rowType = Array.isArray(row) && row !== null ? 'flat' : 'nested';
            if (rowType === 'flat' && this.options.headerPosition !== 'none') {
                console.error('Flat data is not supported with headers');
                return null;
            }

            if (rowType === 'nested' && this.options.headerPosition === 'none') {
                console.error('Nested data requires headers');
                return null;
            }

            switch (this.options.headerPosition) {
                case "none":
                    let $row = $(`<div class="flextable-row"></div>`)
                    for (const item of row) {
                        $row.append($(`
                            <div class="flex-row" role="cell">${item}</div>
                        `));
                    }
                    $table.append($row);
                break;
                case "top":
                    let $flextableRow = $(`<div class="flextable-row"></div>`);
                    for (const [h, c] of Object.entries(row)) {
                        if (!this.headers.has(h)) {
                            this.$headerContainer.append($(`
                                <div class="flex-row">${h}</div>
                            `));
                            this.headers.add(h);
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
                    $table.append($flextableRow);
                break;
                case "left":
                    for (const [h, c] of Object.entries(row)) {
                        if (Array.isArray(c)) {
                            // nested column
                            let $row = $('<div class="flextable-row"></div>');
                            $row.append($(`
                                <div class="flex-row rowspan first"><b>${h}</b></div>
                            `));
                            let $column = $('<div class="column"></div>');
                            for (const item of c) {
                                $column.append($(`
                                    <div class="flex-row">
                                        <div class="flex-cell">${item}</div>
                                    </div>
                                `));
                            }
                            $table.append($row.append($column));
                        } else {
                            $table.append($(`
                            <div class="flextable-row">
                                <div class="flex-row first"><b>${h}</b></div>
                                <div class="flex-row">${c}</div>
                            </div>
                            `));
                        }
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
