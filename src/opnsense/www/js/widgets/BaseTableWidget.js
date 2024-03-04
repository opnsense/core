import BaseWidget from "./BaseWidget.js";

export default class BaseTableWidget extends BaseWidget {
    constructor() {
        super();

        this.options = null;
        this.data = null;
        this.table = null;
    }

    setTableData(options = {}, data = []) {
        // Set default options
        this.options = {
            headerPosition: 'top', // top, left or none
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
        // XXX: refactor and optimize, jquery calls are not always necessary
        if (width <= 767) {
            // Apply styles for max-width: 767px
            $(elem).find('.flex-row').css('width', 'calc(100% / 3)');
            $(elem).find('.flex-row.first').css('width', '100%');
            $(elem).find('.column').css('width', '100%');
        } else {
            // Unset styles for max-width: 767px
            $(elem).find('.flex-row').css('width', '');
            $(elem).find('.flex-row.first').css('width', '');
            $(elem).find('.column').css('width', '');
        }

        if (width <= 430) {
            // Apply styles for max-width: 430px
            $(elem).find('.flextable-row').css('padding', '');
            $(elem).find('.header .flex-row').css('border-bottom', 'solid 1px');
            $(elem).find('.flex-row').css('width', '100%');
            $(elem).find('.column').css('width', '100%');
            $(elem).find('.flex-cell').css('width', '100%');
        } else {
            $(elem).find('.flextable-row').css('padding', '0.5em 0.5em');
            $(elem).find('.header .flex-row').css('border-bottom', '');
            $(elem).find('.flex-row').css('width', '');
            $(elem).find('.column').css('width', '');
            $(elem).find('.flex-cell').css('width', '');
        }
    }
}
