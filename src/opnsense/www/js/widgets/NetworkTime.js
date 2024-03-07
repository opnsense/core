import BaseWidget from "./BaseWidget.js";

export default class NetworkTime extends BaseWidget {
    constructor() {
        super();
        this.title = 'Network Time';
        this.tickTimeout = 1000;
    }

    onWidgetTick() {
        let $nt = $('#network-time');
        let d = new Date();
        $nt.html(d.toLocaleTimeString());
    }

    async getHtml() {
        let $target = $(`
        <div class="network-time-container">
            <p id="network-time"></p>
            <p>185.51.192.63 (Stratum 2)</p>
        </div>`);
        return $target;
    }

    async onMarkupRendered() {

    }
}
