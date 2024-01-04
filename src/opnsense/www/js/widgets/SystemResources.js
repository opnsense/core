import BaseWidget from "./BaseWidget.js";

export default class SystemResources extends BaseWidget {
    constructor() {
        super();
        this.title = 'System Resources';
    }

    async getHtml() {
        let $container = $(`
        <div>
            <div id="gauge-container">
                <div id="gauge-fill"></div>
            </div>
        </div>`);
        return $container;
    }

    async onWidgetTick() {
        let gauge = $('#gauge-fill');
        let fill = Math.random() * 100;
        gauge.css('width', fill + '%');
        if (fill < 70) {
            gauge.css('background-color', 'green');
        } else if (fill < 90) {
            gauge.css('background-color', 'orange');
        } else {
            gauge.css('background-color', 'red');
        }
    }

    async onMarkupRendered() {

    }
}
