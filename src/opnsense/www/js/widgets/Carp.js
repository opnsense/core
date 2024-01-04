import BaseTableWidget from "./BaseTableWidget.js";
import BaseWidget from "./BaseWidget.js";

export default class Carp extends BaseTableWidget {
    constructor() {
        super({
            options: {noHeaders: true},
            data: {

            }
        });
        this.title = 'CARP Status';
    }

    async getHtml() {
    }

    onWidgetTick() {
    }

    async onMarkupRendered() {
        
    }
}
