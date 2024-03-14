export default class BaseWidget {
    constructor(config) {
        this.config = config;
        this.title = "";
        this.id = null;
        this.tickTimeout = 5000; // Default tick timeout
    }

    setId(id) {
        this.id = id;
    }

    getMarkup() {
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

    /* For testing purposes */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

}