export default class BaseWidget {
    constructor() {
        this.title = "";
        this.id = null;
        this.tickTimeout = 5000; // Default tick timeout
        /* 
         * temporary layout of test data interfaces.
         * the order here determines color
         */
        this.test_interfaces = ['ADSL', 'LAN', 'MGMNT', 'PFSYNC', 'WAN', 'WAN2'];
    }

    setId(id) {
        this.id = id;
    }

    async getHtml() {
        return $("");
    }

    async onMarkupRendered() {
        return null;
    }

    onWidgetResize(elem, width, height) {
        return false;
    }

    async onWidgetTick() {
        return null;
    }

    onWidgetClose() {
        return null;
    }

    getConfiguration() {
        return null;
    }

    /* For testing purposes */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

}