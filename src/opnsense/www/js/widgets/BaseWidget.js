export default class BaseWidget {
    constructor(config) {
        this.config = config;
        this.id = null;
        this.title = "";
        this.translations = {};
        this.tickTimeout = 5000; // Default tick timeout
        this.resizeHandles = "all"
    }

    getResizeHandles() {
        return this.resizeHandles;
    }

    setId(id) {
        this.id = id;
    }

    setTitle(title) {
        this.title = title;
    }

    setTranslations(translations) {
        this.translations = translations;
    }

    getGridOptions() {
        // per-widget gridstack options override
        return {};
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
